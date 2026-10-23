<?php
$u = current_user();
$active ??= '';
$unread = 0;
if ($u) {
    $unread = (int) \App\Core\DB::val(
        'SELECT COUNT(*) FROM announcements WHERE created_at > COALESCE(?, "1970-01-01")',
        [$u['announcements_seen_at']]
    );
}
$nav = $u ? [
    ['dashboard', '/dashboard', 'Dashboard', 'dashboard'],
    ['challenges', '/challenges', 'Challenges', 'target'],
    ['scoreboard', '/scoreboard', 'Scoreboard', 'trophy'],
    ['team', '/team', 'Team', 'users'],
    ['rules', '/rules', 'Rules', 'scroll'],
] : [
    ['scoreboard', '/scoreboard', 'Scoreboard', 'trophy'],
    ['rules', '/rules', 'Rules', 'scroll'],
    ['about', '/about', 'About', 'info'],
];
?>
<!doctype html>
<html lang="en">
<head><?= partial('head', ['title' => $title ?? '']) ?></head>
<body data-flag-format="<?= e(setting('flag_format')) ?>">
<header class="topbar">
  <div class="topbar-inner">
    <button class="btn btn-ghost btn-icon nav-toggle" type="button" data-toggle-nav aria-label="Open menu"><?= icon('menu') ?></button>
    <?= partial('brand') ?>
    <nav class="nav" id="main-nav" aria-label="Main">
      <?php foreach ($nav as [$key, $href, $label, $ic]): ?>
        <a href="<?= $href ?>" class="<?= $active === $key ? 'is-active' : '' ?>"><?= icon($ic, 'sm') ?><?= e($label) ?></a>
      <?php endforeach ?>
    </nav>
    <div class="topbar-actions">
      <?= partial('event_chip') ?>
      <button class="btn btn-ghost btn-icon" type="button" data-theme-toggle aria-label="Switch colour theme"><?= icon('sun') ?></button>
      <?php if ($u): ?>
        <a class="btn btn-ghost btn-icon bell" href="/notifications" aria-label="Announcements<?= $unread ? " ($unread new)" : '' ?>">
          <?= icon('bell') ?><?php if ($unread): ?><span class="dot" data-bell-dot></span><?php endif ?>
        </a>
        <details class="menu">
          <summary class="btn btn-ghost" aria-label="Account menu">
            <span class="avatar"><?= e(initials($u['username'])) ?></span>
          </summary>
          <div class="menu-list">
            <div class="menu-head">
              <div><strong><?= e($u['username']) ?></strong></div>
              <div class="subtle small truncate"><?= e($u['email']) ?></div>
            </div>
            <a href="/profile"><?= icon('user', 'sm') ?>Profile</a>
            <a href="/team"><?= icon('users', 'sm') ?>Team</a>
            <?php if (is_admin($u)): ?><a href="/admin"><?= icon('shield', 'sm') ?>Admin console</a><?php endif ?>
            <div class="sep"></div>
            <form method="post" action="/logout"><?= csrf_field() ?><button type="submit"><?= icon('log-out', 'sm') ?>Sign out</button></form>
          </div>
        </details>
      <?php else: ?>
        <a class="btn btn-ghost" href="/login">Sign in</a>
        <a class="btn btn-primary" href="/register">Register</a>
      <?php endif ?>
    </div>
  </div>
</header>
<?php if (setting('maintenance') === '1' && is_admin()): ?>
  <div class="alert alert-warning" style="border-radius:0;justify-content:center"><?= icon('alert', 'sm') ?>Maintenance mode is on. Only admins can use the site.</div>
<?php endif ?>
<main class="<?= isset($bare) ? '' : 'page' ?>">
<?= $content ?>
</main>
<footer class="site-foot">
  <div class="site-foot-inner">
    <span><?= e(setting('org_name')) ?></span>
    <span><a href="/rules">Rules</a> &nbsp; <a href="/about">About</a> &nbsp; <a href="mailto:<?= e(setting('contact_email')) ?>">Contact</a></span>
  </div>
</footer>
<?= partial('toasts') ?>
</body>
</html>
