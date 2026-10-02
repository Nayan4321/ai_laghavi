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
    <p>In hPanel, open <strong>Advanced → Cron Jobs</strong>, choose <strong>Custom</strong>, set it to run <strong>every minute</strong>, and use this command:</p>
    <pre class="copy"><?= e($cronCommand) ?></pre>
    <p class="muted">This page shows “running” within a minute or two of the first run.</p>
  <?php endif; ?>
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
