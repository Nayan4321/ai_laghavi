<?php
declare(strict_types=1);

// One-time web setup: creates tables and the admin account. Requires install_token from
// config.php so nobody else can claim the admin account, and refuses to run once installed.
// Delete this file after setup.
require __DIR__ . '/app/bootstrap.php';

use App\App;
use App\Csrf;
use App\Installer;

$db = App::db();
if (Installer::isInstalled($db)) {
    http_response_code(404);
    exit('Not found.');
}

$token = (string) App::config('install_token', '');
$error = null;
if (strlen($token) < 16) {
    $error = 'Set install_token in config.php to a random value of at least 16 characters first.';
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::check($_POST['_csrf'] ?? null) || !hash_equals($token, (string) ($_POST['token'] ?? ''))) {
        $error = 'Wrong install token.';
    } elseif (($_POST['password'] ?? '') !== ($_POST['confirm'] ?? '')) {
        $error = 'The passwords don’t match.';
    } else {
        try {
            Installer::install($db, (string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
            flash('Setup complete. Log in with your admin account, then delete install.php.', 'success');
            redirect('login.php');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

render('install', ['error' => $error, 'title' => 'Setup']);
