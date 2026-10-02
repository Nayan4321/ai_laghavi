<section class="card narrow">
  <h1>Log in</h1>
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="login.php">
    <?= App\Csrf::field() ?>
    <label>Username <input name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus></label>
    <label>Password <input type="password" name="password" autocomplete="current-password" required></label>
    <button>Log in</button>
  </form>
  <p class="muted">Accounts are created by the administrator.</p>
</section>
