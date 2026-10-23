<h2>Choose a new password</h2>
<p class="lead">Enter the code we sent to <strong class="mono small"><?= e($email) ?></strong> and your new password.</p>
<form method="post" action="/reset">
  <?= csrf_field() ?>
  <div class="field">
    <label class="label" for="code">Reset code</label>
    <input class="input code" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required autofocus>
  </div>
  <div class="field">
    <label class="label" for="password">New password</label>
    <?= partial('password_input', ['name' => 'password', 'id' => 'password', 'autocomplete' => 'new-password', 'meter' => true]) ?>
  </div>
  <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:var(--s-6)">Update password</button>
</form>
<div class="row-between" style="margin-top:var(--s-5)">
  <a class="small" href="/forgot">Request a new code</a>
  <a class="small" href="/login">Back to sign in</a>
</div>
