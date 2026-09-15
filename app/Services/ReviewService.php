<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Model;
use App\Core\Paginator;
use App\Models\Business;
use App\Models\Order;

/**
 * Two-sided reviews. Only the counterparties on a completed order may review,
 * once each — the unique key (order_id, reviewer_id) makes duplicates impossible
 * even under a double submit.
 */
final class ReviewService
{
    public const CRITERIA = [
        'communication_rating' => 'Communication',
        'material_accuracy_rating' => 'Material accuracy',
        'payment_rating' => 'Payment',
        'delivery_rating' => 'Delivery',
        'professionalism_rating' => 'Professionalism',
    ];

    public static function canReview(array $order, int $userId): array
    {
        if ($order['status'] !== 'completed') {
            return ['ok' => false, 'error' => 'You can review once the order is completed.'];
        }
        $role = OrderService::role($order, $userId);
        if ($role === 'observer') {
            return ['ok' => false, 'error' => 'You are not part of this order.'];
        }
        $existing = Database::instance()->first(
            'SELECT id FROM reviews WHERE order_id = :o AND reviewer_id = :u',
            ['o' => (int) $order['id'], 'u' => $userId]
        );
        if ($existing !== null) {
            return ['ok' => false, 'error' => 'You have already reviewed this order.'];
        }
        return ['ok' => true, 'role' => $role];
    }

