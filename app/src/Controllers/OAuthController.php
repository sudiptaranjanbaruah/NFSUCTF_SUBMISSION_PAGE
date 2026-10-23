<?php
namespace App\Controllers;

use App\Auth\AuthService;
use App\Core\DB;
use App\Core\Request;

/**
 * Google and GitHub sign-in (OAuth 2.0 authorization code flow with state;
 * PKCE for Google). Accounts are matched by provider id, then by verified email.
 */
final class OAuthController
{
    private const PROVIDERS = [
        'google' => [
            'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token' => 'https://oauth2.googleapis.com/token',
            'scope' => 'openid email profile',
            'label' => 'Google',
        ],
        'github' => [
            'authorize' => 'https://github.com/login/oauth/authorize',
            'token' => 'https://github.com/login/oauth/access_token',
            'scope' => 'read:user user:email',
            'label' => 'GitHub',
        ],
    ];

    private function config(string $provider): array
    {
        $cfg = self::PROVIDERS[$provider] ?? null;
        $id = env(strtoupper($provider) . '_CLIENT_ID');
        $secret = env(strtoupper($provider) . '_CLIENT_SECRET');
        if (!$cfg || !$id || !$secret) {
            abort(404, 'That sign-in option is not enabled.');
        }
        return $cfg + ['id' => $id, 'secret' => $secret, 'redirect' => url("/auth/$provider/callback")];
    }

    public function redirect(Request $r, string $provider): void
    {
        $cfg = $this->config($provider);
        $state = bin2hex(random_bytes(16));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $_SESSION['oauth'] = ['state' => $state, 'verifier' => $verifier, 'provider' => $provider, 'link' => (bool) current_user()];

        $params = [
            'client_id' => $cfg['id'],
            'redirect_uri' => $cfg['redirect'],
            'response_type' => 'code',
            'scope' => $cfg['scope'],
            'state' => $state,
        ];
        if ($provider === 'google') {
            $params += [
                'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
                'code_challenge_method' => 'S256',
                'prompt' => 'select_account',
            ];
        } else {
            $params['allow_signup'] = 'true';
        }
        redirect($cfg['authorize'] . '?' . http_build_query($params));
    }

    public function callback(Request $r, string $provider): void
    {
        $cfg = $this->config($provider);
        $saved = $_SESSION['oauth'] ?? null;
        unset($_SESSION['oauth']);
        $back = current_user() ? '/profile#security' : '/login';

        if ($r->query('error')) {
            flash('info', $cfg['label'] . ' sign-in was cancelled.');
            redirect($back);
        }
        if (!$saved || $saved['provider'] !== $provider || !hash_equals($saved['state'], (string) $r->query('state'))) {
            flash('error', 'That sign-in attempt expired. Please try again.');
            redirect($back);
        }

        try {
            $profile = $provider === 'google'
                ? $this->google($cfg, (string) $r->query('code'), $saved['verifier'])
                : $this->github($cfg, (string) $r->query('code'));
        } catch (\Throwable $e) {
            error_log("OAuth $provider failed: " . $e->getMessage());
            flash('error', "We couldn't complete {$cfg['label']} sign-in. Try again or use an email code.");
            redirect($back);
        }

        $identity = DB::one('SELECT * FROM oauth_identities WHERE provider = ? AND provider_uid = ?', [$provider, $profile['uid']]);

        // Linking from the profile page.
        if ($saved['link'] && ($me = current_user())) {
            if ($identity && (int) $identity['user_id'] !== (int) $me['id']) {
                flash('error', "That {$cfg['label']} account is already linked to another user.");
            } elseif (!$identity) {
                DB::insert('oauth_identities', ['user_id' => $me['id'], 'provider' => $provider, 'provider_uid' => $profile['uid'], 'email' => $profile['email']]);
                audit('user.oauth_link', 'user:' . $me['id'], ['provider' => $provider]);
                flash('success', "{$cfg['label']} connected.");
            }
            redirect('/profile#security');
        }

        if ($identity) {
            $user = DB::one('SELECT * FROM users WHERE id = ?', [$identity['user_id']]);
        } elseif ($profile['email'] && $profile['email_verified']) {
            // Same verified email as an existing account: link automatically.
            $user = DB::one('SELECT * FROM users WHERE email = ?', [$profile['email']]);
            if ($user) {
                DB::insert('oauth_identities', ['user_id' => $user['id'], 'provider' => $provider, 'provider_uid' => $profile['uid'], 'email' => $profile['email']]);
                DB::query('UPDATE users SET email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = ?', [$user['id']]);
                $user = DB::one('SELECT * FROM users WHERE id = ?', [$user['id']]);
            }
        } else {
            $user = null;
        }

        if ($user) {
            if ($user['is_banned']) {
                flash('error', 'This account has been suspended. Contact the organisers.');
                redirect('/login');
            }
            AuthService::complete($user, $provider);
        }

        // New player: pick a username first.
        if (!$profile['email'] || !$profile['email_verified']) {
            flash('error', "Your {$cfg['label']} account has no verified email. Register with email instead.");
            redirect('/register');
        }
        if (setting('registration_open') !== '1') {
            flash('error', 'Registration is closed.');
            redirect('/login');
        }
        if (!AuthService::emailAllowed($profile['email'])) {
            flash('error', 'Registration is limited to approved email domains.');
            redirect('/register');
        }
        $_SESSION['oauth_pending'] = $profile + ['provider' => $provider];
        redirect('/onboarding');
    }

