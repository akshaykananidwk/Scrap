<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Paginator;

final class Rfq extends Model
{
    protected static string $table = 'rfqs';
    protected static bool $softDeletes = true;
    protected static array $fillable = [
        'reference', 'buyer_id', 'business_id', 'title', 'description', 'category_id', 'delivery_address',
        'city_id', 'state_id', 'city_name', 'payment_terms', 'delivery_required_by', 'visibility',
        'status', 'closes_at',
    ];

    public const STATUSES = [
        'draft' => 'Draft', 'open' => 'Open', 'closing_soon' => 'Closing Soon',
        'closed' => 'Closed', 'awarded' => 'Awarded', 'cancelled' => 'Cancelled',
    ];

    public static function detail(int $id): ?array
    {
        return self::db()->first(
            'SELECT r.*, c.name AS category_name, u.full_name AS buyer_name, u.mobile AS buyer_mobile,
                    b.name AS business_name, b.slug AS business_slug, b.logo AS business_logo, b.kyc_verified,
                    b.city_name AS business_city
             FROM rfqs r
             LEFT JOIN categories c ON c.id = r.category_id
             INNER JOIN users u ON u.id = r.buyer_id
             LEFT JOIN businesses b ON b.id = r.business_id
             WHERE r.id = :id AND r.deleted_at IS NULL LIMIT 1',
            ['id' => $id]
        );
    }

    public static function items(int $rfqId): array
    {
        return self::db()->select(
            'SELECT i.*, un.code AS unit_code, m.name AS material_name, g.name AS grade_name
             FROM rfq_items i
             INNER JOIN units un ON un.id = i.unit_id
             LEFT JOIN materials m ON m.id = i.material_id
             LEFT JOIN material_grades g ON g.id = i.grade_id
             WHERE i.rfq_id = :r ORDER BY i.sort_order, i.id',
            ['r' => $rfqId]
        );
    }

    public static function quotes(int $rfqId): array
    {
        return self::db()->select(
            'SELECT q.*, u.full_name AS seller_name, b.name AS business_name, b.slug AS business_slug,
                    b.kyc_verified, b.rating_avg, b.rating_count, b.city_name
             FROM rfq_quotes q
             INNER JOIN users u ON u.id = q.seller_id
             LEFT JOIN businesses b ON b.id = q.business_id
             WHERE q.rfq_id = :r ORDER BY q.grand_total ASC, q.id',
            ['r' => $rfqId]
        );
    }

    public static function quoteItems(int $quoteId): array
    {
        return self::db()->select(
            'SELECT qi.*, ri.item_name, ri.quantity AS required_quantity, un.code AS unit_code
             FROM rfq_quote_items qi
             INNER JOIN rfq_items ri ON ri.id = qi.rfq_item_id
             INNER JOIN units un ON un.id = ri.unit_id
             WHERE qi.quote_id = :q ORDER BY qi.id',
            ['q' => $quoteId]
        );
    }

    public static function invites(int $rfqId): array
    {
        return self::db()->select(
            'SELECT i.*, u.full_name, b.name AS business_name, b.slug AS business_slug
             FROM rfq_invites i
             INNER JOIN users u ON u.id = i.seller_id
             LEFT JOIN businesses b ON b.user_id = i.seller_id AND b.deleted_at IS NULL
             WHERE i.rfq_id = :r ORDER BY i.id',
            ['r' => $rfqId]
        );
    }

    public static function paginate(array $filters, int $page, int $perPage = 20): Paginator
    {
        $select = 'SELECT r.*, c.name AS category_name, b.name AS business_name, b.slug AS business_slug,
                          b.kyc_verified,
                          (SELECT COUNT(*) FROM rfq_items ri WHERE ri.rfq_id = r.id) AS item_count';
        $from = ' FROM rfqs r
                  LEFT JOIN categories c ON c.id = r.category_id
                  LEFT JOIN businesses b ON b.id = r.business_id
                  WHERE r.deleted_at IS NULL';
        $params = [];

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $ph = [];
                foreach (array_values($filters['status']) as $i => $s) {
                    $ph[] = ':qs' . $i;
                    $params['qs' . $i] = $s;
                }
                $from .= ' AND r.status IN (' . implode(',', $ph) . ')';
            } else {
                $from .= ' AND r.status = :status';
                $params['status'] = $filters['status'];
            }
        }
        if (!empty($filters['buyer_id'])) {
            $from .= ' AND r.buyer_id = :buyer_id';
            $params['buyer_id'] = (int) $filters['buyer_id'];
        }
        if (!empty($filters['category_id'])) {
            $from .= ' AND r.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['q'])) {
            $from .= ' AND (r.title LIKE :q OR r.reference LIKE :q2)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['public_only'])) {
            $from .= " AND r.visibility = 'public'";
        }
        if (!empty($filters['invited_seller_id'])) {
            $from .= ' AND (r.visibility = "public" OR EXISTS (SELECT 1 FROM rfq_invites i WHERE i.rfq_id = r.id AND i.seller_id = :inv))';
            $params['inv'] = (int) $filters['invited_seller_id'];
        }
        if (!empty($filters['quoted_by'])) {
            $from .= ' AND EXISTS (SELECT 1 FROM rfq_quotes q WHERE q.rfq_id = r.id AND q.seller_id = :qb)';
            $params['qb'] = (int) $filters['quoted_by'];
        }

        return self::paginateQuery(
            $select . $from . ' ORDER BY r.id DESC',
            $params,
            $page,
            $perPage,
            'SELECT COUNT(*)' . $from
        );
    }

    public static function generateReference(): string
    {
        return 'RFQ' . gmdate('ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    public static function counts(): array
    {
        $row = self::db()->first(
            "SELECT COUNT(*) AS total, SUM(status = 'open') AS open, SUM(status = 'awarded') AS awarded
             FROM rfqs WHERE deleted_at IS NULL"
        ) ?? [];
        return array_map(static fn ($v) => (int) $v, $row);
    }
}
