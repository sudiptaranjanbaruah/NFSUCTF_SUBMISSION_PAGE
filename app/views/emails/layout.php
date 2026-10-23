<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($event_name) ?></title></head>
<body style="margin:0;padding:0;background:#FAFAFA;font-family:Geist,-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;color:#09090B;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FAFAFA;padding:40px 12px;">
  <tr><td align="center">
    <table role="presentation" width="520" cellpadding="0" cellspacing="0" style="max-width:520px;width:100%;">
      <tr><td style="padding:0 4px 20px;">
        <span style="display:inline-block;width:22px;height:22px;background:#5B5BD6;border-radius:6px;vertical-align:middle;"></span>
        <span style="font-size:15px;font-weight:600;letter-spacing:-0.01em;vertical-align:middle;margin-left:8px;"><?= e($event_name) ?></span>
      </td></tr>
      <tr><td style="background:#FFFFFF;border:1px solid #E4E4E7;border-radius:12px;padding:32px;font-size:14px;line-height:1.6;color:#3F3F46;">
        <?= $body ?>
      </td></tr>
      <tr><td style="padding:20px 4px;font-size:12px;line-height:1.5;color:#71717A;">
        <?= e($org_name) ?><br>
        You are receiving this because of an account on <a href="<?= e($app_url) ?>" style="color:#71717A;"><?= e(parse_url($app_url, PHP_URL_HOST) ?: $app_url) ?></a>.
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
