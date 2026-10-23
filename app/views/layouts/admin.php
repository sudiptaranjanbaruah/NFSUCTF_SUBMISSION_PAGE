<?php
$active ??= '';
$groups = [
    'Event' => [
        ['overview', '/admin', 'Overview', 'chart'],
        ['challenges', '/admin/challenges', 'Challenges', 'target'],
        ['scoring', '/admin/scoring', 'Scoring & awards', 'award'],
        ['submissions', '/admin/submissions', 'Submissions', 'list'],
        ['announcements', '/admin/announcements', 'Announcements', 'megaphone'],
    ],
    'People' => [
        ['users', '/admin/users', 'Users', 'user'],
        ['teams', '/admin/teams', 'Teams', 'users'],
        ['invites', '/admin/invites', 'Invites & import', 'mail'],
    ],
    'Integrity' => [
        ['security', '/admin/security', 'Anti-cheat', 'shield-alert'],
        ['audit', '/admin/audit', 'Audit log', 'fingerprint'],
    ],
    'Configure' => [
        ['settings', '/admin/settings', 'Settings', 'sliders'],
        ['pages', '/admin/pages/rules', 'Pages', 'file-text'],
    ],
];
?>
<!doctype html>
<html lang="en">
<head><?= partial('head', ['title' => ($title ?? 'Admin') . ' · Admin']) ?></head>
<body>
<div class="admin">
  <aside class="admin-side" id="admin-side">
    <?= partial('brand') ?>
    <nav class="side-nav" aria-label="Admin">
      <?php foreach ($groups as $group => $items): ?>
        <div class="group"><?= e($group) ?></div>
        <?php foreach ($items as [$key, $href, $label, $ic]): ?>
          <a href="<?= $href ?>" class="<?= $active === $key ? 'is-active' : '' ?>"><?= icon($ic) ?><?= e($label) ?></a>
        <?php endforeach ?>
      <?php endforeach ?>
    </nav>
    <div class="admin-side-foot">
      <a class="btn btn-outline btn-sm btn-block" href="/dashboard"><?= icon('arrow-left', 'sm') ?>Back to the CTF</a>
    </div>
  </aside>
  <div class="admin-main">
    <header class="admin-top">
      <button class="btn btn-ghost btn-icon admin-toggle" type="button" data-toggle-side aria-label="Open menu"><?= icon('menu') ?></button>
      <div class="crumbs"><?= $crumbs ?? e($title ?? '') ?></div>
      <?= partial('event_chip') ?>
      <button class="btn btn-ghost btn-icon" type="button" data-theme-toggle aria-label="Switch colour theme"><?= icon('sun') ?></button>
      <form method="post" action="/logout" class="inline"><?= csrf_field() ?><button class="btn btn-ghost btn-icon" aria-label="Sign out"><?= icon('log-out') ?></button></form>
    </header>
    <main class="admin-page">
<?= $content ?>
    </main>
  </div>
</div>
<?= partial('toasts') ?>
<script src="<?= asset('js/admin.js') ?>" defer></script>
</body>
</html>
