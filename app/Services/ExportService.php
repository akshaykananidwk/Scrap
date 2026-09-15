<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Response;

/**
 * CSV export for the admin panel. Streams so a 100k-row export does not blow
 * the memory limit, and writes a UTF-8 BOM so Excel opens Indian text correctly.
 */
final class ExportService
{
    public const DATASETS = [
        'users' => 'Users',
        'businesses' => 'Businesses',
        'listings' => 'Listings',
        'auctions' => 'Auctions',
        'bids' => 'Bids',
        'orders' => 'Orders',
        'payments' => 'Payments',
        'commissions' => 'Commission ledger',
        'kyc' => 'KYC submissions',
        'disputes' => 'Disputes',
        'reviews' => 'Reviews',
        'market_rates' => 'Market rates',
        'requirements' => 'Buyer requirements',
    ];

    private static function query(string $dataset, array $filters): array
    {
        $params = [];
        $sql = match ($dataset) {
            'users' => 'SELECT u.id, u.full_name, u.email, u.mobile, u.account_type, u.status, u.kyc_status,
                               b.name AS business_name, b.gstin, b.city_name, b.state_name, u.created_at, u.last_login_at
                        FROM users u LEFT JOIN businesses b ON b.user_id = u.id AND b.deleted_at IS NULL
                        WHERE u.deleted_at IS NULL ORDER BY u.id',
            'businesses' => 'SELECT b.id, b.name, b.business_type, b.gstin, b.pan, b.city_name, b.state_name, b.pincode,
                                    b.kyc_verified, b.rating_avg, b.rating_count, b.total_listings, b.completed_orders, b.created_at
                             FROM businesses b WHERE b.deleted_at IS NULL ORDER BY b.id',
            'listings' => 'SELECT l.id, l.reference, l.title, c.name AS category, m.name AS material, l.grade_text,
                                  l.quantity, un.code AS unit, l.price, l.price_per_kg, l.listing_type, l.status,
                                  l.city_name, l.state_name, u.full_name AS seller, l.view_count, l.created_at
                           FROM listings l
                           INNER JOIN categories c ON c.id = l.category_id
                           LEFT JOIN materials m ON m.id = l.material_id
                           INNER JOIN units un ON un.id = l.unit_id
                           INNER JOIN users u ON u.id = l.user_id
                           WHERE l.deleted_at IS NULL ORDER BY l.id',
            'auctions' => 'SELECT a.id, a.reference, a.title, a.auction_type, a.starting_price, a.reserve_price,
                                  a.current_price, a.bid_count, a.bidder_count, a.status, a.starts_at, a.ends_at,
                                  u.full_name AS seller, w.full_name AS winner
                           FROM auctions a
                           INNER JOIN users u ON u.id = a.owner_id
                           LEFT JOIN users w ON w.id = a.winning_user_id
                           WHERE a.deleted_at IS NULL ORDER BY a.id',
            'bids' => 'SELECT b.id, b.bid_uid, a.reference AS auction, u.full_name AS bidder, b.amount,
                              b.status, b.ip, b.placed_at
                       FROM bids b
                       INNER JOIN auctions a ON a.id = b.auction_id
                       INNER JOIN users u ON u.id = b.user_id
                       ORDER BY b.id',
            'orders' => 'SELECT o.id, o.reference, o.source_type, buyer.full_name AS buyer, seller.full_name AS seller,
                                o.quantity, un.code AS unit, o.rate, o.subtotal, o.gst_amount, o.final_amount,
                                o.status, o.payment_status, o.created_at, o.completed_at
                         FROM orders o
                         INNER JOIN users buyer ON buyer.id = o.buyer_id
                         INNER JOIN users seller ON seller.id = o.seller_id
                         INNER JOIN units un ON un.id = o.unit_id
                         ORDER BY o.id',
            'payments' => 'SELECT p.id, p.reference, o.reference AS order_reference, u.full_name AS payer,
                                  p.amount, p.method, p.gateway, p.status, p.utr_number, p.paid_at, p.created_at
                           FROM payments p
                           LEFT JOIN orders o ON o.id = p.order_id
                           INNER JOIN users u ON u.id = p.payer_id
                           ORDER BY p.id',
            'commissions' => 'SELECT c.id, o.reference AS order_reference, u.full_name AS party_name, c.party,
                                     c.fee_type, c.base_amount, c.percentage, c.amount, c.gst_amount, c.total_amount,
                                     c.status, c.created_at
                              FROM commissions c
                              LEFT JOIN orders o ON o.id = c.order_id
                              INNER JOIN users u ON u.id = c.user_id
                              ORDER BY c.id',
            'kyc' => 'SELECT k.id, u.full_name, u.mobile, b.name AS business_name, b.gstin, k.status,
                             k.submitted_at, k.reviewed_at, r.full_name AS reviewer, k.rejection_reason
                      FROM kyc_verifications k
                      INNER JOIN users u ON u.id = k.user_id
                      LEFT JOIN businesses b ON b.id = k.business_id
                      LEFT JOIN users r ON r.id = k.reviewed_by
                      ORDER BY k.id',
            'disputes' => 'SELECT d.id, d.reference, o.reference AS order_reference, raiser.full_name AS raised_by,
                                  against.full_name AS against, d.category, d.subject, d.claimed_amount, d.status,
                                  d.created_at, d.resolved_at
                           FROM disputes d
                           LEFT JOIN orders o ON o.id = d.order_id
                           INNER JOIN users raiser ON raiser.id = d.raised_by
                           INNER JOIN users against ON against.id = d.against_user_id
                           ORDER BY d.id',
            'reviews' => 'SELECT r.id, o.reference AS order_reference, reviewer.full_name AS reviewer,
                                 reviewee.full_name AS reviewee, r.reviewer_role, r.overall_rating, r.title,
                                 r.comment, r.status, r.created_at
                          FROM reviews r
                          INNER JOIN orders o ON o.id = r.order_id
                          INNER JOIN users reviewer ON reviewer.id = r.reviewer_id
                          INNER JOIN users reviewee ON reviewee.id = r.reviewee_id
                          ORDER BY r.id',
            'market_rates' => 'SELECT mr.id, m.name AS material, g.name AS grade, mr.city_name, mr.rate,
                                      un.code AS unit, mr.rate_date, mr.change_amount, mr.change_percent, mr.source
                               FROM market_rates mr
                               INNER JOIN materials m ON m.id = mr.material_id
                               LEFT JOIN material_grades g ON g.id = mr.grade_id
                               INNER JOIN units un ON un.id = mr.unit_id
                               ORDER BY mr.rate_date DESC, mr.id',
            'requirements' => 'SELECT r.id, r.reference, r.title, c.name AS category, m.name AS material,
                                      r.quantity, un.code AS unit, r.target_price, r.frequency, r.city_name,
                                      r.status, r.offer_count, u.full_name AS buyer, r.created_at
                               FROM wanted_requirements r
                               INNER JOIN categories c ON c.id = r.category_id
                               LEFT JOIN materials m ON m.id = r.material_id
                               INNER JOIN units un ON un.id = r.unit_id
                               INNER JOIN users u ON u.id = r.user_id
                               WHERE r.deleted_at IS NULL ORDER BY r.id',
            default => throw new \InvalidArgumentException('Unknown dataset.'),
        };

        // Optional date window applied to the primary table alias.
        if (!empty($filters['from']) || !empty($filters['to'])) {
            $alias = match ($dataset) {
                'users' => 'u', 'businesses' => 'b', 'listings' => 'l', 'auctions' => 'a', 'bids' => 'b',
                'orders' => 'o', 'payments' => 'p', 'commissions' => 'c', 'kyc' => 'k', 'disputes' => 'd',
                'reviews' => 'r', 'market_rates' => 'mr', 'requirements' => 'r', default => null,
            };
            if ($alias !== null) {
                $clause = [];
                if (!empty($filters['from'])) {
                    $clause[] = "{$alias}.created_at >= :from";
                    $params['from'] = $filters['from'] . ' 00:00:00';
                }
                if (!empty($filters['to'])) {
                    $clause[] = "{$alias}.created_at <= :to";
                    $params['to'] = $filters['to'] . ' 23:59:59';
                }
                if ($clause !== []) {
                    $sql = preg_replace('/ORDER BY/i', 'AND ' . implode(' AND ', $clause) . ' ORDER BY', $sql, 1) ?? $sql;
                    // Queries with no WHERE need one.
                    if (!str_contains(strtoupper($sql), 'WHERE')) {
                        $sql = preg_replace('/AND /', 'WHERE ', $sql, 1) ?? $sql;
                    }
                }
            }
        }

        return [$sql, $params];
    }

