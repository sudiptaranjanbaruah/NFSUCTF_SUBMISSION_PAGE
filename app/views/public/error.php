<?php
$titles = [403 => 'You can\'t open this page', 404 => 'Page not found', 405 => 'That action isn\'t allowed', 419 => 'Session expired', 429 => 'Slow down', 500 => 'Something went wrong'];
?>
<div class="error-page">
  <div class="code"><?= (int) $code ?></div>
  <h1 style="margin-top:var(--s-4)"><?= e($titles[$code] ?? 'Error') ?></h1>
  <p class="muted" style="margin-top:var(--s-2)"><?= e($message ?: ($code === 404 ? 'The link may be broken, or the page may have moved.' : 'Try again in a moment.')) ?></p>
  <a class="btn btn-primary" href="<?= current_user() ? '/dashboard' : '/' ?>" style="margin-top:var(--s-4)">Go to <?= current_user() ? 'dashboard' : 'home page' ?></a>
</div>
