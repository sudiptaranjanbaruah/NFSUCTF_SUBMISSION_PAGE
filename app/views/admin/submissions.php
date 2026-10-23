<?php $f = fn($k) => e($filters[$k] ?? ''); $qs = fn($p) => '?' . http_build_query(array_merge(array_intersect_key($filters, array_flip(['team', 'challenge', 'result', 'ip', 'q'])), ['page' => $p])); ?>
<div class="page-head">
  <div><h1>Submissions</h1><p><?= number_format($total) ?> matching submissions. Correct flags are stored as [correct]; wrong ones are kept for review.</p></div>
  <a class="btn" href="/admin/export/submissions"><?= icon('download', 'sm') ?>Export CSV</a>
</div>
<section class="panel">
  <form class="panel-head filters" method="get" data-autosubmit>
    <select class="select" name="team" aria-label="Team"><option value="">All teams</option><?php foreach ($teams as $t): ?><option value="<?= (int) $t['id'] ?>" <?= ($filters['team'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach ?></select>
    <select class="select" name="challenge" aria-label="Challenge"><option value="">All challenges</option><?php foreach ($challenges as $c): ?><option value="<?= (int) $c['id'] ?>" <?= ($filters['challenge'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach ?></select>
    <select class="select" name="result" aria-label="Result"><option value="">Any result</option><option value="1" <?= ($filters['result'] ?? '') === '1' ? 'selected' : '' ?>>Correct</option><option value="0" <?= ($filters['result'] ?? '') === '0' ? 'selected' : '' ?>>Wrong</option></select>
    <input class="input" name="ip" value="<?= $f('ip') ?>" placeholder="IP address" aria-label="IP address">
    <input class="input" name="q" value="<?= $f('q') ?>" placeholder="Submitted text contains" aria-label="Search submitted text">
    <button class="btn btn-sm" type="submit"><?= icon('search', 'sm') ?>Filter</button>
    <?php if (array_filter(array_intersect_key($filters, array_flip(['team', 'challenge', 'result', 'ip', 'q'])))): ?><a class="small" href="/admin/submissions">Clear</a><?php endif ?>
  </form>
  <?php if (!$rows): ?>
    <div class="empty"><?= icon('list') ?><p>No submissions match these filters.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>When</th><th>Team / player</th><th>Challenge</th><th>Submitted</th><th>IP</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $s): ?>
      <tr>
        <td class="small nowrap"><?= ts($s['created_at']) ?></td>
        <td><a class="rowlink" href="/admin/teams/<?= (int) $s['team_id'] ?>"><?= e($s['team']) ?></a><div class="subtle xs"><?= e($s['username'] ?? '') ?></div></td>
        <td class="small"><?= e($s['challenge']) ?></td>
        <td><?= $s['correct'] ? '<span class="badge badge-success">' . icon('check') . 'Correct</span>' : '<span class="mono small" style="color:var(--danger)">' . e(mb_strimwidth($s['provided'], 0, 60, '…')) . '</span>' ?></td>
        <td class="mono xs"><a href="/admin/submissions?ip=<?= e(urlencode((string) $s['ip'])) ?>"><?= e($s['ip']) ?></a></td>
        <td class="r nowrap">
          <?php if ($s['correct']): ?><a class="btn btn-ghost btn-sm" href="/admin/scoring?team=<?= (int) $s['team_id'] ?>&user=<?= (int) $s['user_id'] ?>&challenge=<?= (int) $s['challenge_id'] ?>&type=unique_solve" title="Award a bonus for this solve"><?= icon('award', 'sm') ?>Bonus</a><?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
  <div class="pagination">
    <span>Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page > 1): ?><a class="btn btn-sm" href="<?= e($qs($page - 1)) ?>">Previous</a><?php endif ?>
    <?php if ($page < $pages): ?><a class="btn btn-sm" href="<?= e($qs($page + 1)) ?>">Next</a><?php endif ?>
  </div>
  <?php endif ?>
</section>

<section class="panel" style="margin-top:var(--s-6);max-width:720px">
  <div class="panel-head"><h2>Record a solve manually</h2><span class="subtle xs">For flags lost to platform issues</span></div>
  <form class="panel-body" method="post" action="/admin/solves/manual">
    <?= csrf_field() ?>
    <div class="grid grid-2">
      <div class="field"><label class="label" for="ms-team">Team</label><select class="select" id="ms-team" name="team_id" required><option value="">Choose…</option><?php foreach ($teams as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach ?></select></div>
      <div class="field"><label class="label" for="ms-ch">Challenge</label><select class="select" id="ms-ch" name="challenge_id" required><option value="">Choose…</option><?php foreach ($challenges as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['title']) ?></option><?php endforeach ?></select></div>
    </div>
    <div class="field"><label class="label" for="ms-reason">Reason</label><input class="input" id="ms-reason" name="reason" required placeholder="Submission failed during the outage at 14:05"></div>
    <div class="field"><label class="check"><input type="checkbox" name="with_bonus" value="1"> Include first-blood bonus if they qualify</label></div>
    <button class="btn" type="submit" style="margin-top:var(--s-4)">Record solve</button>
  </form>
</section>
