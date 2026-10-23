<p style="margin:0 0 16px;">Hi<?= !empty($name) ? ' ' . e($name) : '' ?>,</p>
<p style="margin:0 0 16px;">You've been registered for <strong><?= e($event_name) ?></strong><?= !empty($team) ? ' on team <strong>' . e($team) . '</strong>' : '' ?>. Set a password to activate your account.</p>
<p style="margin:0 0 24px;">
  <a href="<?= e($link) ?>" style="display:inline-block;background:#18181B;color:#FAFAFA;font-weight:500;text-decoration:none;padding:10px 18px;border-radius:8px;">Activate account</a>
</p>
<p style="margin:0;color:#71717A;font-size:13px;">This link works once and expires in <?= (int) $days ?> days. If the button doesn't work, paste this address into your browser:<br><span style="word-break:break-all;"><?= e($link) ?></span></p>
