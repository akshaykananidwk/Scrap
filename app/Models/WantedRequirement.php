<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Paginator;

final class WantedRequirement extends Model
{
    protected static string $table = 'wanted_requirements';
    protected static bool $softDeletes = true;
    protected static array $fillable = [
        'reference', 'user_id', 'business_id', 'title', 'slug', 'category_id', 'material_id', 'grade_id',
        'grade_text', 'description', 'quantity', 'unit_id', 'min_quantity', 'max_quantity', 'target_price',
        'price_basis', 'frequency', 'delivery_required', 'delivery_address', 'city_id', 'state_id',
        'city_name', 'state_name', 'pincode', 'required_by', 'payment_terms', 'status', 'expires_at',
    ];

    public const FREQUENCIES = [
        'one_time' => 'One time', 'daily' => 'Daily', 'weekly' => 'Weekly',
        'fortnightly' => 'Fortnightly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly',
    ];

    public const BASIS = [
        'per_kg' => 'Per KG', 'per_mt' => 'Per MT', 'per_unit' => 'Per unit', 'lot' => 'Lot price',
    ];

    public static function detail(int $id): ?array
    {
        return self::db()->first(self::selectSql() . ' WHERE r.id = :id AND r.deleted_at IS NULL LIMIT 1', ['id' => $id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return self::db()->first(self::selectSql() . ' WHERE r.slug = :s AND r.deleted_at IS NULL LIMIT 1', ['s' => $slug]);
    }

    private static function selectSql(): string
    {
        return 'SELECT r.*, un.code AS unit_code, c.name AS category_name, c.slug AS category_slug,
                       m.name AS material_name, m.slug AS material_slug, g.name AS grade_name,
                       u.full_name AS buyer_name, u.created_at AS buyer_since,
                       b.name AS business_name, b.slug AS business_slug, b.logo AS business_logo,
                       b.kyc_verified, b.rating_avg, b.rating_count
                FROM wanted_requirements r
                INNER JOIN units un ON un.id = r.unit_id
                INNER JOIN categories c ON c.id = r.category_id
                LEFT JOIN materials m ON m.id = r.material_id
                LEFT JOIN material_grades g ON g.id = r.grade_id
                INNER JOIN users u ON u.id = r.user_id
                LEFT JOIN businesses b ON b.id = r.business_id';
    }

    public static function paginate(array $filters, int $page, int $perPage = 20): Paginator
    {
        $select = 'SELECT r.*, un.code AS unit_code, c.name AS category_name, m.name AS material_name,
                          b.name AS business_name, b.slug AS business_slug, b.kyc_verified';
        $from = ' FROM wanted_requirements r
                  INNER JOIN units un ON un.id = r.unit_id
                  INNER JOIN categories c ON c.id = r.category_id
                  LEFT JOIN materials m ON m.id = r.material_id
                  LEFT JOIN businesses b ON b.id = r.business_id
                  WHERE r.deleted_at IS NULL';
        $params = [];

        $status = $filters['status'] ?? 'open';
        if ($status !== 'any') {
            $from .= ' AND r.status = :status';
            $params['status'] = $status;
        }
        if (!empty($filters['q'])) {
            $from .= ' AND (r.title LIKE :q OR r.description LIKE :q2 OR m.name LIKE :q3)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
            $params['q3'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['category_id'])) {
            $ids = Category::withDescendantIds((int) $filters['category_id']);
            $ph = [];
            foreach ($ids as $i => $id) {
                $ph[] = ':rc' . $i;
                $params['rc' . $i] = $id;
            }
            $from .= ' AND r.category_id IN (' . implode(',', $ph) . ')';
        }
        if (!empty($filters['material_id'])) {
            $from .= ' AND r.material_id = :material_id';
            $params['material_id'] = (int) $filters['material_id'];
        }
        if (!empty($filters['state_id'])) {
            $from .= ' AND r.state_id = :state_id';
            $params['state_id'] = (int) $filters['state_id'];
        }
        if (!empty($filters['city_id'])) {
            $from .= ' AND r.city_id = :city_id';
            $params['city_id'] = (int) $filters['city_id'];
        }
        if (!empty($filters['frequency'])) {
            $from .= ' AND r.frequency = :frequency';
            $params['frequency'] = $filters['frequency'];
        }
        if (!empty($filters['user_id'])) {
            $from .= ' AND r.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        $order = match ($filters['sort'] ?? '') {
            'quantity' => ' ORDER BY r.quantity DESC',
            'oldest' => ' ORDER BY r.id ASC',
            'required_by' => ' ORDER BY (r.required_by IS NULL), r.required_by ASC',
            default => ' ORDER BY r.id DESC',
        };

        return self::paginateQuery($select . $from . $order, $params, $page, $perPage, 'SELECT COUNT(*)' . $from);
    }

    public static function documents(int $requirementId): array
    {
        return self::db()->select('SELECT * FROM requirement_documents WHERE requirement_id = :r ORDER BY id', ['r' => $requirementId]);
    }

    public static function offers(int $requirementId): array
    {
        return self::db()->select(
            'SELECT o.*, u.full_name AS seller_name, b.name AS business_name, b.slug AS business_slug,
                    b.kyc_verified, b.rating_avg, b.city_name, l.title AS listing_title, l.slug AS listing_slug
             FROM requirement_offers o
             INNER JOIN users u ON u.id = o.seller_id
             LEFT JOIN businesses b ON b.user_id = o.seller_id AND b.deleted_at IS NULL
             LEFT JOIN listings l ON l.id = o.listing_id
             WHERE o.requirement_id = :r ORDER BY o.offered_price ASC, o.id',
            ['r' => $requirementId]
        );
    }

    public static function generateReference(): string
    {
        return 'REQ' . gmdate('ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    public static function uniqueSlug(string $title): string
    {
        return slugify($title, 180) . '-' . strtolower(str_random(6));
    }

    /** Requirements a given seller is likely able to supply (same material or category). */
    public static function matchingForSeller(int $sellerId, int $limit = 10): array
    {
        return self::db()->select(
            'SELECT DISTINCT r.*, un.code AS unit_code, m.name AS material_name, c.name AS category_name
             FROM wanted_requirements r
             INNER JOIN units un ON un.id = r.unit_id
             INNER JOIN categories c ON c.id = r.category_id
             LEFT JOIN materials m ON m.id = r.material_id
             WHERE r.status = "open" AND r.deleted_at IS NULL AND r.user_id <> :seller
               AND (r.material_id IN (SELECT DISTINCT material_id FROM listings WHERE user_id = :seller2 AND material_id IS NOT NULL)
                    OR r.category_id IN (SELECT DISTINCT category_id FROM listings WHERE user_id = :seller3))
               AND NOT EXISTS (SELECT 1 FROM requirement_offers ro WHERE ro.requirement_id = r.id AND ro.seller_id = :seller4)
             ORDER BY r.id DESC LIMIT ' . max(1, $limit),
            ['seller' => $sellerId, 'seller2' => $sellerId, 'seller3' => $sellerId, 'seller4' => $sellerId]
        );
    }

    public static function counts(): array
    {
        $row = self::db()->first(
            "SELECT COUNT(*) AS total, SUM(status = 'open') AS open, SUM(status = 'fulfilled') AS fulfilled,
                    SUM(created_at >= :today) AS today
             FROM wanted_requirements WHERE deleted_at IS NULL",
            ['today' => gmdate('Y-m-d 00:00:00')]
        ) ?? [];
        return array_map(static fn ($v) => (int) $v, $row);
    }
}
