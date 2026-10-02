<section class="card narrow">
  <h1>Change password</h1>
  <form method="post" action="account.php">
    <?= App\Csrf::field() ?>
    <label>Current password <input type="password" name="current" autocomplete="current-password" required></label>
    <label>New password (10+ characters) <input type="password" name="new" minlength="10" autocomplete="new-password" required></label>
    <label>Confirm new password <input type="password" name="confirm" minlength="10" autocomplete="new-password" required></label>
    <button>Change password</button>
  </form>
</section>
