<?php
declare(strict_types=1);

// Web address for the cron job, for hosting panels that call a URL instead of running
// a command. It needs the secret key shown on the admin Settings page; without it the
// page doesn't exist. All it can do is run one processing pass.
require __DIR__ . '/app/bootstrap.php';

use App\WorkerRunner;

session_write_close();
$given = (string) ($_GET['key'] ?? '');
try {
    $valid = $given !== '' && hash_equals(WorkerRunner::cronKey(), $given);
} catch (RuntimeException) {
    $valid = false;
}
if (!$valid) {
    http_response_code(404);
    exit('Not found.');
}

ignore_user_abort(true);
@set_time_limit(120);
header('Content-Type: text/plain');
header('Cache-Control: no-store');
try {
    $log = WorkerRunner::run('cron', 40);
    echo $log === null ? "skipped: previous run still going\n" : "ok\n" . implode("\n", $log) . "\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'error: ' . $e->getMessage() . "\n";
}
