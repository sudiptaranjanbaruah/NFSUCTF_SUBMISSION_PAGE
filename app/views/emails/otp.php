<?php
$intro = match ($purpose) {
    'verify' => 'Use this code to verify your email and finish creating your account.',
    'login' => 'Use this code to sign in.',
    'reset' => 'Use this code to set a new password.',
    'admin_2fa' => 'Use this code to finish signing in to the admin console.',
    default => 'Here is your code.',
};
?>
<p style="margin:0 0 20px;"><?= e($intro) ?></p>
<p style="margin:0 0 20px;">
  <span style="display:inline-block;font-family:'Geist Mono',Menlo,Consolas,monospace;font-size:28px;font-weight:600;letter-spacing:8px;color:#09090B;background:#F4F4F5;border:1px solid #E4E4E7;border-radius:8px;padding:12px 20px;"><?= e($code) ?></span>
</p>
<p style="margin:0 0 8px;">The code expires in <?= (int) $minutes ?> minutes.</p>
<p style="margin:0;color:#71717A;font-size:13px;">If you didn't request this, you can ignore this email. Nobody can use the code without access to your inbox.</p>