    public function showOnboarding(Request $r): void
    {
        $p = $_SESSION['oauth_pending'] ?? null;
        if (!$p) {
            redirect('/register');
        }
        $suggest = preg_replace('/[^A-Za-z0-9_.-]/', '', $p['login'] ?? '') ?: preg_replace('/[^A-Za-z0-9_.-]/', '', strstr($p['email'], '@', true));
        view('auth/onboarding', ['title' => 'Choose a username', 'p' => $p, 'suggest' => substr($suggest, 0, 24)], 'auth');
    }

    public function completeOnboarding(Request $r): void
    {
        $p = $_SESSION['oauth_pending'] ?? null;
        if (!$p) {
            redirect('/register');
        }
        $username = (string) $r->input('username');
        if ($err = AuthService::validateUsername($username)) {
            flash('error', $err);
            with_old($r->all());
            redirect('/onboarding');
        }
        if (!$r->input('agree')) {
            flash('error', 'Please accept the rules to continue.');
            redirect('/onboarding');
        }
        if (DB::val('SELECT id FROM users WHERE email = ?', [$p['email']])) {
            unset($_SESSION['oauth_pending']);
            flash('error', 'An account with this email already exists. Sign in instead.');
            redirect('/login');
        }
        $id = DB::insert('users', [
            'username' => $username,
            'email' => $p['email'],
            'full_name' => mb_substr((string) ($p['name'] ?? ''), 0, 120) ?: null,
            'affiliation' => mb_substr((string) $r->input('affiliation'), 0, 120) ?: null,
            'email_verified_at' => utc_now(),
        ]);
        DB::insert('oauth_identities', ['user_id' => $id, 'provider' => $p['provider'], 'provider_uid' => $p['uid'], 'email' => $p['email']]);
        unset($_SESSION['oauth_pending']);
        audit('user.register_oauth', 'user:' . $id, ['provider' => $p['provider']]);
        AuthService::complete(DB::one('SELECT * FROM users WHERE id = ?', [$id]), $p['provider']);
    }

    // ---------------- Provider calls ----------------

    private function google(array $cfg, string $code, string $verifier): array
    {
        $token = $this->http('POST', $cfg['token'], [
            'code' => $code,
            'client_id' => $cfg['id'],
            'client_secret' => $cfg['secret'],
            'redirect_uri' => $cfg['redirect'],
            'grant_type' => 'authorization_code',
            'code_verifier' => $verifier,
        ]);
        $info = $this->http('GET', 'https://openidconnect.googleapis.com/v1/userinfo', null, $token['access_token'] ?? '');
        if (empty($info['sub'])) {
            throw new \RuntimeException('No subject in Google userinfo');
        }
        return [
            'uid' => (string) $info['sub'],
            'email' => strtolower((string) ($info['email'] ?? '')),
            'email_verified' => !empty($info['email_verified']),
            'name' => $info['name'] ?? null,
            'login' => isset($info['given_name']) ? strtolower($info['given_name']) : null,
        ];
    }

    private function github(array $cfg, string $code): array
    {
        $token = $this->http('POST', $cfg['token'], [
            'code' => $code,
            'client_id' => $cfg['id'],
            'client_secret' => $cfg['secret'],
            'redirect_uri' => $cfg['redirect'],
        ]);
        $access = $token['access_token'] ?? '';
        $user = $this->http('GET', 'https://api.github.com/user', null, $access);
        $emails = $this->http('GET', 'https://api.github.com/user/emails', null, $access);
        $primary = null;
        foreach ($emails as $e) {
            if (!empty($e['primary']) && !empty($e['verified'])) {
                $primary = strtolower($e['email']);
            }
        }
        if (empty($user['id'])) {
            throw new \RuntimeException('No id in GitHub user');
        }
        return [
            'uid' => (string) $user['id'],
            'email' => $primary,
            'email_verified' => (bool) $primary,
            'name' => $user['name'] ?? null,
            'login' => $user['login'] ?? null,
        ];
    }

    private function http(string $method, string $url, ?array $form = null, string $bearer = ''): array
    {
        $ch = curl_init($url);
        $headers = ['Accept: application/json', 'User-Agent: nfsu-ctf'];
        if ($bearer) {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form ?? []));
        }
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $status >= 400) {
            throw new \RuntimeException("HTTP $status from $url $err");
        }
        $data = json_decode((string) $body, true);
        if (!is_array($data) || isset($data['error'])) {
            throw new \RuntimeException('Bad response from ' . $url . ': ' . substr((string) $body, 0, 200));
        }
        return $data;
    }
}
