<?php
use App\Csrf;
use App\Jobs;

$aspect = $old['aspect'] ?? '16:9';
?>
<section class="card">
  <h1>Create a video</h1>
  <form method="post" action="generate.php" enctype="multipart/form-data" id="gen-form"
        data-min="<?= $limits['min'] ?>" data-max="<?= $limits['max'] ?>">
    <?= Csrf::field() ?>
    <label>Prompt
      <textarea name="prompt" rows="4" maxlength="4000" required placeholder="A slow aerial shot over misty mountains at sunrise"><?= e($old['prompt'] ?? '') ?></textarea>
    </label>

    <div class="row">
      <label>Aspect ratio
        <select name="aspect" id="aspect">
          <?php foreach ($presets as $p): ?>
            <option value="<?= e($p) ?>" <?= $aspect === $p ? 'selected' : '' ?>><?= e($p) ?></option>
          <?php endforeach; ?>
          <option value="custom" <?= $aspect === 'custom' ? 'selected' : '' ?>>Custom (type width and height)</option>
        </select>
      </label>
      <label>Width (px)
        <input type="number" name="width" id="width" value="<?= e($old['width'] ?? 1280) ?>"
               min="<?= $limits['min'] ?>" max="<?= $limits['max'] ?>" step="2" required>
      </label>
      <label>Height (px)
        <input type="number" name="height" id="height" value="<?= e($old['height'] ?? 720) ?>"
               min="<?= $limits['min'] ?>" max="<?= $limits['max'] ?>" step="2" required>
      </label>
      <label>Duration (s)
        <input type="number" name="duration" value="<?= e($old['duration'] ?? 5) ?>"
               min="<?= $limits['duration_min'] ?>" max="<?= $limits['duration_max'] ?>" required>
      </label>
    </div>
    <p class="muted" id="ratio-note"></p>

    <div class="row">
      <label>Reference images (optional, up to <?= $limits['max_images'] ?>)
        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple
               data-max-files="<?= $limits['max_images'] ?>">
      </label>
      <label>Reference video (optional)
        <input type="file" name="video" accept="video/mp4,video/quicktime,video/webm">
      </label>
    </div>
    <p class="muted">Each file up to <?= $limits['upload_mb'] ?> MB.</p>
    <button>Generate video</button>
  </form>
</section>

<section class="card">
  <h1>Your videos</h1>
  <?php if (!$jobs): ?>
    <p class="muted">Nothing yet. Your videos appear here when they’re ready.</p>
  <?php endif; ?>
  <div class="jobs" id="jobs">
  <?php foreach ($jobs as $j): ?>
    <article class="job" data-id="<?= (int) $j['id'] ?>" data-status="<?= e($j['status']) ?>">
      <?php if ($j['status'] === Jobs::COMPLETED): ?>
        <video controls preload="metadata" src="video.php?id=<?= (int) $j['id'] ?>"></video>
      <?php else: ?>
        <div class="placeholder" style="aspect-ratio: <?= (int) $j['width'] ?> / <?= (int) $j['height'] ?>">
          <span class="status <?= e($j['status']) ?>"><?= e(ucfirst($j['status'])) ?></span>
        </div>
      <?php endif; ?>
      <p class="prompt"><?= e($j['prompt']) ?></p>
      <p class="muted"><?= (int) $j['width'] ?>×<?= (int) $j['height'] ?> · <?= e($j['aspect_ratio']) ?> · <?= (int) $j['duration'] ?>s · <?= e($j['created_at']) ?> UTC</p>
      <?php if ($j['status'] === Jobs::FAILED && $j['error']): ?>
        <p class="error-text"><?= e($j['error']) ?></p>
      <?php endif; ?>
      <div class="job-actions">
        <?php if ($j['status'] === Jobs::COMPLETED): ?>
          <a href="video.php?id=<?= (int) $j['id'] ?>&amp;download=1">Download</a>
        <?php endif; ?>
        <?php if ($j['status'] !== Jobs::PROCESSING): ?>
        <form method="post" action="delete_job.php" class="inline">
          <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
          <button class="link danger" data-confirm="Delete this video?">Delete</button>
        </form>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
  </div>
</section>
