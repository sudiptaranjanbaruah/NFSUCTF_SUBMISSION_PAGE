<div class="page-head">
  <div><h1>Teams</h1><p><?= count($rows) ?> teams.</p></div>
  <div class="row"><form method="get" class="filters"><input class="input" name="q" value="<?= e($q) ?>" placeholder="Search teams" aria-label="Search teams"></form><a class="btn" href="/admin/export/scoreboard"><?= icon('download', 'sm') ?>Export scoreboard</a></div>
</div>
<section class="panel">
  <?php if (!$rows): ?><div class="empty"><?= icon('users') ?><p>No teams yet.</p></div><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Rank</th><th>Team</th><th>Captain</th><th class="r">Members</th><th class="r">Solves</th><th class="r">Score</th><th>Status</th><th>Created</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $t): ?>
      <tr>
        <td><?= $t['rank'] ? '<span class="rank ' . ($t['rank'] <= 3 ? 'rank-' . $t['rank'] : '') . '">' . (int) $t['rank'] . '</span>' : '<span class="subtle">—</span>' ?></td>
        <td><a class="rowlink" href="/admin/teams/<?= (int) $t['id'] ?>"><?= e($t['name']) ?></a><?= $t['affiliation'] ? '<div class="subtle xs">' . e($t['affiliation']) . '</div>' : '' ?></td>
        <td class="small"><?= e($t['captain'] ?? '—') ?></td>
        <td class="r num"><?= (int) $t['members'] ?></td>
        <td class="r num"><?= (int) $t['solves'] ?></td>
        <td class="r score"><?= number_format((int) $t['score']) ?></td>
        <td><?= $t['is_banned'] ? '<span class="badge badge-danger">Disqualified</span>' : ($t['is_hidden'] ? '<span class="badge badge-warning">Hidden</span>' : ($t['is_solo'] ? '<span class="badge">Solo</span>' : '<span class="badge badge-success">Active</span>')) ?></td>
        <td class="small"><?= ts($t['created_at'], 'ago') ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
  <?php endif ?>
</section>
