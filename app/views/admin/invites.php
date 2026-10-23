<div class="page-head">
  <div><h1>Invites and bulk import</h1><p>Register participants from a list. Each person gets an email with a one-time link to set their password and join their team.</p></div>
</div>
<div class="grid grid-sidebar">
  <section class="panel">
    <div class="panel-head"><h2>Import participants</h2></div>
    <form class="panel-body" method="post" action="/admin/invites/import" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field">
        <label class="label" for="csv">Paste CSV <span class="subtle">email, name, team</span></label>
        <textarea class="textarea code" id="csv" name="csv" rows="8" placeholder="email,name,team&#10;ananya@college.edu,Ananya Das,Kamakhya Crackers&#10;rohan@college.edu,Rohan Bora,Kamakhya Crackers&#10;priya@college.edu,Priya Sharma,"></textarea>
        <div class="help">Name and team are optional. Invitees with the same team name are placed on that team; it is created if it doesn't exist.</div>
      </div>
      <div class="field"><label class="label" for="csv_file">Or upload a .csv file</label><input class="input" type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" style="height:auto;padding:6px"></div>
      <div class="grid grid-2" style="margin-top:var(--s-4)">
        <div class="field"><label class="label" for="days">Link valid for (days)</label><input class="input num" type="number" id="days" name="days" min="1" max="60" value="14"></div>
        <div class="field" style="justify-content:flex-end"><label class="check"><input type="checkbox" name="send" value="1" checked> Email the invites</label></div>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:var(--s-5)"><?= icon('upload', 'sm') ?>Create invites</button>
    </form>
  </section>
  <aside class="stack">
    <section class="panel">
      <div class="panel-head"><h2>Email queue</h2></div>
      <div class="panel-body">
        <dl class="kv">
          <dt>Waiting to send</dt><dd class="num"><?= $queued ?></dd>
          <dt>Sent in the last hour</dt><dd class="num"><?= $sentHour ?> / <?= (int) env('MAIL_HOURLY_CAP', 90) ?></dd>
          <dt>Failed</dt><dd class="num"><?= $failed ?></dd>
        </dl>
        <p class="help" style="margin:var(--s-3) 0">The cron job sends queued mail every minute within your Hostinger hourly limit.</p>
        <form method="post" action="/admin/invites/drain"><?= csrf_field() ?><button class="btn btn-sm" type="submit" <?= $queued ? '' : 'disabled' ?>><?= icon('mail', 'sm') ?>Send a batch now</button></form>
      </div>
    </section>
  </aside>
</div>

<section class="panel" style="margin-top:var(--s-6)">
  <div class="panel-head"><h2>Invites</h2><span class="subtle xs">Most recent 300</span></div>
  <?php if (!$rows): ?><div class="empty"><?= icon('mail') ?><p>No invites yet.</p></div><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Email</th><th>Name</th><th>Team</th><th>Status</th><th>Created</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $i):
      $expired = !$i['used_at'] && strtotime($i['expires_at'] . ' UTC') < time(); ?>
      <tr>
        <td class="small"><?= e($i['email']) ?></td>
        <td class="small"><?= e($i['full_name'] ?? '—') ?></td>
        <td class="small"><?= e($i['team_name'] ?? '—') ?></td>
        <td><?= $i['used_at'] ? '<span class="badge badge-success">Accepted</span>' : ($expired ? '<span class="badge">Expired</span>' : '<span class="badge badge-warning">Pending</span>') ?></td>
        <td class="small"><?= ts($i['created_at'], 'ago') ?></td>
        <td class="r"><?php if (!$i['used_at']): ?><form method="post" action="/admin/invites/<?= (int) $i['id'] ?>/resend"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Resend</button></form><?php endif ?></td>
      </tr>
    <?php endforeach ?></tbody>
  </table></div>
  <?php endif ?>
</section>
