<?php
// HTTP kernel: security headers, middleware, routes, error handling.

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Router;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'; frame-ancestors 'none'; form-action 'self' https://accounts.google.com https://github.com");

$req = new Request();
$router = new Router();

// ---------------- Middleware ----------------
$deny = function (Request $req, int $code, string $msg, string $to) {
    if ($req->wantsJson()) {
        json_out(['ok' => false, 'message' => $msg], $code);
    } else {
        if ($msg) {
            flash('error', $msg);
        }
        redirect($to);
    }
    return false;
};

$router->middleware('guest', fn(Request $r) => current_user() ? redirect('/dashboard') : true);

$router->middleware('auth', function (Request $r) use ($deny) {
    $u = current_user();
    if (!$u) {
        if (!$r->wantsJson()) {
            $_SESSION['intended'] = $r->path();
        }
        return $deny($r, 401, $r->wantsJson() ? 'Please sign in.' : '', '/login');
    }
    if (!$u['email_verified_at']) {
        return $deny($r, 403, 'Verify your email to continue.', '/verify');
    }
    if (empty($_SESSION['seen_tick']) || $_SESSION['seen_tick'] < time() - 60) {
        $_SESSION['seen_tick'] = time();
        DB::query('UPDATE users SET last_seen_at = UTC_TIMESTAMP() WHERE id = ?', [$u['id']]);
    }
    return true;
});

$router->middleware('team', function (Request $r) use ($deny) {
    return current_team() ? true : $deny($r, 403, 'Create or join a team first.', '/team');
});

$router->middleware('admin', function (Request $r) {
    if (!is_admin()) {
        abort(404);
    }
    return true;
});

// CSRF on every state-changing request. Fetch calls send the token as a header.
if ($req->method() !== 'GET' && !csrf_valid($req->raw('_csrf') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
    if ($req->wantsJson()) {
        json_out(['ok' => false, 'message' => 'Your session expired. Reload the page and try again.'], 419);
    } else {
        flash('error', 'Your session expired. Please try again.');
        back();
    }
    exit;
}

// Maintenance mode lets only admins and the sign-in pages through.
if (setting('maintenance') === '1' && !is_admin()) {
    $p = $req->path();
    $open = ['/login', '/verify', '/logout'];
    if (!in_array($p, $open, true) && !str_starts_with($p, '/assets')) {
        http_response_code(503);
        view('public/maintenance', [], 'auth');
        exit;
    }
}

require APP_ROOT . '/routes.php';

try {
    $router->dispatch($req);
} catch (HttpException $e) {
    http_response_code($e->status);
    if ($req->wantsJson()) {
        json_out(['ok' => false, 'message' => $e->getMessage() ?: 'Error ' . $e->status], $e->status);
    } else {
        view('public/error', ['code' => $e->status, 'message' => $e->getMessage()], current_user() ? 'app' : 'auth');
    }
} catch (\Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    if ($req->wantsJson()) {
        json_out(['ok' => false, 'message' => 'Something went wrong. Please try again.'], 500);
    } elseif (env('APP_DEBUG') === 'true') {
        echo '<pre>' . e((string) $e) . '</pre>';
    } else {
        view('public/error', ['code' => 500, 'message' => 'Something went wrong on our side.'], 'auth');
    }
}
