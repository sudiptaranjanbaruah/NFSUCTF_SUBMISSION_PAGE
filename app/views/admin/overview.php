<?php
$verifiedPct = $kpi['users'] ? round($kpi['verified'] / $kpi['users'] * 100) : 0;
$accuracy = $kpi['submissions'] ? round($kpi['correct'] / $kpi['submissions'] * 100) : 0;
// Fill missing days/hours so the time axes are continuous.
$days = [];
for ($i = 29; $i >= 0; $i--) { $days[gmdate('Y-m-d', time() - $i * 86400)] = 0; }
foreach ($signups as $s) { $days[$s['d']] = (int) $s['n']; }
$hours = [];
for ($i = 47; $i >= 0; $i--) { $hours[gmdate('Y-m-d H:00', time() - $i * 3600)] = [0, 0]; }
foreach ($hourly as $h) { if (isset($hours[$h['h']])) $hours[$h['h']] = [(int) $h['ok'], (int) $h['bad']]; }
$heatGrid = array_fill(0, 7, array_fill(0, 24, 0));
$heatMax = 1;
foreach ($heat as $h) { $heatGrid[$h['wd']][$h['hr']] = (int) $h['n']; $heatMax = max($heatMax, (int) $h['n']); }
$series = array_map(fn($t) => ['name' => $t['name'], 'points' => $series[(int) $t['id']] ?? []], $top);
?>
<div class="page-head">
  <div><h1>Overview</h1><p>Event status: <strong><?= e(ucfirst($state)) ?></strong>. <?= $kpi['active15'] ?> players active in the last 15 minutes.</p></div>
  <div class="row wrap">
    <a class="btn" href="/admin/export/scoreboard"><?= icon('download', 'sm') ?>Scoreboard CSV</a>
    <a class="btn" href="/admin/export/solves"><?= icon('download', 'sm') ?>Solves CSV</a>
    <a class="btn btn-primary" href="/admin/settings"><?= icon('sliders', 'sm') ?>Event settings</a>
  </div>
</div>

<div class="grid grid-4" style="margin-bottom:var(--s-4)">
  <div class="panel stat"><div class="stat-value"><?= number_format($kpi['users']) ?></div><div class="stat-label">Registered players</div><div class="stat-delta"><?= $verifiedPct ?>% verified their email</div></div>
  <div class="panel stat"><div class="stat-value"><?= number_format($kpi['teams']) ?></div><div class="stat-label">Teams</div><div class="stat-delta"><?= $kpi['teamsScoring'] ?> have scored</div></div>
  <div class="panel stat"><div class="stat-value" data-kpi="active15"><?= $kpi['active15'] ?></div><div class="stat-label">Active now</div><div class="stat-delta">Seen in the last 15 minutes</div></div>
  <div class="panel stat"><div class="stat-value"><?= $kpi['solves'] ?></div><div class="stat-label">Solves</div><div class="stat-delta"><?= $kpi['unsolved'] ?> of <?= $kpi['challenges'] ?> challenges still unsolved</div></div>
</div>
<div class="grid grid-4" style="margin-bottom:var(--s-6)">
  <div class="panel stat"><div class="stat-value"><?= number_format($kpi['submissions']) ?></div><div class="stat-label">Flag submissions</div><div class="stat-delta"><?= $kpi['subsHour'] ?> in the last hour</div></div>
  <div class="panel stat"><div class="stat-value"><?= $accuracy ?>%</div><div class="stat-label">Submission accuracy</div><div class="stat-delta"><?= number_format($kpi['correct']) ?> correct</div></div>
  <div class="panel stat"><div class="stat-value"><?= $kpi['challenges'] ?></div><div class="stat-label">Visible challenges</div><div class="stat-delta"><a href="/admin/challenges">Manage challenges</a></div></div>
  <div class="panel stat"><div class="stat-value"><?= $kpi['mailQueued'] ?></div><div class="stat-label">Emails queued</div><div class="stat-delta"><a href="/admin/invites">Email queue</a></div></div>
</div>

<div class="grid grid-2" style="margin-bottom:var(--s-6)">
  <section class="panel">
    <div class="panel-head"><h2>Score progression, top 8</h2></div>
    <div class="panel-body"><?php if (array_filter($series, fn($s) => $s['points'])): ?><div class="chart-box"><canvas data-line-chart='<?= e(json_encode($series)) ?>' role="img" aria-label="Top team score progression"></canvas></div><?php else: ?><div class="empty"><p>No scoring yet.</p></div><?php endif ?></div>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Submissions per hour, last 48h</h2></div>
    <div class="panel-body"><div class="chart-box"><canvas data-bar-chart='<?= e(json_encode([
      'labels' => array_map(fn($h) => substr($h, 11, 5), array_keys($hours)),
      'stacked' => true,
      'series' => [['name' => 'Correct', 'values' => array_column($hours, 0)], ['name' => 'Wrong', 'values' => array_column($hours, 1), 'color' => 'danger']],
    ])) ?>' role="img" aria-label="Submissions per hour"></canvas></div></div>
  </section>
</div>

