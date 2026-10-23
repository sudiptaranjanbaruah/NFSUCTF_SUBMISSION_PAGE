<h2>Reset your password</h2>
<p class="lead">Enter your account email and we'll send you a reset code.</p>
<form method="post" action="/forgot">
  <?= csrf_field() ?>
  <div class="field">
    <label class="label" for="email">Email</label>
    <input class="input" type="email" id="email" name="email" autocomplete="email" required autofocus>
  </div>
  <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:var(--s-6)">Send reset code</button>
</form>
<p class="foot"><a href="/login">Back to sign in</a></p>
