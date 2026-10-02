<?php
declare(strict_types=1);

// Run every minute from hPanel → Advanced → Cron Jobs:
//   /usr/bin/php /home/u123456789/domains/example.com/public_html/cron/worker.php
// Sends queued videos to the provider, checks on running ones and downloads finished videos.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\App;
use App\CurlHttpClient;
use App\Worker;

// Skip this run if the previous one is still going.
$lock = @fopen(App::storagePath('worker.lock'), 'c');
if (!$lock) {
    fwrite(STDERR, 'Cannot write to storage_path ' . App::storagePath() . "\n");
    exit(1);
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

try {
    $http = new CurlHttpClient();
    $worker = new Worker(App::db(), App::provider($http), $http, [
        'timeout_minutes' => (int) App::config('job_timeout_minutes', 120),
        'max_video_bytes' => (int) App::config('max_video_mb', 500) * 1048576,
    ]);
    foreach ($worker->run() as $line) {
        echo gmdate('c') . " $line\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, gmdate('c') . ' worker error: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
}
