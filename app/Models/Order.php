<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Paginator;

final class Order extends Model
{
    protected static string $table = 'orders';
    protected static array $fillable = [
        'reference', 'buyer_id', 'seller_id', 'buyer_business_id', 'seller_business_id', 'source_type',
        'listing_id', 'auction_id', 'offer_id', 'rfq_id', 'requirement_id', 'quantity', 'unit_id',
        'rate', 'price_basis', 'subtotal', 'gst_rate', 'gst_amount', 'transport_charges',
        'loading_charges', 'other_charges', 'discount', 'total_amount', 'final_amount',
        'loading_by', 'transport_by', 'payment_terms', 'pickup_address', 'delivery_address',
        'delivery_city', 'delivery_state', 'delivery_pincode', 'expected_pickup_date',
        'expected_delivery_date', 'status', 'notes',
    ];

    /** status => [label, next statuses allowed] */
    public const FLOW = [
        'pending' => ['Pending', ['confirmed', 'cancelled']],
        'confirmed' => ['Confirmed', ['processing', 'ready_for_pickup', 'cancelled']],
        'processing' => ['Processing', ['ready_for_pickup', 'cancelled']],
        'ready_for_pickup' => ['Ready for Pickup', ['picked_up', 'cancelled']],
        'picked_up' => ['Picked Up', ['in_transit', 'weighment', 'delivered']],
        'in_transit' => ['In Transit', ['delivered', 'disputed']],
        'delivered' => ['Delivered', ['weighment', 'payment_pending', 'disputed']],
        'weighment' => ['Weighment', ['payment_pending', 'disputed']],
        'payment_pending' => ['Payment Pending', ['paid', 'disputed']],
        'paid' => ['Paid', ['completed', 'disputed']],
        'completed' => ['Completed', []],
        'cancelled' => ['Cancelled', []],
        'disputed' => ['Disputed', ['completed', 'cancelled']],
    ];

    public const PAYMENT_STATUSES = [
        'pending' => 'Pending', 'processing' => 'Processing', 'partial' => 'Partially Paid',
        'paid' => 'Paid', 'failed' => 'Failed', 'refunded' => 'Refunded', 'disputed' => 'Disputed',
    ];

