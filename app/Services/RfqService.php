<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Rfq;

/**
 * RFQ workflow:
 *   Draft → Open → (Closing Soon) → Closed → Awarded
 * Sellers are invited or find public RFQs, submit one quote each, the buyer
 * compares, shortlists, negotiates and awards. Awarding creates the order.
 */
final class RfqService
{
    public static function create(array $data, array $items, int $buyerId): array
    {
        if ($items === []) {
            return ['ok' => false, 'error' => 'Add at least one line item to the RFQ.'];
        }

        $db = Database::instance();
        $business = \App\Models\Business::forUser($buyerId);

        $rfqId = $db->transaction(static function (Database $db) use ($data, $items, $buyerId, $business): int {
            $id = $db->insert('rfqs', [
                'reference' => Rfq::generateReference(),
                'buyer_id' => $buyerId,
                'business_id' => $business['id'] ?? null,
                'title' => substr((string) $data['title'], 0, 190),
                'description' => $data['description'] ?? null,
                'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
                'delivery_address' => $data['delivery_address'] ?? null,
                'city_id' => !empty($data['city_id']) ? (int) $data['city_id'] : null,
                'state_id' => !empty($data['state_id']) ? (int) $data['state_id'] : null,
                'city_name' => $data['city_name'] ?? ($business['city_name'] ?? null),
                'payment_terms' => $data['payment_terms'] ?? 'on_delivery',
                'delivery_required_by' => $data['delivery_required_by'] ?? null,
                'visibility' => $data['visibility'] ?? 'public',
                'status' => $data['status'] ?? 'open',
                'closes_at' => $data['closes_at'] ?? gmdate('Y-m-d H:i:s', strtotime('+7 days')),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (array_values($items) as $sort => $item) {
                $db->insert('rfq_items', [
                    'rfq_id' => $id,
                    'material_id' => !empty($item['material_id']) ? (int) $item['material_id'] : null,
                    'grade_id' => !empty($item['grade_id']) ? (int) $item['grade_id'] : null,
                    'item_name' => substr((string) $item['item_name'], 0, 190),
                    'specification' => $item['specification'] ?? null,
                    'quantity' => dec($item['quantity'], 3),
                    'unit_id' => (int) $item['unit_id'],
                    'target_price' => !empty($item['target_price']) ? dec($item['target_price'], 2) : null,
                    'sort_order' => $sort,
                    'created_at' => now(),
                ]);
            }

            return $id;
        });

        AuditService::log('rfq_created', 'rfq', $rfqId, null, ['title' => $data['title'], 'items' => count($items)]);

        if (($data['status'] ?? 'open') === 'open') {
            self::notifyMatchingSellers($rfqId);
        }

        return ['ok' => true, 'rfq_id' => $rfqId, 'message' => 'RFQ published.'];
    }

    public static function invite(int $rfqId, array $sellerIds, int $invitedBy): int
    {
        $db = Database::instance();
        $rfq = Rfq::find($rfqId);
        if ($rfq === null) {
            return 0;
        }
        $invited = 0;
        foreach (array_unique(array_map('intval', $sellerIds)) as $sellerId) {
            if ($sellerId === (int) $rfq['buyer_id']) {
                continue;
            }
            $rows = $db->statement(
                'INSERT IGNORE INTO rfq_invites (rfq_id, seller_id, invited_by, status, created_at)
                 VALUES (:r, :s, :i, :st, :c)',
                ['r' => $rfqId, 's' => $sellerId, 'i' => $invitedBy, 'st' => 'invited', 'c' => now()]
            );
            if ($rows > 0) {
                $invited++;
                NotificationService::dispatch($sellerId, 'new_rfq', [
                    'body' => 'You were invited to quote on "' . $rfq['title'] . '".',
                    'link' => '/rfq/' . $rfqId,
                    'entity_type' => 'rfq',
                    'entity_id' => $rfqId,
                    'vars' => ['title' => $rfq['title'], 'closes' => fmt_dt($rfq['closes_at'])],
                ]);
            }
        }
        return $invited;
    }

    /** Public RFQs are pushed to sellers who list in the same category. */
    private static function notifyMatchingSellers(int $rfqId): void
    {
        $rfq = Rfq::find($rfqId);
        if ($rfq === null || $rfq['visibility'] !== 'public' || empty($rfq['category_id'])) {
            return;
        }
        $sellers = Database::instance()->select(
            'SELECT DISTINCT l.user_id FROM listings l
             WHERE l.category_id = :c AND l.status = "active" AND l.deleted_at IS NULL AND l.user_id <> :b
             LIMIT 50',
            ['c' => (int) $rfq['category_id'], 'b' => (int) $rfq['buyer_id']]
        );
        NotificationService::dispatchMany(
            array_map(static fn (array $r): int => (int) $r['user_id'], $sellers),
            'new_rfq',
            [
                'body' => 'New RFQ you can quote on: ' . $rfq['title'],
                'link' => '/rfq/' . $rfqId,
                'entity_type' => 'rfq',
                'entity_id' => $rfqId,
                'vars' => ['title' => $rfq['title'], 'closes' => fmt_dt($rfq['closes_at'])],
            ]
        );
    }

    /**
     * Submit (or replace) a seller's quote.
     * @param array<int, array{rfq_item_id:int, offered_quantity:string, rate:string, gst_rate?:string, remarks?:string}> $lines
     */
    public static function submitQuote(int $rfqId, int $sellerId, array $lines, array $meta): array
    {
        $rfq = Rfq::find($rfqId);
        if ($rfq === null) {
            return ['ok' => false, 'error' => 'RFQ not found.'];
        }
        if (!in_array($rfq['status'], ['open', 'closing_soon'], true)) {
            return ['ok' => false, 'error' => 'This RFQ is ' . label($rfq['status']) . ' and no longer accepts quotes.'];
        }
        if ((int) $rfq['buyer_id'] === $sellerId) {
            return ['ok' => false, 'error' => 'You cannot quote on your own RFQ.'];
        }
        if ($rfq['closes_at'] !== null && strtotime($rfq['closes_at'] . ' UTC') < time()) {
            return ['ok' => false, 'error' => 'The quote window for this RFQ has closed.'];
        }
        if ($rfq['visibility'] === 'invited') {
            $invited = Database::instance()->first(
                'SELECT id FROM rfq_invites WHERE rfq_id = :r AND seller_id = :s',
                ['r' => $rfqId, 's' => $sellerId]
            );
            if ($invited === null) {
                return ['ok' => false, 'error' => 'This RFQ is open to invited sellers only.'];
            }
        }
        if ($lines === []) {
            return ['ok' => false, 'error' => 'Quote at least one line item.'];
        }

        $validItemIds = array_map(
            static fn (array $i): int => (int) $i['id'],
            Database::instance()->select('SELECT id FROM rfq_items WHERE rfq_id = :r', ['r' => $rfqId])
        );

        $db = Database::instance();
        $business = \App\Models\Business::forUser($sellerId);

        $quoteId = $db->transaction(static function (Database $db) use ($rfqId, $sellerId, $lines, $meta, $business, $validItemIds): int {
            $existing = $db->first(
                'SELECT id FROM rfq_quotes WHERE rfq_id = :r AND seller_id = :s',
                ['r' => $rfqId, 's' => $sellerId]
            );

            $subtotal = 0.0;
            $gstTotal = 0.0;
            $prepared = [];
            foreach ($lines as $line) {
                $itemId = (int) ($line['rfq_item_id'] ?? 0);
                if (!in_array($itemId, $validItemIds, true)) {
                    continue;
                }
                $quantity = (float) dec($line['offered_quantity'] ?? 0, 3);
                $rate = (float) dec($line['rate'] ?? 0, 4);
                if ($quantity <= 0 || $rate <= 0) {
                    continue;
                }
                $amount = $quantity * $rate;
                $gstRate = (float) dec($line['gst_rate'] ?? 18, 2);
                $gst = ($amount * $gstRate) / 100;
                $subtotal += $amount;
                $gstTotal += $gst;
                $prepared[] = [
                    'rfq_item_id' => $itemId,
                    'offered_quantity' => dec($quantity, 3),
                    'rate' => dec($rate, 4),
                    'amount' => dec($amount, 2),
                    'gst_rate' => dec($gstRate, 2),
                    'remarks' => !empty($line['remarks']) ? substr((string) $line['remarks'], 0, 255) : null,
                    'created_at' => now(),
                ];
            }

            if ($prepared === []) {
                throw new \RuntimeException('No valid line items were quoted.');
            }

            $delivery = (float) dec($meta['delivery_charges'] ?? 0, 2);
            $grandTotal = $subtotal + $gstTotal + $delivery;

            $quoteData = [
                'rfq_id' => $rfqId,
                'seller_id' => $sellerId,
                'business_id' => $business['id'] ?? null,
                'total_amount' => dec($subtotal, 2),
                'gst_amount' => dec($gstTotal, 2),
                'grand_total' => dec($grandTotal, 2),
                'delivery_included' => !empty($meta['delivery_included']) ? 1 : 0,
                'delivery_charges' => dec($delivery, 2),
                'delivery_days' => !empty($meta['delivery_days']) ? (int) $meta['delivery_days'] : null,
                'payment_terms' => !empty($meta['payment_terms']) ? substr((string) $meta['payment_terms'], 0, 120) : null,
                'validity_days' => (int) ($meta['validity_days'] ?? 7),
                'notes' => $meta['notes'] ?? null,
                'attachment_path' => $meta['attachment_path'] ?? null,
                'status' => 'submitted',
                'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+' . (int) ($meta['validity_days'] ?? 7) . ' days')),
                'updated_at' => now(),
            ];

            if ($existing !== null) {
                $quoteId = (int) $existing['id'];
                $db->update('rfq_quotes', $quoteData, ['id' => $quoteId]);
                $db->delete('rfq_quote_items', ['quote_id' => $quoteId]);
            } else {
                $quoteData['created_at'] = now();
                $quoteId = $db->insert('rfq_quotes', $quoteData);
                $db->statement('UPDATE rfqs SET quote_count = quote_count + 1 WHERE id = :r', ['r' => $rfqId]);
            }

            foreach ($prepared as $item) {
                $item['quote_id'] = $quoteId;
                $db->insert('rfq_quote_items', $item);
            }

            $db->statement(
                "UPDATE rfq_invites SET status = 'quoted' WHERE rfq_id = :r AND seller_id = :s",
                ['r' => $rfqId, 's' => $sellerId]
            );

            return $quoteId;
        });

        $quote = Database::instance()->first('SELECT grand_total FROM rfq_quotes WHERE id = :id', ['id' => $quoteId]);
        NotificationService::dispatch((int) $rfq['buyer_id'], 'rfq_quote', [
            'body' => ($business['name'] ?? 'A seller') . ' quoted ' . money($quote['grand_total'] ?? 0) . ' on ' . $rfq['title'] . '.',
            'link' => '/dashboard/rfq/' . $rfqId,
            'entity_type' => 'rfq',
            'entity_id' => $rfqId,
            'vars' => [
                'title' => $rfq['title'],
                'seller' => $business['name'] ?? 'A seller',
                'amount' => money($quote['grand_total'] ?? 0),
            ],
        ]);

        AuditService::log('rfq_quote_submitted', 'rfq_quote', $quoteId, null, ['rfq_id' => $rfqId]);
        return ['ok' => true, 'quote_id' => $quoteId, 'message' => 'Your quote has been submitted.'];
    }

