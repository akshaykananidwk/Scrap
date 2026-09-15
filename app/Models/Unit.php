<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Unit extends Model
{
    protected static string $table = 'units';
    protected static array $fillable = ['code', 'name', 'kg_factor', 'is_weight', 'is_active', 'sort_order'];

    private static ?array $cache = null;

    public static function active(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        return self::$cache = self::db()->select('SELECT * FROM units WHERE is_active = 1 ORDER BY sort_order, code');
    }

    public static function byCode(string $code): ?array
    {
        foreach (self::active() as $unit) {
            if (strcasecmp((string) $unit['code'], $code) === 0) {
                return $unit;
            }
        }
        return self::findBy('code', strtoupper($code));
    }

    public static function code(int $unitId): string
    {
        foreach (self::active() as $unit) {
            if ((int) $unit['id'] === $unitId) {
                return (string) $unit['code'];
            }
        }
        $unit = self::find($unitId);
        return (string) ($unit['code'] ?? '');
    }

    public static function defaultId(): int
    {
        $unit = self::byCode('MT');
        return (int) ($unit['id'] ?? (self::active()[0]['id'] ?? 1));
    }

    /** Convert a quantity in the given unit to kilograms, when the unit is weight-based. */
    public static function toKg(float|string $quantity, int $unitId): ?string
    {
        foreach (self::active() as $unit) {
            if ((int) $unit['id'] === $unitId) {
                if (!(int) $unit['is_weight'] || $unit['kg_factor'] === null) {
                    return null;
                }
                return dec((float) $quantity * (float) $unit['kg_factor'], 3);
            }
        }
        return null;
    }

    public static function fromKg(float|string $kg, int $unitId): ?string
    {
        foreach (self::active() as $unit) {
            if ((int) $unit['id'] === $unitId) {
                if (!(int) $unit['is_weight'] || !$unit['kg_factor'] || (float) $unit['kg_factor'] == 0.0) {
                    return null;
                }
                return dec((float) $kg / (float) $unit['kg_factor'], 3);
            }
        }
        return null;
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::active() as $unit) {
            $options[(int) $unit['id']] = $unit['code'] . ' — ' . $unit['name'];
        }
        return $options;
    }
}
