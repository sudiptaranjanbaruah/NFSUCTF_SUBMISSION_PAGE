<div class="page-head"><div><h1>Anti-cheat</h1><p>Signals worth a closer look. None of these prove cheating on their own: shared campus Wi-Fi, for example, puts many teams on one IP.</p></div></div>
<div class="grid grid-2">
  <section class="panel">
    <div class="panel-head"><h2>Same wrong flag from several teams</h2></div>
    <?php if (!$sharedWrong): ?><div class="empty" style="padding:var(--s-6)"><?= icon('check-circle') ?><p>Nothing suspicious.</p></div><?php else: ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Challenge</th><th>Submitted</th><th>Teams</th></tr></thead><tbody>
      <?php foreach ($sharedWrong as $s): ?><tr><td class="small"><?= e($s['title']) ?></td><td class="mono xs truncate" style="max-width:220px"><a href="/admin/submissions?challenge=<?= (int) $s['challenge_id'] ?>&q=<?= e(urlencode($s['provided'])) ?>"><?= e($s['provided']) ?></a></td><td class="small"><span class="badge badge-warning"><?= (int) $s['teams'] ?></span> <?= e($s['team_names']) ?></td></tr><?php endforeach ?>
    </tbody></table></div>
    <?php endif ?>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Teams solving in lockstep</h2><span class="subtle xs">Same challenge within 60s, 2+ times</span></div>
    <?php if (!$pairs): ?><div class="empty" style="padding:var(--s-6)"><?= icon('check-circle') ?><p>Nothing suspicious.</p></div><?php else: ?>
    <table class="table"><tbody><?php foreach ($pairs as $p): ?><tr><td><a href="/admin/teams/<?= (int) $p['a_id'] ?>"><?= e($p['a_name']) ?></a> and <a href="/admin/teams/<?= (int) $p['b_id'] ?>"><?= e($p['b_name']) ?></a></td><td class="r"><span class="badge badge-warning"><?= (int) $p['n'] ?> times</span></td></tr><?php endforeach ?></tbody></table>
    <?php endif ?>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>IP addresses shared across teams</h2></div>
    <?php if (!$sharedIps): ?><div class="empty" style="padding:var(--s-6)"><?= icon('check-circle') ?><p>Nothing suspicious.</p></div><?php else: ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>IP</th><th>Teams</th><th class="r">Submissions</th></tr></thead><tbody>
      <?php foreach ($sharedIps as $s): ?><tr><td class="mono xs"><a href="/admin/submissions?ip=<?= e(urlencode((string) $s['ip'])) ?>"><?= e($s['ip']) ?></a></td><td class="small"><span class="badge badge-warning"><?= (int) $s['teams'] ?></span> <?= e($s['team_names']) ?></td><td class="r num"><?= (int) $s['n'] ?></td></tr><?php endforeach ?>
    </tbody></table></div>
    <?php endif ?>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Bursts of wrong flags</h2><span class="subtle xs">8+ in one minute</span></div>
    <?php if (!$bursts): ?><div class="empty" style="padding:var(--s-6)"><?= icon('check-circle') ?><p>Nothing suspicious.</p></div><?php else: ?>
    <table class="table"><tbody><?php foreach ($bursts as $b): ?><tr><td><a href="/admin/teams/<?= (int) $b['team_id'] ?>"><?= e($b['name']) ?></a><div class="subtle xs"><?= e($b['title']) ?></div></td><td class="small"><?= ts($b['first']) ?></td><td class="r"><span class="badge badge-danger"><?= (int) $b['n'] ?></span></td></tr><?php endforeach ?></tbody></table>
    <?php endif ?>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Failed sign-ins, last 24h</h2><span class="subtle xs">5+ per IP</span></div>
    <?php if (!$failedLogins): ?><div class="empty" style="padding:var(--s-6)"><?= icon('check-circle') ?><p>Nothing suspicious.</p></div><?php else: ?>
    <table class="table"><tbody><?php foreach ($failedLogins as $f): ?><tr><td class="mono xs"><?= e($f['ip']) ?></td><td class="small"><?= (int) $f['emails'] ?> accounts</td><td class="r"><span class="badge badge-danger"><?= (int) $f['n'] ?></span></td></tr><?php endforeach ?></tbody></table>
    <?php endif ?>
  </section>
</div>
