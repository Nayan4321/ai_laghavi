<?php
declare(strict_types=1);

// Lets any logged-in user change their own password.
require __DIR__ . '/app/bootstrap.php';

use App\App;
use App\Auth;
use App\Users;

$user = Auth::requireLogin();
$db = App::db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post();
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    try {
        if (!password_verify((string) ($_POST['current'] ?? ''), (string) $stmt->fetchColumn())) {
            throw new InvalidArgumentException('Your current password is wrong.');
        }
        if (($_POST['new'] ?? '') !== ($_POST['confirm'] ?? '')) {
            throw new InvalidArgumentException('The new passwords don’t match.');
        }
        Users::setPassword($db, (int) $user['id'], (string) $_POST['new']);
        session_regenerate_id(true);
        flash('Password changed.', 'success');
    } catch (InvalidArgumentException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect('account.php');
}

render('account', ['user' => $user, 'title' => 'My account']);
