<?php $chk = fn($k) => setting($k) === '1' ? 'checked' : ''; ?>
<div class="page-head"><div><h1>Settings</h1><p>Times are shown in your local time zone (<span data-tz-name></span>) and stored in UTC.</p></div></div>

<div class="grid grid-2">
  <section class="panel" id="event">
    <div class="panel-head"><h2>Event</h2></div>
    <form class="panel-body" method="post" action="/admin/settings">
      <?= csrf_field() ?><input type="hidden" name="section" value="event"><input type="hidden" name="tz_offset" value="0">
      <div class="field"><label class="label" for="event_name">Event name</label><input class="input" id="event_name" name="event_name" value="<?= e(setting('event_name')) ?>"></div>
      <div class="field"><label class="label" for="event_tagline">Tagline</label><input class="input" id="event_tagline" name="event_tagline" value="<?= e(setting('event_tagline')) ?>"></div>
      <div class="field"><label class="label" for="org_name">Organiser</label><input class="input" id="org_name" name="org_name" value="<?= e(setting('org_name')) ?>"></div>
      <div class="grid grid-2" style="margin-top:var(--s-4)">
        <div class="field"><label class="label" for="contact_email">Contact email</label><input class="input" type="email" id="contact_email" name="contact_email" value="<?= e(setting('contact_email')) ?>"></div>
        <div class="field"><label class="label" for="flag_format">Flag format</label><input class="input code" id="flag_format" name="flag_format" value="<?= e(setting('flag_format')) ?>"></div>
      </div>
      <div class="grid grid-3" style="margin-top:var(--s-4)">
        <div class="field"><label class="label" for="starts_at">Starts</label><input class="input" type="datetime-local" id="starts_at" name="starts_at" data-utc="<?= e(dt_iso(setting('starts_at'))) ?>"></div>
        <div class="field"><label class="label" for="ends_at">Ends</label><input class="input" type="datetime-local" id="ends_at" name="ends_at" data-utc="<?= e(dt_iso(setting('ends_at'))) ?>"></div>
        <div class="field"><label class="label" for="freeze_at">Freeze scoreboard</label><input class="input" type="datetime-local" id="freeze_at" name="freeze_at" data-utc="<?= e(dt_iso(setting('freeze_at'))) ?>"></div>
      </div>
      <p class="help" style="margin-top:var(--s-2)">Leave Starts empty to open immediately and Ends empty to run until you pause it. After the freeze, players see the scoreboard as of that moment; admins always see live scores.</p>
      <button class="btn btn-primary" type="submit" style="margin-top:var(--s-4)">Save event</button>
    </form>
  </section>

  <div class="stack">
    <section class="panel" id="state">
      <div class="panel-head"><h2>Access and registration</h2></div>
      <form class="panel-body" method="post" action="/admin/settings">
        <?= csrf_field() ?><input type="hidden" name="section" value="state">
        <div class="stack-sm">
          <label class="check"><input type="checkbox" name="registration_open" value="1" <?= $chk('registration_open') ?>> Registration open</label>
          <label class="check"><input type="checkbox" name="allow_solo" value="1" <?= $chk('allow_solo') ?>> Allow solo players</label>
          <label class="check"><input type="checkbox" name="paused" value="1" <?= $chk('paused') ?>> Pause submissions (event stays visible)</label>
          <label class="check"><input type="checkbox" name="maintenance" value="1" <?= $chk('maintenance') ?>> Maintenance mode (only admins can use the site)</label>
        </div>
        <div class="grid grid-2" style="margin-top:var(--s-4)">
          <div class="field"><label class="label" for="team_size_max">Max team size</label><input class="input num" type="number" min="1" max="20" id="team_size_max" name="team_size_max" value="<?= e(setting('team_size_max')) ?>"></div>
          <div class="field"><label class="label" for="scoreboard_visibility">Scoreboard</label>
            <select class="select" id="scoreboard_visibility" name="scoreboard_visibility">
              <?php foreach (['public' => 'Public', 'private' => 'Signed-in players only', 'hidden' => 'Hidden (admins only)'] as $k => $l): ?><option value="<?= $k ?>" <?= setting('scoreboard_visibility') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?>
            </select></div>
        </div>
        <div class="field"><label class="label" for="signup_domain_allowlist">Allowed email domains <span class="subtle">optional</span></label><input class="input" id="signup_domain_allowlist" name="signup_domain_allowlist" value="<?= e(setting('signup_domain_allowlist')) ?>" placeholder="nfsu.ac.in, gauhati.ac.in"><div class="help">Comma separated. Leave empty to allow any email.</div></div>
        <button class="btn btn-primary" type="submit" style="margin-top:var(--s-4)">Save access</button>
      </form>
    </section>

    <section class="panel" id="scoring">
      <div class="panel-head"><h2>First-blood bonus</h2></div>
      <form class="panel-body" method="post" action="/admin/settings">
        <?= csrf_field() ?><input type="hidden" name="section" value="scoring">
        <div class="grid grid-2">
          <div class="field"><label class="label" for="first_blood_mode">Bonus type</label>
            <select class="select" id="first_blood_mode" name="first_blood_mode">
              <?php foreach (['fixed' => 'Fixed points', 'percent' => 'Percent of challenge value', 'off' => 'Off'] as $k => $l): ?><option value="<?= $k ?>" <?= setting('first_blood_mode') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?>
            </select></div>
          <div class="field"><label class="label" for="first_blood_values">1st, 2nd, 3rd…</label><input class="input code" id="first_blood_values" name="first_blood_values" value="<?= e(setting('first_blood_values')) ?>"></div>
        </div>
        <p class="help" style="margin-top:var(--s-2)">Awarded automatically on solve and kept even if the challenge's dynamic value drops later. Override per challenge in its scoring panel.</p>
        <button class="btn btn-primary" type="submit" style="margin-top:var(--s-4)">Save bonus</button>
      </form>
    </section>
  </div>

  <section class="panel" id="brand">
    <div class="panel-head"><h2>Brand</h2></div>
    <form class="panel-body" method="post" action="/admin/settings" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="section" value="brand">
      <div class="row" style="margin-bottom:var(--s-4)"><img src="<?= e(setting('logo_url') ?: asset('img/logo.svg')) ?>" alt="Current logo" width="48" height="48" style="border-radius:8px"><span class="small muted">Current logo</span></div>
      <div class="field"><label class="label" for="logo_file">Logo <span class="subtle">square PNG/SVG, under 1 MB</span></label><input class="input" type="file" id="logo_file" name="logo_file" accept=".png,.svg,.webp,.jpg,.jpeg" style="height:auto;padding:6px"></div>
      <div class="field"><label class="label" for="logo_url">Or logo URL</label><input class="input" id="logo_url" name="logo_url" value="<?= e(setting('logo_url')) ?>" placeholder="https://… or /assets/img/…"></div>
      <div class="field"><label class="label" for="brand_accent">Accent colour <span class="subtle">links, focus and highlights</span></label>
        <div class="row"><input type="color" id="brand_accent_picker" value="<?= e(setting('brand_accent') ?: '#5B5BD6') ?>" data-sync-to="brand_accent" aria-label="Pick colour" style="width:44px;height:40px;border:0;background:none"><input class="input code" id="brand_accent" name="brand_accent" value="<?= e(setting('brand_accent')) ?>" placeholder="#5B5BD6"></div></div>
      <button class="btn btn-primary" type="submit" style="margin-top:var(--s-4)">Save brand</button>
    </form>
  </section>

  <section class="panel" id="mail">
    <div class="panel-head"><h2>Email and sign-in providers</h2></div>
    <div class="panel-body">
      <dl class="kv">
        <dt>Mail driver</dt><dd><?= e($mail['driver']) ?><?= $mail['driver'] === 'log' ? ' <span class="badge badge-warning">emails are only logged</span>' : '' ?></dd>
        <dt>SMTP host</dt><dd class="mono small"><?= e($mail['host']) ?></dd>
        <dt>From</dt><dd class="mono small"><?= e($mail['from']) ?></dd>
        <dt>Hourly cap</dt><dd><?= (int) $mail['cap'] ?> emails</dd>
        <dt>Google sign-in</dt><dd><?= $oauth['google'] ? '<span class="badge badge-success">On</span>' : '<span class="badge">Off: set GOOGLE_CLIENT_ID</span>' ?></dd>
        <dt>GitHub sign-in</dt><dd><?= $oauth['github'] ? '<span class="badge badge-success">On</span>' : '<span class="badge">Off: set GITHUB_CLIENT_ID</span>' ?></dd>
      </dl>
      <p class="help" style="margin:var(--s-3) 0">Credentials live in <code>app/config/.env</code> on the server and never in the database.</p>
      <form method="post" action="/admin/settings/test-mail"><?= csrf_field() ?><button class="btn btn-sm" type="submit"><?= icon('mail', 'sm') ?>Send me a test email</button></form>
    </div>
  </section>
</div>
