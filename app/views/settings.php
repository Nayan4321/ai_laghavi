<?php use App\Csrf; ?>
<section class="card">
  <h1>Background processing</h1>
  <?php if ($cronRunning): ?>
    <p>The cron job is running. Last run: <?= e(gmdate('Y-m-d H:i:s', (int) $status['last_cron_run'])) ?> UTC.</p>
  <?php else: ?>
    <div class="flash error">
      The cron job isn’t running<?= $status['last_cron_run'] ? ' (last run ' . e(gmdate('Y-m-d H:i', (int) $status['last_cron_run'])) . ' UTC)' : '' ?>.
      Until it is, videos only move forward while someone has the Create page open.
    </div>
    <p>In hPanel, open <strong>Advanced → Cron Jobs</strong> and add a job that runs <strong>every minute</strong>.</p>
  <?php endif; ?>
  <p><strong>If it asks for a URL</strong>, use this web address:</p>
  <pre class="copy"><?= e($cronUrl) ?></pre>
  <p><strong>If it asks for a command</strong>, use either of these:</p>
  <pre class="copy"><?= e($cronCommand) ?></pre>
  <pre class="copy">wget -q -O /dev/null "<?= e($cronUrl) ?>"</pre>
  <p class="muted">Keep the URL private: it contains a secret key. This page shows “running” within a minute or two of the first run.</p>
  <?php if ($status['error']): ?>
    <div class="flash error">Videos can’t start yet: <?= e($status['error']) ?></div>
  <?php endif; ?>
</section>

<section class="card">
  <h1>Video API (Replicate)</h1>
  <?php if ($provider !== 'replicate'): ?>
    <p class="muted">config.php is set to the “<?= e($provider) ?>” provider, so these settings aren’t used.</p>
  <?php endif; ?>
  <form method="post" action="settings.php" autocomplete="off">
    <?= Csrf::field() ?>
    <label>API token <?= $hasToken ? '(saved; leave blank to keep it)' : '(not set yet)' ?>
      <input type="password" name="api_token" placeholder="r8_...">
    </label>
    <p class="muted">From replicate.com → Account → API tokens. Replicate needs billing set up before it will run video models.</p>
    <label>Model
      <input name="model" value="<?= e($model) ?>" placeholder="owner/model-name">
    </label>
    <p class="muted">If you change the model, check its API tab on replicate.com: the input names in config.php (<code>input_map</code>) may need to match.</p>
    <button>Save</button>
  </form>
</section>

<section class="card">
  <h1>Update the site</h1>
  <p>Upload a ZIP of the new code, for example from GitHub: <strong>Code → Download ZIP</strong>.
    Every file in it is installed; <code>config.php</code>, your users and your videos stay as they are.
    The files being replaced are saved to <code>storage/backups</code> first.</p>
  <?php if ($lastUpdate): ?><p class="muted">Last update: <?= e($lastUpdate) ?></p><?php endif; ?>
  <form method="post" action="update.php" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <label>Code ZIP <input type="file" name="package" accept=".zip,application/zip" required></label>
    <button data-confirm="Install this ZIP over the current site?">Upload and update</button>
  </form>
</section>
