<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Models\Business;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Unit;

/**
 * Creates and advances orders. Every entry point into the marketplace — a won
 * auction, an accepted offer, an awarded RFQ quote, a direct fixed-price buy —
 * converges here so the money maths and the status machine exist exactly once.
 */
final class OrderService
{
    /**
     * @param array{buyer_id:int, seller_id:int, source_type:string, quantity:string|float, unit_id:int,
     *              rate:string|float, price_basis?:string, gst_rate?:string|float, listing_id?:int,
     *              auction_id?:int, offer_id?:int, rfq_id?:int, requirement_id?:int,
     *              transport_charges?:string, loading_charges?:string, other_charges?:string,
     *              discount?:string, payment_terms?:string, loading_by?:string, transport_by?:string,
     *              delivery_address?:string, notes?:string} $data
     */
    public static function create(array $data): int
    {
        $db = Database::instance();

        return $db->transaction(static function (Database $db) use ($data): int {
            $buyerId = (int) $data['buyer_id'];
            $sellerId = (int) $data['seller_id'];
            if ($buyerId === $sellerId) {
                throw new HttpException(422, 'A buyer and seller cannot be the same account.');
            }

            $unitId = (int) $data['unit_id'];
            $quantity = dec($data['quantity'], 3);
            $rate = dec($data['rate'], 4);
            $basis = $data['price_basis'] ?? 'per_mt';

            $amounts = self::calculate([
                'quantity' => $quantity,
                'unit_id' => $unitId,
                'rate' => $rate,
                'price_basis' => $basis,
                'gst_rate' => $data['gst_rate'] ?? '0',
                'transport_charges' => $data['transport_charges'] ?? '0',
                'loading_charges' => $data['loading_charges'] ?? '0',
                'other_charges' => $data['other_charges'] ?? '0',
                'discount' => $data['discount'] ?? '0',
            ]);

            $buyerBusiness = Business::forUser($buyerId);
            $sellerBusiness = Business::forUser($sellerId);
            $listing = !empty($data['listing_id']) ? Listing::find((int) $data['listing_id']) : null;

            $orderId = $db->insert('orders', [
                'reference' => Order::generateReference(),
                'buyer_id' => $buyerId,
                'seller_id' => $sellerId,
                'buyer_business_id' => $buyerBusiness['id'] ?? null,
                'seller_business_id' => $sellerBusiness['id'] ?? null,
                'source_type' => $data['source_type'] ?? 'manual',
                'listing_id' => $data['listing_id'] ?? null,
                'auction_id' => $data['auction_id'] ?? null,
                'offer_id' => $data['offer_id'] ?? null,
                'rfq_id' => $data['rfq_id'] ?? null,
                'requirement_id' => $data['requirement_id'] ?? null,
                'quantity' => $quantity,
                'unit_id' => $unitId,
                'rate' => $rate,
                'price_basis' => $basis,
                'subtotal' => $amounts['subtotal'],
                'gst_rate' => dec($data['gst_rate'] ?? 0, 2),
                'gst_amount' => $amounts['gst_amount'],
                'transport_charges' => dec($data['transport_charges'] ?? 0, 2),
                'loading_charges' => dec($data['loading_charges'] ?? 0, 2),
                'other_charges' => dec($data['other_charges'] ?? 0, 2),
                'discount' => dec($data['discount'] ?? 0, 2),
                'total_amount' => $amounts['total'],
                'final_amount' => $amounts['total'],
                'loading_by' => $data['loading_by'] ?? ($listing['loading_by'] ?? 'buyer'),
                'transport_by' => $data['transport_by'] ?? ($listing['transport_by'] ?? 'buyer'),
                'payment_terms' => $data['payment_terms'] ?? ($listing['payment_terms'] ?? 'advance'),
                'payment_status' => 'pending',
                'pickup_address' => $data['pickup_address'] ?? ($listing['pickup_address'] ?? null),
                'delivery_address' => $data['delivery_address'] ?? ($buyerBusiness['address_line1'] ?? null),
                'delivery_city' => $data['delivery_city'] ?? ($buyerBusiness['city_name'] ?? null),
                'delivery_state' => $data['delivery_state'] ?? ($buyerBusiness['state_name'] ?? null),
                'delivery_pincode' => $data['delivery_pincode'] ?? ($buyerBusiness['pincode'] ?? null),
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'is_demo' => (int) ($listing['is_demo'] ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $db->insert('order_items', [
                'order_id' => $orderId,
                'material_id' => $listing['material_id'] ?? null,
                'grade_id' => $listing['grade_id'] ?? null,
                'description' => substr((string) ($data['description'] ?? $listing['title'] ?? 'Scrap material'), 0, 255),
                'hsn_code' => $listing['hsn_code'] ?? null,
                'quantity' => $quantity,
                'unit_id' => $unitId,
                'rate' => $rate,
                'amount' => $amounts['subtotal'],
                'gst_rate' => dec($data['gst_rate'] ?? 0, 2),
                'gst_amount' => $amounts['gst_amount'],
                'created_at' => now(),
            ]);

            $db->insert('order_status_history', [
                'order_id' => $orderId,
                'from_status' => null,
                'to_status' => 'pending',
                'changed_by' => \App\Core\Auth::id(),
                'notes' => 'Order created from ' . label((string) ($data['source_type'] ?? 'manual')),
                'created_at' => now(),
            ]);

            // Commission is booked at creation so the platform ledger is never
            // dependent on someone remembering to run a report later.
            CommissionService::bookForOrder($orderId);

            AuditService::log('order_created', 'order', $orderId, null, [
                'buyer_id' => $buyerId,
                'seller_id' => $sellerId,
                'amount' => $amounts['total'],
            ]);

            return $orderId;
        });
    }

    /**
     * Money maths, in one place, on DECIMAL strings.
     * @return array{subtotal:string, gst_amount:string, charges:string, total:string, weight_kg:?string}
     */
    public static function calculate(array $input): array
    {
        $quantity = (float) dec($input['quantity'] ?? 0, 3);
        $rate = (float) dec($input['rate'] ?? 0, 4);
        $basis = $input['price_basis'] ?? 'per_mt';
        $unitId = (int) ($input['unit_id'] ?? 0);
        $weightKg = $unitId > 0 ? Unit::toKg($quantity, $unitId) : null;

        $subtotal = match ($basis) {
            'per_kg' => $weightKg !== null ? (float) $weightKg * $rate : $quantity * $rate,
            'per_mt' => $weightKg !== null ? ((float) $weightKg / 1000) * $rate : $quantity * $rate,
            'per_unit' => $quantity * $rate,
            'lot' => $rate,
            default => $quantity * $rate,
        };

        $gstRate = (float) dec($input['gst_rate'] ?? 0, 2);
        $discount = (float) dec($input['discount'] ?? 0, 2);
        $taxable = max(0, $subtotal - $discount);
        $gstAmount = ($taxable * $gstRate) / 100;

        $charges = (float) dec($input['transport_charges'] ?? 0, 2)
            + (float) dec($input['loading_charges'] ?? 0, 2)
            + (float) dec($input['other_charges'] ?? 0, 2);

        return [
            'subtotal' => dec($subtotal, 2),
            'taxable' => dec($taxable, 2),
            'gst_amount' => dec($gstAmount, 2),
            'charges' => dec($charges, 2),
            'total' => dec($taxable + $gstAmount + $charges, 2),
            'weight_kg' => $weightKg,
        ];
    }

    /** Recalculate an order's money after a change (weighment, charges, discount). */
    public static function recalculate(int $orderId): array
    {
        $order = Order::findOrFail($orderId);
        $amounts = self::calculate([
            'quantity' => $order['quantity'],
            'unit_id' => (int) $order['unit_id'],
            'rate' => $order['rate'],
            'price_basis' => $order['price_basis'],
            'gst_rate' => $order['gst_rate'],
            'transport_charges' => $order['transport_charges'],
            'loading_charges' => $order['loading_charges'],
            'other_charges' => $order['other_charges'],
            'discount' => $order['discount'],
        ]);

        Database::instance()->update('orders', [
            'subtotal' => $amounts['subtotal'],
            'gst_amount' => $amounts['gst_amount'],
            'total_amount' => $amounts['total'],
            'final_amount' => $amounts['total'],
            'updated_at' => now(),
        ], ['id' => $orderId]);

        CommissionService::bookForOrder($orderId);
        return $amounts;
    }

    /** Move an order to a new status, enforcing the state machine. */
    public static function changeStatus(int $orderId, string $newStatus, ?int $actorId, string $notes = ''): array
    {
        $order = Order::findOrFail($orderId);
        $from = (string) $order['status'];

        if ($from === $newStatus) {
            return ['ok' => false, 'error' => 'The order is already ' . label($newStatus) . '.'];
        }
        if (!isset(Order::FLOW[$newStatus])) {
            return ['ok' => false, 'error' => 'Unknown order status.'];
        }
        if (!Order::canTransitionTo($from, $newStatus)) {
            return [
                'ok' => false,
                'error' => sprintf(
                    'An order that is %s cannot move to %s. Allowed: %s.',
                    label($from),
                    label($newStatus),
                    implode(', ', array_map('label', Order::FLOW[$from][1])) ?: 'none'
                ),
            ];
        }

        $db = Database::instance();
        $db->transaction(static function (Database $db) use ($orderId, $order, $from, $newStatus, $actorId, $notes): void {
            $update = ['status' => $newStatus, 'updated_at' => now()];
            if ($newStatus === 'completed') {
                $update['completed_at'] = now();
            }
            if ($newStatus === 'paid') {
                $update['payment_status'] = 'paid';
                $update['amount_paid'] = $order['final_amount'];
            }
            if ($newStatus === 'cancelled') {
                $update['cancelled_by'] = $actorId;
                $update['cancel_reason'] = substr($notes, 0, 255);
            }
            $db->update('orders', $update, ['id' => $orderId]);

            $db->insert('order_status_history', [
                'order_id' => $orderId,
                'from_status' => $from,
                'to_status' => $newStatus,
                'changed_by' => $actorId,
                'notes' => substr($notes, 0, 255),
                'created_at' => now(),
            ]);

            if ($newStatus === 'completed') {
                CommissionService::markInvoiced($orderId);
                Business::refreshStats((int) ($order['seller_business_id'] ?? 0));
                Business::refreshStats((int) ($order['buyer_business_id'] ?? 0));
            }
            if ($newStatus === 'cancelled') {
                CommissionService::cancelForOrder($orderId);
                // Return the material to the marketplace.
                if (!empty($order['listing_id'])) {
                    $db->statement(
                        "UPDATE listings SET status = 'active', sold_at = NULL, updated_at = :n
                         WHERE id = :l AND status = 'sold'",
                        ['n' => now(), 'l' => (int) $order['listing_id']]
                    );
                }
            }
        });

        AuditService::log('order_status_changed', 'order', $orderId, ['status' => $from], ['status' => $newStatus], $notes);

        foreach ([(int) $order['buyer_id'], (int) $order['seller_id']] as $userId) {
            if ($userId !== $actorId) {
                NotificationService::dispatch($userId, 'order_status', [
                    'body' => 'Order ' . $order['reference'] . ' is now ' . label($newStatus) . '.',
                    'link' => '/dashboard/orders/' . $orderId,
                    'entity_type' => 'order',
                    'entity_id' => $orderId,
                    'vars' => ['order' => $order['reference'], 'status' => label($newStatus)],
                ]);
            }
        }

        return ['ok' => true, 'status' => $newStatus, 'message' => 'Order marked as ' . label($newStatus) . '.'];
    }

    /** Both parties and platform staff may view an order; nobody else. */
    public static function canView(array $order, ?array $user): bool
    {
        if ($user === null) {
            return false;
        }
        if (\App\Core\Auth::isStaff()) {
            return true;
        }
        return (int) $order['buyer_id'] === (int) $user['id'] || (int) $order['seller_id'] === (int) $user['id'];
    }

    public static function role(array $order, int $userId): string
    {
        if ((int) $order['buyer_id'] === $userId) {
            return 'buyer';
        }
        if ((int) $order['seller_id'] === $userId) {
            return 'seller';
        }
        return 'observer';
    }
}
