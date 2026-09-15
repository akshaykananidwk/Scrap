<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Paginator;

final class Auction extends Model
{
    protected static string $table = 'auctions';
    protected static bool $softDeletes = true;
    protected static array $fillable = [
        'reference', 'listing_id', 'requirement_id', 'owner_id', 'auction_type', 'title', 'description',
        'quantity', 'unit_id', 'price_basis', 'starting_price', 'reserve_price', 'bid_increment',
        'current_price', 'starts_at', 'ends_at', 'original_ends_at', 'extension_window_seconds',
        'extension_duration_seconds', 'max_extensions', 'max_bidders', 'requires_kyc', 'requires_approval',
        'deposit_required', 'deposit_amount', 'payment_terms', 'mask_bidders', 'status',
    ];

    public static function detail(int $id): ?array
    {
        return self::db()->first(self::detailSelect() . ' WHERE a.id = :id AND a.deleted_at IS NULL LIMIT 1', ['id' => $id]);
    }

    public static function findByReference(string $reference): ?array
    {
        return self::db()->first(
            self::detailSelect() . ' WHERE a.reference = :r AND a.deleted_at IS NULL LIMIT 1',
            ['r' => $reference]
        );
    }

    private static function detailSelect(): string
    {
        return 'SELECT a.*, un.code AS unit_code,
                       l.slug AS listing_slug, l.title AS listing_title, l.city_name, l.state_name,
                       l.material_id, l.category_id, l.description AS listing_description,
                       l.pickup_address, l.material_condition, l.gst_rate, l.inspection_available,
                       m.name AS material_name, m.slug AS material_slug,
                       c.name AS category_name, c.slug AS category_slug,
                       r.title AS requirement_title, r.slug AS requirement_slug,
                       u.full_name AS owner_name,
                       b.id AS business_id, b.name AS business_name, b.slug AS business_slug,
                       b.logo AS business_logo, b.kyc_verified, b.rating_avg, b.rating_count
                FROM auctions a
                INNER JOIN units un ON un.id = a.unit_id
                INNER JOIN users u ON u.id = a.owner_id
                LEFT JOIN listings l ON l.id = a.listing_id
                LEFT JOIN wanted_requirements r ON r.id = a.requirement_id
                LEFT JOIN materials m ON m.id = l.material_id
                LEFT JOIN categories c ON c.id = l.category_id
                LEFT JOIN businesses b ON b.user_id = a.owner_id AND b.deleted_at IS NULL';
    }

