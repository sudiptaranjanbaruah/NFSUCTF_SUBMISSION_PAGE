<?php
declare(strict_types=1);

define('APP_ROOT', __DIR__);
define('PUBLIC_ROOT', is_dir(dirname(__DIR__) . '/public_html') ? dirname(__DIR__) . '/public_html' : dirname(__DIR__));

// Composer's vendor/ is shipped as deps/ on hosts whose uploader strips vendor/.
require dirname(__DIR__) . (is_dir(dirname(__DIR__) . '/vendor') ? '/vendor' : '/deps') . '/autoload.php';

date_default_timezone_set('UTC');

if (env('APP_DEBUG') === 'true') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

if (PHP_SAPI !== 'cli') {
    $secure = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $lifetime = 7 * 24 * 3600;
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    ini_set('session.use_strict_mode', '1');
    session_save_path(APP_ROOT . '/storage/sessions');
    session_name('nfsuctf_sid');
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        // Lax so the OAuth provider redirect back to us keeps the session.
        'samesite' => 'Lax',
    ]);
    session_start();

    // Old form input survives exactly one redirect.
    $_SESSION['_old_view'] = $_SESSION['_old'] ?? [];
    unset($_SESSION['_old']);
}
