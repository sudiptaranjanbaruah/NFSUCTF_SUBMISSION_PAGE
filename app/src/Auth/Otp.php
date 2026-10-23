<?php
namespace App\Auth;

use App\Core\DB;
use App\Mail\Mailer;

/**
 * Six-digit email codes for signup verification, passwordless login,
 * password reset and admin two-step sign-in.
 */
final class Otp
{
    public const TTL_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_SECONDS = 60;
    public const MAX_PER_HOUR = 6;

    private const SUBJECTS = [
        'verify' => 'Verify your email',
        'login' => 'Your sign-in code',
        'reset' => 'Reset your password',
        'admin_2fa' => 'Admin sign-in code',
    ];

    public static function cooldown(string $email, string $purpose): int
    {
        $last = DB::val(
            'SELECT UNIX_TIMESTAMP(created_at) FROM email_otps WHERE email = ? AND purpose = ? ORDER BY id DESC LIMIT 1',
            [$email, $purpose]
        );
        return $last ? max(0, self::RESEND_SECONDS - (time() - (int) $last)) : 0;
    }

    /**
     * Issue and email a new code. Returns null on success or an error message.
     * When $deliver is false (unknown email) we go through the same motions
     * without sending, so responses can't be used to discover accounts.
     */
    public static function issue(string $email, string $purpose, bool $deliver = true): ?string
    {
        if (($wait = self::cooldown($email, $purpose)) > 0) {
            return "Please wait {$wait}s before requesting another code.";
        }
        $recent = (int) DB::val(
            'SELECT COUNT(*) FROM email_otps WHERE email = ? AND created_at > UTC_TIMESTAMP() - INTERVAL 1 HOUR',
            [$email]
        );
        if ($recent >= self::MAX_PER_HOUR) {
            return 'Too many codes requested for this email. Try again in an hour.';
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::query('UPDATE email_otps SET consumed_at = UTC_TIMESTAMP() WHERE email = ? AND purpose = ? AND consumed_at IS NULL', [$email, $purpose]);
        DB::insert('email_otps', [
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => self::hash($email, $purpose, $code),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60),
        ]);

        if ($deliver) {
            $ok = Mailer::sendNow($email, $code . ' is your ' . strtolower(self::SUBJECTS[$purpose]) . ' code', 'otp', [
                'code' => $code,
                'purpose' => $purpose,
                'minutes' => self::TTL_MINUTES,
            ]);
            if (!$ok) {
                return 'We could not send the email right now. Please try again in a minute.';
            }
        }
        return null;
    }

    /** @return string 'ok' | 'invalid' | 'expired' | 'locked' */
    public static function check(string $email, string $purpose, string $code): string
    {
        $row = DB::one(
            'SELECT * FROM email_otps WHERE email = ? AND purpose = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1',
            [$email, $purpose]
        );
        if (!$row) {
            return 'expired';
        }
        if (strtotime($row['expires_at'] . ' UTC') < time()) {
            return 'expired';
        }
        if ($row['attempts'] >= self::MAX_ATTEMPTS) {
            return 'locked';
        }
        if (!preg_match('/^\d{6}$/', $code) || !hash_equals($row['code_hash'], self::hash($email, $purpose, $code))) {
            DB::query('UPDATE email_otps SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
            return $row['attempts'] + 1 >= self::MAX_ATTEMPTS ? 'locked' : 'invalid';
        }
        DB::query('UPDATE email_otps SET consumed_at = UTC_TIMESTAMP() WHERE id = ?', [$row['id']]);
        return 'ok';
    }

    public static function message(string $result): string
    {
        return match ($result) {
            'invalid' => 'That code is not right. Check the latest email and try again.',
            'expired' => 'That code has expired. Request a new one.',
            'locked' => 'Too many wrong attempts. Request a new code.',
            default => '',
        };
    }

    private static function hash(string $email, string $purpose, string $code): string
    {
        return hash_hmac('sha256', strtolower($email) . '|' . $purpose . '|' . $code, (string) env('APP_KEY'));
    }
}