    public static function paginate(array $filters, int $page, int $perPage = 12): Paginator
    {
        $select = 'SELECT a.*, un.code AS unit_code, l.slug AS listing_slug, l.city_name, l.state_name,
                          m.name AS material_name, b.name AS business_name, b.slug AS business_slug, b.kyc_verified,
                          (SELECT file_path FROM listing_images li WHERE li.listing_id = a.listing_id
                            ORDER BY li.is_primary DESC, li.id LIMIT 1) AS image';
        $from = ' FROM auctions a
                  INNER JOIN units un ON un.id = a.unit_id
                  LEFT JOIN listings l ON l.id = a.listing_id
                  LEFT JOIN materials m ON m.id = l.material_id
                  LEFT JOIN businesses b ON b.user_id = a.owner_id AND b.deleted_at IS NULL
                  WHERE a.deleted_at IS NULL';
        $params = [];

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = [];
                foreach (array_values($filters['status']) as $i => $status) {
                    $placeholders[] = ':st' . $i;
                    $params['st' . $i] = $status;
                }
                $from .= ' AND a.status IN (' . implode(',', $placeholders) . ')';
            } else {
                $from .= ' AND a.status = :status';
                $params['status'] = $filters['status'];
            }
        }
        if (!empty($filters['auction_type'])) {
            $from .= ' AND a.auction_type = :auction_type';
            $params['auction_type'] = $filters['auction_type'];
        }
        if (!empty($filters['owner_id'])) {
            $from .= ' AND a.owner_id = :owner_id';
            $params['owner_id'] = (int) $filters['owner_id'];
        }
        if (!empty($filters['category_id'])) {
            $ids = Category::withDescendantIds((int) $filters['category_id']);
            $placeholders = [];
            foreach ($ids as $i => $id) {
                $placeholders[] = ':ac' . $i;
                $params['ac' . $i] = $id;
            }
            $from .= ' AND (l.category_id IN (' . implode(',', $placeholders) . ') OR l.subcategory_id IN (' . implode(',', $placeholders) . '))';
        }
        if (!empty($filters['material_id'])) {
            $from .= ' AND l.material_id = :material_id';
            $params['material_id'] = (int) $filters['material_id'];
        }
        if (!empty($filters['state_id'])) {
            $from .= ' AND l.state_id = :state_id';
            $params['state_id'] = (int) $filters['state_id'];
        }
        if (!empty($filters['q'])) {
            $from .= ' AND (a.title LIKE :q OR m.name LIKE :q2)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['bidder_id'])) {
            $from .= ' AND EXISTS (SELECT 1 FROM bids bd WHERE bd.auction_id = a.id AND bd.user_id = :bidder)';
            $params['bidder'] = (int) $filters['bidder_id'];
        }

        $order = match ($filters['sort'] ?? '') {
            'newest' => ' ORDER BY a.id DESC',
            'most_bids' => ' ORDER BY a.bid_count DESC, a.ends_at ASC',
            'price_high' => ' ORDER BY a.current_price DESC',
            'price_low' => ' ORDER BY a.current_price ASC',
            default => ' ORDER BY FIELD(a.status, "live", "scheduled", "ended", "awarded", "unsold", "cancelled"), a.ends_at ASC',
        };

        return self::paginateQuery($select . $from . $order, $params, $page, $perPage, 'SELECT COUNT(*)' . $from);
    }

    public static function live(int $limit = 6): array
    {
        return self::paginate(['status' => 'live'], 1, $limit)->items;
    }

    /** Public bid history; identities are masked when the seller enabled masking. */
    public static function bidHistory(int $auctionId, bool $mask = true, int $limit = 50): array
    {
        $rows = self::db()->select(
            'SELECT b.id, b.bid_uid, b.amount, b.placed_at, b.status, b.user_id, b.is_auto,
                    u.full_name, biz.name AS business_name,
                    ab.bidder_number
             FROM bids b
             INNER JOIN users u ON u.id = b.user_id
             LEFT JOIN businesses biz ON biz.user_id = b.user_id AND biz.deleted_at IS NULL
             LEFT JOIN auction_bidders ab ON ab.auction_id = b.auction_id AND ab.user_id = b.user_id
             WHERE b.auction_id = :a AND b.status <> "retracted"
             ORDER BY b.amount DESC, b.placed_at ASC
             LIMIT ' . max(1, $limit),
            ['a' => $auctionId]
        );

        foreach ($rows as &$row) {
            $row['display_name'] = $mask
                ? 'Bidder #' . str_pad((string) ($row['bidder_number'] ?: 1), 2, '0', STR_PAD_LEFT)
                : ($row['business_name'] ?: $row['full_name']);
        }
        return $rows;
    }

    public static function bidders(int $auctionId): array
    {
        return self::db()->select(
            'SELECT ab.*, u.full_name, u.mobile, u.kyc_status, b.name AS business_name
             FROM auction_bidders ab
             INNER JOIN users u ON u.id = ab.user_id
             LEFT JOIN businesses b ON b.user_id = u.id AND b.deleted_at IS NULL
             WHERE ab.auction_id = :a ORDER BY ab.bidder_number, ab.id',
            ['a' => $auctionId]
        );
    }

    public static function userPosition(int $auctionId, int $userId): array
    {
        $highest = self::db()->first(
            'SELECT MAX(amount) AS amount FROM bids WHERE auction_id = :a AND user_id = :u AND status <> "retracted"',
            ['a' => $auctionId, 'u' => $userId]
        );
        $userAmount = $highest['amount'] ?? null;
        if ($userAmount === null) {
            return ['has_bid' => false, 'amount' => null, 'rank' => null, 'is_winning' => false];
        }
        $auction = self::find($auctionId);
        $better = (int) self::db()->scalar(
            ($auction['auction_type'] ?? 'forward') === 'reverse'
                ? 'SELECT COUNT(DISTINCT user_id) FROM bids WHERE auction_id = :a AND amount < :amt AND status <> "retracted"'
                : 'SELECT COUNT(DISTINCT user_id) FROM bids WHERE auction_id = :a AND amount > :amt AND status <> "retracted"',
            ['a' => $auctionId, 'amt' => $userAmount],
            0
        );
        return [
            'has_bid' => true,
            'amount' => $userAmount,
            'rank' => $better + 1,
            'is_winning' => $better === 0,
        ];
    }

    public static function generateReference(): string
    {
        return 'AUC' . gmdate('ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    public static function counts(): array
    {
        $row = self::db()->first(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'live') AS live,
                    SUM(status = 'scheduled') AS scheduled,
                    SUM(status = 'ended') AS ended,
                    SUM(status = 'awarded') AS awarded,
                    SUM(status = 'unsold') AS unsold
             FROM auctions WHERE deleted_at IS NULL"
        ) ?? [];
        return array_map(static fn ($v) => (int) $v, $row);
    }

    public static function logEvent(int $auctionId, ?int $userId, string $type, string $details = '', ?string $ip = null): void
    {
        self::db()->insert('auction_events', [
            'auction_id' => $auctionId,
            'user_id' => $userId,
            'event_type' => $type,
            'details' => substr($details, 0, 500),
            'ip' => $ip,
            'created_at' => gmdate('Y-m-d H:i:s.v'),
        ]);
    }

    public static function events(int $auctionId, int $limit = 50): array
    {
        return self::db()->select(
            'SELECT * FROM auction_events WHERE auction_id = :a ORDER BY id DESC LIMIT ' . max(1, $limit),
            ['a' => $auctionId]
        );
    }
}
