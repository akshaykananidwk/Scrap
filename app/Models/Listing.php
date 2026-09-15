<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Paginator;

final class Listing extends Model
{
    protected static string $table = 'listings';
    protected static bool $softDeletes = true;
    protected static array $fillable = [
        'reference', 'user_id', 'business_id', 'title', 'slug', 'category_id', 'subcategory_id',
        'material_id', 'grade_id', 'grade_text', 'description', 'listing_type', 'quantity', 'unit_id',
        'min_order_quantity', 'estimated_weight_kg', 'actual_weight_kg', 'price', 'price_per_kg',
        'price_per_mt', 'total_price', 'is_negotiable', 'show_price', 'gst_applicable', 'gst_rate',
        'hsn_code', 'material_condition', 'material_source', 'pickup_address', 'city_id', 'state_id',
        'city_name', 'state_name', 'pincode', 'latitude', 'longitude', 'delivery_available',
        'pickup_available', 'loading_by', 'transport_by', 'payment_terms', 'inspection_available',
        'inspection_notes', 'status', 'meta_title', 'meta_description', 'published_at', 'expires_at',
    ];

    public const TYPES = [
        'fixed' => 'Fixed Price',
        'negotiable' => 'Negotiable',
        'auction' => 'Auction',
        'make_offer' => 'Make an Offer',
        'tender' => 'Tender',
        'rfq' => 'RFQ',
        'wanted' => 'Wanted',
    ];

    public const CONDITIONS = [
        'loose' => 'Loose', 'baled' => 'Baled', 'bundled' => 'Bundled', 'sorted' => 'Sorted',
        'unsorted' => 'Unsorted', 'shredded' => 'Shredded', 'processed' => 'Processed', 'as_is' => 'As Is',
    ];

    public const SOURCES = [
        'industrial' => 'Industrial', 'commercial' => 'Commercial', 'domestic' => 'Domestic',
        'demolition' => 'Demolition', 'manufacturing' => 'Manufacturing', 'import' => 'Import', 'other' => 'Other',
    ];

    public const PAYMENT_TERMS = [
        'advance' => 'Full advance',
        'partial_advance' => 'Partial advance',
        'on_delivery' => 'Payment on delivery',
        'after_weighment' => 'After weighment',
        'credit_7' => '7 days credit',
        'credit_15' => '15 days credit',
        'credit_30' => '30 days credit',
        'negotiable' => 'Negotiable',
    ];

    public const RESPONSIBILITY = [
        'seller' => 'Seller', 'buyer' => 'Buyer', 'shared' => 'Shared', 'not_applicable' => 'Not applicable',
    ];

    public static function findBySlug(string $slug): ?array
    {
        return self::db()->first(
            self::detailSelect() . ' WHERE l.slug = :s AND l.deleted_at IS NULL LIMIT 1',
            ['s' => $slug]
        );
    }

    public static function findDetail(int $id): ?array
    {
        return self::db()->first(
            self::detailSelect() . ' WHERE l.id = :id AND l.deleted_at IS NULL LIMIT 1',
            ['id' => $id]
        );
    }

    private static function detailSelect(): string
    {
        return 'SELECT l.*, c.name AS category_name, c.slug AS category_slug,
                       sc.name AS subcategory_name, sc.slug AS subcategory_slug,
                       m.name AS material_name, m.slug AS material_slug,
                       g.name AS grade_name, un.code AS unit_code, un.name AS unit_name,
                       b.name AS business_name, b.slug AS business_slug, b.logo AS business_logo,
                       b.kyc_verified, b.gst_verified, b.rating_avg, b.rating_count,
                       b.response_rate, b.avg_response_minutes, b.business_type,
                       u.full_name AS seller_name, u.mobile AS seller_mobile, u.email AS seller_email,
                       u.created_at AS seller_since, u.status AS seller_status,
                       a.id AS auction_id, a.status AS auction_status, a.ends_at AS auction_ends_at,
                       a.current_price AS auction_current_price, a.bid_count AS auction_bid_count,
                       a.starting_price AS auction_starting_price, a.bid_increment AS auction_increment,
                       a.reference AS auction_reference
                FROM listings l
                INNER JOIN categories c ON c.id = l.category_id
                LEFT JOIN categories sc ON sc.id = l.subcategory_id
                LEFT JOIN materials m ON m.id = l.material_id
                LEFT JOIN material_grades g ON g.id = l.grade_id
                INNER JOIN units un ON un.id = l.unit_id
                INNER JOIN users u ON u.id = l.user_id
                LEFT JOIN businesses b ON b.id = l.business_id
                LEFT JOIN auctions a ON a.listing_id = l.id AND a.deleted_at IS NULL';
    }

