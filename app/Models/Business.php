<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Paginator;

final class Business extends Model
{
    protected static string $table = 'businesses';
    protected static bool $softDeletes = true;
    protected static array $fillable = [
        'user_id', 'name', 'slug', 'business_type', 'logo', 'cover_image', 'about', 'established_year',
        'employee_count', 'annual_turnover', 'website', 'contact_person', 'contact_mobile', 'contact_email',
        'gstin', 'pan', 'registration_number', 'address_line1', 'address_line2', 'city_id', 'state_id',
        'country_id', 'city_name', 'state_name', 'pincode', 'latitude', 'longitude',
    ];

    public const TYPES = [
        'trader' => 'Scrap Trader',
        'dealer' => 'Scrap Dealer',
        'aggregator' => 'Scrap Aggregator',
        'recycler' => 'Recycler',
        'manufacturer' => 'Manufacturer',
        'factory' => 'Factory / Industrial Unit',
        'contractor' => 'Contractor',
        'industrial' => 'Industrial Business',
        'ewaste_dealer' => 'E-Waste Dealer',
        'metal_trader' => 'Metal Trader',
        'plastic_paper_trader' => 'Plastic / Paper Trader',
        'transporter' => 'Transporter / Logistics',
        'other' => 'Other',
    ];

    public static function forUser(int $userId): ?array
    {
        return self::first(['user_id' => $userId]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return self::db()->first(
            'SELECT b.*, u.full_name, u.created_at AS member_since, u.status AS user_status,
                    u.kyc_status, u.last_active_at
             FROM businesses b
             INNER JOIN users u ON u.id = b.user_id
             WHERE b.slug = :s AND b.deleted_at IS NULL AND u.deleted_at IS NULL
             LIMIT 1',
            ['s' => $slug]
        );
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = slugify($name);
        $slug = $base;
        $suffix = 1;
        while (true) {
            $sql = 'SELECT id FROM businesses WHERE slug = :s';
            $params = ['s' => $slug];
            if ($ignoreId !== null) {
                $sql .= ' AND id <> :i';
                $params['i'] = $ignoreId;
            }
            if (self::db()->first($sql, $params) === null) {
                return $slug;
            }
            $slug = $base . '-' . (++$suffix);
        }
    }

    public static function paginate(array $filters, int $page, int $perPage = 24): Paginator
    {
        $sql = 'SELECT b.*, u.status AS user_status, u.created_at AS member_since
                FROM businesses b
                INNER JOIN users u ON u.id = b.user_id
                WHERE b.deleted_at IS NULL AND u.deleted_at IS NULL AND u.status = "active"';
        $count = 'SELECT COUNT(*) FROM businesses b INNER JOIN users u ON u.id = b.user_id
                  WHERE b.deleted_at IS NULL AND u.deleted_at IS NULL AND u.status = "active"';
        $params = [];

        if (!empty($filters['q'])) {
            $clause = ' AND (b.name LIKE :q OR b.city_name LIKE :q OR b.about LIKE :q)';
            $sql .= $clause;
            $count .= $clause;
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['business_type'])) {
            $sql .= ' AND b.business_type = :business_type';
            $count .= ' AND b.business_type = :business_type';
            $params['business_type'] = $filters['business_type'];
        }
        if (!empty($filters['state_id'])) {
            $sql .= ' AND b.state_id = :state_id';
            $count .= ' AND b.state_id = :state_id';
            $params['state_id'] = (int) $filters['state_id'];
        }
        if (!empty($filters['city_id'])) {
            $sql .= ' AND b.city_id = :city_id';
            $count .= ' AND b.city_id = :city_id';
            $params['city_id'] = (int) $filters['city_id'];
        }
        if (!empty($filters['verified'])) {
            $sql .= ' AND b.kyc_verified = 1';
            $count .= ' AND b.kyc_verified = 1';
        }

        $sql .= match ($filters['sort'] ?? '') {
            'rating' => ' ORDER BY b.rating_avg DESC, b.rating_count DESC',
            'listings' => ' ORDER BY b.total_listings DESC',
            'oldest' => ' ORDER BY b.id ASC',
            default => ' ORDER BY b.kyc_verified DESC, b.rating_avg DESC, b.id DESC',
        };

        return self::paginateQuery($sql, $params, $page, $perPage, $count);
    }

    /** Recompute the public metrics shown on a business profile. */
    public static function refreshStats(int $businessId): void
    {
        $db = self::db();
        $business = self::find($businessId);
        if ($business === null) {
            return;
        }
        $userId = (int) $business['user_id'];

        $totalListings = (int) $db->scalar(
            "SELECT COUNT(*) FROM listings WHERE user_id = :u AND status = 'active' AND deleted_at IS NULL",
            ['u' => $userId],
            0
        );
        $completedOrders = (int) $db->scalar(
            "SELECT COUNT(*) FROM orders WHERE (buyer_id = :u OR seller_id = :u2) AND status = 'completed'",
            ['u' => $userId, 'u2' => $userId],
            0
        );
        $rating = $db->first(
            "SELECT COALESCE(AVG(overall_rating), 0) AS avg_rating, COUNT(*) AS total
             FROM reviews WHERE reviewee_id = :u AND status = 'published'",
            ['u' => $userId]
        ) ?? ['avg_rating' => 0, 'total' => 0];
        $followers = (int) $db->scalar(
            "SELECT COUNT(*) FROM follows WHERE followable_type = 'business' AND followable_id = :b",
            ['b' => $businessId],
            0
        );

        // Response rate: conversations where this user replied at least once.
        $conversations = (int) $db->scalar(
            'SELECT COUNT(*) FROM conversations WHERE buyer_id = :u OR seller_id = :u2',
            ['u' => $userId, 'u2' => $userId],
            0
        );
        $responded = (int) $db->scalar(
            'SELECT COUNT(DISTINCT c.id) FROM conversations c
             INNER JOIN messages m ON m.conversation_id = c.id AND m.sender_id = :u
             WHERE c.buyer_id = :u2 OR c.seller_id = :u3',
            ['u' => $userId, 'u2' => $userId, 'u3' => $userId],
            0
        );
        $responseRate = $conversations > 0 ? round(($responded / $conversations) * 100, 2) : 0;

        $avgMinutes = (int) $db->scalar(
            'SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, c.created_at, m.first_reply)), 0) FROM conversations c
             INNER JOIN (
                SELECT conversation_id, MIN(created_at) AS first_reply
                FROM messages WHERE sender_id = :u GROUP BY conversation_id
             ) m ON m.conversation_id = c.id
             WHERE (c.buyer_id = :u2 OR c.seller_id = :u3)',
            ['u' => $userId, 'u2' => $userId, 'u3' => $userId],
            0
        );

        $db->update('businesses', [
            'total_listings' => $totalListings,
            'completed_orders' => $completedOrders,
            'rating_avg' => dec($rating['avg_rating'], 2),
            'rating_count' => (int) $rating['total'],
            'follower_count' => $followers,
            'response_rate' => dec($responseRate, 2),
            'avg_response_minutes' => max(0, $avgMinutes),
            'updated_at' => now(),
        ], ['id' => $businessId]);
    }

    public static function refreshAllStats(): int
    {
        $rows = self::db()->select('SELECT id FROM businesses WHERE deleted_at IS NULL');
        foreach ($rows as $row) {
            self::refreshStats((int) $row['id']);
        }
        return count($rows);
    }
}
