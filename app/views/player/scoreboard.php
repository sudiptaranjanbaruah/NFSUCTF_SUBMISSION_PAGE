<div class="page-head">
  <div>
    <h1>Scoreboard</h1>
    <p><?= count($rows) ?> teams ranked by score. Ties go to whoever got there first.</p>
  </div>
  <?php if ($affiliations): ?>
  <form method="get" class="filters" data-autosubmit>
    <label class="sr-only" for="aff">College</label>
    <select class="select" id="aff" name="affiliation">
      <option value="">All colleges</option>
      <?php foreach ($affiliations as $a): ?><option <?= $aff === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach ?>
    </select>
  </form>
  <?php endif ?>
</div>

<?php if ($cutoff): ?>
  <div class="freeze-banner"><?= icon('snowflake') ?><div>The scoreboard froze at <?= ts(gmdate('Y-m-d H:i:s', $cutoff), 'full') ?>. Final standings are revealed at the closing ceremony.</div></div>
<?php endif ?>

<?php if (!$rows): ?>
  <div class="panel"><div class="empty"><?= icon('trophy') ?><h3>No teams yet</h3><p>Teams appear here as soon as they register.</p><a class="btn btn-primary" href="/register">Register your team</a></div></div>
<?php else: ?>
<div class="grid grid-sidebar" style="margin-bottom:var(--s-6)">
  <section class="panel">
    <div class="panel-head">
      <div><h2>How far teams got</h2><div class="subtle xs" style="margin-top:2px">Share of scoring teams that solved at least each share of the challenges</div></div>
      <span class="subtle xs" data-sb-updated></span>
    </div>
    <div class="panel-body"><div class="chart-box"><canvas id="sb-chart" data-distribution='<?= e(json_encode($distribution)) ?>' aria-label="Percentage of teams against percentage of tasks solved" role="img"></canvas></div></div>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>First bloods</h2></div>
    <?= partial('feed', ['feed' => array_map(fn($f) => $f + ['kind' => 'blood', 'subject' => $f['challenge'], 'amount' => $f['bonus_points'], 'reason' => null], $feed)]) ?>
  </section>
</div>

<section class="panel">
  <div class="table-wrap">
    <table class="table" id="sb-table">
      <thead><tr><th style="width:64px">Rank</th><th>Team</th><th class="r">Solves</th><th class="r">First bloods</th><th class="r">Last scored</th><th class="r">Score</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $t): ?>
        <tr class="<?= $myTeam && (int) $myTeam === (int) $t['id'] ? 'is-me' : '' ?>">
          <td><span class="rank <?= $t['rank'] <= 3 ? 'rank-' . $t['rank'] : '' ?>"><?= (int) $t['rank'] ?></span></td>
          <td>
            <a class="rowlink" href="/teams/<?= (int) $t['id'] ?>"><?= e($t['name']) ?></a>
            <?php if ($t['is_solo']): ?><span class="badge" style="margin-left:6px">Solo</span><?php endif ?>
            <?php if ($t['affiliation']): ?><div class="subtle xs"><?= e($t['affiliation']) ?></div><?php endif ?>
          </td>
          <td class="r num"><?= (int) $t['solves'] ?></td>
          <td class="r num"><?= $t['bloods'] ? '<span class="badge badge-blood">' . icon('droplet') . (int) $t['bloods'] . '</span>' : '<span class="subtle">0</span>' ?></td>
          <td class="r small"><?= $t['solves'] ? ts($t['last_at'], 'ago') : '<span class="subtle">—</span>' ?></td>
          <td class="r score"><?= number_format($t['score']) ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</section>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" defer></script>
<script src="<?= asset('js/charts.js') ?>" defer></script>
<script src="<?= asset('js/scoreboard.js') ?>" defer></script>
<?php endif ?>