    public static function images(int $listingId): array
    {
        return self::db()->select(
            'SELECT * FROM listing_images WHERE listing_id = :l ORDER BY is_primary DESC, sort_order, id',
            ['l' => $listingId]
        );
    }

    public static function primaryImage(int $listingId): ?string
    {
        $row = self::db()->first(
            'SELECT file_path FROM listing_images WHERE listing_id = :l ORDER BY is_primary DESC, sort_order, id LIMIT 1',
            ['l' => $listingId]
        );
        return $row['file_path'] ?? null;
    }

    public static function videos(int $listingId): array
    {
        return self::db()->select('SELECT * FROM listing_videos WHERE listing_id = :l ORDER BY id', ['l' => $listingId]);
    }

    public static function documents(int $listingId): array
    {
        return self::db()->select('SELECT * FROM listing_documents WHERE listing_id = :l ORDER BY id', ['l' => $listingId]);
    }

    /**
     * Marketplace search. Every filter is bound; sort keys are whitelisted.
     * FULLTEXT is used when a keyword is supplied, LIKE as the relevance fallback.
     */
    public static function search(array $filters, int $page, int $perPage = 20): Paginator
    {
        $select = 'SELECT l.*, un.code AS unit_code, m.name AS material_name, m.slug AS material_slug,
                          c.name AS category_name, c.slug AS category_slug,
                          b.name AS business_name, b.slug AS business_slug, b.kyc_verified, b.rating_avg,
                          (SELECT file_path FROM listing_images li WHERE li.listing_id = l.id
                            ORDER BY li.is_primary DESC, li.sort_order, li.id LIMIT 1) AS image,
                          a.id AS auction_id, a.ends_at AS auction_ends_at, a.current_price AS auction_current_price,
                          a.bid_count AS auction_bid_count, a.status AS auction_status';
        $from = ' FROM listings l
                  INNER JOIN units un ON un.id = l.unit_id
                  INNER JOIN categories c ON c.id = l.category_id
                  LEFT JOIN materials m ON m.id = l.material_id
                  LEFT JOIN businesses b ON b.id = l.business_id
                  LEFT JOIN auctions a ON a.listing_id = l.id AND a.deleted_at IS NULL
                  WHERE l.deleted_at IS NULL';
        $params = [];

        $status = $filters['status'] ?? 'active';
        if ($status !== 'any') {
            $from .= ' AND l.status = :status';
            $params['status'] = $status;
        }

        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            // Boolean-mode FULLTEXT on the indexed columns, plus a LIKE net for
            // seller/material names that live in joined tables.
            $from .= ' AND (MATCH(l.title, l.description, l.grade_text) AGAINST (:ft IN BOOLEAN MODE)
                            OR l.title LIKE :like OR m.name LIKE :like2 OR c.name LIKE :like3 OR b.name LIKE :like4)';
            $params['ft'] = self::booleanTerm($term);
            $params['like'] = '%' . $term . '%';
            $params['like2'] = '%' . $term . '%';
            $params['like3'] = '%' . $term . '%';
            $params['like4'] = '%' . $term . '%';
        }

        if (!empty($filters['category_id'])) {
            $ids = Category::withDescendantIds((int) $filters['category_id']);
            // The id list is needed twice (category and subcategory). Native
            // prepared statements bind one value per placeholder occurrence, so
            // each side gets its own set rather than reusing :cat0, :cat1, …
            $categoryPlaceholders = [];
            $subcategoryPlaceholders = [];
            foreach ($ids as $i => $id) {
                $categoryPlaceholders[] = ':cat' . $i;
                $subcategoryPlaceholders[] = ':subcat' . $i;
                $params['cat' . $i] = $id;
                $params['subcat' . $i] = $id;
            }
            $from .= ' AND (l.category_id IN (' . implode(',', $categoryPlaceholders) . ')'
                . ' OR l.subcategory_id IN (' . implode(',', $subcategoryPlaceholders) . '))';
        }
        if (!empty($filters['material_id'])) {
            $from .= ' AND l.material_id = :material_id';
            $params['material_id'] = (int) $filters['material_id'];
        }
        if (!empty($filters['grade_id'])) {
            $from .= ' AND l.grade_id = :grade_id';
            $params['grade_id'] = (int) $filters['grade_id'];
        }
        if (!empty($filters['listing_type'])) {
            $from .= ' AND l.listing_type = :listing_type';
            $params['listing_type'] = $filters['listing_type'];
        }
        if (!empty($filters['city_id'])) {
            $from .= ' AND l.city_id = :city_id';
            $params['city_id'] = (int) $filters['city_id'];
        }
        if (!empty($filters['state_id'])) {
            $from .= ' AND l.state_id = :state_id';
            $params['state_id'] = (int) $filters['state_id'];
        }
        if (!empty($filters['user_id'])) {
            $from .= ' AND l.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['business_id'])) {
            $from .= ' AND l.business_id = :business_id';
            $params['business_id'] = (int) $filters['business_id'];
        }
        if (!empty($filters['verified_only'])) {
            $from .= ' AND b.kyc_verified = 1';
        }
        if (!empty($filters['condition'])) {
            $from .= ' AND l.material_condition = :condition';
            $params['condition'] = $filters['condition'];
        }
        if (!empty($filters['min_price'])) {
            $from .= ' AND l.price_per_kg >= :min_price';
            $params['min_price'] = dec($filters['min_price'], 4);
        }
        if (!empty($filters['max_price'])) {
            $from .= ' AND l.price_per_kg <= :max_price';
            $params['max_price'] = dec($filters['max_price'], 4);
        }
        if (!empty($filters['min_quantity'])) {
            $from .= ' AND l.quantity >= :min_quantity';
            $params['min_quantity'] = dec($filters['min_quantity'], 3);
        }
        if (!empty($filters['max_quantity'])) {
            $from .= ' AND l.quantity <= :max_quantity';
            $params['max_quantity'] = dec($filters['max_quantity'], 3);
        }
        if (!empty($filters['posted_within_days'])) {
            $from .= ' AND l.published_at >= :posted_since';
            $params['posted_since'] = gmdate('Y-m-d H:i:s', strtotime('-' . (int) $filters['posted_within_days'] . ' days'));
        }
        if (!empty($filters['delivery_available'])) {
            $from .= ' AND l.delivery_available = 1';
        }
        if (!empty($filters['ending_soon'])) {
            $from .= " AND a.status = 'live' AND a.ends_at <= :ending_soon";
            $params['ending_soon'] = gmdate('Y-m-d H:i:s', strtotime('+24 hours'));
        }
        if (!empty($filters['pincode_prefix'])) {
            $from .= ' AND l.pincode LIKE :pin';
            $params['pin'] = substr((string) $filters['pincode_prefix'], 0, 3) . '%';
        }

        $order = match ($filters['sort'] ?? '') {
            'price_low' => ' ORDER BY l.is_featured DESC, l.price_per_kg ASC, l.id DESC',
            'price_high' => ' ORDER BY l.is_featured DESC, l.price_per_kg DESC, l.id DESC',
            'quantity' => ' ORDER BY l.is_featured DESC, l.estimated_weight_kg DESC, l.id DESC',
            'ending_soon' => ' ORDER BY (a.ends_at IS NULL), a.ends_at ASC, l.id DESC',
            'most_bids' => ' ORDER BY a.bid_count DESC, l.id DESC',
            'most_viewed' => ' ORDER BY l.view_count DESC, l.id DESC',
            'oldest' => ' ORDER BY l.published_at ASC, l.id ASC',
            default => ' ORDER BY l.is_featured DESC, FIELD(l.promotion_tier, "sponsored", "premium", "featured", "none"), COALESCE(l.published_at, l.created_at) DESC, l.id DESC',
        };

        $countSql = 'SELECT COUNT(*)' . $from;
        return self::paginateQuery($select . $from . $order, $params, $page, $perPage, $countSql);
    }

    /** Escape a user phrase for BOOLEAN MODE and require each word. */
    private static function booleanTerm(string $term): string
    {
        $words = preg_split('/\s+/', preg_replace('/[+\-><()~*"@]+/', ' ', $term) ?? '') ?: [];
        $parts = [];
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word) < 2) {
                continue;
            }
            $parts[] = '+' . $word . '*';
        }
        return $parts === [] ? $term : implode(' ', $parts);
    }

    public static function latest(int $limit = 8): array
    {
        return self::search(['sort' => 'newest'], 1, $limit)->items;
    }

    public static function featured(int $limit = 6): array
    {
        return self::search(['sort' => 'newest', 'featured' => 1], 1, $limit)->items;
    }

    public static function forUser(int $userId, array $filters, int $page, int $perPage = 20): Paginator
    {
        $filters['user_id'] = $userId;
        $filters['status'] = $filters['status'] ?? 'any';
        return self::search($filters, $page, $perPage);
    }

    public static function uniqueSlug(string $title): string
    {
        return slugify($title, 180) . '-' . strtolower(str_random(6));
    }

    public static function generateReference(): string
    {
        return 'LST' . gmdate('ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    /** One view per IP per day, counted without inflating the hot path. */
    public static function recordView(int $listingId, ?int $userId, string $ip): void
    {
        $hash = hash('sha256', $ip . '|' . $listingId);
        try {
            $inserted = self::db()->statement(
                'INSERT IGNORE INTO listing_views (listing_id, user_id, ip_hash, viewed_on, created_at)
                 VALUES (:l, :u, :h, :d, :c)',
                ['l' => $listingId, 'u' => $userId, 'h' => $hash, 'd' => gmdate('Y-m-d'), 'c' => now()]
            );
            if ($inserted > 0) {
                self::db()->statement('UPDATE listings SET view_count = view_count + 1 WHERE id = :l', ['l' => $listingId]);
            }
        } catch (\Throwable) {
            // View counting is best-effort.
        }
    }

    public static function related(array $listing, int $limit = 4): array
    {
        return self::db()->select(
            'SELECT l.*, un.code AS unit_code,
                    (SELECT file_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.is_primary DESC, li.id LIMIT 1) AS image
             FROM listings l
             INNER JOIN units un ON un.id = l.unit_id
             WHERE l.status = "active" AND l.deleted_at IS NULL AND l.id <> :id
               AND (l.material_id = :material OR l.category_id = :category)
             ORDER BY (l.material_id = :material2) DESC, l.published_at DESC
             LIMIT ' . max(1, $limit),
            [
                'id' => (int) $listing['id'],
                'material' => $listing['material_id'] !== null ? (int) $listing['material_id'] : 0,
                'material2' => $listing['material_id'] !== null ? (int) $listing['material_id'] : 0,
                'category' => (int) $listing['category_id'],
            ]
        );
    }

    public static function counts(): array
    {
        $row = self::db()->first(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'active') AS active,
                    SUM(status = 'pending') AS pending,
                    SUM(status = 'sold') AS sold,
                    SUM(status = 'expired') AS expired,
                    SUM(listing_type = 'auction') AS auctions,
                    SUM(created_at >= :today) AS today
             FROM listings WHERE deleted_at IS NULL",
            ['today' => gmdate('Y-m-d 00:00:00')]
        ) ?? [];
        return array_map(static fn ($v) => (int) $v, $row);
    }

    /** Derived price fields kept consistent no matter which one the seller typed. */
    public static function computePricing(array $input, int $unitId): array
    {
        $price = $input['price'] !== null && $input['price'] !== '' ? dec($input['price'], 2) : null;
        $basis = $input['price_basis'] ?? 'per_mt';
        $quantity = (float) dec($input['quantity'] ?? 0, 3);
        $weightKg = Unit::toKg($quantity, $unitId);

        $perKg = null;
        if ($price !== null) {
            $perKg = match ($basis) {
                'per_kg' => dec($price, 4),
                'per_mt' => dec((float) $price / 1000, 4),
                'per_unit' => $weightKg !== null && (float) $weightKg > 0 && $quantity > 0
                    ? dec(((float) $price * $quantity) / (float) $weightKg, 4)
                    : null,
                'lot' => $weightKg !== null && (float) $weightKg > 0 ? dec((float) $price / (float) $weightKg, 4) : null,
                default => dec((float) $price / 1000, 4),
            };
        }

        $total = null;
        if ($price !== null) {
            $total = match ($basis) {
                'per_kg' => $weightKg !== null ? dec((float) $weightKg * (float) $price, 2) : null,
                'per_mt' => $weightKg !== null ? dec(((float) $weightKg / 1000) * (float) $price, 2) : null,
                'per_unit' => dec($quantity * (float) $price, 2),
                'lot' => dec($price, 2),
                default => null,
            };
        }

        return [
            'price' => $price,
            'price_per_kg' => $perKg,
            'price_per_mt' => $perKg !== null ? dec((float) $perKg * 1000, 2) : null,
            'total_price' => $total,
            'estimated_weight_kg' => $weightKg,
        ];
    }
}
