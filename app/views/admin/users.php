<?php $tabs = ['' => 'All', 'unverified' => 'Unverified', 'no_team' => 'No team', 'admins' => 'Admins', 'banned' => 'Banned']; ?>
<div class="page-head">
  <div><h1>Users</h1><p><?= number_format($total) ?> accounts.</p></div>
  <div class="row"><a class="btn" href="/admin/export/users"><?= icon('download', 'sm') ?>Export CSV</a><a class="btn btn-primary" href="/admin/invites"><?= icon('user-plus', 'sm') ?>Invite people</a></div>
</div>
<section class="panel">
  <div class="tabs" style="padding:0 var(--s-3)">
    <?php foreach ($tabs as $k => $label): ?><a href="/admin/users?<?= e(http_build_query(['filter' => $k, 'q' => $q])) ?>" class="<?= (string) $filter === $k ? 'is-active' : '' ?>"><?= $label ?></a><?php endforeach ?>
  </div>
  <form class="panel-head filters" method="get"><input type="hidden" name="filter" value="<?= e($filter) ?>"><input class="input" name="q" value="<?= e($q) ?>" placeholder="Search name, username or email" style="min-width:280px" aria-label="Search users"><button class="btn btn-sm" type="submit"><?= icon('search', 'sm') ?>Search</button></form>
  <?php if (!$rows): ?><div class="empty"><?= icon('user') ?><p>No users match.</p></div><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>User</th><th>Team</th><th>Sign-in</th><th>Status</th><th class="r">Solves</th><th>Joined</th><th>Last seen</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $u): ?>
      <tr>
        <td><a class="rowlink" href="/admin/users/<?= (int) $u['id'] ?>"><?= e($u['username']) ?></a><div class="subtle xs"><?= e($u['email']) ?></div></td>
        <td class="small"><?= $u['team'] ? '<a href="/admin/teams/' . (int) $u['team_id'] . '">' . e($u['team']) . '</a>' : '<span class="subtle">—</span>' ?></td>
        <td class="small"><?= $u['password_hash'] ? 'Password' : '' ?><?= $u['providers'] ? ($u['password_hash'] ? ', ' : '') . e(ucwords(str_replace(',', ', ', $u['providers']))) : '' ?><?= !$u['password_hash'] && !$u['providers'] ? 'Email code' : '' ?></td>
        <td>
          <?php if ($u['role'] !== 'user'): ?><span class="badge badge-accent"><?= e(str_replace('_', ' ', $u['role'])) ?></span><?php endif ?>
          <?php if ($u['is_banned']): ?><span class="badge badge-danger">Banned</span><?php elseif (!$u['email_verified_at']): ?><span class="badge badge-warning">Unverified</span><?php else: ?><span class="badge badge-success">Active</span><?php endif ?>
        </td>
        <td class="r num"><?= (int) $u['solves'] ?></td>
        <td class="small"><?= ts($u['created_at'], 'ago') ?></td>
        <td class="small"><?= ts($u['last_seen_at'], 'ago') ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
  <div class="pagination"><span>Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page > 1): ?><a class="btn btn-sm" href="?<?= e(http_build_query(['filter' => $filter, 'q' => $q, 'page' => $page - 1])) ?>">Previous</a><?php endif ?>
    <?php if ($page < $pages): ?><a class="btn btn-sm" href="?<?= e(http_build_query(['filter' => $filter, 'q' => $q, 'page' => $page + 1])) ?>">Next</a><?php endif ?>
  </div>
  <?php endif ?>
</section>
