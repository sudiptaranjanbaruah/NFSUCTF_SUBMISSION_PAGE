<?php
namespace App\Controllers;

use App\Auth\AuthService;
use App\Auth\Otp;
use App\Core\DB;
use App\Core\RateLimit;
use App\Core\Request;
use App\Services\Teams;

final class AuthController
{
    // ---------------- Register ----------------

    public function showRegister(Request $r): void
    {
        view('auth/register', ['title' => 'Create account', 'errors' => $_SESSION['_errors'] ?? []], 'auth');
        unset($_SESSION['_errors']);
    }

    public function register(Request $r): void
    {
        if (setting('registration_open') !== '1') {
            flash('error', 'Registration is closed.');
            redirect('/register');
        }
        if (!RateLimit::hit('register:' . $r->ip(), 10, 3600)) {
            flash('error', 'Too many sign-ups from your network. Try again later.');
            redirect('/register');
        }

        $email = AuthService::normalizeEmail((string) $r->input('email'));
        $username = (string) $r->input('username');
        $password = (string) $r->raw('password');
        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (!AuthService::emailAllowed($email)) {
            $errors['email'] = 'Registration is limited to approved email domains.';
        }
        $existing = $email ? DB::one('SELECT * FROM users WHERE email = ?', [$email]) : null;
        if ($existing && $existing['email_verified_at']) {
            $errors['email'] = 'An account with this email already exists. Sign in instead.';
        }
        if ($e = AuthService::validateUsername($username, $existing ? (int) $existing['id'] : null)) {
            $errors['username'] = $e;
        }
        if ($e = AuthService::validatePassword($password)) {
            $errors['password'] = $e;
        }
        if (!$r->input('agree')) {
            $errors['agree'] = 'Please accept the rules to continue.';
        }

        if ($errors) {
            with_old($r->all());
            $_SESSION['_errors'] = $errors;
            redirect('/register');
        }

        $data = [
            'username' => $username,
            'email' => $email,
            'full_name' => mb_substr((string) $r->input('full_name'), 0, 120) ?: null,
            'affiliation' => mb_substr((string) $r->input('affiliation'), 0, 120) ?: null,
            'password_hash' => AuthService::hash($password),
        ];
        if ($existing) {
            // Unverified account with this email: the inbox owner can restart sign-up.
            DB::update('users', $data, 'id = ?', [$existing['id']]);
        } else {
            DB::insert('users', $data);
        }

        $_SESSION['otp_email'] = $email;
        $_SESSION['otp_purpose'] = 'verify';
        if ($err = Otp::issue($email, 'verify')) {
            flash('warning', $err);
        }
        redirect('/verify');
    }

    // ---------------- Login ----------------

    public function showLogin(Request $r): void
    {
        view('auth/login', [
            'title' => 'Sign in',
            'mode' => $r->query('mode') === 'code' ? 'code' : 'password',
        ], 'auth');
    }

    public function login(Request $r): void
    {
        $email = AuthService::normalizeEmail((string) $r->input('email'));
        $password = (string) $r->raw('password');

        if (!RateLimit::hit('login-ip:' . $r->ip(), 30, 900) || !RateLimit::hit('login:' . $email, 8, 900)) {
            flash('error', 'Too many sign-in attempts. Wait 15 minutes or sign in with an email code.');
            with_old(['email' => $email]);
            redirect('/login');
        }

        $user = DB::one('SELECT * FROM users WHERE email = ? OR username = ?', [$email, $r->input('email')]);
        $ok = $user && $user['password_hash'] && password_verify($password, $user['password_hash']);

        if (!$ok) {
            log_login($email, $user['id'] ?? null, 'password', false);
            if ($user && !$user['password_hash']) {
                flash('error', 'This account has no password yet. Sign in with an email code or your linked Google/GitHub account.');
            } else {
                flash('error', 'Email or password is incorrect.');
            }
            with_old(['email' => $r->input('email')]);
            redirect('/login');
        }
        if ($user['is_banned']) {
            flash('error', 'This account has been suspended. Contact the organisers.');
            redirect('/login');
        }
        if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
            DB::query('UPDATE users SET password_hash = ? WHERE id = ?', [AuthService::hash($password), $user['id']]);
        }
        RateLimit::clear('login:' . $email);

        if (!$user['email_verified_at']) {
            $_SESSION['otp_email'] = $user['email'];
            $_SESSION['otp_purpose'] = 'verify';
            if ($err = Otp::issue($user['email'], 'verify')) {
                flash('warning', $err);
            } else {
                flash('info', 'Verify your email to finish signing in. We sent you a code.');
            }
            redirect('/verify');
        }

