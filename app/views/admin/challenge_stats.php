<?php
$solveCount = count($solves);
$rate = $teamsTried ? round($solveCount / $teamsTried * 100) : 0;
$curve = array_map(fn($s, $i) => [strtotime($s['created_at'] . ' UTC') * 1000, $i + 1], $solves, array_keys($solves));
?>
<div class="page-head">
  <div>
    <a class="small" href="/admin/challenges/<?= (int) $ch['id'] ?>"><?= icon('arrow-left', 'sm') ?> Back to editor</a>
    <h1 style="margin-top:var(--s-2)"><?= e($ch['title']) ?></h1>
    <p><?= e($ch['category']) ?>, <?= e($ch['difficulty']) ?>, currently worth <?= (int) $value ?> points.</p>
  </div>
</div>
<div class="grid grid-4" style="margin-bottom:var(--s-6)">
  <div class="panel stat"><div class="stat-value"><?= $solveCount ?></div><div class="stat-label">Solves</div></div>
  <div class="panel stat"><div class="stat-value"><?= $teamsTried ?></div><div class="stat-label">Teams that tried</div></div>
  <div class="panel stat"><div class="stat-value"><?= $rate ?>%</div><div class="stat-label">Solve rate among them</div></div>
  <div class="panel stat"><div class="stat-value"><?= $attempts ?></div><div class="stat-label">Total submissions</div></div>
</div>
<div class="grid grid-2">
  <section class="panel">
    <div class="panel-head"><h2>Solves over time</h2></div>
    <div class="panel-body">
      <?php if ($curve): ?><div class="chart-box sm"><canvas data-line-chart='<?= e(json_encode([['name' => 'Solves', 'points' => $curve]])) ?>' role="img" aria-label="Cumulative solves over time"></canvas></div>
      <?php else: ?><div class="empty" style="padding:var(--s-8)"><p>No solves yet.</p></div><?php endif ?>
    </div>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Most common wrong flags</h2><span class="subtle xs">Spot typos or misleading descriptions</span></div>
    <?php if (!$wrongFlags): ?><div class="empty" style="padding:var(--s-8)"><p>No wrong submissions.</p></div><?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Submitted</th><th class="r">Times</th><th class="r">Teams</th></tr></thead>
      <tbody><?php foreach ($wrongFlags as $w): ?><tr><td class="mono small truncate" style="max-width:360px"><?= e($w['provided']) ?></td><td class="r num"><?= (int) $w['n'] ?></td><td class="r num"><?= (int) $w['teams'] ?></td></tr><?php endforeach ?></tbody>
    </table></div>
    <?php endif ?>
  </section>
</div>
<section class="panel" style="margin-top:var(--s-6)">
  <div class="panel-head"><h2>Solvers</h2></div>
  <?php if (!$solves): ?><div class="empty" style="padding:var(--s-8)"><p>Nobody has solved this yet.</p></div><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>#</th><th>Team</th><th>Player</th><th>When</th><th class="r">Wrong tries first</th><th class="r">Points</th><th class="r">Bonus</th></tr></thead>
    <tbody><?php foreach ($solves as $s): ?>
      <tr>
        <td><span class="rank <?= $s['solve_rank'] <= 3 ? 'rank-' . $s['solve_rank'] : '' ?>"><?= (int) $s['solve_rank'] ?></span></td>
        <td><a class="rowlink" href="/admin/teams/<?= (int) $s['team_id'] ?>"><?= e($s['team']) ?></a></td>
        <td class="small"><?= e($s['username'] ?? '—') ?></td>
        <td class="small"><?= ts($s['created_at']) ?></td>
        <td class="r num"><?= (int) $s['wrong'] ?></td>
        <td class="r num"><?= (int) $s['points_awarded'] ?></td>
        <td class="r num"><?= $s['bonus_points'] ? '+' . (int) $s['bonus_points'] : '—' ?></td>
      </tr>
    <?php endforeach ?></tbody>
  </table></div>
  <?php endif ?>
</section>
<?php if ($hintUnlocks): ?>
<section class="panel" style="margin-top:var(--s-6);max-width:520px">
  <div class="panel-head"><h2>Hint usage</h2></div>
  <table class="table"><tbody><?php foreach ($hintUnlocks as $i => $h): ?><tr><td>Hint <?= $i + 1 ?> <span class="subtle">(<?= (int) $h['cost'] ?> pts)</span></td><td class="r num"><?= (int) $h['n'] ?> teams</td></tr><?php endforeach ?></tbody></table>
</section>
<?php endif ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" defer></script>
<script src="<?= asset('js/charts.js') ?>" defer></script>
<script src="<?= asset('js/admin-charts.js') ?>" defer></script>