    public static function shortlist(int $quoteId, int $buyerId): array
    {
        $quote = Database::instance()->first(
            'SELECT q.*, r.buyer_id FROM rfq_quotes q INNER JOIN rfqs r ON r.id = q.rfq_id WHERE q.id = :id',
            ['id' => $quoteId]
        );
        if ($quote === null || (int) $quote['buyer_id'] !== $buyerId) {
            return ['ok' => false, 'error' => 'Quote not found.'];
        }
        $newStatus = $quote['status'] === 'shortlisted' ? 'submitted' : 'shortlisted';
        Database::instance()->update('rfq_quotes', ['status' => $newStatus, 'updated_at' => now()], ['id' => $quoteId]);
        return ['ok' => true, 'status' => $newStatus, 'message' => $newStatus === 'shortlisted' ? 'Quote shortlisted.' : 'Removed from shortlist.'];
    }

    /** Award the RFQ to one quote and create the order. */
    public static function award(int $rfqId, int $quoteId, int $buyerId): array
    {
        $db = Database::instance();

        try {
            $result = $db->transaction(static function (Database $db) use ($rfqId, $quoteId, $buyerId): array {
                $rfq = $db->lockRow('rfqs', $rfqId);
                if ($rfq === null || (int) $rfq['buyer_id'] !== $buyerId) {
                    return ['ok' => false, 'error' => 'RFQ not found.'];
                }
                if ($rfq['status'] === 'awarded') {
                    return ['ok' => false, 'error' => 'This RFQ has already been awarded.'];
                }
                if ($rfq['status'] === 'cancelled') {
                    return ['ok' => false, 'error' => 'This RFQ was cancelled.'];
                }

                $quote = $db->first('SELECT * FROM rfq_quotes WHERE id = :id AND rfq_id = :r', ['id' => $quoteId, 'r' => $rfqId]);
                if ($quote === null) {
                    return ['ok' => false, 'error' => 'Quote not found on this RFQ.'];
                }

                $items = $db->select(
                    'SELECT qi.*, ri.unit_id, ri.item_name FROM rfq_quote_items qi
                     INNER JOIN rfq_items ri ON ri.id = qi.rfq_item_id WHERE qi.quote_id = :q',
                    ['q' => $quoteId]
                );
                if ($items === []) {
                    return ['ok' => false, 'error' => 'This quote has no line items.'];
                }

                // A multi-line quote collapses into one order line. Deriving a
                // blended per-unit rate and multiplying back would reintroduce a
                // rounding error against the quoted figure, so the order is priced
                // as a LOT at the quote's exact total; the quantity is carried for
                // reference and for weighment.
                $totalQuantity = 0.0;
                foreach ($items as $item) {
                    $totalQuantity += (float) $item['offered_quantity'];
                }
                $rate = (float) $quote['total_amount'];
                $gstRate = (float) $quote['total_amount'] > 0
                    ? ((float) $quote['gst_amount'] / (float) $quote['total_amount']) * 100
                    : 0.0;

                $orderId = OrderService::create([
                    'buyer_id' => $buyerId,
                    'seller_id' => (int) $quote['seller_id'],
                    'source_type' => 'rfq',
                    'rfq_id' => $rfqId,
                    'quantity' => dec($totalQuantity, 3),
                    'unit_id' => (int) $items[0]['unit_id'],
                    'rate' => dec($rate, 4),
                    'price_basis' => 'lot',
                    'gst_rate' => dec($gstRate, 2),
                    'transport_charges' => $quote['delivery_charges'],
                    'payment_terms' => $rfq['payment_terms'],
                    'delivery_address' => $rfq['delivery_address'],
                    'description' => $rfq['title'],
                    'notes' => 'Awarded from RFQ ' . $rfq['reference'],
                ]);

                $db->update('rfqs', [
                    'status' => 'awarded',
                    'awarded_quote_id' => $quoteId,
                    'awarded_at' => now(),
                    'updated_at' => now(),
                ], ['id' => $rfqId]);

                $db->update('rfq_quotes', ['status' => 'awarded', 'order_id' => $orderId, 'updated_at' => now()], ['id' => $quoteId]);
                $db->statement(
                    "UPDATE rfq_quotes SET status = 'rejected', updated_at = :n WHERE rfq_id = :r AND id <> :id AND status <> 'withdrawn'",
                    ['n' => now(), 'r' => $rfqId, 'id' => $quoteId]
                );

                return ['ok' => true, 'order_id' => $orderId, 'rfq' => $rfq, 'quote' => $quote];
            });
        } catch (\Throwable $e) {
            logger()->error('RFQ award failed: ' . $e->getMessage(), ['rfq' => $rfqId]);
            return ['ok' => false, 'error' => 'The RFQ could not be awarded. Please try again.'];
        }

        if (!$result['ok']) {
            return $result;
        }

        NotificationService::dispatch((int) $result['quote']['seller_id'], 'rfq_awarded', [
            'body' => 'Your quote on "' . $result['rfq']['title'] . '" was awarded.',
            'link' => '/dashboard/orders/' . $result['order_id'],
            'entity_type' => 'rfq',
            'entity_id' => $rfqId,
        ]);

        $losers = Database::instance()->select(
            'SELECT seller_id FROM rfq_quotes WHERE rfq_id = :r AND id <> :q',
            ['r' => $rfqId, 'q' => $quoteId]
        );
        NotificationService::dispatchMany(
            array_map(static fn (array $r): int => (int) $r['seller_id'], $losers),
            'rfq_awarded',
            [
                'title' => 'RFQ awarded to another supplier',
                'body' => '"' . $result['rfq']['title'] . '" has been awarded.',
                'link' => '/rfq/' . $rfqId,
                'entity_type' => 'rfq',
                'entity_id' => $rfqId,
            ]
        );

        AuditService::log('rfq_awarded', 'rfq', $rfqId, null, ['quote_id' => $quoteId, 'order_id' => $result['order_id']]);
        return ['ok' => true, 'order_id' => $result['order_id'], 'message' => 'RFQ awarded and order created.'];
    }

