<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

App\Auth::requireLogin();
require_post();
App\Auth::logout();
redirect('login.php');
