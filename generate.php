<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\App;
use App\Auth;
use App\Dimensions;
use App\Jobs;
use App\Uploads;

$user = Auth::requireLogin();
// An empty POST with a body means the request was bigger than PHP's post_max_size (which also drops the CSRF field).
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('The upload is larger than the server allows. Try smaller reference files.', 'error');
    redirect('dashboard.php');
}
require_post();
$db = App::db();

$_SESSION['old_input'] = array_intersect_key($_POST, array_flip(['prompt', 'width', 'height', 'aspect', 'duration']));
$stored = [];
try {
    $prompt = trim((string) ($_POST['prompt'] ?? ''));
    if ($prompt === '' || mb_strlen($prompt) > 4000) {
        throw new InvalidArgumentException('Write a prompt (up to 4000 characters).');
    }

    [$width, $height, $ratio] = Dimensions::resolve(
        $_POST['width'] ?? '', $_POST['height'] ?? '', (string) ($_POST['aspect'] ?? 'custom'),
        (int) App::config('dimensions.min', 128), (int) App::config('dimensions.max', 4096),
    );

    $dMin = (int) App::config('duration.min', 1);
    $dMax = (int) App::config('duration.max', 20);
    $duration = filter_var($_POST['duration'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => $dMin, 'max_range' => $dMax]]);
    if ($duration === false) {
        throw new InvalidArgumentException("Duration must be between $dMin and $dMax seconds.");
    }

    $maxActive = (int) App::config('max_active_jobs_per_user', 3);
    if (Jobs::activeCount($db, (int) $user['id']) >= $maxActive) {
        throw new InvalidArgumentException("You already have $maxActive videos in progress. Wait for one to finish.");
    }

    $images = Uploads::normalize($_FILES['images'] ?? null);
    $videos = Uploads::normalize($_FILES['video'] ?? null);
    $maxImages = (int) App::config('max_reference_images', 4);
    if (count($images) > $maxImages) {
        throw new InvalidArgumentException("Attach at most $maxImages reference images.");
    }
    if (count($videos) > 1) {
        throw new InvalidArgumentException('Attach at most one reference video.');
    }
    $maxBytes = (int) App::config('upload_max_mb', 50) * 1048576;
    foreach ($images as $f) {
        $stored[] = Uploads::store($f, 'image', App::storagePath(), $maxBytes);
    }
    foreach ($videos as $f) {
        $stored[] = Uploads::store($f, 'video', App::storagePath(), $maxBytes);
    }

    Jobs::create($db, (int) $user['id'], [
        'prompt' => $prompt,
        'width' => $width,
        'height' => $height,
        'aspect_ratio' => $ratio,
        'duration' => $duration,
    ], $stored, (string) App::config('provider', 'replicate'));

    unset($_SESSION['old_input']);
    flash("Queued: {$width}×{$height} ($ratio), {$duration}s. It will start within a minute.", 'success');
} catch (InvalidArgumentException $e) {
    Jobs::removeFiles(array_column($stored, 'path'));
    flash($e->getMessage(), 'error');
} catch (Throwable $e) {
    Jobs::removeFiles(array_column($stored, 'path'));
    error_log('generate.php: ' . $e->getMessage());
    flash('Something went wrong saving your request. Please try again.', 'error');
}
redirect('dashboard.php');
