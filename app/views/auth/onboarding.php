<h2>One last step</h2>
<p class="lead">Signed in with <?= e(['google' => 'Google', 'github' => 'GitHub'][$p['provider']] ?? ucfirst($p['provider'])) ?> as <strong class="mono small"><?= e($p['email']) ?></strong>. Pick the username shown on the scoreboard.</p>
<form method="post" action="/onboarding">
  <?= csrf_field() ?>
  <div class="field">
    <label class="label" for="username">Username</label>
    <input class="input" id="username" name="username" value="<?= old('username', $suggest) ?>" required pattern="[A-Za-z0-9_.\-]{3,24}" autofocus>
    <div class="help">3–24 characters: letters, numbers, _ . -</div>
  </div>
  <div class="field">
    <label class="label" for="affiliation">College <span class="subtle">optional</span></label>
    <input class="input" id="affiliation" name="affiliation" value="<?= old('affiliation') ?>" autocomplete="organization">
  </div>
  <div class="field">
    <label class="check"><input type="checkbox" name="agree" value="1"> I agree to the <a href="/rules" target="_blank" rel="noopener">competition rules</a></label>
  </div>
  <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:var(--s-6)">Create account</button>
</form>
<p class="foot"><a href="/register">Use a different method</a></p>
