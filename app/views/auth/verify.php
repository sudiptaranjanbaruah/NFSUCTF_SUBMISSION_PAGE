<?php
[$heading, $lead] = match ($purpose) {
    'verify' => ['Check your email', 'Enter the 6-digit code we sent to finish creating your account.'],
    'admin_2fa' => ['Confirm it\'s you', 'Admin sign-in needs a second step. Enter the code we just emailed you.'],
    default => ['Enter your sign-in code', 'If an account exists for this email, we sent it a 6-digit code.'],
};
?>
<h2><?= e($heading) ?></h2>
<p class="lead"><?= e($lead) ?><br><strong class="mono small"><?= e($email) ?></strong></p>
<form method="post" action="/verify" data-no-busy>
  <?= csrf_field() ?>
  <input type="hidden" name="code" id="otp-code">
  <div class="otp" data-otp="otp-code" role="group" aria-label="6-digit code">
    <?php for ($i = 0; $i < 6; $i++): ?>
      <input inputmode="numeric" autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>" maxlength="<?= $i === 0 ? 6 : 1 ?>" aria-label="Digit <?= $i + 1 ?>">
    <?php endfor ?>
  </div>
  <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:var(--s-6)">Verify</button>
</form>
<div class="row-between" style="margin-top:var(--s-5)">
  <form method="post" action="/verify/resend" class="inline">
    <?= csrf_field() ?>
    <button class="btn btn-ghost btn-sm" type="submit" data-cooldown="<?= (int) $cooldown ?>">Resend code</button>
  </form>
  <a class="small" href="/login">Use a different email</a>
</div>
<p class="help" style="margin-top:var(--s-6)">Codes expire after <?= \App\Auth\Otp::TTL_MINUTES ?> minutes. Check your spam folder if it doesn't arrive within a minute.</p>