    public static function close(int $rfqId, int $actorId, string $status = 'closed'): array
    {
        $rfq = Rfq::find($rfqId);
        if ($rfq === null) {
            return ['ok' => false, 'error' => 'RFQ not found.'];
        }
        Database::instance()->update('rfqs', ['status' => $status, 'updated_at' => now()], ['id' => $rfqId]);
        AuditService::log('rfq_' . $status, 'rfq', $rfqId);
        return ['ok' => true, 'message' => 'RFQ ' . label($status) . '.'];
    }

    /** Scheduler: flag RFQs closing within 24h, close the ones past their window. */
    public static function processDue(): array
    {
        $db = Database::instance();
        $soon = $db->statement(
            "UPDATE rfqs SET status = 'closing_soon', updated_at = :n
             WHERE status = 'open' AND closes_at IS NOT NULL AND closes_at BETWEEN :now AND :soon",
            ['n' => now(), 'now' => now(), 'soon' => gmdate('Y-m-d H:i:s', time() + 86400)]
        );
        $closed = $db->statement(
            "UPDATE rfqs SET status = 'closed', updated_at = :n
             WHERE status IN ('open','closing_soon') AND closes_at IS NOT NULL AND closes_at < :now",
            ['n' => now(), 'now' => now()]
        );
        return ['closing_soon' => $soon, 'closed' => $closed];
    }
}
