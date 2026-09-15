<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Reporting for the three dashboards. Every figure here is computed from the
 * transactional tables — nothing is cached or estimated.
 */
final class AnalyticsService
{
    public static function platformOverview(): array
    {
        $db = Database::instance();
        $users = \App\Models\User::counts();
        $listings = \App\Models\Listing::counts();
        $auctions = \App\Models\Auction::counts();
        $orders = \App\Models\Order::counts();
        $revenue = CommissionService::revenueSummary();
        $disputes = DisputeService::counts();
        $kyc = KycService::counts();

        return [
            'users' => $users,
            'listings' => $listings,
            'auctions' => $auctions,
            'orders' => $orders,
            'revenue' => $revenue,
            'disputes' => $disputes,
            'kyc' => $kyc,
            'requirements' => \App\Models\WantedRequirement::counts(),
            'rfqs' => \App\Models\Rfq::counts(),
            'active_bids' => (int) $db->scalar(
                "SELECT COUNT(*) FROM bids b INNER JOIN auctions a ON a.id = b.auction_id WHERE a.status = 'live'",
                [],
                0
            ),
            'gmv' => dec($orders['gmv'] ?? 0, 2),
            'pending_payouts' => dec($db->scalar(
                "SELECT COALESCE(SUM(total_amount), 0) FROM commissions WHERE status IN ('pending','invoiced')",
                [],
                0
            ), 2),
        ];
    }

    /** Daily series for the admin charts. */
    public static function dailySeries(string $metric, int $days = 30): array
    {
        $since = gmdate('Y-m-d', strtotime("-{$days} days"));
        $db = Database::instance();

        [$sql, $params] = match ($metric) {
            'registrations' => [
                'SELECT DATE(created_at) AS day, COUNT(*) AS value FROM users
                 WHERE created_at >= :since AND deleted_at IS NULL GROUP BY DATE(created_at)',
                ['since' => $since . ' 00:00:00'],
            ],
            'listings' => [
                'SELECT DATE(created_at) AS day, COUNT(*) AS value FROM listings
                 WHERE created_at >= :since AND deleted_at IS NULL GROUP BY DATE(created_at)',
                ['since' => $since . ' 00:00:00'],
            ],
            'orders' => [
                'SELECT DATE(created_at) AS day, COUNT(*) AS value FROM orders
                 WHERE created_at >= :since GROUP BY DATE(created_at)',
                ['since' => $since . ' 00:00:00'],
            ],
            'gmv' => [
                'SELECT DATE(created_at) AS day, COALESCE(SUM(final_amount), 0) AS value FROM orders
                 WHERE created_at >= :since GROUP BY DATE(created_at)',
                ['since' => $since . ' 00:00:00'],
            ],
            'revenue' => [
                "SELECT DATE(created_at) AS day, COALESCE(SUM(total_amount), 0) AS value FROM commissions
                 WHERE created_at >= :since AND status <> 'cancelled' GROUP BY DATE(created_at)",
                ['since' => $since . ' 00:00:00'],
            ],
            'bids' => [
                'SELECT DATE(created_at) AS day, COUNT(*) AS value FROM bids
                 WHERE created_at >= :since GROUP BY DATE(created_at)',
                ['since' => $since . ' 00:00:00'],
            ],
            default => ['SELECT CURDATE() AS day, 0 AS value', []],
        };

        $rows = $db->select($sql, $params);
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[(string) $row['day']] = (float) $row['value'];
        }

