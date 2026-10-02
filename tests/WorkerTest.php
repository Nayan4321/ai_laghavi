<?php
declare(strict_types=1);

use App\App;
use App\Jobs;
use App\Providers\VideoProvider;
use App\Users;
use App\Worker;

/** Scripted provider: each call takes the next result from its list. */
final class ScriptedProvider implements VideoProvider
{
    public array $submitted = [];

    public function __construct(public array $submits = [], public array $checks = [])
    {
    }

    public function name(): string
    {
        return 'scripted';
    }

    public function submit(array $job, array $assets): string
    {
        $this->submitted[] = [$job, $assets];
        $next = array_shift($this->submits) ?? 'prov-' . $job['id'];
        if ($next instanceof Throwable) {
            throw $next;
        }
        return $next;
    }

    public function check(string $providerJobId): array
    {
        $next = array_shift($this->checks) ?? ['state' => 'running'];
        if ($next instanceof Throwable) {
            throw $next;
        }
        return $next + ['video_url' => null, 'error' => null];
    }
}

$makeJob = function (array $assets = []): int {
    $db = App::db();
    $uid = Users::create($db, 'u' . random_int(1000, 9999), 'a long password');
    return Jobs::create($db, $uid, ['prompt' => 'p', 'width' => 640, 'height' => 360, 'aspect_ratio' => '16:9', 'duration' => 5], $assets, 'scripted');
};

return [
    'queued job is submitted, polled and downloaded' => function () use ($makeJob) {
        $db = App::db();
        $id = $makeJob([['kind' => 'image', 'path' => 'uploads/r.png', 'mime' => 'image/png', 'original_name' => 'r.png', 'size_bytes' => 1]]);
        $provider = new ScriptedProvider(['pred-1'], [['state' => 'running'], ['state' => 'succeeded', 'video_url' => 'https://cdn.test/v/out.webm']]);
        $http = new FakeHttp();
        $worker = new Worker($db, $provider, $http);

        $worker->run(); // Submits, then the first check says it's still running.
        $job = Jobs::find($db, $id);
        assert_same(Jobs::PROCESSING, $job['status']);
        assert_same('pred-1', $job['provider_job_id']);
        assert_same(App::storagePath('uploads/r.png'), $provider->submitted[0][1][0]['abs_path']);

        $worker->run();
        $job = Jobs::find($db, $id);
        assert_same(Jobs::COMPLETED, $job['status']);
        assert_true(str_ends_with($job['output_path'], '.webm'), 'keeps the video extension');
        assert_true(is_file(App::storagePath($job['output_path'])), 'video saved');
    },
    'provider failure marks the job failed with its reason' => function () use ($makeJob) {
        $db = App::db();
        $id = $makeJob();
        $w = new Worker($db, new ScriptedProvider([], [['state' => 'failed', 'error' => 'Content policy']]), new FakeHttp());
        $w->run();
        $w->run();
        $job = Jobs::find($db, $id);
        assert_same(Jobs::FAILED, $job['status']);
        assert_same('Content policy', $job['error']);
    },
    'submit errors are retried, then the job fails' => function () use ($makeJob) {
        $db = App::db();
        $id = $makeJob();
        $err = new RuntimeException('HTTP 503');
        $w = new Worker($db, new ScriptedProvider([$err, $err, $err]), new FakeHttp());
        $w->run();
        assert_same(Jobs::QUEUED, Jobs::find($db, $id)['status']);
        assert_same(1, (int) Jobs::find($db, $id)['attempts']);
        $w->run();
        $w->run();
        $job = Jobs::find($db, $id);
        assert_same(Jobs::FAILED, $job['status']);
        assert_same('HTTP 503', $job['error']);
    },
    'a transient error then success still completes' => function () use ($makeJob) {
        $db = App::db();
        $id = $makeJob();
        $w = new Worker($db, new ScriptedProvider([new RuntimeException('timeout'), 'p9'],
            [['state' => 'succeeded', 'video_url' => 'https://cdn.test/a.mp4']]), new FakeHttp());
        $w->run();
        $w->run();
        $w->run();
        assert_same(Jobs::COMPLETED, Jobs::find($db, $id)['status']);
    },
    'download failures are retried' => function () use ($makeJob) {
        $db = App::db();
        $id = $makeJob();
        $ok = ['state' => 'succeeded', 'video_url' => 'https://cdn.test/broken.mp4'];
        $w = new Worker($db, new ScriptedProvider([], [$ok, $ok, $ok]), new FakeHttp());
        $w->run();
        $w->run();
        assert_same(Jobs::PROCESSING, Jobs::find($db, $id)['status']);
        $w->run();
        $w->run();
        assert_same(Jobs::FAILED, Jobs::find($db, $id)['status']);
    },
    'jobs running past the timeout fail' => function () use ($makeJob) {
        $db = App::db();
        $id = $makeJob();
        Jobs::update($db, $id, ['status' => Jobs::PROCESSING, 'provider_job_id' => 'x', 'submitted_at' => gmdate('Y-m-d H:i:s', time() - 3 * 3600)]);
        (new Worker($db, new ScriptedProvider(), new FakeHttp(), ['timeout_minutes' => 120]))->run();
        $job = Jobs::find($db, $id);
        assert_same(Jobs::FAILED, $job['status']);
        assert_true(str_contains($job['error'], 'Timed out'));
    },
];
