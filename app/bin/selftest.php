<?php
// Quick self-checks for scoring maths, flag matching and OTP handling:
//   php app/bin/selftest.php
require __DIR__ . '/../bootstrap.php';

use App\Auth\Otp;
use App\Core\DB;
use App\Services\Scoring;

$fail = 0;
function check(string $name, bool $ok): void
{
    global $fail;
    echo ($ok ? "  ok   " : "  FAIL ") . $name . "\n";
    $fail += $ok ? 0 : 1;
}

echo "Dynamic scoring\n";
$dyn = ['scoring' => 'dynamic', 'points' => 500, 'min_points' => 100, 'decay' => 10];
check('first solver gets the full value', Scoring::value($dyn, 1) === 500);
check('value decreases with solves', Scoring::value($dyn, 5) < Scoring::value($dyn, 2));
check('value reaches the minimum after decay solves', Scoring::value($dyn, 11) === 100);
check('value never drops below the minimum', Scoring::value($dyn, 500) === 100);
check('static challenges keep their value', Scoring::value(['scoring' => 'static', 'points' => 250, 'min_points' => 0, 'decay' => 1], 99) === 250);

echo "First-blood bonus\n";
set_setting('first_blood_mode', 'fixed');
set_setting('first_blood_values', '50,30,10');
check('1st solver gets 50', Scoring::bonus([], 1, 300) === 50);
check('3rd solver gets 10', Scoring::bonus([], 3, 300) === 10);
check('4th solver gets nothing', Scoring::bonus([], 4, 300) === 0);
check('per-challenge override wins', Scoring::bonus(['first_blood_bonus' => '100,0'], 1, 300) === 100);
check('per-challenge off', Scoring::bonus(['first_blood_bonus' => 'off'], 1, 300) === 0);
set_setting('first_blood_mode', 'percent');
check('percent mode uses the challenge value', Scoring::bonus([], 1, 400) === 200);
set_setting('first_blood_mode', 'fixed');

echo "Flag matching\n";
$static = ['type' => 'static', 'content' => 'NFSUCTF{Abc}', 'case_insensitive' => 0];
check('exact match', Scoring::flagMatches($static, 'NFSUCTF{Abc}'));
check('case-sensitive by default', !Scoring::flagMatches($static, 'nfsuctf{abc}'));
check('case-insensitive option', Scoring::flagMatches(['case_insensitive' => 1] + $static, 'nfsuctf{ABC}'));
$regex = ['type' => 'regex', 'content' => 'NFSUCTF\{[0-9]{4}\}', 'case_insensitive' => 0];
check('regex matches', Scoring::flagMatches($regex, 'NFSUCTF{1234}'));
check('regex is anchored', !Scoring::flagMatches($regex, 'xNFSUCTF{1234}y'));
check('broken regex never matches', !Scoring::flagMatches(['type' => 'regex', 'content' => '(', 'case_insensitive' => 0], '('));

echo "Email codes\n";
$email = 'selftest-' . bin2hex(random_bytes(3)) . '@example.test';
$prev = $_ENV['MAIL_DRIVER'] ?? null;
$_ENV['MAIL_DRIVER'] = 'log';
check('issue a code', Otp::issue($email, 'login') === null);
check('resend is rate limited', Otp::issue($email, 'login') !== null);
check('wrong code rejected', Otp::check($email, 'login', '000000') !== 'ok');
for ($i = 0; $i < 5; $i++) {
    Otp::check($email, 'login', '111111');
}
check('locks after too many attempts', Otp::check($email, 'login', '222222') === 'locked');
DB::query('DELETE FROM email_otps WHERE email = ?', [$email]);
DB::query("DELETE FROM mail_queue WHERE to_email = ?", [$email]);
$_ENV['MAIL_DRIVER'] = $prev;

echo $fail ? "\n$fail check(s) failed.\n" : "\nAll checks passed.\n";
exit($fail ? 1 : 0);