        // Fill the gaps so the chart has a continuous x-axis.
        $series = [];
        for ($i = $days; $i >= 0; $i--) {
            $day = gmdate('Y-m-d', strtotime("-{$i} days"));
            $series[] = ['day' => $day, 'label' => gmdate('d M', strtotime($day)), 'value' => $byDay[$day] ?? 0];
        }
        return $series;
    }

    public static function categoryPerformance(int $limit = 10): array
    {
        return Database::instance()->select(
            'SELECT c.name, c.slug,
                    COUNT(DISTINCT l.id) AS listings,
                    COUNT(DISTINCT o.id) AS orders,
                    COALESCE(SUM(o.final_amount), 0) AS value
             FROM categories c
             LEFT JOIN listings l ON (l.category_id = c.id OR l.subcategory_id = c.id) AND l.deleted_at IS NULL
             LEFT JOIN orders o ON o.listing_id = l.id
             WHERE c.parent_id IS NULL
             GROUP BY c.id, c.name, c.slug
             ORDER BY value DESC, listings DESC
             LIMIT ' . max(1, $limit)
        );
    }

    public static function topSellers(int $limit = 10): array
    {
        return Database::instance()->select(
            "SELECT b.name, b.slug, b.city_name, b.rating_avg,
                    COUNT(o.id) AS orders, COALESCE(SUM(o.final_amount), 0) AS value
             FROM orders o
             INNER JOIN businesses b ON b.user_id = o.seller_id AND b.deleted_at IS NULL
             WHERE o.status NOT IN ('cancelled')
             GROUP BY b.id, b.name, b.slug, b.city_name, b.rating_avg
             ORDER BY value DESC LIMIT " . max(1, $limit)
        );
    }

    /** Seller dashboard: views → enquiries → offers → sales funnel. */
    public static function sellerAnalytics(int $userId, int $days = 30): array
    {
        $db = Database::instance();
        $since = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        $listings = $db->first(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'active') AS active,
                    SUM(status = 'sold') AS sold,
                    COALESCE(SUM(view_count), 0) AS views,
                    COALESCE(SUM(enquiry_count), 0) AS enquiries,
                    COALESCE(SUM(offer_count), 0) AS offers
             FROM listings WHERE user_id = :u AND deleted_at IS NULL",
            ['u' => $userId]
        ) ?? [];

        $sales = $db->first(
            "SELECT COUNT(*) AS orders,
                    COALESCE(SUM(final_amount), 0) AS revenue,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN final_amount ELSE 0 END), 0) AS completed_revenue,
                    SUM(status = 'completed') AS completed
             FROM orders WHERE seller_id = :u",
            ['u' => $userId]
        ) ?? [];

        $bidsReceived = (int) $db->scalar(
            'SELECT COUNT(*) FROM bids b INNER JOIN auctions a ON a.id = b.auction_id WHERE a.owner_id = :u',
            ['u' => $userId],
            0
        );

        $views = (int) ($listings['views'] ?? 0);
        $orders = (int) ($sales['orders'] ?? 0);

        return [
            'listings' => $listings,
            'sales' => $sales,
            'bids_received' => $bidsReceived,
            'conversion_rate' => $views > 0 ? dec(($orders / $views) * 100, 2) : '0.00',
            'recent_views' => (int) $db->scalar(
                'SELECT COUNT(*) FROM listing_views v INNER JOIN listings l ON l.id = v.listing_id
                 WHERE l.user_id = :u AND v.created_at >= :since',
                ['u' => $userId, 'since' => $since],
                0
            ),
            'top_listings' => $db->select(
                'SELECT id, title, slug, view_count, offer_count, enquiry_count, status
                 FROM listings WHERE user_id = :u AND deleted_at IS NULL
                 ORDER BY view_count DESC LIMIT 5',
                ['u' => $userId]
            ),
            'revenue_series' => self::userSeries($userId, 'seller', $days),
        ];
    }

    public static function buyerAnalytics(int $userId, int $days = 30): array
    {
        $db = Database::instance();

        $purchases = $db->first(
            "SELECT COUNT(*) AS orders,
                    COALESCE(SUM(final_amount), 0) AS spend,
                    SUM(status = 'completed') AS completed
             FROM orders WHERE buyer_id = :u",
            ['u' => $userId]
        ) ?? [];

        $bids = $db->first(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'won') AS won,
                    SUM(status IN ('winning','active')) AS active
             FROM bids WHERE user_id = :u",
            ['u' => $userId]
        ) ?? [];

        return [
            'purchases' => $purchases,
            'bids' => $bids,
            'requirements' => (int) $db->scalar(
                'SELECT COUNT(*) FROM wanted_requirements WHERE user_id = :u AND deleted_at IS NULL',
                ['u' => $userId],
                0
            ),
            'offers_made' => (int) $db->scalar('SELECT COUNT(*) FROM offers WHERE buyer_id = :u', ['u' => $userId], 0),
            'saved' => (int) $db->scalar('SELECT COUNT(*) FROM favorites WHERE user_id = :u', ['u' => $userId], 0),
            'spend_series' => self::userSeries($userId, 'buyer', $days),
            'top_materials' => $db->select(
                'SELECT m.name, COUNT(*) AS orders, COALESCE(SUM(o.final_amount), 0) AS value
                 FROM orders o
                 LEFT JOIN listings l ON l.id = o.listing_id
                 LEFT JOIN materials m ON m.id = l.material_id
                 WHERE o.buyer_id = :u AND m.id IS NOT NULL
                 GROUP BY m.id, m.name ORDER BY value DESC LIMIT 5',
                ['u' => $userId]
            ),
        ];
    }

    private static function userSeries(int $userId, string $role, int $days): array
    {
        $column = $role === 'seller' ? 'seller_id' : 'buyer_id';
        $rows = Database::instance()->select(
            "SELECT DATE(created_at) AS day, COALESCE(SUM(final_amount), 0) AS value
             FROM orders WHERE {$column} = :u AND created_at >= :since
             GROUP BY DATE(created_at)",
            ['u' => $userId, 'since' => gmdate('Y-m-d 00:00:00', strtotime("-{$days} days"))]
        );
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[(string) $row['day']] = (float) $row['value'];
        }
        $series = [];
        for ($i = $days; $i >= 0; $i--) {
            $day = gmdate('Y-m-d', strtotime("-{$i} days"));
            $series[] = ['day' => $day, 'label' => gmdate('d M', strtotime($day)), 'value' => $byDay[$day] ?? 0];
        }
        return $series;
    }

    /** Homepage counters. */
    public static function publicStats(): array
    {
        $db = Database::instance();
        return [
            'businesses' => (int) $db->scalar("SELECT COUNT(*) FROM businesses b INNER JOIN users u ON u.id = b.user_id WHERE b.deleted_at IS NULL AND u.status = 'active'", [], 0),
            'listings' => (int) $db->scalar("SELECT COUNT(*) FROM listings WHERE status = 'active' AND deleted_at IS NULL", [], 0),
            'live_auctions' => (int) $db->scalar("SELECT COUNT(*) FROM auctions WHERE status = 'live' AND deleted_at IS NULL", [], 0),
            'requirements' => (int) $db->scalar("SELECT COUNT(*) FROM wanted_requirements WHERE status = 'open' AND deleted_at IS NULL", [], 0),
            'cities' => (int) $db->scalar("SELECT COUNT(DISTINCT city_id) FROM listings WHERE status = 'active' AND city_id IS NOT NULL AND deleted_at IS NULL", [], 0),
            'materials' => (int) $db->scalar('SELECT COUNT(*) FROM materials WHERE is_active = 1', [], 0),
        ];
    }
}