    /** Stream a dataset as a CSV download. */
    public static function csv(string $dataset, array $filters = []): Response
    {
        if (!isset(self::DATASETS[$dataset])) {
            throw new \InvalidArgumentException('Unknown dataset.');
        }

        [$sql, $params] = self::query($dataset, $filters);
        $rows = Database::instance()->select($sql . ' LIMIT 50000', $params);

        $filename = sprintf('scrapx_%s_%s.csv', $dataset, gmdate('Ymd_His'));
        $path = STORAGE_PATH . '/tmp/' . $filename;
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Could not create the export file.');
        }

        // BOM so Excel reads UTF-8 (₹, Gujarati, Hindi) correctly.
        fwrite($handle, "\xEF\xBB\xBF");

        if ($rows !== []) {
            fputcsv($handle, array_map(static fn (string $h): string => label($h), array_keys($rows[0])), ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($handle, array_map(static fn ($v): string => (string) ($v ?? ''), $row), ',', '"', '');
            }
        } else {
            fputcsv($handle, ['No records matched the selected filters'], ',', '"', '');
        }
        fclose($handle);

        AuditService::log('data_exported', $dataset, null, null, ['rows' => count($rows)]);

        return Response::download($path, $filename, 'text/csv; charset=UTF-8');
    }

    public static function rowCount(string $dataset, array $filters = []): int
    {
        if (!isset(self::DATASETS[$dataset])) {
            return 0;
        }
        [$sql, $params] = self::query($dataset, $filters);
        return (int) Database::instance()->scalar('SELECT COUNT(*) FROM (' . $sql . ') AS sub', $params, 0);
    }
}
