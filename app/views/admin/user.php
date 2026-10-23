<?php $isSuper = current_user()['role'] === 'super_admin'; ?>
<div class="page-head">
  <div class="team-hero">
    <span class="avatar lg"><?= e(initials($u['username'])) ?></span>
    <div>
      <a class="small" href="/admin/users"><?= icon('arrow-left', 'sm') ?> Users</a>
      <h1><?= e($u['username']) ?></h1>
      <p><?= e($u['email']) ?><?= $u['team'] ? ', on <a href="/admin/teams/' . (int) $u['team_id'] . '">' . e($u['team']) . '</a>' : ', no team' ?></p>
    </div>
  </div>
  <div class="row wrap">
    <?php if (!$u['email_verified_at']): ?>
      <form method="post" action="/admin/users/<?= (int) $u['id'] ?>"><?= csrf_field() ?><input type="hidden" name="action" value="verify"><button class="btn" type="submit"><?= icon('check', 'sm') ?>Mark verified</button></form>
    <?php endif ?>
    <form method="post" action="/admin/users/<?= (int) $u['id'] ?>" data-confirm="<?= $u['is_banned'] ? 'Unban' : 'Ban' ?> <?= e($u['username']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $u['is_banned'] ? 'unban' : 'ban' ?>"><button class="btn <?= $u['is_banned'] ? '' : 'btn-danger' ?>" type="submit"><?= icon('ban', 'sm') ?><?= $u['is_banned'] ? 'Unban' : 'Ban user' ?></button></form>
  </div>
</div>

<div class="grid grid-2">
  <section class="panel">
    <div class="panel-head"><h2>Profile</h2></div>
    <form class="panel-body" method="post" action="/admin/users/<?= (int) $u['id'] ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <div class="grid grid-2">
        <div class="field"><label class="label" for="username">Username</label><input class="input" id="username" name="username" value="<?= e($u['username']) ?>"></div>
        <div class="field"><label class="label" for="email">Email</label><input class="input" id="email" name="email" value="<?= e($u['email']) ?>"></div>
        <div class="field"><label class="label" for="full_name">Full name</label><input class="input" id="full_name" name="full_name" value="<?= e($u['full_name']) ?>"></div>
        <div class="field"><label class="label" for="affiliation">College</label><input class="input" id="affiliation" name="affiliation" value="<?= e($u['affiliation']) ?>"></div>
      </div>
      <button class="btn btn-primary btn-sm" type="submit" style="margin-top:var(--s-4)">Save profile</button>
    </form>
    <div class="panel-foot">
      <dl class="kv">
        <dt>Joined</dt><dd><?= ts($u['created_at'], 'full') ?></dd>
        <dt>Verified</dt><dd><?= $u['email_verified_at'] ? ts($u['email_verified_at'], 'full') : 'No' ?></dd>
        <dt>Last sign-in</dt><dd><?= ts($u['last_login_at'], 'full') ?></dd>
        <dt>Sign-in methods</dt><dd><?= $u['password_hash'] ? 'Password, ' : '' ?><?= implode(', ', array_map(fn($i) => ucfirst($i['provider']), $identities)) ?: '' ?> email code</dd>
      </dl>
    </div>
  </section>

  <div class="stack">
    <section class="panel">
      <div class="panel-head"><h2>Team</h2></div>
      <form class="panel-body row" method="post" action="/admin/users/<?= (int) $u['id'] ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="team">
        <select class="select grow" name="team_id" aria-label="Team"><option value="">No team</option><?php foreach ($teams as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) $u['team_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach ?></select>
        <button class="btn" type="submit">Move</button>
      </form>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>Role</h2></div>
      <form class="panel-body row" method="post" action="/admin/users/<?= (int) $u['id'] ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="role">
        <select class="select grow" name="role" <?= $isSuper ? '' : 'disabled' ?> aria-label="Role">
          <?php foreach (['user' => 'Player', 'admin' => 'Admin', 'super_admin' => 'Super admin'] as $k => $label): ?><option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach ?>
        </select>
        <button class="btn" type="submit" <?= $isSuper ? '' : 'disabled' ?>>Change</button>
      </form>
      <?php if (!$isSuper): ?><div class="panel-foot help">Only super admins can change roles.</div><?php endif ?>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>IP addresses</h2></div>
      <?php if (!$ips): ?><div class="panel-body subtle small">No submissions yet.</div><?php else: ?>
      <table class="table"><tbody><?php foreach ($ips as $ip): ?><tr><td class="mono small"><a href="/admin/submissions?ip=<?= e(urlencode((string) $ip['ip'])) ?>"><?= e($ip['ip']) ?></a></td><td class="r num small"><?= (int) $ip['n'] ?></td><td class="r small"><?= ts($ip['last'], 'ago') ?></td></tr><?php endforeach ?></tbody></table>
      <?php endif ?>
    </section>
  </div>
</div>

<div class="grid grid-2" style="margin-top:var(--s-6)">
  <section class="panel">
    <div class="panel-head"><h2>Solves</h2></div>
    <?php if (!$solves): ?><div class="empty" style="padding:var(--s-6)"><p>No solves.</p></div><?php else: ?>
    <table class="table"><tbody><?php foreach ($solves as $s): ?><tr><td><?= e($s['title']) ?></td><td class="small"><?= ts($s['created_at'], 'ago') ?></td><td class="r num"><?= (int) $s['points_awarded'] + (int) $s['bonus_points'] ?></td></tr><?php endforeach ?></tbody></table>
    <?php endif ?>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Recent sign-ins</h2></div>
    <?php if (!$logins): ?><div class="empty" style="padding:var(--s-6)"><p>No sign-in activity.</p></div><?php else: ?>
    <div class="table-wrap"><table class="table"><tbody><?php foreach ($logins as $l): ?>
      <tr><td><?= $l['success'] ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-danger">Failed</span>' ?></td><td class="small"><?= e($l['method']) ?></td><td class="mono xs"><?= e($l['ip']) ?></td><td class="small r"><?= ts($l['created_at'], 'ago') ?></td></tr>
    <?php endforeach ?></tbody></table></div>
    <?php endif ?>
  </section>
</div>
