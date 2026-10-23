<!doctype html>
<html lang="en">
<head><?= partial('head', ['title' => $title ?? '']) ?></head>
<body>
<div class="auth">
  <div class="auth-top"><?= partial('brand') ?></div>
  <main class="auth-card">
    <?= $content ?>
  </main>
  <div class="auth-bottom">
    <?= e(setting('org_name')) ?><br>
    <a href="/rules">Rules</a> &nbsp; <a href="/about">About</a> &nbsp; <a href="/scoreboard">Scoreboard</a>
  </div>
</div>
<?= partial('toasts') ?>
</body>
</html>
