<?php $labels = \App\Controllers\Admin\ScoringController::TYPES; ?>
<div class="page-head">
  <div class="team-hero">
    <span class="avatar lg"><?= e(initials($t['name'])) ?></span>
    <div>
      <a class="small" href="/admin/teams"><?= icon('arrow-left', 'sm') ?> Teams</a>
      <h1><?= e($t['name']) ?></h1>
      <p>Rank <?= $rank ? '#' . $rank : '—' ?>, <?= number_format($score) ?> points, <?= count($solves) ?> solves, <?= $attempts ?> submissions. Invite code <span class="mono"><?= e($t['invite_code']) ?></span>.</p>
    </div>
  </div>
  <div class="row wrap">
    <a class="btn" href="/admin/scoring?team=<?= (int) $t['id'] ?>"><?= icon('award', 'sm') ?>Award points</a>
    <a class="btn" href="/teams/<?= (int) $t['id'] ?>"><?= icon('external', 'sm') ?>Public page</a>
  </div>
</div>

<div class="grid grid-sidebar">
  <div class="stack">
    <section class="panel">
      <div class="panel-head"><h2>Score over time</h2></div>
      <div class="panel-body"><?php if ($timeline): ?><div class="chart-box sm"><canvas data-line-chart='<?= e(json_encode([['name' => $t['name'], 'points' => $timeline]])) ?>' role="img" aria-label="Team score over time"></canvas></div><?php else: ?><p class="subtle small">No scoring activity yet.</p><?php endif ?></div>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>Solves</h2></div>
      <?php if (!$solves): ?><div class="empty" style="padding:var(--s-6)"><p>No solves.</p></div><?php else: ?>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Challenge</th><th>Player</th><th>When</th><th class="r">Points</th><th class="r">Bonus</th><th></th></tr></thead>
        <tbody><?php foreach ($solves as $s): ?>
          <tr>
            <td><?= e($s['title']) ?><?= $s['solve_rank'] <= 3 ? ' <span class="badge ' . ($s['solve_rank'] == 1 ? 'badge-blood' : '') . '">#' . (int) $s['solve_rank'] . '</span>' : '' ?></td>
            <td class="small"><?= e($s['username'] ?? 'manual') ?></td>
            <td class="small"><?= ts($s['created_at']) ?></td>
            <td class="r num"><?= (int) $s['points_awarded'] ?></td>
            <td class="r num"><?= $s['bonus_points'] ? '+' . (int) $s['bonus_points'] : '—' ?></td>
            <td class="r nowrap">
              <a class="btn btn-ghost btn-sm btn-icon" href="/admin/scoring?team=<?= (int) $t['id'] ?>&challenge=<?= (int) $s['challenge_id'] ?>&user=<?= (int) $s['user_id'] ?>&type=unique_solve" title="Award a unique-solve bonus" aria-label="Award bonus"><?= icon('award', 'sm') ?></a>
              <form method="post" action="/admin/solves/<?= (int) $s['id'] ?>/delete" class="inline" data-confirm="Remove this solve? The team loses <?= (int) $s['points_awarded'] + (int) $s['bonus_points'] ?> points."><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-icon" aria-label="Remove solve"><?= icon('trash', 'sm') ?></button></form>
            </td>
          </tr>
        <?php endforeach ?></tbody>
      </table></div>
      <?php endif ?>
    </section>
    <?php if ($awards || $hints): ?>
    <section class="panel">
      <div class="panel-head"><h2>Adjustments and hints</h2></div>
      <ul class="feed">
        <?php foreach ($awards as $a): ?><li style="<?= $a['revoked_at'] ? 'opacity:.5' : '' ?>"><span class="feed-icon award"><?= icon('award', 'sm') ?></span><div class="grow"><strong><?= e($labels[$a['type']] ?? $a['type']) ?></strong>: <?= e($a['reason']) ?><?= $a['revoked_at'] ? ' (revoked)' : '' ?><div><?= ts($a['created_at'], 'ago') ?></div></div><span class="num"><?= sprintf('%+d', $a['delta']) ?></span></li><?php endforeach ?>
        <?php foreach ($hints as $h): ?><li><span class="feed-icon"><?= icon('lightbulb', 'sm') ?></span><div class="grow">Hint on <?= e($h['title']) ?><div><?= ts($h['created_at'], 'ago') ?></div></div><span class="num">−<?= (int) $h['cost'] ?></span></li><?php endforeach ?>
      </ul>
    </section>
    <?php endif ?>
  </div>

  <aside class="stack">
    <section class="panel">
      <div class="panel-head"><h2>Members</h2></div>
      <ul class="feed"><?php foreach ($members as $m): ?>
        <li><span class="avatar"><?= e(initials($m['username'])) ?></span><div class="grow"><a href="/admin/users/<?= (int) $m['id'] ?>"><strong><?= e($m['username']) ?></strong></a><?= (int) $t['captain_id'] === (int) $m['id'] ? ' <span class="badge badge-accent">Captain</span>' : '' ?><div class="subtle xs"><?= (int) $m['solves'] ?> solves, <?= (int) $m['attempts'] ?> submissions</div></div></li>
      <?php endforeach ?><?php if (!$members): ?><li class="subtle">No members.</li><?php endif ?></ul>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>IP addresses</h2></div>
      <?php if (!$ips): ?><div class="panel-body subtle small">No submissions yet.</div><?php else: ?>
      <table class="table"><tbody><?php foreach ($ips as $ip): ?><tr><td class="mono xs"><a href="/admin/submissions?ip=<?= e(urlencode((string) $ip['ip'])) ?>"><?= e($ip['ip']) ?></a><?= $ip['teams_on_ip'] > 1 ? ' <span class="badge badge-warning" title="Other teams also submitted from this IP">' . (int) $ip['teams_on_ip'] . ' teams</span>' : '' ?></td><td class="r num small"><?= (int) $ip['n'] ?></td></tr><?php endforeach ?></tbody></table>
      <?php endif ?>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>Edit team</h2></div>
      <form class="panel-body" method="post" action="/admin/teams/<?= (int) $t['id'] ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="details">
        <div class="field"><label class="label" for="tn">Name</label><input class="input" id="tn" name="name" value="<?= e($t['name']) ?>"></div>
        <div class="field"><label class="label" for="ta">College</label><input class="input" id="ta" name="affiliation" value="<?= e($t['affiliation']) ?>"></div>
        <div class="field"><label class="label" for="tc">Country</label><input class="input" id="tc" name="country" value="<?= e($t['country']) ?>"></div>
        <div class="field"><label class="label" for="tcap">Captain</label><select class="select" id="tcap" name="captain_id"><option value="">None</option><?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>" <?= (int) $t['captain_id'] === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['username']) ?></option><?php endforeach ?></select></div>
        <button class="btn btn-sm btn-primary" type="submit" style="margin-top:var(--s-4)">Save</button>
      </form>
      <div class="panel-foot stack-sm">
        <form method="post" action="/admin/teams/<?= (int) $t['id'] ?>"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $t['is_hidden'] ? 'unhide' : 'hide' ?>"><button class="btn btn-sm btn-block" type="submit"><?= icon('eye-off', 'sm') ?><?= $t['is_hidden'] ? 'Show on scoreboard' : 'Hide from scoreboard' ?></button></form>
        <form method="post" action="/admin/teams/<?= (int) $t['id'] ?>" data-confirm="<?= $t['is_banned'] ? 'Reinstate' : 'Disqualify' ?> <?= e($t['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $t['is_banned'] ? 'unban' : 'ban' ?>"><button class="btn btn-sm btn-block btn-danger" type="submit"><?= icon('ban', 'sm') ?><?= $t['is_banned'] ? 'Reinstate team' : 'Disqualify team' ?></button></form>
        <form method="post" action="/admin/teams/<?= (int) $t['id'] ?>" data-confirm="Delete <?= e($t['name']) ?> with all its solves and submissions? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-block btn-ghost" type="submit" style="color:var(--danger)"><?= icon('trash', 'sm') ?>Delete team</button></form>
      </div>
    </section>
  </aside>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" defer></script>
<script src="<?= asset('js/charts.js') ?>" defer></script>
<script src="<?= asset('js/admin-charts.js') ?>" defer></script>
