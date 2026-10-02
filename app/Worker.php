<?php
declare(strict_types=1);

namespace App;

use App\Providers\VideoProvider;
use PDO;

/**
 * One cron pass: sends queued jobs to the provider, then checks running ones and
 * downloads finished videos. Each step is short, so it fits shared-hosting limits.
 */
final class Worker
{
    public const MAX_ATTEMPTS = 3;

    private array $log = [];

    public function __construct(
        private PDO $db,
        private VideoProvider $provider,
        private HttpClient $http,
        private array $options = [],
    ) {
        $this->options += [
            'timeout_minutes' => 120,
            'max_video_bytes' => 500 * 1048576,
            'time_budget_seconds' => 50,
            'batch' => 10,
        ];
    }

    public function run(): array
    {
        $deadline = microtime(true) + $this->options['time_budget_seconds'];
        foreach (Jobs::byStatus($this->db, Jobs::QUEUED, $this->options['batch']) as $job) {
            if (microtime(true) > $deadline) {
                break;
            }
            $this->submit($job);
        }
        foreach (Jobs::byStatus($this->db, Jobs::PROCESSING, $this->options['batch'] * 3) as $job) {
            if (microtime(true) > $deadline) {
                break;
            }
            $this->poll($job);
        }
        return $this->log;
    }

    private function submit(array $job): void
    {
        $id = (int) $job['id'];
        $assets = array_map(
            static fn ($a) => $a + ['abs_path' => App::storagePath($a['path'])],
            Jobs::assets($this->db, $id),
        );
        try {
            $providerId = $this->provider->submit($job, $assets);
            Jobs::update($this->db, $id, [
                'status' => Jobs::PROCESSING,
                'provider_job_id' => $providerId,
                'submitted_at' => now_utc(),
                'error' => null,
            ]);
            $this->log[] = "job $id submitted as $providerId";
        } catch (\Throwable $e) {
            $this->recordError($job, $e->getMessage());
        }
    }

    private function poll(array $job): void
    {
        $id = (int) $job['id'];
        try {
            $result = $this->provider->check((string) $job['provider_job_id']);
            if ($result['state'] === 'succeeded') {
                $this->saveVideo($job, (string) $result['video_url']);
                return;
            }
            if ($result['state'] === 'failed') {
                $this->fail($id, $result['error'] ?: 'Generation failed.');
                return;
            }
        } catch (\Throwable $e) {
            $this->recordError($job, $e->getMessage());
            return;
        }
        $started = strtotime(($job['submitted_at'] ?: $job['created_at']) . ' UTC');
        if (time() - $started > $this->options['timeout_minutes'] * 60) {
            $this->fail($id, 'Timed out waiting for the video provider.');
        }
    }

    private function saveVideo(array $job, string $url): void
    {
        $id = (int) $job['id'];
        $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $ext = in_array($ext, ['mp4', 'webm', 'mov'], true) ? $ext : 'mp4';
        $relative = 'videos/job-' . $id . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
        $this->http->download($url, App::storagePath($relative), $this->options['max_video_bytes']);
        Jobs::update($this->db, $id, [
            'status' => Jobs::COMPLETED,
            'output_path' => $relative,
            'completed_at' => now_utc(),
            'error' => null,
        ]);
        $this->log[] = "job $id completed";
    }

    /** Transient problem: keep the job's status and retry next run, up to MAX_ATTEMPTS. */
    private function recordError(array $job, string $message): void
    {
        $id = (int) $job['id'];
        $attempts = (int) $job['attempts'] + 1;
        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->fail($id, $message, $attempts);
            return;
        }
        Jobs::update($this->db, $id, ['attempts' => $attempts, 'error' => mb_substr($message, 0, 2000)]);
        $this->log[] = "job $id error (attempt $attempts): $message";
    }

    private function fail(int $id, string $message, ?int $attempts = null): void
    {
        $fields = ['status' => Jobs::FAILED, 'error' => mb_substr($message, 0, 2000), 'completed_at' => now_utc()];
        if ($attempts !== null) {
            $fields['attempts'] = $attempts;
        }
        Jobs::update($this->db, $id, $fields);
        $this->log[] = "job $id failed: $message";
    }
}
