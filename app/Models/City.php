<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class City extends Model
{
    protected static string $table = 'cities';
    protected static array $fillable = ['state_id', 'district_id', 'name', 'slug', 'latitude', 'longitude', 'is_major', 'is_active'];

    public static function forState(int $stateId): array
    {
        return self::db()->select(
            'SELECT id, name, slug FROM cities WHERE state_id = :s AND is_active = 1 ORDER BY is_major DESC, name',
            ['s' => $stateId]
        );
    }

    public static function major(int $limit = 24): array
    {
        return self::db()->select(
            'SELECT c.id, c.name, c.slug, s.name AS state_name FROM cities c
             INNER JOIN states s ON s.id = c.state_id
             WHERE c.is_major = 1 AND c.is_active = 1 ORDER BY c.name LIMIT ' . max(1, $limit)
        );
    }

    public static function search(string $term, int $limit = 15): array
    {
        return self::db()->select(
            'SELECT c.id, c.name, s.name AS state_name FROM cities c
             INNER JOIN states s ON s.id = c.state_id
             WHERE c.is_active = 1 AND c.name LIKE :q ORDER BY c.is_major DESC, c.name LIMIT ' . max(1, $limit),
            ['q' => $term . '%']
        );
    }

    public static function withState(int $id): ?array
    {
        return self::db()->first(
            'SELECT c.*, s.name AS state_name, s.id AS state_id, s.gst_state_code
             FROM cities c INNER JOIN states s ON s.id = c.state_id WHERE c.id = :id',
            ['id' => $id]
        );
    }

    /** Great-circle distance in km, for "nearest first" sorting. */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    public static function resolveByPincode(string $pincode): ?array
    {
        return self::db()->first(
            'SELECT p.*, c.name AS city_name, s.name AS state_name, s.id AS state_id
             FROM pincodes p
             LEFT JOIN cities c ON c.id = p.city_id
             LEFT JOIN states s ON s.id = p.state_id
             WHERE p.pincode = :p LIMIT 1',
            ['p' => $pincode]
        );
    }
}
