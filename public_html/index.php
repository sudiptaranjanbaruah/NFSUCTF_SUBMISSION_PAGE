<?php
// Built-in dev server: let it serve real static files directly.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

// The app directory sits next to public_html on Hostinger (domains/<site>/app).
require dirname(__DIR__) . '/app/bootstrap.php';
require APP_ROOT . '/kernel.php';
