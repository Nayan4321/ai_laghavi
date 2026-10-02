<?php use App\Csrf; ?>
<section class="card">
  <h1>Create a user</h1>
  <form method="post" action="admin.php" class="row">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="create">
    <label>Username <input name="username" required pattern="[A-Za-z0-9._@\-]{3,64}"></label>
    <label>Password (10+ characters) <input type="text" name="password" minlength="10" required autocomplete="off"></label>
    <button>Create user</button>
  </form>
</section>

<section class="card">
  <h1>Users</h1>
  <table>
    <thead><tr><th>Username</th><th>Status</th><th>Videos</th><th>Last login (UTC)</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['username']) ?><?= (int) $u['is_admin'] === 1 ? ' <span class="tag">admin</span>' : '' ?></td>
        <td><?= (int) $u['is_active'] === 1 ? 'Active' : '<span class="tag off">Disabled</span>' ?></td>
        <td><?= (int) $u['job_count'] ?></td>
        <td><?= e($u['last_login_at'] ?? 'never') ?></td>
        <td><div class="actions">
        <?php if ((int) $u['is_admin'] !== 1): ?>
          <form method="post" action="admin.php" class="inline">
            <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <input type="text" name="password" minlength="10" placeholder="New password" required autocomplete="off">
            <button name="action" value="password">Reset</button>
          </form>
          <form method="post" action="admin.php" class="inline">
            <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <?php if ((int) $u['is_active'] === 1): ?>
              <button name="action" value="disable">Disable</button>
            <?php else: ?>
              <button name="action" value="enable">Enable</button>
            <?php endif; ?>
            <button name="action" value="delete" class="danger" data-confirm="Delete <?= e($u['username']) ?> and all their videos?">Delete</button>
          </form>
        <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
