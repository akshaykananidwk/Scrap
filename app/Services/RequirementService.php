<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\WantedRequirement;

/**
 * "Wanted material" — a buyer posts what they need, sellers respond with an
 * offer (price, available quantity, delivery). Accepting a seller offer creates
 * the order directly.
 */
final class RequirementService
{
    public static function submitOffer(int $requirementId, int $sellerId, array $data): array
    {
        $requirement = WantedRequirement::find($requirementId);
        if ($requirement === null) {
            return ['ok' => false, 'error' => 'Requirement not found.'];
        }
        if ($requirement['status'] !== 'open') {
            return ['ok' => false, 'error' => 'This requirement is ' . label($requirement['status']) . '.'];
        }
        if ((int) $requirement['user_id'] === $sellerId) {
            return ['ok' => false, 'error' => 'You cannot respond to your own requirement.'];
        }

        $quantity = dec($data['available_quantity'] ?? 0, 3);
        $price = dec($data['offered_price'] ?? 0, 2);
        if ((float) $quantity <= 0) {
            return ['ok' => false, 'error' => 'Enter the quantity you can supply.'];
        }
        if ((float) $price <= 0) {
            return ['ok' => false, 'error' => 'Enter your price.'];
        }
        if (!empty($requirement['min_quantity']) && (float) $quantity < (float) $requirement['min_quantity']) {
            return ['ok' => false, 'error' => 'The buyer needs at least ' . qty($requirement['min_quantity']) . '.'];
        }

        $db = Database::instance();
        $existing = $db->first(
            'SELECT id FROM requirement_offers WHERE requirement_id = :r AND seller_id = :s',
            ['r' => $requirementId, 's' => $sellerId]
        );

        $row = [
            'requirement_id' => $requirementId,
            'seller_id' => $sellerId,
            'listing_id' => !empty($data['listing_id']) ? (int) $data['listing_id'] : null,
            'available_quantity' => $quantity,
            'offered_price' => $price,
            'price_basis' => $data['price_basis'] ?? $requirement['price_basis'],
            'gst_included' => !empty($data['gst_included']) ? 1 : 0,
            'delivery_offered' => !empty($data['delivery_offered']) ? 1 : 0,
            'delivery_days' => !empty($data['delivery_days']) ? (int) $data['delivery_days'] : null,
            'message' => $data['message'] ?? null,
            'status' => 'pending',
            'updated_at' => now(),
        ];

        if ($existing !== null) {
            $offerId = (int) $existing['id'];
            $db->update('requirement_offers', $row, ['id' => $offerId]);
        } else {
            $row['created_at'] = now();
            $offerId = $db->insert('requirement_offers', $row);
            $db->statement(
                'UPDATE wanted_requirements SET offer_count = offer_count + 1, updated_at = :n WHERE id = :r',
                ['n' => now(), 'r' => $requirementId]
            );
        }

        $business = \App\Models\Business::forUser($sellerId);
        NotificationService::dispatch((int) $requirement['user_id'], 'requirement_offer', [
            'body' => ($business['name'] ?? 'A seller') . ' can supply ' . qty($quantity) . ' at ' . money($price) . '.',
            'link' => '/dashboard/requirements/' . $requirementId,
            'entity_type' => 'requirement',
            'entity_id' => $requirementId,
            'vars' => [
                'title' => $requirement['title'],
                'seller' => $business['name'] ?? 'A seller',
                'quantity' => qty($quantity),
                'amount' => money($price),
            ],
        ]);

        AuditService::log('requirement_offer', 'requirement', $requirementId, null, ['offer_id' => $offerId]);
        return ['ok' => true, 'offer_id' => $offerId, 'message' => 'Your offer has been sent to the buyer.'];
    }

