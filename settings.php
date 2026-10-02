<?php
declare(strict_types=1);

// Admin settings: video API key and model (saved into config.php), and cron job status.
require __DIR__ . '/app/bootstrap.php';

use App\App;
use App\Auth;
use App\ConfigWriter;
use App\WorkerRunner;

$admin = Auth::requireAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post();
    $values = [];
    $token = trim((string) ($_POST['api_token'] ?? ''));
    if ($token !== '') {
        $values['api_token'] = $token;
    }
    $model = trim((string) ($_POST['model'] ?? ''));
    if ($model !== '') {
        if (!preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $model)) {
            flash('The model should look like owner/model-name, as shown on replicate.com.', 'error');
            redirect('settings.php');
        }
        $values['model'] = $model;
    }
    try {
        if ($values) {
            $updated = ConfigWriter::render((string) file_get_contents(CONFIG_FILE), $values);
            if (@file_put_contents(CONFIG_FILE, $updated) === false) {
                throw new RuntimeException('config.php isn’t writable. In File Manager, set its permissions to 644 and try again.');
            }
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate(CONFIG_FILE, true);
            }
        }
        flash('Settings saved. Queued videos start within a minute.', 'success');
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect('settings.php');
}

$status = WorkerRunner::status();
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/settings.php')), '/');
$cronUrl = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'yourdomain.com') . $basePath . '/cron.php?key=' . WorkerRunner::cronKey();
render('settings', [
    'user' => $admin,
    'title' => 'Settings',
    'status' => $status,
    'cronRunning' => WorkerRunner::cronIsRunning(),
    'cronCommand' => '/usr/bin/php ' . APP_ROOT . '/cron/worker.php',
    'cronUrl' => $cronUrl,
    'hasToken' => (string) App::config('replicate.api_token', '') !== '',
    'model' => (string) App::config('replicate.model', ''),
    'provider' => (string) App::config('provider', 'replicate'),
    'lastUpdate' => is_file(App::storagePath('last-update.txt')) ? trim((string) file_get_contents(App::storagePath('last-update.txt'))) : null,
]);
