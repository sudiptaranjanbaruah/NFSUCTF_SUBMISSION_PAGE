<?php
namespace App\Core;

/**
 * Fixed-window rate limiter stored in MySQL (works on shared hosting without Redis).
 */
final class RateLimit
{
    /** Returns true when the action is allowed, false when the limit is exceeded. */
    public static function hit(string $key, int $max, int $windowSeconds): bool
    {
        $key = substr(hash('sha256', $key), 0, 64);
        $now = time();
        DB::query(
            'INSERT INTO rate_limits (k, hits, reset_at) VALUES (?, 1, ?)
             ON DUPLICATE KEY UPDATE
               hits = IF(reset_at < ?, 1, hits + 1),
               reset_at = IF(reset_at < ?, VALUES(reset_at), reset_at)',
            [$key, $now + $windowSeconds, $now, $now]
        );
        $hits = (int) DB::val('SELECT hits FROM rate_limits WHERE k = ?', [$key]);
        return $hits <= $max;
    }

    public static function retryAfter(string $key): int
    {
        $key = substr(hash('sha256', $key), 0, 64);
        $reset = (int) DB::val('SELECT reset_at FROM rate_limits WHERE k = ?', [$key]);
        return max(0, $reset - time());
    }

    public static function clear(string $key): void
    {
        DB::query('DELETE FROM rate_limits WHERE k = ?', [substr(hash('sha256', $key), 0, 64)]);
    }
}
