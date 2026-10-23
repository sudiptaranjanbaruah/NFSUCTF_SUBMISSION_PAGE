<div class="page-head">
  <div>
    <h1>Set up your team</h1>
    <p>Teams of up to <?= (int) setting('team_size_max') ?> share one score. You can switch teams until you score.</p>
  </div>
</div>
<div class="choice-grid">
  <section class="panel">
    <div class="panel-head"><h2><?= icon('plus', 'sm') ?> Create a team</h2></div>
    <form class="panel-body" method="post" action="/team/create">
      <?= csrf_field() ?>
      <div class="field">
        <label class="label" for="name">Team name</label>
        <input class="input" id="name" name="name" value="<?= old('name') ?>" maxlength="40" required>
      </div>
      <div class="field">
        <label class="label" for="affiliation">College or organisation <span class="subtle">optional</span></label>
        <input class="input" id="affiliation" name="affiliation" value="<?= old('affiliation', (string) current_user()['affiliation']) ?>" maxlength="120">
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:var(--s-5)">Create team</button>
      <p class="help" style="margin-top:var(--s-3)">You become captain and get an invite code to share.</p>
    </form>
  </section>
  <section class="panel">
    <div class="panel-head"><h2><?= icon('door', 'sm') ?> Join a team</h2></div>
    <form class="panel-body" method="post" action="/team/join">
      <?= csrf_field() ?>
      <div class="field">
        <label class="label" for="code">Invite code</label>
        <input class="input code" id="code" name="code" placeholder="e.g. K7Q2M9XA" maxlength="16" required autocapitalize="characters" style="letter-spacing:.12em">
        <div class="help">Ask your captain for the code on their team page.</div>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:var(--s-5)">Join team</button>
    </form>
    <?php if (setting('allow_solo') === '1'): ?>
    <div class="panel-foot row-between">
      <span class="small muted">Prefer to play alone?</span>
      <form method="post" action="/team/create" class="inline"><?= csrf_field() ?><input type="hidden" name="solo" value="1"><button class="btn btn-sm" type="submit"><?= icon('user', 'sm') ?>Play solo</button></form>
    </div>
    <?php endif ?>
  </section>
</div>
