<?php
use App\App;
use App\Csrf;

$f = flash();
$appName = App::config('app_name', 'Video Studio');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title === $appName ? $appName : "$title · $appName") ?></title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<header class="top">
  <a class="brand" href="<?= isset($user) ? 'dashboard.php' : 'login.php' ?>"><?= e($appName) ?></a>
  <nav>
  <?php if (isset($user)): ?>
    <a href="dashboard.php">Create</a>
    <?php if ((int) $user['is_admin'] === 1): ?><a href="admin.php">Users</a><?php endif; ?>
    <a href="account.php"><?= e($user['username']) ?></a>
    <form method="post" action="logout.php" class="inline"><?= Csrf::field() ?><button class="link">Log out</button></form>
  <?php else: ?>
    <a href="login.php">Log in</a>
  <?php endif; ?>
  </nav>
</header>
<main>
<?php if ($f): ?><div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endif; ?>
<?= $content ?>
</main>
<script src="assets/app.js"></script>
</body>
</html>
