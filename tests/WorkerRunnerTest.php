<?php
declare(strict_types=1);

use App\App;
use App\Jobs;
use App\Providers\MockProvider;
use App\Users;
use App\WorkerRunner;

return [
    'records each run and whether it came from cron' => function () {
        assert_true(WorkerRunner::isDue(), 'due before any run');
        assert_same(false, WorkerRunner::cronIsRunning());
        WorkerRunner::run('web', 5, fn () => new MockProvider());
        assert_same(false, WorkerRunner::isDue());
        assert_same(false, WorkerRunner::cronIsRunning());
        WorkerRunner::run('cron', 5, fn () => new MockProvider());
        assert_same(true, WorkerRunner::cronIsRunning());
    },
    'a missing API setting is reported and jobs stay queued' => function () {
        $db = App::db();
        $uid = Users::create($db, 'bob', 'a long password');
        $id = Jobs::create($db, $uid, ['prompt' => 'p', 'width' => 640, 'height' => 360, 'aspect_ratio' => '16:9', 'duration' => 5], [], 'replicate');
        WorkerRunner::run('cron', 5, fn () => throw new RuntimeException('Replicate api_token is not set in config.php'));
        assert_same('Replicate api_token is not set in config.php', WorkerRunner::status()['error']);
        assert_same(Jobs::QUEUED, Jobs::find($db, $id)['status']);

        WorkerRunner::run('cron', 5, fn () => new MockProvider());
        assert_same(null, WorkerRunner::status()['error']);
        assert_same('mock-' . $id, Jobs::find($db, $id)['provider_job_id']);
    },
    'skips when another run holds the lock' => function () {
        $lock = fopen(App::storagePath('worker.lock'), 'c');
        flock($lock, LOCK_EX);
        try {
            assert_same(null, WorkerRunner::run('web', 5, fn () => new MockProvider()));
        } finally {
            flock($lock, LOCK_UN);
        }
    },
];
