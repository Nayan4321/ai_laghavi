<?php
declare(strict_types=1);

// Streams a finished video (with Range support so players can seek). Owner or admin only.
require __DIR__ . '/app/bootstrap.php';

use App\App;
use App\Auth;
use App\Jobs;

$user = Auth::requireLogin();
session_write_close(); // Don't hold the session lock while streaming.

$job = Jobs::findVisible(App::db(), (int) ($_GET['id'] ?? 0), $user);
$path = $job && $job['status'] === Jobs::COMPLETED && $job['output_path'] ? App::storagePath($job['output_path']) : null;
if (!$path || !is_file($path)) {
    http_response_code(404);
    exit('Video not found.');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$type = ['webm' => 'video/webm', 'mov' => 'video/quicktime'][$ext] ?? 'video/mp4';
$size = filesize($path);
$start = 0;
$end = $size - 1;

header('Content-Type: ' . $type);
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=3600');
if (!empty($_GET['download'])) {
    header('Content-Disposition: attachment; filename="video-' . (int) $job['id'] . '.' . $ext . '"');
}

if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] === '' && $m[2] !== '') {
        $start = max(0, $size - (int) $m[2]);
    } else {
        $start = (int) $m[1];
        $end = $m[2] === '' ? $end : min($end, (int) $m[2]);
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header("Content-Range: bytes */$size");
        exit;
    }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}
header('Content-Length: ' . ($end - $start + 1));

$fh = fopen($path, 'rb');
fseek($fh, $start);
$left = $end - $start + 1;
while ($left > 0 && !feof($fh) && !connection_aborted()) {
    $chunk = fread($fh, min(1048576, $left));
    echo $chunk;
    flush();
    $left -= strlen($chunk);
}
fclose($fh);
