<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        session_name('scrapx_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) (60 * 60 * 8));
        @session_start();
        self::$started = true;

        // Rotate the id periodically to limit fixation windows.
        $now = time();
        $last = (int) ($_SESSION['_regenerated_at'] ?? 0);
        if ($last === 0) {
            $_SESSION['_regenerated_at'] = $now;
        } elseif ($now - $last > 1800) {
            self::regenerate();
        }
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['_regenerated_at'] = time();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function all(): array
    {
        return $_SESSION ?? [];
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        self::$started = false;
    }

    public static function flash(string $type, mixed $message): void
    {
        $_SESSION['_flash'][$type][] = $message;
    }

    public static function flashes(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flashes;
    }

    public static function flashInput(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['_token'], $input['current_password']);
        $_SESSION['_old_next'] = $input;
    }

    public static function flashErrors(array $errors): void
    {
        $_SESSION['_errors_next'] = $errors;
    }

    /** Promote "next request" buckets into the current request and clear them. */
    public static function rotateFlashBuckets(): void
    {
        $_SESSION['_old'] = $_SESSION['_old_next'] ?? [];
        $_SESSION['_errors'] = $_SESSION['_errors_next'] ?? [];
        unset($_SESSION['_old_next'], $_SESSION['_errors_next']);
    }

    public static function errors(): array
    {
        return $_SESSION['_errors'] ?? [];
    }
}
