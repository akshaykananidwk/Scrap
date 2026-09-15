<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Model;
use App\Core\Paginator;
use App\Models\Listing;

/**
 * Offer / counter-offer negotiation.
 *
 *   buyer → offer → seller → counter → buyer → counter → … → accepted → ORDER
 *
 * Accepting an offer is transactional: the offer is marked accepted, every
 * sibling offer on the same listing is closed, and the order is created in the
 * same commit, so a listing can never be sold twice.
 */
final class OfferService
{
    public const STATUSES = [
        'pending' => 'Pending', 'accepted' => 'Accepted', 'rejected' => 'Rejected',
        'countered' => 'Countered', 'expired' => 'Expired', 'cancelled' => 'Cancelled',
    ];

    /**
     * @param array{listing_id?:int, requirement_id?:int, buyer_id:int, seller_id:int, created_by:int,
     *              amount:string, quantity:string, unit_id:int, price_basis?:string, message?:string,
     *              parent_offer_id?:int, payment_terms?:string, gst_included?:bool, transport_included?:bool} $data
     */
    public static function create(array $data): array
    {
        $amount = dec($data['amount'], 2);
        $quantity = dec($data['quantity'], 3);

        if ((float) $amount <= 0) {
            return ['ok' => false, 'error' => 'Offer amount must be greater than zero.'];
        }
        if ((float) $quantity <= 0) {
            return ['ok' => false, 'error' => 'Offer quantity must be greater than zero.'];
        }

        $buyerId = (int) $data['buyer_id'];
        $sellerId = (int) $data['seller_id'];
        if ($buyerId === $sellerId) {
            return ['ok' => false, 'error' => 'You cannot make an offer on your own listing.'];
        }

        $listing = !empty($data['listing_id']) ? Listing::find((int) $data['listing_id']) : null;
        if ($listing !== null) {
            if ($listing['status'] !== 'active') {
                return ['ok' => false, 'error' => 'This listing is no longer accepting offers.'];
            }
            if ($listing['min_order_quantity'] !== null && (float) $quantity < (float) $listing['min_order_quantity']) {
                return [
                    'ok' => false,
                    'error' => 'The minimum order quantity for this listing is ' . qty($listing['min_order_quantity']) . '.',
                ];
            }
            if ((float) $quantity > (float) $listing['quantity']) {
                return ['ok' => false, 'error' => 'Only ' . qty($listing['quantity']) . ' is available on this listing.'];
            }
        }

        $db = Database::instance();
        $creatorId = (int) $data['created_by'];
        $direction = $creatorId === $buyerId ? 'buyer_to_seller' : 'seller_to_buyer';
        $hours = SettingsService::int('offer_default_expiry_hours', 72);

        $offerId = $db->transaction(static function (Database $db) use ($data, $amount, $quantity, $buyerId, $sellerId, $creatorId, $direction, $hours): int {
            // A counter supersedes its parent.
            if (!empty($data['parent_offer_id'])) {
                $db->update('offers', [
                    'status' => 'countered',
                    'responded_at' => now(),
                    'responded_by' => $creatorId,
                    'updated_at' => now(),
                ], ['id' => (int) $data['parent_offer_id']]);
            }

            $id = $db->insert('offers', [
                'reference' => 'OFR' . gmdate('ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)),
                'listing_id' => $data['listing_id'] ?? null,
                'requirement_id' => $data['requirement_id'] ?? null,
                'thread_id' => $data['thread_id'] ?? null,
                'parent_offer_id' => $data['parent_offer_id'] ?? null,
                'buyer_id' => $buyerId,
                'seller_id' => $sellerId,
                'created_by' => $creatorId,
                'direction' => $direction,
                'amount' => $amount,
                'price_basis' => $data['price_basis'] ?? 'per_mt',
                'quantity' => $quantity,
                'unit_id' => (int) $data['unit_id'],
                'gst_included' => !empty($data['gst_included']) ? 1 : 0,
                'transport_included' => !empty($data['transport_included']) ? 1 : 0,
                'payment_terms' => $data['payment_terms'] ?? 'advance',
                'message' => $data['message'] ?? null,
                'status' => 'pending',
                'expires_at' => gmdate('Y-m-d H:i:s', time() + ($hours * 3600)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (!empty($data['listing_id'])) {
                $db->statement(
                    'UPDATE listings SET offer_count = offer_count + 1, updated_at = :n WHERE id = :l',
                    ['n' => now(), 'l' => (int) $data['listing_id']]
                );
            }

            return $id;
        });

        // Keep the negotiation visible in chat.
        $conversationId = ChatService::ensureConversation($buyerId, $sellerId, [
            'listing_id' => $data['listing_id'] ?? null,
            'requirement_id' => $data['requirement_id'] ?? null,
        ]);
        $db->update('offers', ['thread_id' => $conversationId], ['id' => $offerId]);
        ChatService::systemMessage(
            $conversationId,
            $creatorId,
            sprintf(
                '%s offered %s for %s.',
                $direction === 'buyer_to_seller' ? 'Buyer' : 'Seller',
                money($amount),
                qty($quantity)
            ),
            ['offer_id' => $offerId]
        );

        $recipientId = $creatorId === $buyerId ? $sellerId : $buyerId;
        NotificationService::dispatch($recipientId, empty($data['parent_offer_id']) ? 'new_offer' : 'offer_countered', [
            'body' => money($amount) . ' for ' . qty($quantity) . ($listing ? ' on ' . $listing['title'] : ''),
            'link' => '/dashboard/offers/' . $offerId,
            'entity_type' => 'offer',
            'entity_id' => $offerId,
            'vars' => [
                'title' => $listing['title'] ?? 'your requirement',
                'amount' => money($amount),
                'quantity' => qty($quantity),
            ],
        ]);

        AuditService::log('offer_created', 'offer', $offerId, null, ['amount' => $amount, 'quantity' => $quantity]);

        return ['ok' => true, 'offer_id' => $offerId, 'message' => 'Your offer has been sent.'];
    }

    /** Accept an offer. Creates the order and closes competing offers. */
    public static function accept(int $offerId, int $actorId): array
    {
        $db = Database::instance();

        try {
            $result = $db->transaction(static function (Database $db) use ($offerId, $actorId): array {
                $offer = $db->lockRow('offers', $offerId);
                if ($offer === null) {
                    return ['ok' => false, 'error' => 'Offer not found.'];
                }
                if ($offer['status'] !== 'pending') {
                    return ['ok' => false, 'error' => 'This offer is ' . $offer['status'] . ' and can no longer be accepted.'];
                }
                if ($offer['expires_at'] !== null && strtotime($offer['expires_at'] . ' UTC') < time()) {
                    $db->update('offers', ['status' => 'expired', 'updated_at' => now()], ['id' => $offerId]);
                    return ['ok' => false, 'error' => 'This offer has expired.'];
                }

                // Only the recipient may accept.
                $recipientId = $offer['direction'] === 'buyer_to_seller' ? (int) $offer['seller_id'] : (int) $offer['buyer_id'];
                if ($recipientId !== $actorId) {
                    return ['ok' => false, 'error' => 'Only the recipient of this offer can accept it.'];
                }

                $listing = !empty($offer['listing_id']) ? $db->lockRow('listings', (int) $offer['listing_id']) : null;
                if ($listing !== null && $listing['status'] !== 'active') {
                    return ['ok' => false, 'error' => 'The listing is no longer available.'];
                }

                $orderId = OrderService::create([
                    'buyer_id' => (int) $offer['buyer_id'],
                    'seller_id' => (int) $offer['seller_id'],
                    'source_type' => 'offer',
                    'listing_id' => $offer['listing_id'] ?? null,
                    'requirement_id' => $offer['requirement_id'] ?? null,
                    'offer_id' => $offerId,
                    'quantity' => $offer['quantity'],
                    'unit_id' => (int) $offer['unit_id'],
                    'rate' => $offer['amount'],
                    'price_basis' => $offer['price_basis'],
                    'gst_rate' => (int) $offer['gst_included'] === 1 ? 0 : ($listing['gst_rate'] ?? 0),
                    'payment_terms' => $offer['payment_terms'],
                    'description' => $listing['title'] ?? 'Negotiated deal',
                    'notes' => 'Created from accepted offer ' . $offer['reference'],
                ]);

                $db->update('offers', [
                    'status' => 'accepted',
                    'order_id' => $orderId,
                    'responded_at' => now(),
                    'responded_by' => $actorId,
                    'updated_at' => now(),
                ], ['id' => $offerId]);

                // Close the competing offers on the same listing.
                if ($listing !== null) {
                    $remaining = (float) $listing['quantity'] - (float) $offer['quantity'];
                    if ($remaining <= 0.0001) {
                        $db->update('listings', [
                            'status' => 'sold',
                            'sold_at' => now(),
                            'updated_at' => now(),
                        ], ['id' => (int) $listing['id']]);
                        $db->statement(
                            "UPDATE offers SET status = 'rejected', responded_at = :n, updated_at = :n2
                             WHERE listing_id = :l AND status = 'pending' AND id <> :id",
                            ['n' => now(), 'n2' => now(), 'l' => (int) $listing['id'], 'id' => $offerId]
                        );
                    } else {
                        $db->update('listings', [
                            'quantity' => dec($remaining, 3),
                            'updated_at' => now(),
                        ], ['id' => (int) $listing['id']]);
                    }
                }

                return ['ok' => true, 'order_id' => $orderId, 'offer' => $offer];
            });
        } catch (\Throwable $e) {
            logger()->error('Offer accept failed: ' . $e->getMessage(), ['offer' => $offerId]);
            return ['ok' => false, 'error' => 'The offer could not be accepted. Please try again.'];
        }

        if (!$result['ok']) {
            return $result;
        }

        $offer = $result['offer'];
        $orderId = $result['order_id'];
        $order = \App\Models\Order::find($orderId);
        $notifyId = $actorId === (int) $offer['buyer_id'] ? (int) $offer['seller_id'] : (int) $offer['buyer_id'];

        NotificationService::dispatch($notifyId, 'offer_accepted', [
            'body' => 'Your offer of ' . money($offer['amount']) . ' was accepted. Order ' . ($order['reference'] ?? '') . ' created.',
            'link' => '/dashboard/orders/' . $orderId,
            'entity_type' => 'order',
            'entity_id' => $orderId,
            'vars' => [
                'amount' => money($offer['amount']),
                'order' => $order['reference'] ?? '',
                'title' => 'your deal',
            ],
        ]);

        if (!empty($offer['thread_id'])) {
            ChatService::systemMessage(
                (int) $offer['thread_id'],
                $actorId,
                'Offer accepted at ' . money($offer['amount']) . '. Order ' . ($order['reference'] ?? '') . ' created.',
                ['offer_id' => $offerId, 'order_id' => $orderId]
            );
        }

        AuditService::log('offer_accepted', 'offer', $offerId, null, ['order_id' => $orderId]);
        return ['ok' => true, 'order_id' => $orderId, 'message' => 'Offer accepted. Order created.'];
    }

    public static function reject(int $offerId, int $actorId, string $reason = ''): array
    {
        $offer = Database::instance()->first('SELECT * FROM offers WHERE id = :id', ['id' => $offerId]);
        if ($offer === null) {
            return ['ok' => false, 'error' => 'Offer not found.'];
        }
        if ($offer['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'This offer is already ' . $offer['status'] . '.'];
        }
        $recipientId = $offer['direction'] === 'buyer_to_seller' ? (int) $offer['seller_id'] : (int) $offer['buyer_id'];
        if ($recipientId !== $actorId) {
            return ['ok' => false, 'error' => 'Only the recipient can decline this offer.'];
        }

        Database::instance()->update('offers', [
            'status' => 'rejected',
            'responded_at' => now(),
            'responded_by' => $actorId,
            'updated_at' => now(),
        ], ['id' => $offerId]);

        $notifyId = $actorId === (int) $offer['buyer_id'] ? (int) $offer['seller_id'] : (int) $offer['buyer_id'];
        NotificationService::dispatch($notifyId, 'offer_rejected', [
            'body' => 'Your offer of ' . money($offer['amount']) . ' was declined.' . ($reason !== '' ? ' Reason: ' . $reason : ''),
            'link' => '/dashboard/offers/' . $offerId,
            'entity_type' => 'offer',
            'entity_id' => $offerId,
        ]);

        AuditService::log('offer_rejected', 'offer', $offerId);
        return ['ok' => true, 'message' => 'Offer declined.'];
    }

    public static function cancel(int $offerId, int $actorId): array
    {
        $offer = Database::instance()->first('SELECT * FROM offers WHERE id = :id', ['id' => $offerId]);
        if ($offer === null) {
            return ['ok' => false, 'error' => 'Offer not found.'];
        }
        if ((int) $offer['created_by'] !== $actorId) {
            return ['ok' => false, 'error' => 'Only the sender can withdraw this offer.'];
        }
        if ($offer['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'This offer is already ' . $offer['status'] . '.'];
        }
        Database::instance()->update('offers', [
            'status' => 'cancelled',
            'responded_at' => now(),
            'responded_by' => $actorId,
            'updated_at' => now(),
        ], ['id' => $offerId]);

        AuditService::log('offer_cancelled', 'offer', $offerId);
        return ['ok' => true, 'message' => 'Offer withdrawn.'];
    }

    public static function detail(int $offerId): ?array
    {
        return Database::instance()->first(
            'SELECT o.*, un.code AS unit_code,
                    l.title AS listing_title, l.slug AS listing_slug, l.status AS listing_status,
                    r.title AS requirement_title, r.slug AS requirement_slug,
                    buyer.full_name AS buyer_name, seller.full_name AS seller_name,
                    bb.name AS buyer_business, sb.name AS seller_business,
                    ord.reference AS order_reference
             FROM offers o
             INNER JOIN units un ON un.id = o.unit_id
             LEFT JOIN listings l ON l.id = o.listing_id
             LEFT JOIN wanted_requirements r ON r.id = o.requirement_id
             INNER JOIN users buyer ON buyer.id = o.buyer_id
             INNER JOIN users seller ON seller.id = o.seller_id
             LEFT JOIN businesses bb ON bb.user_id = o.buyer_id AND bb.deleted_at IS NULL
             LEFT JOIN businesses sb ON sb.user_id = o.seller_id AND sb.deleted_at IS NULL
             LEFT JOIN orders ord ON ord.id = o.order_id
             WHERE o.id = :id LIMIT 1',
            ['id' => $offerId]
        );
    }

    public static function thread(int $offerId): array
    {
        $offer = Database::instance()->first('SELECT * FROM offers WHERE id = :id', ['id' => $offerId]);
        if ($offer === null) {
            return [];
        }
        // Walk to the root of the counter-offer chain, then list the whole chain.
        $rootId = (int) $offer['id'];
        $guard = 0;
        while ($guard++ < 25) {
            $parent = Database::instance()->first('SELECT id, parent_offer_id FROM offers WHERE id = :id', ['id' => $rootId]);
            if ($parent === null || empty($parent['parent_offer_id'])) {
                break;
            }
            $rootId = (int) $parent['parent_offer_id'];
        }

        $chain = [];
        $currentIds = [$rootId];
        $guard = 0;
        while ($currentIds !== [] && $guard++ < 25) {
            [$where, $params] = Database::instance()->compileWhere(['id' => $currentIds]);
            $rows = Database::instance()->select(
                'SELECT o.*, u.full_name AS created_by_name FROM offers o
                 INNER JOIN users u ON u.id = o.created_by WHERE ' . $where . ' ORDER BY o.id',
                $params
            );
            foreach ($rows as $row) {
                $chain[] = $row;
            }
            [$where2, $params2] = Database::instance()->compileWhere(['parent_offer_id' => $currentIds]);
            $children = Database::instance()->select('SELECT id FROM offers WHERE ' . $where2, $params2);
            $currentIds = array_map(static fn (array $r): int => (int) $r['id'], $children);
        }

        return $chain;
    }

    public static function paginate(array $filters, int $page, int $perPage = 20): Paginator
    {
        $sql = 'SELECT o.*, un.code AS unit_code, l.title AS listing_title, l.slug AS listing_slug,
                       buyer.full_name AS buyer_name, seller.full_name AS seller_name,
                       bb.name AS buyer_business, sb.name AS seller_business
                FROM offers o
                INNER JOIN units un ON un.id = o.unit_id
                LEFT JOIN listings l ON l.id = o.listing_id
                INNER JOIN users buyer ON buyer.id = o.buyer_id
                INNER JOIN users seller ON seller.id = o.seller_id
                LEFT JOIN businesses bb ON bb.user_id = o.buyer_id AND bb.deleted_at IS NULL
                LEFT JOIN businesses sb ON sb.user_id = o.seller_id AND sb.deleted_at IS NULL
                WHERE 1 = 1';
        $count = 'SELECT COUNT(*) FROM offers o WHERE 1 = 1';
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= ' AND (o.buyer_id = :u OR o.seller_id = :u2)';
            $count .= ' AND (o.buyer_id = :u OR o.seller_id = :u2)';
            $params['u'] = (int) $filters['user_id'];
            $params['u2'] = (int) $filters['user_id'];
        }
        if (!empty($filters['role']) && !empty($filters['user_id'])) {
            $column = $filters['role'] === 'buyer' ? 'o.buyer_id' : 'o.seller_id';
            $sql .= " AND {$column} = :role_user";
            $count .= " AND {$column} = :role_user";
            $params['role_user'] = (int) $filters['user_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND o.status = :status';
            $count .= ' AND o.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['listing_id'])) {
            $sql .= ' AND o.listing_id = :listing_id';
            $count .= ' AND o.listing_id = :listing_id';
            $params['listing_id'] = (int) $filters['listing_id'];
        }

        return Model::paginateQuery($sql . ' ORDER BY o.id DESC', $params, $page, $perPage, $count);
    }

    /** Scheduler job: expire offers past their validity window. */
    public static function expireDue(): int
    {
        return Database::instance()->statement(
            "UPDATE offers SET status = 'expired', updated_at = :n
             WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at < :now",
            ['n' => now(), 'now' => now()]
        );
    }
}
