<div class="page-head">
  <div>
    <h1>Hi, <?= e($user['username']) ?></h1>
    <p><?= $team ? 'Playing for <a href="/team">' . e($team['name']) . '</a>.' : 'You are not on a team yet.' ?></p>
  </div>
  <?php if ($team && in_array($state, ['live', 'paused', 'ended'], true)): ?>
    <a class="btn btn-primary" href="/challenges"><?= icon('target', 'sm') ?>Open challenges</a>
  <?php endif ?>
</div>

<?php if (!$team): ?>
  <div class="panel" style="margin-bottom:var(--s-6)">
    <div class="panel-body row-between">
      <div>
        <h2 style="margin-bottom:var(--s-1)">Create or join a team to play</h2>
        <p class="muted" style="margin:0">Teams of up to <?= (int) setting('team_size_max') ?> share points.<?= setting('allow_solo') === '1' ? ' You can also play solo.' : '' ?></p>
      </div>
      <a class="btn btn-primary" href="/team"><?= icon('users', 'sm') ?>Set up your team</a>
    </div>
  </div>
<?php else: ?>
  <div class="grid grid-4" style="margin-bottom:var(--s-6)">
    <div class="panel stat"><div class="stat-value"><?= number_format($score) ?></div><div class="stat-label">Team score</div></div>
    <div class="panel stat"><div class="stat-value"><?= $rank ? '#' . $rank : '—' ?></div><div class="stat-label">Rank of <?= (int) $teamCount ?> teams</div></div>
    <div class="panel stat"><div class="stat-value"><?= (int) $solved ?><span class="subtle" style="font-size:var(--fs-md)">/<?= (int) $totalChallenges ?></span></div><div class="stat-label">Challenges solved</div></div>
    <div class="panel stat"><div class="stat-value"><?= (int) $mySolves ?></div><div class="stat-label">Solved by you</div></div>
  </div>
<?php endif ?>

<div class="grid grid-sidebar">
  <div class="stack">
    <?php if ($team && !empty($next) && $state === 'live'): ?>
    <section class="panel">
      <div class="panel-head"><h2>Good next picks</h2><a class="small" href="/challenges">All challenges</a></div>
      <div class="panel-body flush">
        <table class="table">
          <tbody>
          <?php foreach ($next as $c): ?>
            <tr>
              <td><a class="rowlink" href="/challenges#c<?= (int) $c['id'] ?>"><?= e($c['title']) ?></a><div class="subtle xs"><?= e($c['category']) ?></div></td>
              <td><span class="diff diff-<?= e($c['difficulty']) ?>"><?= e(ucfirst($c['difficulty'])) ?></span></td>
              <td class="r subtle small"><?= (int) $c['solve_count'] ?> solves</td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif ?>

    <section class="panel">
      <div class="panel-head"><h2>Announcements</h2><a class="small" href="/notifications">See all</a></div>
      <?php if (!$announcements): ?>
        <div class="empty" style="padding:var(--s-8) var(--s-4)"><?= icon('megaphone') ?><p>No announcements yet. Updates from the organisers will appear here.</p></div>
      <?php else: foreach ($announcements as $a): ?>
        <article class="announcement">
          <h3><?php if ($a['is_pinned']): ?><span class="badge badge-accent">Pinned</span><?php endif ?><?= e($a['title']) ?></h3>
          <div class="prose small muted"><?= md($a['body']) ?></div>
          <div class="subtle xs"><?= ts($a['created_at'], 'ago') ?></div>
        </article>
      <?php endforeach; endif ?>
    </section>

    <?php if ($team): ?>
    <section class="panel">
      <div class="panel-head"><h2>Your team's recent solves</h2></div>
      <?php if (empty($recent)): ?>
        <div class="empty" style="padding:var(--s-8) var(--s-4)"><?= icon('flag') ?><p>No solves yet. Your first flag is waiting.</p></div>
      <?php else: ?>
        <ul class="feed">
          <?php foreach ($recent as $s): ?>
            <li>
              <span class="feed-icon <?= $s['solve_rank'] == 1 ? 'blood' : 'solve' ?>"><?= icon($s['solve_rank'] == 1 ? 'droplet' : 'check', 'sm') ?></span>
              <div class="grow"><strong><?= e($s['title']) ?></strong> by <?= e($s['username'] ?? 'a former member') ?><div><?= ts($s['created_at'], 'ago') ?></div></div>
              <span class="num">+<?= (int) $s['points_awarded'] + (int) $s['bonus_points'] ?></span>
            </li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
    </section>
    <?php endif ?>
  </div>

  <aside class="stack">
    <section class="panel"><div class="panel-body"><?= partial('countdown') ?></div></section>
    <section class="panel">
      <div class="panel-head"><h2>Live activity</h2></div>
      <?= partial('feed', ['feed' => $feed]) ?>
    </section>
  </aside>
</div>
<script src="<?= asset('js/live.js') ?>" defer></script>
