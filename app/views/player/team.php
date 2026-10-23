<?php
$awardLabels = ['unique_solve' => 'Unique solve', 'writeup' => 'Write-up', 'bug_report' => 'Bug report', 'penalty' => 'Penalty', 'other' => 'Award'];
$solvePts = array_sum(array_map(fn($s) => $s['points_awarded'], $solves));
$bonusPts = array_sum(array_map(fn($s) => $s['bonus_points'], $solves));
$awardPts = array_sum(array_map(fn($a) => $a['delta'], $awards));
?>
<div class="page-head">
  <div class="team-hero">
    <span class="avatar lg"><?= e(initials($team['name'])) ?></span>
    <div>
      <h1><?= e($team['name']) ?></h1>
      <p><?= $team['affiliation'] ? e($team['affiliation']) : ($team['is_solo'] ? 'Solo player' : 'Team') ?><?= $team['country'] ? ', ' . e($team['country']) : '' ?></p>
    </div>
  </div>
  <?php if ($isMine && !$team['is_solo']): ?>
    <div class="row">
      <span class="small muted">Invite code</span>
      <span class="invite-code"><?= e($team['invite_code']) ?></span>
      <button class="btn btn-icon" type="button" data-copy="<?= e($team['invite_code']) ?>" aria-label="Copy invite code"><?= icon('copy', 'sm') ?></button>
    </div>
  <?php endif ?>
</div>

<?php if ($frozen): ?><div class="freeze-banner"><?= icon('snowflake') ?><div>Showing activity up to the scoreboard freeze.</div></div><?php endif ?>

<div class="grid grid-4" style="margin-bottom:var(--s-6)">
  <div class="panel stat"><div class="stat-value"><?= $row ? '#' . (int) $row['rank'] : '—' ?></div><div class="stat-label">Rank</div></div>
  <div class="panel stat"><div class="stat-value"><?= number_format($row['score'] ?? 0) ?></div><div class="stat-label">Score</div>
    <div class="stat-delta"><?= $solvePts ?> solves<?= $bonusPts ? " + $bonusPts first blood" : '' ?><?= $awardPts ? ($awardPts > 0 ? ' + ' : ' − ') . abs($awardPts) . ' awards' : '' ?><?= $hintCost ? " − $hintCost hints" : '' ?></div></div>
  <div class="panel stat"><div class="stat-value"><?= count($solves) ?></div><div class="stat-label">Challenges solved</div></div>
  <div class="panel stat"><div class="stat-value"><?= (int) ($row['bloods'] ?? 0) ?></div><div class="stat-label">First bloods</div></div>
</div>

