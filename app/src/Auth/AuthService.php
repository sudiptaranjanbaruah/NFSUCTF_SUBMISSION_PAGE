<?php
namespace App\Auth;

use App\Core\DB;

final class AuthService
{
    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function validateUsername(string $u, ?int $ignoreId = null): ?string
    {
        if (!preg_match('/^[A-Za-z0-9_.-]{3,24}$/', $u)) {
            return 'Usernames are 3–24 characters: letters, numbers, _ . -';
        }
        $id = DB::val('SELECT id FROM users WHERE username = ?', [$u]);
        if ($id && (int) $id !== $ignoreId) {
            return 'That username is taken.';
        }
        return null;
    }

    public static function validatePassword(string $p): ?string
    {
        if (strlen($p) < 8) {
            return 'Use at least 8 characters for your password.';
        }
        if (strlen($p) > 200) {
            return 'That password is too long.';
        }
        return null;
    }

    public static function emailAllowed(string $email): bool
    {
        $list = array_filter(array_map('trim', explode(',', strtolower((string) setting('signup_domain_allowlist')))));
        if (!$list) {
            return true;
        }
        $domain = substr(strrchr($email, '@') ?: '', 1);
        return in_array($domain, $list, true);
    }

    public static function hash(string $password): string
    {
        return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
    }

    /**
     * Finish a successful first factor. Admins always confirm an emailed code
     * unless the first factor already was an email code.
     */
    public static function complete(array $user, string $method): never
    {
        if (in_array($user['role'], ['admin', 'super_admin'], true) && !in_array($method, ['otp', 'reset', 'admin_2fa'], true)) {
            $_SESSION['pending_2fa'] = (int) $user['id'];
            $_SESSION['pending_method'] = $method;
            $_SESSION['otp_email'] = $user['email'];
            $_SESSION['otp_purpose'] = 'admin_2fa';
            $err = Otp::issue($user['email'], 'admin_2fa');
            if ($err && !str_starts_with($err, 'Please wait')) {
                flash('error', $err);
            }
            redirect('/verify');
        }

        login_user($user, $method);
        $to = $_SESSION['intended'] ?? null;
        unset($_SESSION['intended']);
        if (!$user['team_id'] && !in_array($user['role'], ['admin', 'super_admin'], true)) {
            redirect('/team');
        }
        redirect($to && str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : '/dashboard');
    }
}