    public static function submit(int $orderId, int $reviewerId, array $data): array
    {
        $order = Order::find($orderId);
        if ($order === null) {
            return ['ok' => false, 'error' => 'Order not found.'];
        }

        $check = self::canReview($order, $reviewerId);
        if (!$check['ok']) {
            return $check;
        }

        $overall = (int) ($data['overall_rating'] ?? 0);
        if ($overall < 1 || $overall > 5) {
            return ['ok' => false, 'error' => 'Give an overall rating between 1 and 5 stars.'];
        }

        $role = $check['role'];
        $revieweeId = $role === 'buyer' ? (int) $order['seller_id'] : (int) $order['buyer_id'];
        $revieweeBusiness = Business::forUser($revieweeId);

        $row = [
            'order_id' => $orderId,
            'reviewer_id' => $reviewerId,
            'reviewee_id' => $revieweeId,
            'business_id' => $revieweeBusiness['id'] ?? null,
            'reviewer_role' => $role,
            'overall_rating' => $overall,
            'title' => !empty($data['title']) ? substr((string) $data['title'], 0, 150) : null,
            'comment' => !empty($data['comment']) ? (string) $data['comment'] : null,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        foreach (array_keys(self::CRITERIA) as $criterion) {
            $value = (int) ($data[$criterion] ?? 0);
            $row[$criterion] = ($value >= 1 && $value <= 5) ? $value : null;
        }

        try {
            $reviewId = Database::instance()->insert('reviews', $row);
        } catch (\Throwable $e) {
            // Unique key collision = concurrent double submit.
            return ['ok' => false, 'error' => 'You have already reviewed this order.'];
        }

        $reviewedColumn = $role === 'buyer' ? 'buyer_reviewed' : 'seller_reviewed';
        Database::instance()->update('orders', [
            $reviewedColumn => 1,
            'updated_at' => now(),
        ], ['id' => $orderId]);

        if (!empty($revieweeBusiness['id'])) {
            Business::refreshStats((int) $revieweeBusiness['id']);
        }

        NotificationService::dispatch($revieweeId, 'review_received', [
            'body' => str_repeat('★', $overall) . ' review received on order ' . $order['reference'] . '.',
            'link' => '/dashboard/reviews',
            'entity_type' => 'review',
            'entity_id' => $reviewId,
        ]);

        AuditService::log('review_submitted', 'review', $reviewId, null, ['order_id' => $orderId, 'rating' => $overall]);
        return ['ok' => true, 'review_id' => $reviewId, 'message' => 'Thank you — your review has been published.'];
    }

    public static function respond(int $reviewId, int $userId, string $response): array
    {
        $review = Database::instance()->first('SELECT * FROM reviews WHERE id = :id', ['id' => $reviewId]);
        if ($review === null) {
            return ['ok' => false, 'error' => 'Review not found.'];
        }
        if ((int) $review['reviewee_id'] !== $userId) {
            return ['ok' => false, 'error' => 'You can only respond to reviews about your business.'];
        }
        if (!empty($review['seller_response'])) {
            return ['ok' => false, 'error' => 'You have already responded to this review.'];
        }
        if (trim($response) === '') {
            return ['ok' => false, 'error' => 'Write a response first.'];
        }

        Database::instance()->update('reviews', [
            'seller_response' => substr($response, 0, 2000),
            'responded_at' => now(),
            'updated_at' => now(),
        ], ['id' => $reviewId]);

        return ['ok' => true, 'message' => 'Your response has been published.'];
    }

    public static function forUser(int $userId, int $page, int $perPage = 15, string $direction = 'received'): Paginator
    {
        $column = $direction === 'given' ? 'r.reviewer_id' : 'r.reviewee_id';
        return Model::paginateQuery(
            "SELECT r.*, o.reference AS order_reference,
                    reviewer.full_name AS reviewer_name, rb.name AS reviewer_business, rb.slug AS reviewer_slug,
                    reviewee.full_name AS reviewee_name, eb.name AS reviewee_business
             FROM reviews r
             INNER JOIN orders o ON o.id = r.order_id
             INNER JOIN users reviewer ON reviewer.id = r.reviewer_id
             INNER JOIN users reviewee ON reviewee.id = r.reviewee_id
             LEFT JOIN businesses rb ON rb.user_id = r.reviewer_id AND rb.deleted_at IS NULL
             LEFT JOIN businesses eb ON eb.user_id = r.reviewee_id AND eb.deleted_at IS NULL
             WHERE {$column} = :u AND r.status = 'published'
             ORDER BY r.id DESC",
            ['u' => $userId],
            $page,
            $perPage,
            "SELECT COUNT(*) FROM reviews r WHERE {$column} = :u AND r.status = 'published'"
        );
    }

    public static function forBusiness(int $businessId, int $limit = 10): array
    {
        return Database::instance()->select(
            "SELECT r.*, reviewer.full_name AS reviewer_name, rb.name AS reviewer_business, rb.slug AS reviewer_slug
             FROM reviews r
             INNER JOIN users reviewer ON reviewer.id = r.reviewer_id
             LEFT JOIN businesses rb ON rb.user_id = r.reviewer_id AND rb.deleted_at IS NULL
             WHERE r.business_id = :b AND r.status = 'published'
             ORDER BY r.id DESC LIMIT " . max(1, $limit),
            ['b' => $businessId]
        );
    }

    /** Star distribution + per-criterion averages for a profile page. */
    public static function summary(int $userId): array
    {
        $db = Database::instance();
        $overall = $db->first(
            "SELECT COUNT(*) AS total, COALESCE(AVG(overall_rating), 0) AS average,
                    SUM(overall_rating = 5) AS five, SUM(overall_rating = 4) AS four,
                    SUM(overall_rating = 3) AS three, SUM(overall_rating = 2) AS two,
                    SUM(overall_rating = 1) AS one
             FROM reviews WHERE reviewee_id = :u AND status = 'published'",
            ['u' => $userId]
        ) ?? [];

        $criteria = [];
        foreach (self::CRITERIA as $column => $labelText) {
            $criteria[$labelText] = dec($db->scalar(
                "SELECT COALESCE(AVG({$column}), 0) FROM reviews WHERE reviewee_id = :u AND status = 'published' AND {$column} IS NOT NULL",
                ['u' => $userId],
                0
            ), 1);
        }

        return ['overall' => $overall, 'criteria' => $criteria];
    }

    public static function moderate(int $reviewId, string $status): array
    {
        if (!in_array($status, ['published', 'hidden', 'removed', 'pending'], true)) {
            return ['ok' => false, 'error' => 'Invalid status.'];
        }
        $review = Database::instance()->first('SELECT * FROM reviews WHERE id = :id', ['id' => $reviewId]);
        if ($review === null) {
            return ['ok' => false, 'error' => 'Review not found.'];
        }
        Database::instance()->update('reviews', ['status' => $status, 'updated_at' => now()], ['id' => $reviewId]);
        if (!empty($review['business_id'])) {
            Business::refreshStats((int) $review['business_id']);
        }
        AuditService::log('review_moderated', 'review', $reviewId, null, ['status' => $status]);
        return ['ok' => true, 'message' => 'Review ' . label($status) . '.'];
    }

    public static function pendingForUser(int $userId): array
    {
        return Database::instance()->select(
            "SELECT o.id, o.reference, o.final_amount, o.completed_at,
                    CASE WHEN o.buyer_id = :u THEN 'buyer' ELSE 'seller' END AS my_role,
                    CASE WHEN o.buyer_id = :u2 THEN seller.full_name ELSE buyer.full_name END AS counterparty
             FROM orders o
             INNER JOIN users buyer ON buyer.id = o.buyer_id
             INNER JOIN users seller ON seller.id = o.seller_id
             WHERE o.status = 'completed'
               AND ((o.buyer_id = :u3 AND o.buyer_reviewed = 0) OR (o.seller_id = :u4 AND o.seller_reviewed = 0))
             ORDER BY o.completed_at DESC LIMIT 10",
            ['u' => $userId, 'u2' => $userId, 'u3' => $userId, 'u4' => $userId]
        );
    }
}
