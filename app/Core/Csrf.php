<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = str_random(64);
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(?string $token): bool
    {
        $expected = $_SESSION['_csrf'] ?? '';
        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }

    public static function rotate(): void
    {
        $_SESSION['_csrf'] = str_random(64);
    }
}
