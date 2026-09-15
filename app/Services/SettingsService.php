<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Database;

/**
 * Database-driven settings with a per-request cache. Secrets (SMTP password,
 * GitHub token, payment keys) are stored with type='secret' and encrypted.
 */
final class SettingsService
{
    private static ?array $cache = null;
    private static array $types = [];

    public static function flush(): void
    {
        self::$cache = null;
        self::$types = [];
    }

    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::$cache = [];
        try {
            $rows = Database::instance()->select('SELECT key_name, value, type FROM settings');
        } catch (\Throwable) {
            // Before installation / during a failed update the table may not exist.
            return self::$cache;
        }
        foreach ($rows as $row) {
            $key = (string) $row['key_name'];
            self::$types[$key] = (string) $row['type'];
            self::$cache[$key] = self::cast((string) $row['type'], $row['value']);
        }
        return self::$cache;
    }

    private static function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'decimal' => (string) ($value ?? '0'),
            'boolean' => in_array((string) $value, ['1', 'true', 'yes', 'on'], true),
            'json' => json_decode((string) ($value ?? '[]'), true) ?? [],
            'secret' => Crypto::decrypt((string) ($value ?? '')),
            default => $value,
        };
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = self::load();
        $value = $settings[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        return $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $settings = self::load();
        if (!array_key_exists($key, $settings)) {
            return $default;
        }
        $value = $settings[$key];
        if (is_bool($value)) {
            return $value;
        }
        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function decimal(string $key, string $default = '0.00'): string
    {
        $value = self::get($key, $default);
        return is_numeric($value) ? dec($value, 3) : $default;
    }

    public static function json(string $key, array $default = []): array
    {
        $value = self::get($key, null);
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : $default;
        }
        return $default;
    }

    /** Has a secret actually been configured? Used to show "not configured" states honestly. */
    public static function configured(string ...$keys): bool
    {
        foreach ($keys as $key) {
            $value = self::get($key, '');
            if ($value === '' || $value === null) {
                return false;
            }
        }
        return true;
    }

    public static function set(string $key, mixed $value, ?string $type = null, string $group = 'general'): void
    {
        $db = Database::instance();
        $existing = $db->first('SELECT id, type FROM settings WHERE key_name = :k', ['k' => $key]);
        $type ??= $existing['type'] ?? self::inferType($value);

        $stored = match ($type) {
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'boolean' => $value ? '1' : '0',
            'secret' => $value === '' ? '' : Crypto::encrypt((string) $value),
            default => $value === null ? null : (string) $value,
        };

        if ($existing !== null) {
            $db->update('settings', [
                'value' => $stored,
                'type' => $type,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ], ['id' => (int) $existing['id']]);
        } else {
            $db->insert('settings', [
                'group_name' => $group,
                'key_name' => $key,
                'value' => $stored,
                'type' => $type,
                'label' => ucwords(str_replace('_', ' ', $key)),
                'updated_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        self::flush();
    }

    /** Bulk update from an admin settings form. */
    public static function setMany(array $values, string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            self::set((string) $key, $value, null, $group);
        }
    }

    private static function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'json',
            is_float($value) => 'decimal',
            default => 'string',
        };
    }

    public static function group(string $group): array
    {
        $rows = Database::instance()->select(
            'SELECT * FROM settings WHERE group_name = :g ORDER BY id',
            ['g' => $group]
        );
        foreach ($rows as &$row) {
            if ($row['type'] === 'secret') {
                // Never hand a decrypted secret to a template.
                $row['value'] = Crypto::decrypt((string) $row['value']) === '' ? '' : '__SET__';
            }
        }
        return $rows;
    }

    public static function groups(): array
    {
        $rows = Database::instance()->select('SELECT DISTINCT group_name FROM settings ORDER BY group_name');
        return array_map(static fn (array $r): string => (string) $r['group_name'], $rows);
    }

    /** Settings exposed to the front-end (never secrets). */
    public static function publicSettings(): array
    {
        $rows = Database::instance()->select("SELECT key_name, value, type FROM settings WHERE is_public = 1 AND type <> 'secret'");
        $out = [];
        foreach ($rows as $row) {
            $out[$row['key_name']] = self::cast((string) $row['type'], $row['value']);
        }
        return $out;
    }
}
