<div class="page-head">
  <div><h1>Audit log</h1><p><?= number_format($total) ?> recorded actions: admin changes, solves, sign-in events and exports.</p></div>
  <form method="get" class="filters" data-autosubmit>
    <select class="select" name="action" aria-label="Action type"><option value="">All actions</option><?php foreach ($actions as $a): ?><option <?= $filterAction === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach ?></select>
  </form>
</div>
<section class="panel">
  <?php if (!$rows): ?><div class="empty"><?= icon('fingerprint') ?><p>No entries.</p></div><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Target</th><th>Details</th><th>IP</th></tr></thead>
    <tbody><?php foreach ($rows as $l): ?>
      <tr>
        <td class="small nowrap"><?= ts($l['created_at']) ?></td>
        <td class="small"><?= $l['user_id'] ? '<a href="/admin/users/' . (int) $l['user_id'] . '">' . e($l['username'] ?? '#' . $l['user_id']) . '</a>' : '<span class="subtle">system</span>' ?></td>
        <td><span class="badge mono"><?= e($l['action']) ?></span></td>
        <td class="mono xs"><?= e($l['target'] ?? '') ?></td>
        <td class="mono xs truncate" style="max-width:340px" title="<?= e($l['meta'] ?? '') ?>"><?= e($l['meta'] ?? '') ?></td>
        <td class="mono xs"><?= e($l['ip']) ?></td>
      </tr>
    <?php endforeach ?></tbody>
  </table></div>
  <div class="pagination"><span>Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page > 1): ?><a class="btn btn-sm" href="?<?= e(http_build_query(['action' => $filterAction, 'page' => $page - 1])) ?>">Previous</a><?php endif ?>
    <?php if ($page < $pages): ?><a class="btn btn-sm" href="?<?= e(http_build_query(['action' => $filterAction, 'page' => $page + 1])) ?>">Next</a><?php endif ?>
  </div>
  <?php endif ?>
</section>
