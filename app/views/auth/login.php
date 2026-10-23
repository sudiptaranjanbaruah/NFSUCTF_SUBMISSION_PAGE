<h2>Sign in</h2>
<p class="lead">Welcome back. Pick up where your team left off.</p>
<?= partial('oauth') ?>
<div class="segmented" role="tablist" style="width:100%;margin-bottom:var(--s-5)">
  <a href="/login" role="tab" class="<?= $mode === 'password' ? 'is-active' : '' ?>" style="flex:1;justify-content:center" aria-selected="<?= $mode === 'password' ? 'true' : 'false' ?>">Password</a>
  <a href="/login?mode=code" role="tab" class="<?= $mode === 'code' ? 'is-active' : '' ?>" style="flex:1;justify-content:center" aria-selected="<?= $mode === 'code' ? 'true' : 'false' ?>">Email code</a>
</div>
<?php if ($mode === 'password'): ?>
<form method="post" action="/login">
  <?= csrf_field() ?>
  <div class="field">
    <label class="label" for="email">Email or username</label>
    <input class="input" id="email" name="email" value="<?= old('email') ?>" autocomplete="username" required autofocus>
  </div>
  <div class="field">
    <label class="label" for="password">Password</label>
    <?= partial('password_input', ['name' => 'password', 'id' => 'password', 'autocomplete' => 'current-password']) ?>
    <a href="/forgot" class="small" style="align-self:flex-end">Forgot password?</a>
  </div>
  <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:var(--s-6)">Sign in</button>
</form>
<?php else: ?>
<form method="post" action="/login/code">
  <?= csrf_field() ?>
  <div class="field">
    <label class="label" for="email">Email</label>
    <input class="input" type="email" id="email" name="email" value="<?= old('email') ?>" autocomplete="email" required autofocus>
    <div class="help">We'll email you a 6-digit code. No password needed.</div>
  </div>
  <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:var(--s-6)">Email me a code</button>
</form>
<?php endif ?>
<p class="foot">New here? <a href="/register">Create an account</a></p>
