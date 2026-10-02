<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\App;
use App\Auth;
use App\Dimensions;
use App\Jobs;

$user = Auth::requireLogin();
$db = App::db();

render('dashboard', [
    'user' => $user,
    'jobs' => Jobs::forUser($db, (int) $user['id']),
    'presets' => Dimensions::PRESETS,
    'old' => $_SESSION['old_input'] ?? [],
    'limits' => [
        'min' => (int) App::config('dimensions.min', 128),
        'max' => (int) App::config('dimensions.max', 4096),
        'duration_min' => (int) App::config('duration.min', 1),
        'duration_max' => (int) App::config('duration.max', 20),
        'max_images' => (int) App::config('max_reference_images', 4),
        'upload_mb' => (int) App::config('upload_max_mb', 50),
    ],
]);
unset($_SESSION['old_input']);
