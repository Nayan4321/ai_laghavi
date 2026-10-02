<?php
declare(strict_types=1);

// Admin: upload a ZIP of the new code to update the site in place (form is on the Settings page).
require __DIR__ . '/app/bootstrap.php';

use App\App;
use App\Auth;
use App\Updater;

Auth::requireAdmin();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('That ZIP is larger than the server allows. Raise upload_max_filesize in hPanel → PHP Configuration.', 'error');
    redirect('settings.php');
}
require_post();

$file = $_FILES['package'] ?? null;
try {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Choose the ZIP file to upload.');
    }
    $result = Updater::apply($file['tmp_name'], APP_ROOT, App::storagePath('backups'));
    @file_put_contents(App::storagePath('last-update.txt'), gmdate('Y-m-d H:i:s') . ' UTC, ' . $result['updated'] . " files\n");
    flash("Site updated: {$result['updated']} files installed. config.php and your videos were left as they were." .
        ($result['backup'] ? ' The previous files are saved in storage/backups/' . basename($result['backup']) . '.' : ''), 'success');
} catch (RuntimeException $e) {
    flash('Update failed: ' . $e->getMessage(), 'error');
}
redirect('settings.php');
