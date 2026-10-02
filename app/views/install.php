<section class="card narrow">
  <h1>Set up the admin account</h1>
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="install.php">
    <?= App\Csrf::field() ?>
    <label>Install token (from config.php) <input type="password" name="token" required></label>
    <label>Admin username <input name="username" required></label>
    <label>Password (10+ characters) <input type="password" name="password" minlength="10" required></label>
    <label>Confirm password <input type="password" name="confirm" minlength="10" required></label>
    <button>Create admin</button>
  </form>
</section>
