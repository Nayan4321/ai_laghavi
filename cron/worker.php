<?php
declare(strict_types=1);

// Run every minute from hPanel → Advanced → Cron Jobs. The exact command for your
// site is shown on the Settings page (Users → Settings) once you're logged in as admin.
// Sends queued videos to the provider, checks on running ones and downloads finished videos.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    foreach (App\WorkerRunner::run('cron') ?? [] as $line) {
        echo gmdate('c') . " $line\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, gmdate('c') . ' worker error: ' . $e->getMessage() . "\n");
    exit(1);
}
