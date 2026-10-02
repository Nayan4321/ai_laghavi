<?php
declare(strict_types=1);

// Admin panel: the only place user accounts are created.
require __DIR__ . '/app/bootstrap.php';

use App\App;
use App\Auth;
use App\Jobs;
use App\Users;

$admin = Auth::requireAdmin();
$db = App::db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post();
    $id = (int) ($_POST['id'] ?? 0);
    try {
        switch ($_POST['action'] ?? '') {
            case 'create':
                Users::create($db, (string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
                flash('User created. Share the username and password with them privately.', 'success');
                break;
            case 'password':
                Users::setPassword($db, $id, (string) ($_POST['password'] ?? ''));
                flash('Password reset.', 'success');
                break;
            case 'disable':
                Users::setActive($db, $id, false);
                flash('User disabled. They are logged out on their next click.', 'success');
                break;
            case 'enable':
                Users::setActive($db, $id, true);
                flash('User enabled.', 'success');
                break;
            case 'delete':
                Jobs::removeFiles(Users::delete($db, $id));
                flash('User and their videos deleted.', 'success');
                break;
            default:
                flash('Unknown action.', 'error');
        }
    } catch (InvalidArgumentException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect('admin.php');
}

render('admin', ['user' => $admin, 'users' => Users::all($db), 'title' => 'Users']);
