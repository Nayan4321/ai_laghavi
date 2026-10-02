<?php
declare(strict_types=1);

// The only page reachable without logging in. There is no signup: accounts come from the admin panel.
require __DIR__ . '/app/bootstrap.php';

use App\App;
use App\Auth;
use App\Csrf;

$db = App::db();
if (Auth::user($db)) {
    redirect('dashboard.php');
}

$error = null;
$username = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = (string) ($_POST['username'] ?? '');
    if (!Csrf::check($_POST['_csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $error = Auth::attempt($db, $username, (string) ($_POST['password'] ?? ''), $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if ($error === null) {
            redirect('dashboard.php');
        }
    }
}

render('login', ['error' => $error, 'username' => $username, 'title' => 'Log in']);
