<?php
declare(strict_types=1);

namespace App;

/**
 * Runs one worker pass with a lock, and remembers when it last ran and whether the
 * video API is set up, so the site can warn when the cron job isn't running.
 * Used by cron/worker.php and, as a fallback, by the dashboard's status polling.
 */
final class WorkerRunner
{
    /** How long without a run before the site starts processing from page visits instead. */
    public const FALLBACK_AFTER_SECONDS = 90;

    public static function statusFile(): string
    {
        return App::storagePath('worker-status.json');
    }

    /** ['last_run' => ?int, 'last_cron_run' => ?int, 'error' => ?string] */
    public static function status(): array
    {
        $data = is_file(self::statusFile()) ? json_decode((string) @file_get_contents(self::statusFile()), true) : null;
        return (is_array($data) ? $data : []) + ['last_run' => null, 'last_cron_run' => null, 'error' => null];
    }

    public static function cronIsRunning(): bool
    {
        $last = self::status()['last_cron_run'];
        return $last !== null && time() - (int) $last < 5 * 60;
    }

    public static function isDue(): bool
    {
        $last = self::status()['last_run'];
        return $last === null || time() - (int) $last >= self::FALLBACK_AFTER_SECONDS;
    }

    /**
     * Returns the worker's log lines, or null if another run holds the lock.
     * $providerFactory is injectable for tests.
     */
    public static function run(string $source, int $timeBudget = 50, ?callable $providerFactory = null): ?array
    {
        $lock = @fopen(App::storagePath('worker.lock'), 'c');
        if (!$lock) {
            throw new \RuntimeException('Cannot write to the storage folder ' . App::storagePath() . '. Set its permissions to 755.');
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return null;
        }
        $status = self::status();
        $status['last_run'] = time();
        if ($source === 'cron') {
            $status['last_cron_run'] = time();
        }
        try {
            $http = new CurlHttpClient();
            try {
                $provider = $providerFactory ? $providerFactory($http) : App::provider($http);
            } catch (\RuntimeException $e) {
                // Not set up yet: leave jobs queued so they start once the setting is fixed.
                $status['error'] = $e->getMessage();
                return ['video API not set up: ' . $e->getMessage()];
            }
            $status['error'] = null;
            $worker = new Worker(App::db(), $provider, $http, [
                'timeout_minutes' => (int) App::config('job_timeout_minutes', 120),
                'max_video_bytes' => (int) App::config('max_video_mb', 500) * 1048576,
                'time_budget_seconds' => $timeBudget,
            ]);
            return $worker->run();
        } finally {
            @file_put_contents(self::statusFile(), json_encode($status));
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
