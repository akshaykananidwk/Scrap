<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Database-backed throttling. Shared hosting has no APCu/Redis guarantee, so
 * the counters live in a table with a (key, window) unique index.
 */
final class RateLimiter
{
    public static function hit(string $key, int $decaySeconds = 60): int
    {
        $window = (int) floor(time() / max(1, $decaySeconds));
        $hash = hash('sha256', $key);
        $db = Database::instance();
        try {
            $db->statement(
                'INSERT INTO rate_limits (bucket_key, window_start, hits, expires_at, created_at)
                 VALUES (:k, :w, 1, :e, :c)
                 ON DUPLICATE KEY UPDATE hits = hits + 1',
                [
                    'k' => $hash,
                    'w' => $window,
                    'e' => gmdate('Y-m-d H:i:s', ($window + 1) * $decaySeconds),
                    'c' => now(),
                ]
            );
        } catch (\Throwable $e) {
            Logger::instance()->warning('Rate limiter unavailable: ' . $e->getMessage());
            return 0;
        }
        return (int) $db->scalar(
            'SELECT hits FROM rate_limits WHERE bucket_key = :k AND window_start = :w',
            ['k' => $hash, 'w' => $window],
            0
        );
    }

    public static function attempts(string $key, int $decaySeconds = 60): int
    {
        $window = (int) floor(time() / max(1, $decaySeconds));
        return (int) Database::instance()->scalar(
            'SELECT hits FROM rate_limits WHERE bucket_key = :k AND window_start = :w',
            ['k' => hash('sha256', $key), 'w' => $window],
            0
        );
    }

    public static function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds = 60): bool
    {
        return self::attempts($key, $decaySeconds) >= $maxAttempts;
    }

    /** Record a hit and throw 429 when the limit is exceeded. */
    public static function enforce(string $key, int $maxAttempts, int $decaySeconds = 60, ?string $message = null): void
    {
        $hits = self::hit($key, $decaySeconds);
        if ($hits > $maxAttempts) {
            throw new HttpException(429, $message ?? 'Too many attempts. Please wait and try again.');
        }
    }

    public static function clear(string $key, int $decaySeconds = 60): void
    {
        $window = (int) floor(time() / max(1, $decaySeconds));
        Database::instance()->statement(
            'DELETE FROM rate_limits WHERE bucket_key = :k AND window_start = :w',
            ['k' => hash('sha256', $key), 'w' => $window]
        );
    }

    public static function prune(): int
    {
        return Database::instance()->statement('DELETE FROM rate_limits WHERE expires_at < :n', ['n' => now()]);
    }
}
