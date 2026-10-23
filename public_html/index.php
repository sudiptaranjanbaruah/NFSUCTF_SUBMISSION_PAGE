<?php
// Built-in dev server: let it serve real static files directly.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

// The app directory sits next to public_html (domains/<site>/app), or inside it when the
// host only deploys to the web root; .htaccess then blocks direct access to app/ and vendor/.
require (is_dir(__DIR__ . '/app') ? __DIR__ : dirname(__DIR__)) . '/app/bootstrap.php';
require APP_ROOT . '/kernel.php';
