<div class="page-head">
  <div class="team-hero">
    <span class="avatar lg"><?= e(initials($user['username'])) ?></span>
    <div><h1><?= e($user['username']) ?></h1><p><?= e($user['email']) ?><?= $team ? ', playing for <a href="/team">' . e($team['name']) . '</a>' : '' ?></p></div>
  </div>
</div>
<div class="grid grid-2">
  <section class="panel">
    <div class="panel-head"><h2>Account details</h2></div>
    <form class="panel-body" method="post" action="/profile">
      <?= csrf_field() ?>
      <div class="field"><label class="label" for="username">Username</label><input class="input" id="username" name="username" value="<?= e($user['username']) ?>" required pattern="[A-Za-z0-9_.\-]{3,24}"></div>
      <div class="field"><label class="label" for="full_name">Full name</label><input class="input" id="full_name" name="full_name" value="<?= e($user['full_name']) ?>"></div>
      <div class="grid grid-2" style="margin-top:var(--s-4)">
        <div class="field"><label class="label" for="affiliation">College</label><input class="input" id="affiliation" name="affiliation" value="<?= e($user['affiliation']) ?>"></div>
        <div class="field"><label class="label" for="country">Country</label><input class="input" id="country" name="country" value="<?= e($user['country']) ?>"></div>
      </div>
      <div class="field"><label class="label">Email</label><input class="input" value="<?= e($user['email']) ?>" disabled><div class="help">Contact the organisers to change your email.</div></div>
      <button class="btn btn-primary" type="submit" style="margin-top:var(--s-5)">Save changes</button>
    </form>
  </section>

  <div class="stack" id="security">
    <section class="panel">
      <div class="panel-head"><h2><?= $user['password_hash'] ? 'Change password' : 'Set a password' ?></h2></div>
      <form class="panel-body" method="post" action="/profile/password">
        <?= csrf_field() ?>
        <?php if ($user['password_hash']): ?>
          <div class="field"><label class="label" for="cur">Current password</label><?= partial('password_input', ['name' => 'current_password', 'id' => 'cur', 'autocomplete' => 'current-password']) ?></div>
        <?php else: ?>
          <p class="muted small">You sign in with email codes or a linked account. Add a password if you'd like another option.</p>
        <?php endif ?>
        <div class="field"><label class="label" for="newpw">New password</label><?= partial('password_input', ['name' => 'password', 'id' => 'newpw', 'autocomplete' => 'new-password', 'meter' => true]) ?></div>
        <button class="btn btn-primary" type="submit" style="margin-top:var(--s-5)"><?= $user['password_hash'] ? 'Change password' : 'Set password' ?></button>
      </form>
    </section>

    <section class="panel">
      <div class="panel-head"><h2>Connected accounts</h2></div>
      <div class="panel-body stack-sm">
        <?php foreach (['google' => 'Google', 'github' => 'GitHub'] as $p => $label):
          if (!env(strtoupper($p) . '_CLIENT_ID')) continue;
          $linked = array_values(array_filter($identities, fn($i) => $i['provider'] === $p))[0] ?? null; ?>
          <div class="row-between">
            <div><strong><?= $label ?></strong><div class="subtle xs"><?= $linked ? 'Connected as ' . e($linked['email'] ?: 'your account') : 'Not connected' ?></div></div>
            <?php if ($linked): ?>
              <form method="post" action="/profile/unlink/<?= $p ?>" data-confirm="Disconnect <?= $label ?>?"><?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit">Disconnect</button></form>
            <?php else: ?>
              <a class="btn btn-sm" href="/auth/<?= $p ?>">Connect</a>
            <?php endif ?>
          </div>
        <?php endforeach ?>
        <p class="help">You can always sign in with a code sent to <?= e($user['email']) ?>.</p>
      </div>
    </section>
  </div>
</div>

<section class="panel" style="margin-top:var(--s-6)">
  <div class="panel-head"><h2>Your solves</h2></div>
  <?php if (!$solves): ?>
    <div class="empty" style="padding:var(--s-8)"><?= icon('flag') ?><p>Flags you capture for your team show up here.</p></div>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Challenge</th><th>Category</th><th>When</th><th class="r">Points</th></tr></thead>
      <tbody><?php foreach ($solves as $s): ?>
        <tr><td><strong><?= e($s['title']) ?></strong></td><td class="small"><?= e($s['category']) ?></td><td class="small"><?= ts($s['created_at'], 'ago') ?></td><td class="r num"><?= (int) $s['points_awarded'] + (int) $s['bonus_points'] ?></td></tr>
      <?php endforeach ?></tbody>
    </table></div>
  <?php endif ?>
</section>
