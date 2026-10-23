<?php

use App\Core\DB;
use App\Core\HttpException;

// ---------------------------------------------------------------
// Environment & settings
// ---------------------------------------------------------------

function env(string $key, mixed $default = null): mixed
{
    static $loaded = false;
    if (!$loaded) {
        $loaded = true;
        $file = APP_ROOT . '/config/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
                    $v = substr($v, 1, -1);
                }
                $_ENV[$k] = $v;
            }
        }
    }
    $v = $_ENV[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : $v;
}

/** Admin-editable settings stored in the `settings` table, with defaults. */
function setting(string $key, mixed $default = null): mixed
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (DB::all('SELECT k, v FROM settings') as $row) {
                $cache[$row['k']] = $row['v'];
            }
        } catch (\Throwable) {
            // Table missing before migrations ran.
        }
    }
    if ($key === '__flush') {
        $cache = null;
        return null;
    }
    return $cache[$key] ?? SETTING_DEFAULTS[$key] ?? $default;
}

function set_setting(string $key, ?string $value): void
{
    DB::query('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$key, $value]);
    setting('__flush');
}

const SETTING_DEFAULTS = [
    'event_name' => 'NFSU Guwahati CTF',
    'event_tagline' => 'Capture the flag. Crack the case.',
    'org_name' => 'National Forensic Sciences University, Guwahati Campus',
    'contact_email' => 'sudipta@spectrona.in',
    'starts_at' => '',
    'ends_at' => '',
    'freeze_at' => '',
    'paused' => '0',
    'maintenance' => '0',
    'registration_open' => '1',
    'scoreboard_visibility' => 'public', // public | private | hidden
    'team_size_max' => '4',
    'allow_solo' => '1',
    'flag_format' => 'NFSUCTF{...}',
    'first_blood_mode' => 'fixed', // off | fixed | percent
    'first_blood_values' => '50,30,10',
    'signup_domain_allowlist' => '',
];

// ---------------------------------------------------------------
// Output helpers
// ---------------------------------------------------------------

function e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/', array $query = []): string
{
    $base = rtrim((string) env('APP_URL', ''), '/');
    $q = $query ? '?' . http_build_query($query) : '';
    return $base . '/' . ltrim($path, '/') . $q;
}

function asset(string $path): string
{
    $file = PUBLIC_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function icon(string $name, string $class = ''): string
{
    return '<svg class="icon ' . e($class) . '" aria-hidden="true"><use href="' . asset('icons.svg') . '#' . e($name) . '"></use></svg>';
}

function md(?string $text): string
{
    static $converter = null;
    $converter ??= new \League\CommonMark\GithubFlavoredMarkdownConverter([
        'html_input' => 'escape',
        'allow_unsafe_links' => false,
        'max_nesting_level' => 20,
    ]);
    return (string) $converter->convert($text ?? '');
}

function view(string $template, array $data = [], ?string $layout = 'app'): void
{
    echo render($template, $data, $layout);
}

function render(string $template, array $data = [], ?string $layout = null): string
{
    $file = APP_ROOT . '/views/' . $template . '.php';
    if (!is_file($file)) {
        throw new \RuntimeException("View not found: $template");
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    $content = ob_get_clean();
    if ($layout === null) {
        return $content;
    }
    $data['content'] = $content;
    return render('layouts/' . $layout, $data, null);
}

function partial(string $name, array $data = []): string
{
    return render('partials/' . $name, $data, null);
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 302);
    exit;
}

function back(string $fallback = '/'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = parse_url($ref, PHP_URL_HOST);
    redirect($ref && $host === ($_SERVER['HTTP_HOST'] ?? '') ? $ref : $fallback);
}

function abort(int $code, string $message = ''): never
{
    throw new HttpException($code, $message);
}

// ---------------------------------------------------------------
// Session, flash, CSRF, old input
// ---------------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function with_old(array $input): void
{
    unset($input['password'], $input['password_confirm'], $input['_csrf']);
    $_SESSION['_old'] = $input;
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['_old_view'][$key] ?? $default);
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}

// ---------------------------------------------------------------
// Auth & event state
// ---------------------------------------------------------------

function current_user(bool $refresh = false): ?array
{
    static $user = false;
    if ($refresh) {
        $user = false;
    }
    if ($user === false) {
        $id = $_SESSION['user_id'] ?? null;
        $user = $id ? DB::one('SELECT * FROM users WHERE id = ?', [$id]) : null;
        if ($user && $user['is_banned']) {
            $user = null;
            unset($_SESSION['user_id']);
        }
    }
    return $user;
}

function current_team(): ?array
{
    $u = current_user();
    if (!$u || !$u['team_id']) {
        return null;
    }
    static $team = null;
    if ($team === null || $team['id'] !== $u['team_id']) {
        $team = DB::one('SELECT * FROM teams WHERE id = ?', [$u['team_id']]);
    }
    return $team;
}

function is_admin(?array $u = null): bool
{
    $u ??= current_user();
    return $u && in_array($u['role'], ['admin', 'super_admin'], true);
}

function login_user(array $user, string $method): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['login_at'] = time();
    unset($_SESSION['pending_2fa'], $_SESSION['otp_email'], $_SESSION['otp_purpose']);
    DB::query('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [$user['id']]);
    log_login($user['email'], (int) $user['id'], $method, true);
    current_user(true);
}

function log_login(string $email, ?int $userId, string $method, bool $success): void
{
    DB::insert('login_events', [
        'user_id' => $userId,
        'email' => mb_substr($email, 0, 190),
        'method' => $method,
        'success' => $success ? 1 : 0,
        'ip' => client_ip(),
        'ua' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
}

function audit(string $action, ?string $target = null, array $meta = []): void
{
    DB::insert('audit_logs', [
        'user_id' => $_SESSION['user_id'] ?? null,
        'action' => $action,
        'target' => $target,
        'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        'ip' => client_ip(),
    ]);
}

function client_ip(): string
{
    return (new \App\Core\Request())->ip();
}

function parse_dt(?string $v): ?int
{
    return $v ? (strtotime($v . (preg_match('/[zZ]|[+-]\d\d:?\d\d$/', $v) ? '' : ' UTC')) ?: null) : null;
}

/** upcoming | live | paused | ended */
function event_state(): string
{
    $now = time();
    $start = parse_dt(setting('starts_at'));
    $end = parse_dt(setting('ends_at'));
    if ($start && $now < $start) {
        return 'upcoming';
    }
    if ($end && $now >= $end) {
        return 'ended';
    }
    if (setting('paused') === '1') {
        return 'paused';
    }
    return 'live';
}

/** Unix timestamp the public scoreboard is frozen at, or null. */
function freeze_cutoff(): ?int
{
    $f = parse_dt(setting('freeze_at'));
    return ($f && time() >= $f) ? $f : null;
}

function utc_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function time_ago(?string $ts): string
{
    if (!$ts) {
        return '—';
    }
    $d = time() - strtotime($ts . ' UTC');
    return match (true) {
        $d < 60 => 'just now',
        $d < 3600 => floor($d / 60) . 'm ago',
        $d < 86400 => floor($d / 3600) . 'h ago',
        default => floor($d / 86400) . 'd ago',
    };
}

/** Render a UTC DB timestamp as an element the client localises. */
function ts(?string $ts, string $fmt = 'datetime'): string
{
    if (!$ts) {
        return '<span class="muted">—</span>';
    }
    $iso = gmdate('c', strtotime($ts . ' UTC'));
    return '<time datetime="' . e($iso) . '" data-fmt="' . e($fmt) . '">' . e(gmdate('M j, H:i', strtotime($ts . ' UTC'))) . ' UTC</time>';
}

function str_random(int $len, string $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'): string
{
    $out = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s ?: 'item';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $i = mb_substr($parts[0] ?? '?', 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '');
    return mb_strtoupper($i);
}

function human_bytes(int $b): string
{
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($b >= 1024 && $i < 3) {
        $b /= 1024;
        $i++;
    }
    return round($b, $i ? 1 : 0) . ' ' . $u[$i];
}

/** Convert a datetime-local form value plus the browser's getTimezoneOffset() into a UTC DB string. */
function dt_from_form(?string $local, mixed $offsetMinutes): ?string
{
    if (!$local) {
        return null;
    }
    $ts = strtotime($local . ' UTC');
    if ($ts === false) {
        return null;
    }
    return gmdate('Y-m-d H:i:s', $ts + (int) $offsetMinutes * 60);
}

/** ISO string for a UTC DB/setting value, for <input type="datetime-local" data-utc="…">. */
function dt_iso(?string $utc): string
{
    $ts = parse_dt($utc);
    return $ts ? gmdate('c', $ts) : '';
}
