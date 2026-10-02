<?php
declare(strict_types=1);

// JSON status of the user's recent jobs; the dashboard polls this while videos are in progress.
// If the cron job hasn't run lately, this also does a short worker pass, so videos still
// move forward while someone has the dashboard open.
require __DIR__ . '/app/bootstrap.php';

use App\App;
use App\Auth;
use App\Jobs;
use App\WorkerRunner;

$user = Auth::requireLogin();
session_write_close();
$db = App::db();

$pending = static fn () => array_filter(Jobs::forUser($db, (int) $user['id']), static fn ($j) => in_array($j['status'], [Jobs::QUEUED, Jobs::PROCESSING], true));
if ($pending() && WorkerRunner::isDue()) {
    ignore_user_abort(true);
    @set_time_limit(120);
    try {
        WorkerRunner::run('web', 20);
    } catch (Throwable $e) {
        error_log('job_status.php worker pass: ' . $e->getMessage());
    }
}

$jobs = array_map(static fn ($j) => [
    'id' => (int) $j['id'],
    'status' => $j['status'],
    'error' => $j['status'] === Jobs::FAILED ? $j['error'] : null,
], Jobs::forUser($db, (int) $user['id']));

json_response(['jobs' => $jobs, 'setup_error' => WorkerRunner::status()['error']]);