    public static function acceptOffer(int $offerId, int $buyerId): array
    {
        $db = Database::instance();

        try {
            $result = $db->transaction(static function (Database $db) use ($offerId, $buyerId): array {
                $offer = $db->lockRow('requirement_offers', $offerId);
                if ($offer === null) {
                    return ['ok' => false, 'error' => 'Offer not found.'];
                }
                $requirement = $db->first(
                    'SELECT * FROM wanted_requirements WHERE id = :r',
                    ['r' => (int) $offer['requirement_id']]
                );
                if ($requirement === null || (int) $requirement['user_id'] !== $buyerId) {
                    return ['ok' => false, 'error' => 'You cannot accept this offer.'];
                }
                if ($offer['status'] !== 'pending' && $offer['status'] !== 'shortlisted') {
                    return ['ok' => false, 'error' => 'This offer is ' . label($offer['status']) . '.'];
                }

                $orderId = OrderService::create([
                    'buyer_id' => $buyerId,
                    'seller_id' => (int) $offer['seller_id'],
                    'source_type' => 'requirement',
                    'requirement_id' => (int) $offer['requirement_id'],
                    'listing_id' => $offer['listing_id'] ?? null,
                    'quantity' => $offer['available_quantity'],
                    'unit_id' => (int) $requirement['unit_id'],
                    'rate' => $offer['offered_price'],
                    'price_basis' => $offer['price_basis'],
                    'gst_rate' => (int) $offer['gst_included'] === 1 ? 0 : 18,
                    'payment_terms' => $requirement['payment_terms'],
                    'delivery_address' => $requirement['delivery_address'],
                    'delivery_city' => $requirement['city_name'],
                    'delivery_state' => $requirement['state_name'],
                    'delivery_pincode' => $requirement['pincode'],
                    'description' => $requirement['title'],
                    'notes' => 'Created from requirement ' . $requirement['reference'],
                ]);

                $db->update('requirement_offers', [
                    'status' => 'accepted',
                    'order_id' => $orderId,
                    'responded_at' => now(),
                    'updated_at' => now(),
                ], ['id' => $offerId]);

                $db->statement(
                    "UPDATE requirement_offers SET status = 'rejected', responded_at = :n, updated_at = :n2
                     WHERE requirement_id = :r AND id <> :id AND status IN ('pending','shortlisted')",
                    ['n' => now(), 'n2' => now(), 'r' => (int) $offer['requirement_id'], 'id' => $offerId]
                );

                $db->update('wanted_requirements', [
                    'status' => 'fulfilled',
                    'updated_at' => now(),
                ], ['id' => (int) $offer['requirement_id']]);

                return ['ok' => true, 'order_id' => $orderId, 'offer' => $offer, 'requirement' => $requirement];
            });
        } catch (\Throwable $e) {
            logger()->error('Requirement offer accept failed: ' . $e->getMessage(), ['offer' => $offerId]);
            return ['ok' => false, 'error' => 'Could not accept the offer. Please try again.'];
        }

        if (!$result['ok']) {
            return $result;
        }

        NotificationService::dispatch((int) $result['offer']['seller_id'], 'offer_accepted', [
            'body' => 'Your offer on "' . $result['requirement']['title'] . '" was accepted.',
            'link' => '/dashboard/orders/' . $result['order_id'],
            'entity_type' => 'order',
            'entity_id' => $result['order_id'],
        ]);

        return ['ok' => true, 'order_id' => $result['order_id'], 'message' => 'Offer accepted. Order created.'];
    }

    public static function rejectOffer(int $offerId, int $buyerId): array
    {
        $offer = Database::instance()->first(
            'SELECT o.*, r.user_id AS buyer_id FROM requirement_offers o
             INNER JOIN wanted_requirements r ON r.id = o.requirement_id WHERE o.id = :id',
            ['id' => $offerId]
        );
        if ($offer === null || (int) $offer['buyer_id'] !== $buyerId) {
            return ['ok' => false, 'error' => 'Offer not found.'];
        }
        Database::instance()->update('requirement_offers', [
            'status' => 'rejected',
            'responded_at' => now(),
            'updated_at' => now(),
        ], ['id' => $offerId]);
        return ['ok' => true, 'message' => 'Offer declined.'];
    }

    public static function shortlistOffer(int $offerId, int $buyerId): array
    {
        $offer = Database::instance()->first(
            'SELECT o.*, r.user_id AS buyer_id FROM requirement_offers o
             INNER JOIN wanted_requirements r ON r.id = o.requirement_id WHERE o.id = :id',
            ['id' => $offerId]
        );
        if ($offer === null || (int) $offer['buyer_id'] !== $buyerId) {
            return ['ok' => false, 'error' => 'Offer not found.'];
        }
        $status = $offer['status'] === 'shortlisted' ? 'pending' : 'shortlisted';
        Database::instance()->update('requirement_offers', ['status' => $status, 'updated_at' => now()], ['id' => $offerId]);
        return ['ok' => true, 'status' => $status, 'message' => $status === 'shortlisted' ? 'Offer shortlisted.' : 'Removed from shortlist.'];
    }

    /** Scheduler: expire requirements past their date. */
    public static function expireDue(): int
    {
        return Database::instance()->statement(
            "UPDATE wanted_requirements SET status = 'expired', updated_at = :n
             WHERE status = 'open' AND expires_at IS NOT NULL AND expires_at < :now AND deleted_at IS NULL",
            ['n' => now(), 'now' => now()]
        );
    }
}
