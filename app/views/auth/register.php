<?php $err = fn($k) => isset($errors[$k]) ? '<div class="field-error">' . e($errors[$k]) . '</div>' : ''; ?>
<h2>Create your account</h2>
<p class="lead">Sign up, verify your email, then create or join a team.</p>
<?php if (setting('registration_open') !== '1'): ?>
  <div class="alert alert-warning"><?= icon('lock', 'sm') ?><div>Registration is closed. If you were invited, use the link in your email.</div></div>
<?php else: ?>
<?= partial('oauth') ?>
<form method="post" action="/register" novalidate>
  <?= csrf_field() ?>
  <div class="field">
    <label class="label" for="email">Email</label>
    <input class="input" type="email" id="email" name="email" value="<?= old('email') ?>" autocomplete="email" required <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>>
    <?= $err('email') ?>
  </div>
  <div class="field">
    <label class="label" for="username">Username <span class="subtle">shown on the scoreboard</span></label>
    <input class="input" id="username" name="username" value="<?= old('username') ?>" autocomplete="username" required pattern="[A-Za-z0-9_.\-]{3,24}" <?= isset($errors['username']) ? 'aria-invalid="true"' : '' ?>>
    <?= $err('username') ?>
  </div>
  <div class="grid grid-2" style="margin-top:var(--s-4)">
    <div class="field">
      <label class="label" for="full_name">Full name <span class="subtle">optional</span></label>
      <input class="input" id="full_name" name="full_name" value="<?= old('full_name') ?>" autocomplete="name">
    </div>
    <div class="field">
      <label class="label" for="affiliation">College <span class="subtle">optional</span></label>
      <input class="input" id="affiliation" name="affiliation" value="<?= old('affiliation') ?>" autocomplete="organization">
    </div>
  </div>
  <div class="field">
    <label class="label" for="password">Password</label>
    <?= partial('password_input', ['name' => 'password', 'id' => 'password', 'autocomplete' => 'new-password', 'meter' => true]) ?>
    <?= $err('password') ?>
  </div>
  <div class="field">
    <label class="check"><input type="checkbox" name="agree" value="1" <?= old('agree') ? 'checked' : '' ?>> I agree to the <a href="/rules" target="_blank">competition rules</a></label>
    <?= $err('agree') ?>
  </div>
  <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:var(--s-6)">Create account</button>
</form>
<?php endif ?>
<p class="foot">Already registered? <a href="/login">Sign in</a></p>