        AuthService::complete($user, 'password');
    }

    public function sendLoginCode(Request $r): void
    {
        $email = AuthService::normalizeEmail((string) $r->input('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid email address.');
            redirect('/login?mode=code');
        }
        if (!RateLimit::hit('otp-ip:' . $r->ip(), 15, 900)) {
            flash('error', 'Too many code requests from your network. Try again in 15 minutes.');
            redirect('/login?mode=code');
        }
        $user = DB::one('SELECT id, is_banned FROM users WHERE email = ?', [$email]);
        $err = Otp::issue($email, 'login', $user && !$user['is_banned']);
        if ($err) {
            flash('error', $err);
            if (str_starts_with($err, 'Please wait')) {
                $_SESSION['otp_email'] = $email;
                $_SESSION['otp_purpose'] = 'login';
                redirect('/verify');
            }
            redirect('/login?mode=code');
        }
        $_SESSION['otp_email'] = $email;
        $_SESSION['otp_purpose'] = 'login';
        redirect('/verify');
    }

    // ---------------- Code verification (signup, login, admin) ----------------

    public function showVerify(Request $r): void
    {
        $email = $_SESSION['otp_email'] ?? null;
        $purpose = $_SESSION['otp_purpose'] ?? null;
        $u = current_user();
        if (!$email && $u && !$u['email_verified_at']) {
            $email = $_SESSION['otp_email'] = $u['email'];
            $purpose = $_SESSION['otp_purpose'] = 'verify';
            Otp::issue($email, 'verify');
        }
        if (!$email || !$purpose) {
            redirect('/login');
        }
        view('auth/verify', [
            'title' => 'Enter your code',
            'email' => $email,
            'purpose' => $purpose,
            'cooldown' => Otp::cooldown($email, $purpose),
        ], 'auth');
    }

    public function verify(Request $r): void
    {
        $email = $_SESSION['otp_email'] ?? null;
        $purpose = $_SESSION['otp_purpose'] ?? null;
        if (!$email || !$purpose) {
            redirect('/login');
        }
        if (!RateLimit::hit('verify-ip:' . $r->ip(), 40, 900)) {
            flash('error', 'Too many attempts. Wait a few minutes and try again.');
            redirect('/verify');
        }

        $result = Otp::check($email, $purpose, preg_replace('/\D/', '', (string) $r->input('code')));
        if ($result !== 'ok') {
            log_login($email, null, $purpose, false);
            flash('error', Otp::message($result));
            redirect('/verify');
        }

        $user = DB::one('SELECT * FROM users WHERE email = ?', [$email]);
        if (!$user || $user['is_banned']) {
            flash('error', 'We could not sign you in with that email.');
            redirect('/login');
        }

        if ($purpose === 'admin_2fa') {
            if ((int) ($_SESSION['pending_2fa'] ?? 0) !== (int) $user['id']) {
                redirect('/login');
            }
            AuthService::complete($user, 'admin_2fa');
        }

        // Owning the inbox verifies the email for every purpose.
        if (!$user['email_verified_at']) {
            DB::query('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?', [$user['id']]);
            $user['email_verified_at'] = utc_now();
            if ($purpose === 'verify') {
                flash('success', 'Email verified. Welcome aboard!');
                audit('user.verified', 'user:' . $user['id']);
            }
        }
        AuthService::complete($user, $purpose === 'verify' ? 'signup' : 'otp');
    }

    public function resend(Request $r): void
    {
        $email = $_SESSION['otp_email'] ?? null;
        $purpose = $_SESSION['otp_purpose'] ?? null;
        if (!$email || !$purpose) {
            redirect('/login');
        }
        $exists = (bool) DB::val('SELECT id FROM users WHERE email = ? AND is_banned = 0', [$email]);
        $err = Otp::issue($email, $purpose, $exists);
        flash($err ? 'error' : 'success', $err ?: 'A new code is on its way.');
        redirect('/verify');
    }

    // ---------------- Password reset ----------------

    public function showForgot(Request $r): void
    {
        view('auth/forgot', ['title' => 'Reset password'], 'auth');
    }

    public function forgot(Request $r): void
    {
        $email = AuthService::normalizeEmail((string) $r->input('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid email address.');
            redirect('/forgot');
        }
        if (!RateLimit::hit('reset-ip:' . $r->ip(), 10, 900)) {
            flash('error', 'Too many requests. Try again in 15 minutes.');
            redirect('/forgot');
        }
        $exists = (bool) DB::val('SELECT id FROM users WHERE email = ? AND is_banned = 0', [$email]);
        $err = Otp::issue($email, 'reset', $exists);
        if ($err && !str_starts_with($err, 'Please wait')) {
            flash('error', $err);
            redirect('/forgot');
        }
        $_SESSION['reset_email'] = $email;
        flash('info', 'If an account exists for that email, a reset code is on its way.');
        redirect('/reset');
    }

    public function showReset(Request $r): void
    {
        $email = $_SESSION['reset_email'] ?? null;
        if (!$email) {
            redirect('/forgot');
        }
        view('auth/reset', ['title' => 'Choose a new password', 'email' => $email, 'cooldown' => Otp::cooldown($email, 'reset')], 'auth');
    }

    public function reset(Request $r): void
    {
        $email = $_SESSION['reset_email'] ?? null;
        if (!$email) {
            redirect('/forgot');
        }
        $password = (string) $r->raw('password');
        if ($e = AuthService::validatePassword($password)) {
            flash('error', $e);
            redirect('/reset');
        }
        $result = Otp::check($email, 'reset', preg_replace('/\D/', '', (string) $r->input('code')));
        if ($result !== 'ok') {
            flash('error', Otp::message($result));
            redirect('/reset');
        }
        $user = DB::one('SELECT * FROM users WHERE email = ?', [$email]);
        if (!$user || $user['is_banned']) {
            flash('error', 'We could not reset that account.');
            redirect('/forgot');
        }
        DB::query(
            'UPDATE users SET password_hash = ?, email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = ?',
            [AuthService::hash($password), $user['id']]
        );
        unset($_SESSION['reset_email']);
        audit('user.password_reset', 'user:' . $user['id']);
        flash('success', 'Password updated. You are signed in.');
        $user = DB::one('SELECT * FROM users WHERE id = ?', [$user['id']]);
        AuthService::complete($user, 'reset');
    }

    // ---------------- Invites (admin bulk import) ----------------

    private function findInvite(string $token): ?array
    {
        return DB::one(
            'SELECT * FROM invites WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()',
            [hash('sha256', $token)]
        );
    }

    public function showInvite(Request $r, string $token): void
    {
        $invite = $this->findInvite($token);
        if (!$invite) {
            view('auth/invite_invalid', ['title' => 'Invite link expired'], 'auth');
            return;
        }
        $existing = DB::one('SELECT id FROM users WHERE email = ?', [$invite['email']]);
        view('auth/invite', ['title' => 'Activate your account', 'invite' => $invite, 'token' => $token, 'existing' => (bool) $existing], 'auth');
    }

    public function acceptInvite(Request $r, string $token): void
    {
        $invite = $this->findInvite($token);
        if (!$invite) {
            flash('error', 'This invite link is no longer valid.');
            redirect('/login');
        }
        $user = DB::one('SELECT * FROM users WHERE email = ?', [$invite['email']]);

        if (!$user) {
            $username = (string) $r->input('username');
            $password = (string) $r->raw('password');
            $err = AuthService::validateUsername($username) ?? AuthService::validatePassword($password);
            if ($err) {
                flash('error', $err);
                with_old($r->all());
                redirect('/invite/' . $token);
            }
            $id = DB::insert('users', [
                'username' => $username,
                'email' => $invite['email'],
                'full_name' => $invite['full_name'],
                'password_hash' => AuthService::hash($password),
                'email_verified_at' => utc_now(),
            ]);
            $user = DB::one('SELECT * FROM users WHERE id = ?', [$id]);
        } else {
            DB::query('UPDATE users SET email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = ?', [$user['id']]);
        }

        if ($invite['team_name'] && !$user['team_id']) {
            $team = DB::one('SELECT * FROM teams WHERE name = ?', [$invite['team_name']]);
            if ($team) {
                if ($err = Teams::join((int) $user['id'], $team)) {
                    flash('warning', $err);
                }
            } else {
                Teams::create($invite['team_name'], (int) $user['id']);
            }
        }
        DB::query('UPDATE invites SET used_at = UTC_TIMESTAMP() WHERE id = ?', [$invite['id']]);
        audit('invite.accepted', 'user:' . $user['id']);
        flash('success', 'Your account is active.');
        $user = DB::one('SELECT * FROM users WHERE id = ?', [$user['id']]);
        AuthService::complete($user, 'invite');
    }

    public function logout(Request $r): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('info', 'You have signed out.');
        redirect('/login');
    }
}
