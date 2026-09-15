<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use App\Services\AuditService;

/**
 * Session + API-token authentication with role/permission checks (RBAC).
 */
final class Auth
{
    private static ?array $user = null;
    private static ?array $permissions = null;
    private static ?array $roles = null;
    private static bool $resolved = false;

    public static function restoreSession(): void
    {
        if (self::$resolved) {
            return;
        }
        self::$resolved = true;
        $id = (int) (Session::get('user_id') ?? 0);
        if ($id <= 0) {
            return;
        }
        $user = User::find($id);
        if ($user === null || !in_array($user['status'], ['active'], true)) {
            self::logout();
            return;
        }
        self::$user = $user;
        self::loadAbilities((int) $user['id']);
    }

    /** Authenticate an API request from a bearer token. */
    public static function authenticateToken(string $token): bool
    {
        $hash = hash('sha256', $token);
        $row = Database::instance()->first(
            'SELECT u.* FROM api_tokens t INNER JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = :h AND t.revoked_at IS NULL AND (t.expires_at IS NULL OR t.expires_at > :now)
             LIMIT 1',
            ['h' => $hash, 'now' => now()]
        );
        if ($row === null || $row['status'] !== 'active') {
            return false;
        }
        Database::instance()->statement('UPDATE api_tokens SET last_used_at = :n WHERE token_hash = :h', ['n' => now(), 'h' => $hash]);
        self::$user = $row;
        self::$resolved = true;
        self::loadAbilities((int) $row['id']);
        return true;
    }

    public static function attempt(string $identifier, string $password): ?array
    {
        $identifier = trim($identifier);
        $user = str_contains($identifier, '@')
            ? User::findBy('email', strtolower($identifier))
            : User::findBy('mobile', preg_replace('/\D/', '', $identifier) ?? '');

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            return null;
        }
        return $user;
    }

    public static function login(array $user, bool $regenerate = true): void
    {
        if ($regenerate) {
            Session::regenerate();
        }
        Session::put('user_id', (int) $user['id']);
        Session::put('login_at', now());
        Csrf::rotate();
        self::$user = $user;
        self::$resolved = true;
        self::loadAbilities((int) $user['id']);

        User::updateById((int) $user['id'], ['last_login_at' => now()], true);
    }

    public static function logout(): void
    {
        if (self::$user !== null) {
            AuditService::log('logout', 'user', (int) self::$user['id']);
        }
        self::$user = null;
        self::$permissions = null;
        self::$roles = null;
        Session::forget('user_id');
        Session::destroy();
    }

    public static function check(): bool
    {
        return self::$user !== null;
    }

    public static function user(): ?array
    {
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::$user !== null ? (int) self::$user['id'] : null;
    }

    public static function refresh(): void
    {
        if (self::$user !== null) {
            self::$user = User::find((int) self::$user['id']) ?? self::$user;
        }
    }

    public static function setUser(?array $user): void
    {
        self::$user = $user;
        self::$resolved = true;
        if ($user !== null) {
            self::loadAbilities((int) $user['id']);
        }
    }

    private static function loadAbilities(int $userId): void
    {
        $rows = Database::instance()->select(
            'SELECT r.slug AS role_slug, p.slug AS permission_slug
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             LEFT JOIN role_permissions rp ON rp.role_id = r.id
             LEFT JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = :u',
            ['u' => $userId]
        );
        $roles = [];
        $permissions = [];
        foreach ($rows as $row) {
            $roles[$row['role_slug']] = true;
            if (!empty($row['permission_slug'])) {
                $permissions[$row['permission_slug']] = true;
            }
        }
        self::$roles = array_keys($roles);
        self::$permissions = array_keys($permissions);
    }

    public static function roles(): array
    {
        return self::$roles ?? [];
    }

    public static function permissions(): array
    {
        return self::$permissions ?? [];
    }

    public static function hasRole(string ...$roles): bool
    {
        foreach ($roles as $role) {
            if (in_array($role, self::roles(), true)) {
                return true;
            }
        }
        return false;
    }

    public static function can(string $permission): bool
    {
        if (!self::check()) {
            return false;
        }
        if (self::hasRole('super_admin')) {
            return true;
        }
        return in_array($permission, self::permissions(), true);
    }

    /** Staff = anyone with an admin-side role. */
    public static function isStaff(): bool
    {
        return self::hasRole('super_admin', 'admin', 'moderator', 'kyc_manager', 'finance_manager', 'support_manager');
    }

    public static function isSeller(): bool
    {
        $type = self::$user['account_type'] ?? '';
        return in_array($type, ['seller', 'both'], true);
    }

    public static function isBuyer(): bool
    {
        $type = self::$user['account_type'] ?? '';
        return in_array($type, ['buyer', 'both'], true);
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 11]);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 11]);
    }

    /** Create a plaintext API token (shown once) and store only its hash. */
    public static function issueToken(int $userId, string $name = 'api', ?string $expiresAt = null): string
    {
        $token = str_random(64);
        Database::instance()->insert('api_tokens', [
            'user_id' => $userId,
            'name' => substr($name, 0, 60),
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'created_at' => now(),
        ]);
        return $token;
    }
}
