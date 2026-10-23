<?php
// Run every minute from Hostinger cron:  php /home/<user>/domains/<site>/app/bin/cron.php
require __DIR__ . '/../bootstrap.php';

use App\Core\DB;
use App\Mail\Mailer;

$lock = fopen(APP_ROOT . '/storage/cache/cron.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0); // previous run still going
}

$sent = Mailer::drain(40);

DB::query('DELETE FROM email_otps WHERE created_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
DB::query('DELETE FROM rate_limits WHERE reset_at < ?', [time() - 3600]);
DB::query('DELETE FROM mail_queue WHERE sent_at < UTC_TIMESTAMP() - INTERVAL 30 DAY');

if ($sent) {
    echo gmdate('c') . " sent $sent mail(s)\n";
}
