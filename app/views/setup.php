<section class="card narrow wide">
  <h1>Set up Laghavi Video</h1>
  <?php if ($manualConfig !== null): ?>
    <div class="flash success">Your admin account is ready, but the server didn’t let this page save <code>config.php</code>.</div>
    <p>In hPanel → File Manager, create a file named <code>config.php</code> next to <code>login.php</code>, paste everything below into it, save, then <a href="login.php">log in</a>.</p>
    <textarea rows="16" readonly><?= e($manualConfig) ?></textarea>
  <?php else: ?>
    <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="setup.php" autocomplete="off">
      <?= App\Csrf::field() ?>
      <h2>Database</h2>
      <p class="muted">From hPanel → Databases → MySQL Databases. Create one there first if you haven’t. Names start with your account id, like <code>u123456789_video</code>.</p>
      <label>Database name <input name="db_name" value="<?= e($in['db_name']) ?>" required placeholder="u123456789_video"></label>
      <label>Database username <input name="db_user" value="<?= e($in['db_user']) ?>" required placeholder="u123456789_video"></label>
      <label>Database password <input type="password" name="db_pass" required></label>

      <h2>Your admin account</h2>
      <label>Admin username <input name="username" value="<?= e($in['username']) ?>" required></label>
      <label>Admin password (10+ characters) <input type="password" name="password" minlength="10" required></label>
      <label>Confirm admin password <input type="password" name="confirm" minlength="10" required></label>

      <h2>Video API</h2>
      <label>Replicate API token (optional, can be added to config.php later)
        <input type="password" name="api_token" value="<?= e($in['api_token']) ?>" placeholder="r8_...">
      </label>
      <label>Site name <input name="app_name" value="<?= e($in['app_name']) ?>"></label>
      <button>Finish setup</button>
    </form>
  <?php endif; ?>
</section>