<div class="grid grid-2" style="margin-bottom:var(--s-6)">
  <section class="panel">
    <div class="panel-head"><h2>Challenge difficulty check</h2><span class="subtle xs">Solves vs. wrong attempts</span></div>
    <div class="panel-body"><?php if ($perChallenge): ?><div class="chart-box" style="height:<?= max(220, count($perChallenge) * 28 + 60) ?>px"><canvas data-horizontal="1" data-bar-chart='<?= e(json_encode([
      'labels' => array_map(fn($c) => mb_strimwidth($c['title'], 0, 28, '…'), $perChallenge),
      'series' => [['name' => 'Solves', 'values' => array_map('intval', array_column($perChallenge, 'solves'))], ['name' => 'Wrong attempts', 'values' => array_map('intval', array_column($perChallenge, 'wrong')), 'color' => 'danger']],
    ])) ?>' role="img" aria-label="Solves and wrong attempts per challenge"></canvas></div><?php else: ?><div class="empty"><p>No visible challenges.</p></div><?php endif ?></div>
  </section>
  <div class="stack">
    <section class="panel">
      <div class="panel-head"><h2>Sign-ups, last 30 days</h2></div>
      <div class="panel-body"><div class="chart-box sm"><canvas data-bar-chart='<?= e(json_encode(['labels' => array_map(fn($d) => date('M j', strtotime($d)), array_keys($days)), 'series' => [['name' => 'Sign-ups', 'values' => array_values($days)]]])) ?>' role="img" aria-label="Sign-ups per day"></canvas></div></div>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>Category completion</h2><span class="subtle xs">Share of possible solves among scoring teams</span></div>
      <div class="panel-body stack-sm">
        <?php if (!$categories): ?><p class="subtle small">No visible challenges.</p><?php endif ?>
        <?php foreach ($categories as $c): ?>
          <div><div class="row-between small"><span><?= e($c['name']) ?> <span class="subtle">(<?= (int) $c['total'] ?>)</span></span><span class="num subtle"><?= $c['pct'] ?>%</span></div><div class="bar" style="margin-top:4px"><span style="width:<?= min(100, $c['pct']) ?>%"></span></div></div>
        <?php endforeach ?>
      </div>
    </section>
  </div>
</div>

<div class="grid grid-3">
  <section class="panel">
    <div class="panel-head"><h2>When players submit</h2><span class="subtle xs">UTC, Mon–Sun</span></div>
    <div class="panel-body">
      <div class="heatmap" role="img" aria-label="Submissions by weekday and hour">
        <span></span><?php for ($h = 0; $h < 24; $h++): ?><span style="text-align:center"><?= $h % 6 === 0 ? $h : '' ?></span><?php endfor ?>
        <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $wd => $label): ?>
          <span><?= $label ?></span>
          <?php for ($h = 0; $h < 24; $h++): $v = $heatGrid[$wd][$h]; ?>
            <span class="cell" title="<?= $label ?> <?= $h ?>:00, <?= $v ?> submissions" style="<?= $v ? 'background:color-mix(in srgb, var(--accent) ' . (15 + round($v / $heatMax * 85)) . '%, var(--surface-2))' : '' ?>"></span>
          <?php endfor ?>
        <?php endforeach ?>
      </div>
    </div>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Recent first bloods</h2></div>
    <?php if (!$bloods): ?><div class="empty" style="padding:var(--s-6)"><p>None yet.</p></div><?php else: ?>
    <ul class="feed"><?php foreach ($bloods as $b): ?><li><span class="feed-icon blood"><?= icon('droplet', 'sm') ?></span><div class="grow"><strong><?= e($b['team']) ?></strong> on <?= e($b['title']) ?><div><?= ts($b['created_at'], 'ago') ?></div></div></li><?php endforeach ?></ul>
    <?php endif ?>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Sign-in methods, 30 days</h2></div>
    <div class="panel-body">
      <?php if (!$methods): ?><p class="subtle small">No sign-ins yet.</p><?php else: $mx = max(array_column($methods, 'n')); ?>
      <div class="stack-sm"><?php foreach ($methods as $m): ?>
        <div><div class="row-between small"><span><?= e(['password' => 'Password', 'otp' => 'Email code', 'signup' => 'New sign-up', 'google' => 'Google', 'github' => 'GitHub', 'invite' => 'Invite link', 'reset' => 'Password reset', 'admin_2fa' => 'Admin 2-step'][$m['method']] ?? $m['method']) ?></span><span class="num subtle"><?= (int) $m['n'] ?></span></div><div class="bar" style="margin-top:4px"><span style="width:<?= round($m['n'] / $mx * 100) ?>%"></span></div></div>
      <?php endforeach ?></div>
      <?php endif ?>
      <?php if ($hintUse): ?>
        <hr style="margin:var(--s-4) 0">
        <div class="small" style="margin-bottom:var(--s-2)"><strong>Most-used hints</strong></div>
        <table class="table"><tbody><?php foreach ($hintUse as $h): ?><tr><td class="small"><?= e($h['title']) ?></td><td class="r num small"><?= (int) $h['n'] ?>×</td></tr><?php endforeach ?></tbody></table>
      <?php endif ?>
    </div>
  </section>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" defer></script>
<script src="<?= asset('js/charts.js') ?>" defer></script>
<script src="<?= asset('js/admin-charts.js') ?>" defer></script>