<div class="grid grid-sidebar">
  <div class="stack">
    <section class="panel">
      <div class="panel-head"><h2>Score over time</h2></div>
      <div class="panel-body"><div class="chart-box sm"><canvas id="team-chart" data-series='<?= e(json_encode($timeline)) ?>' role="img" aria-label="Team score over time"></canvas></div></div>
    </section>

    <section class="panel">
      <div class="panel-head"><h2>Solves</h2></div>
      <?php if (!$solves): ?>
        <div class="empty" style="padding:var(--s-8)"><?= icon('flag') ?><p>No solves yet.</p></div>
      <?php else: ?>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Challenge</th><th>Solved by</th><th>When</th><th class="r">Points</th></tr></thead>
        <tbody>
        <?php foreach ($solves as $s): ?>
          <tr>
            <td><strong><?= e($s['title']) ?></strong> <?php if ($s['solve_rank'] <= 3 && $s['bonus_points']): ?><span class="badge <?= $s['solve_rank'] == 1 ? 'badge-blood' : 'badge-accent' ?>"><?= icon('droplet') ?><?= ['', '1st', '2nd', '3rd'][$s['solve_rank']] ?></span><?php endif ?><div class="subtle xs"><?= e($s['category']) ?></div></td>
            <td class="small"><?= e($s['username'] ?? '—') ?></td>
            <td class="small"><?= ts($s['created_at'], 'ago') ?></td>
            <td class="r num"><?= (int) $s['points_awarded'] ?><?= $s['bonus_points'] ? '<span class="subtle"> +' . (int) $s['bonus_points'] . '</span>' : '' ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table></div>
      <?php endif ?>
    </section>

    <?php if ($awards): ?>
    <section class="panel">
      <div class="panel-head"><h2>Awards and adjustments</h2></div>
      <ul class="feed">
        <?php foreach ($awards as $a): ?>
          <li>
            <span class="feed-icon <?= $a['delta'] >= 0 ? 'award' : '' ?>"><?= icon($a['delta'] >= 0 ? 'award' : 'minus', 'sm') ?></span>
            <div class="grow">
              <strong><?= e($awardLabels[$a['type']] ?? 'Award') ?></strong><?= $a['challenge_title'] ? ' on ' . e($a['challenge_title']) : '' ?><?= $a['username'] ? ' for ' . e($a['username']) : '' ?>
              <div class="muted"><?= e($a['reason']) ?></div>
              <div><?= ts($a['created_at'], 'ago') ?></div>
            </div>
            <span class="num" style="color:<?= $a['delta'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= $a['delta'] >= 0 ? '+' : '−' ?><?= abs((int) $a['delta']) ?></span>
          </li>
        <?php endforeach ?>
      </ul>
    </section>
    <?php endif ?>
  </div>

  <aside class="stack">
    <section class="panel">
      <div class="panel-head"><h2>Members</h2><span class="subtle xs"><?= count($members) ?>/<?= (int) setting('team_size_max') ?></span></div>
      <ul class="feed">
        <?php foreach ($members as $m): ?>
          <li>
            <span class="avatar"><?= e(initials($m['username'])) ?></span>
            <div class="grow">
              <strong><?= e($m['username']) ?></strong><?php if ((int) $team['captain_id'] === (int) $m['id']): ?> <span class="badge badge-accent"><?= icon('crown') ?>Captain</span><?php endif ?>
              <div class="subtle xs"><?= (int) $m['solves'] ?> solves, <?= (int) $m['points'] ?> pts</div>
            </div>
            <?php if ($isCaptain && (int) $m['id'] !== (int) current_user()['id']): ?>
              <details class="menu">
                <summary class="btn btn-ghost btn-sm btn-icon" aria-label="Member actions"><?= icon('chevron-down', 'sm') ?></summary>
                <div class="menu-list">
                  <form method="post" action="/team/captain/<?= (int) $m['id'] ?>" data-confirm="Make <?= e($m['username']) ?> the captain? You'll lose captain controls."><?= csrf_field() ?><button type="submit"><?= icon('crown', 'sm') ?>Make captain</button></form>
                  <form method="post" action="/team/kick/<?= (int) $m['id'] ?>" data-confirm="Remove <?= e($m['username']) ?> from the team?"><?= csrf_field() ?><button type="submit"><?= icon('x', 'sm') ?>Remove from team</button></form>
                </div>
              </details>
            <?php endif ?>
          </li>
        <?php endforeach ?>
      </ul>
    </section>

    <?php if ($categories): ?>
    <section class="panel">
      <div class="panel-head"><h2>By category</h2></div>
      <div class="panel-body stack-sm">
        <?php foreach ($categories as $c): ?>
          <div>
            <div class="row-between small"><span><?= e($c['name']) ?></span><span class="num subtle"><?= (int) $c['solved'] ?>/<?= (int) $c['total'] ?></span></div>
            <div class="bar" style="margin-top:4px"><span style="width:<?= $c['total'] ? round($c['solved'] / $c['total'] * 100) : 0 ?>%"></span></div>
          </div>
        <?php endforeach ?>
      </div>
    </section>
    <?php endif ?>

    <?php if ($isCaptain): ?>
    <section class="panel">
      <div class="panel-head"><h2>Team settings</h2></div>
      <form class="panel-body" method="post" action="/team/update">
        <?= csrf_field() ?>
        <div class="field"><label class="label" for="t-name">Name</label><input class="input" id="t-name" name="name" value="<?= e($team['name']) ?>" maxlength="40" required></div>
        <div class="field"><label class="label" for="t-aff">College or organisation</label><input class="input" id="t-aff" name="affiliation" value="<?= e($team['affiliation']) ?>" maxlength="120"></div>
        <div class="field"><label class="label" for="t-country">Country</label><input class="input" id="t-country" name="country" value="<?= e($team['country']) ?>" maxlength="64"></div>
        <button class="btn btn-primary btn-sm" type="submit" style="margin-top:var(--s-4)">Save team</button>
      </form>
      <?php if (!$team['is_solo']): ?>
      <div class="panel-foot">
        <form method="post" action="/team/regenerate" data-confirm="Create a new invite code? The current one stops working."><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('refresh', 'sm') ?>New invite code</button></form>
      </div>
      <?php endif ?>
    </section>
    <?php endif ?>

    <?php if ($isMine): ?>
      <form method="post" action="/team/leave" data-confirm="Leave <?= e($team['name']) ?>?"><?= csrf_field() ?><button class="btn btn-danger btn-block" type="submit"><?= icon('log-out', 'sm') ?>Leave team</button></form>
    <?php endif ?>
  </aside>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" defer></script>
<script src="<?= asset('js/charts.js') ?>" defer></script>
<script src="<?= asset('js/team.js') ?>" defer></script>
