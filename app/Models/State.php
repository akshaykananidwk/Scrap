<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class State extends Model
{
    protected static string $table = 'states';
    protected static array $fillable = ['country_id', 'name', 'slug', 'code', 'gst_state_code', 'latitude', 'longitude', 'is_active'];

    private static ?array $cache = null;

    public static function active(): array
    {
        return self::$cache ??= self::db()->select('SELECT * FROM states WHERE is_active = 1 ORDER BY name');
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::active() as $state) {
            $options[(int) $state['id']] = $state['name'];
        }
        return $options;
    }

    /** GST state code drives CGST+SGST vs IGST on invoices. */
    public static function gstCode(int $stateId): ?string
    {
        foreach (self::active() as $state) {
            if ((int) $state['id'] === $stateId) {
                return $state['gst_state_code'];
            }
        }
        return null;
    }

    public static function withListingCounts(): array
    {
        return self::db()->select(
            'SELECT s.id, s.name, s.slug, COUNT(l.id) AS listing_count
             FROM states s
             LEFT JOIN listings l ON l.state_id = s.id AND l.status = "active" AND l.deleted_at IS NULL
             WHERE s.is_active = 1
             GROUP BY s.id, s.name, s.slug
             HAVING listing_count > 0
             ORDER BY listing_count DESC, s.name'
        );
    }
}
