<h2>Activate your account</h2>
<p class="lead">You were registered as <strong><?= e($invite['email']) ?></strong><?= $invite['team_name'] ? ' on team <strong>' . e($invite['team_name']) . '</strong>' : '' ?>.</p>
<form method="post" action="/invite/<?= e($token) ?>">
  <?= csrf_field() ?>
  <?php if ($existing): ?>
    <div class="alert alert-info"><?= icon('info', 'sm') ?><div>You already have an account with this email. Continue to sign in<?= $invite['team_name'] ? ' and join your team' : '' ?>.</div></div>
    <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:var(--s-6)">Continue</button>
  <?php else: ?>
    <div class="field">
      <label class="label" for="username">Username <span class="subtle">shown on the scoreboard</span></label>
      <input class="input" id="username" name="username" value="<?= old('username') ?>" required pattern="[A-Za-z0-9_.\-]{3,24}" autofocus>
    </div>
    <div class="field">
      <label class="label" for="password">Password</label>
      <?= partial('password_input', ['name' => 'password', 'id' => 'password', 'autocomplete' => 'new-password', 'meter' => true]) ?>
    </div>
    <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:var(--s-6)">Activate account</button>
  <?php endif ?>
</form>
