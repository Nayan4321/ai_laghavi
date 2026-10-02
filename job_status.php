<?php
declare(strict_types=1);

// JSON status of the user's recent jobs; the dashboard polls this while videos are in progress.
require __DIR__ . '/app/bootstrap.php';

use App\App;
use App\Auth;
use App\Jobs;

$user = Auth::requireLogin();
$jobs = array_map(static fn ($j) => [
    'id' => (int) $j['id'],
    'status' => $j['status'],
    'error' => $j['status'] === Jobs::FAILED ? $j['error'] : null,
], Jobs::forUser(App::db(), (int) $user['id']));

json_response(['jobs' => $jobs]);
