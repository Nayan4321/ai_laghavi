<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\App;
use App\Auth;
use App\Jobs;

$user = Auth::requireLogin();
require_post();
$db = App::db();

$job = Jobs::findVisible($db, (int) ($_POST['id'] ?? 0), $user);
if ($job && $job['status'] === Jobs::PROCESSING) {
    flash('This video is still being generated. Delete it once it finishes.', 'error');
} elseif ($job) {
    Jobs::removeFiles(Jobs::delete($db, (int) $job['id']));
    flash('Deleted.', 'success');
}
redirect('dashboard.php');