    public static function detail(int $id): ?array
    {
        return self::db()->first(
            'SELECT o.*, un.code AS unit_code,
                    buyer.full_name AS buyer_name, buyer.mobile AS buyer_mobile, buyer.email AS buyer_email,
                    seller.full_name AS seller_name, seller.mobile AS seller_mobile, seller.email AS seller_email,
                    bb.name AS buyer_business, bb.gstin AS buyer_gstin, bb.address_line1 AS buyer_address,
                    bb.city_name AS buyer_city, bb.state_name AS buyer_state, bb.pincode AS buyer_pincode,
                    sb.name AS seller_business, sb.gstin AS seller_gstin, sb.address_line1 AS seller_address,
                    sb.city_name AS seller_city, sb.state_name AS seller_state, sb.pincode AS seller_pincode,
                    l.title AS listing_title, l.slug AS listing_slug, l.hsn_code,
                    m.name AS material_name,
                    a.reference AS auction_reference, a.id AS auction_id_ref
             FROM orders o
             INNER JOIN units un ON un.id = o.unit_id
             INNER JOIN users buyer ON buyer.id = o.buyer_id
             INNER JOIN users seller ON seller.id = o.seller_id
             LEFT JOIN businesses bb ON bb.id = o.buyer_business_id
             LEFT JOIN businesses sb ON sb.id = o.seller_business_id
             LEFT JOIN listings l ON l.id = o.listing_id
             LEFT JOIN materials m ON m.id = l.material_id
             LEFT JOIN auctions a ON a.id = o.auction_id
             WHERE o.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public static function findByReference(string $reference): ?array
    {
        $row = self::findBy('reference', $reference);
        return $row !== null ? self::detail((int) $row['id']) : null;
    }

    public static function paginate(array $filters, int $page, int $perPage = 20): Paginator
    {
        $sql = 'SELECT o.*, un.code AS unit_code,
                       buyer.full_name AS buyer_name, seller.full_name AS seller_name,
                       bb.name AS buyer_business, sb.name AS seller_business,
                       l.title AS listing_title, l.slug AS listing_slug
                FROM orders o
                INNER JOIN units un ON un.id = o.unit_id
                INNER JOIN users buyer ON buyer.id = o.buyer_id
                INNER JOIN users seller ON seller.id = o.seller_id
                LEFT JOIN businesses bb ON bb.id = o.buyer_business_id
                LEFT JOIN businesses sb ON sb.id = o.seller_business_id
                LEFT JOIN listings l ON l.id = o.listing_id
                WHERE 1 = 1';
        $count = 'SELECT COUNT(*) FROM orders o WHERE 1 = 1';
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= ' AND (o.buyer_id = :user_id OR o.seller_id = :user_id2)';
            $count .= ' AND (o.buyer_id = :user_id OR o.seller_id = :user_id2)';
            $params['user_id'] = (int) $filters['user_id'];
            $params['user_id2'] = (int) $filters['user_id'];
        }
        if (!empty($filters['buyer_id'])) {
            $sql .= ' AND o.buyer_id = :buyer_id';
            $count .= ' AND o.buyer_id = :buyer_id';
            $params['buyer_id'] = (int) $filters['buyer_id'];
        }
        if (!empty($filters['seller_id'])) {
            $sql .= ' AND o.seller_id = :seller_id';
            $count .= ' AND o.seller_id = :seller_id';
            $params['seller_id'] = (int) $filters['seller_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND o.status = :status';
            $count .= ' AND o.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['payment_status'])) {
            $sql .= ' AND o.payment_status = :payment_status';
            $count .= ' AND o.payment_status = :payment_status';
            $params['payment_status'] = $filters['payment_status'];
        }
        if (!empty($filters['source_type'])) {
            $sql .= ' AND o.source_type = :source_type';
            $count .= ' AND o.source_type = :source_type';
            $params['source_type'] = $filters['source_type'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (o.reference LIKE :q OR l.title LIKE :q2)';
            $count .= ' AND o.reference LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND o.created_at >= :from';
            $count .= ' AND o.created_at >= :from';
            $params['from'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND o.created_at <= :to';
            $count .= ' AND o.created_at <= :to';
            $params['to'] = $filters['to'] . ' 23:59:59';
        }

        $sql .= match ($filters['sort'] ?? '') {
            'oldest' => ' ORDER BY o.id ASC',
            'value_high' => ' ORDER BY o.final_amount DESC',
            default => ' ORDER BY o.id DESC',
        };

        return self::paginateQuery($sql, $params, $page, $perPage, $count);
    }

    public static function items(int $orderId): array
    {
        return self::db()->select(
            'SELECT oi.*, un.code AS unit_code FROM order_items oi
             INNER JOIN units un ON un.id = oi.unit_id
             WHERE oi.order_id = :o ORDER BY oi.id',
            ['o' => $orderId]
        );
    }

    public static function history(int $orderId): array
    {
        return self::db()->select(
            'SELECT h.*, u.full_name FROM order_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.order_id = :o ORDER BY h.id',
            ['o' => $orderId]
        );
    }

    public static function weighments(int $orderId): array
    {
        return self::db()->select(
            'SELECT w.*, u.full_name AS recorded_by_name FROM weighments w
             LEFT JOIN users u ON u.id = w.recorded_by
             WHERE w.order_id = :o ORDER BY w.id DESC',
            ['o' => $orderId]
        );
    }

    public static function deliveries(int $orderId): array
    {
        return self::db()->select(
            'SELECT d.*, t.name AS transporter_name, t.mobile AS transporter_mobile
             FROM deliveries d LEFT JOIN transporters t ON t.id = d.transporter_id
             WHERE d.order_id = :o ORDER BY d.id DESC',
            ['o' => $orderId]
        );
    }

    public static function payments(int $orderId): array
    {
        return self::db()->select(
            'SELECT p.*, u.full_name AS payer_name FROM payments p
             LEFT JOIN users u ON u.id = p.payer_id
             WHERE p.order_id = :o ORDER BY p.id DESC',
            ['o' => $orderId]
        );
    }

    public static function generateReference(): string
    {
        return 'ORD' . gmdate('ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    public static function counts(): array
    {
        $row = self::db()->first(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'pending') AS pending,
                    SUM(status = 'completed') AS completed,
                    SUM(status = 'cancelled') AS cancelled,
                    SUM(status = 'disputed') AS disputed,
                    SUM(payment_status = 'paid') AS paid,
                    COALESCE(SUM(final_amount), 0) AS gmv,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN final_amount ELSE 0 END), 0) AS completed_value,
                    SUM(created_at >= :today) AS today
             FROM orders",
            ['today' => gmdate('Y-m-d 00:00:00')]
        ) ?? [];
        return $row;
    }

    public static function canTransitionTo(string $from, string $to): bool
    {
        return in_array($to, self::FLOW[$from][1] ?? [], true);
    }
}
