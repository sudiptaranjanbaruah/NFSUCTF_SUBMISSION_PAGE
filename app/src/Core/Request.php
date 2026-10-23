<?php
namespace App\Core;

final class Request
{
    private array $json = [];

    public function __construct()
    {
        if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $decoded = json_decode(file_get_contents('php://input') ?: '', true);
            $this->json = is_array($decoded) ? $decoded : [];
        }
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $v = $this->json[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    /** Raw value without trimming (passwords, flags). */
    public function raw(string $key, mixed $default = null): mixed
    {
        return $this->json[$key] ?? $_POST[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST, $this->json);
    }

    public function file(string $key): ?array
    {
        $f = $_FILES[$key] ?? null;
        return ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) ? $f : null;
    }

    public function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    }

    public function ip(): string
    {
        // Hostinger sits behind a proxy; trust the first forwarded hop only when configured.
        if (env('TRUST_PROXY') === 'true' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    }
}
