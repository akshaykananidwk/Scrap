<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Order;
use App\Models\Unit;

/**
 * Weighment and settlement.
 *
 * Scrap is sold on paper quantity but paid on weighbridge weight. This service
 * records the actual weight, computes the difference, and recalculates the
 * order's settlement amount on that actual weight — the number both parties
 * ultimately pay against.
 *
 *   Expected 10,000 KG  ·  Actual 9,850 KG  ·  Difference −150 KG (−1.5%)
 *   Final = 9,850 × rate_per_kg  (+ GST, charges, − deductions)
 */
final class WeighmentService
{
    /**
     * @param array{gross_weight_kg?:string, tare_weight_kg?:string, actual_weight_kg:string,
     *              weighbridge_name?:string, slip_number?:string, slip_path?:string, photo_path?:string,
     *              operator_name?:string, vehicle_number?:string, weighed_at?:string,
     *              deduction_amount?:string, deduction_reason?:string, delivery_id?:int} $data
     */
    public static function record(int $orderId, array $data, int $recordedBy): array
    {
        $order = Order::find($orderId);
        if ($order === null) {
            return ['ok' => false, 'error' => 'Order not found.'];
        }

        $actual = dec($data['actual_weight_kg'] ?? 0, 3);
        if ((float) $actual <= 0) {
            return ['ok' => false, 'error' => 'Actual weight must be greater than zero.'];
        }

        $expected = Unit::toKg($order['quantity'], (int) $order['unit_id']);
        if ($expected === null) {
            // The order is in a non-weight unit (pieces, lots): treat the ordered
            // quantity as the expected figure so the comparison is still meaningful.
            $expected = dec($order['quantity'], 3);
        }

        $difference = (float) $actual - (float) $expected;
        $differencePercent = (float) $expected > 0 ? ($difference / (float) $expected) * 100 : 0;

        $ratePerKg = self::ratePerKg($order);
        $deduction = dec($data['deduction_amount'] ?? 0, 2);
        $settled = max(0, ((float) $actual * (float) $ratePerKg) - (float) $deduction);

        $db = Database::instance();
        $weighmentId = $db->transaction(static function (Database $db) use (
            $orderId, $order, $data, $recordedBy, $expected, $actual, $difference, $differencePercent, $ratePerKg, $deduction, $settled
        ): int {
            $id = $db->insert('weighments', [
                'order_id' => $orderId,
                'delivery_id' => $data['delivery_id'] ?? null,
                'expected_weight_kg' => $expected,
                'gross_weight_kg' => !empty($data['gross_weight_kg']) ? dec($data['gross_weight_kg'], 3) : null,
                'tare_weight_kg' => !empty($data['tare_weight_kg']) ? dec($data['tare_weight_kg'], 3) : null,
                'actual_weight_kg' => $actual,
                'difference_kg' => dec($difference, 3),
                'difference_percent' => dec($differencePercent, 3),
                'rate_per_kg' => $ratePerKg,
                'settled_amount' => dec($settled, 2),
                'deduction_amount' => $deduction,
                'deduction_reason' => !empty($data['deduction_reason']) ? substr((string) $data['deduction_reason'], 0, 190) : null,
                'weighbridge_name' => !empty($data['weighbridge_name']) ? substr((string) $data['weighbridge_name'], 0, 150) : null,
                'slip_number' => !empty($data['slip_number']) ? substr((string) $data['slip_number'], 0, 60) : null,
                'slip_path' => $data['slip_path'] ?? null,
                'photo_path' => $data['photo_path'] ?? null,
                'operator_name' => !empty($data['operator_name']) ? substr((string) $data['operator_name'], 0, 120) : null,
                'vehicle_number' => !empty($data['vehicle_number']) ? strtoupper(substr((string) $data['vehicle_number'], 0, 20)) : null,
                'weighed_at' => $data['weighed_at'] ?? now(),
                'recorded_by' => $recordedBy,
                'status' => 'recorded',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Settlement is recomputed on the ACTUAL weight.
            $gstRate = (float) $order['gst_rate'];
            $discount = (float) $order['discount'];
            $taxable = max(0, $settled - $discount);
            $gstAmount = ($taxable * $gstRate) / 100;
            $charges = (float) $order['transport_charges'] + (float) $order['loading_charges'] + (float) $order['other_charges'];

            $db->update('orders', [
                'subtotal' => dec($settled, 2),
                'gst_amount' => dec($gstAmount, 2),
                'final_amount' => dec($taxable + $gstAmount + $charges, 2),
                'updated_at' => now(),
            ], ['id' => $orderId]);

            $db->insert('order_status_history', [
                'order_id' => $orderId,
                'from_status' => $order['status'],
                'to_status' => $order['status'],
                'changed_by' => $recordedBy,
                'notes' => sprintf(
                    'Weighment recorded: %s KG actual vs %s KG expected (%s%s KG). Settlement %s.',
                    $actual,
                    $expected,
                    $difference >= 0 ? '+' : '',
                    dec($difference, 3),
                    money($taxable + $gstAmount + $charges)
                ),
                'created_at' => now(),
            ]);

            return $id;
        });

        // Commission follows the settled value.
        OrderService::recalculate($orderId);

        $notifyId = $recordedBy === (int) $order['buyer_id'] ? (int) $order['seller_id'] : (int) $order['buyer_id'];
        NotificationService::dispatch($notifyId, 'weighment_recorded', [
            'body' => sprintf(
                'Actual weight %s KG recorded for order %s (%s%s KG vs expected). Settlement %s.',
                $actual,
                $order['reference'],
                $difference >= 0 ? '+' : '',
                dec($difference, 3),
                money($settled)
            ),
            'link' => '/dashboard/orders/' . $orderId,
            'entity_type' => 'order',
            'entity_id' => $orderId,
        ]);

        AuditService::log('weighment_recorded', 'order', $orderId, null, [
            'expected_kg' => $expected,
            'actual_kg' => $actual,
            'difference_kg' => dec($difference, 3),
            'settled_amount' => dec($settled, 2),
        ]);

        return [
            'ok' => true,
            'weighment_id' => $weighmentId,
            'expected_kg' => $expected,
            'actual_kg' => $actual,
            'difference_kg' => dec($difference, 3),
            'difference_percent' => dec($differencePercent, 3),
            'settled_amount' => dec($settled, 2),
            'message' => 'Weighment recorded and settlement recalculated.',
        ];
    }

    /** Normalise the order's rate into rupees per kilogram. */
    public static function ratePerKg(array $order): string
    {
        $rate = (float) $order['rate'];
        return match ($order['price_basis']) {
            'per_kg' => dec($rate, 4),
            'per_mt' => dec($rate / 1000, 4),
            'per_unit', 'lot' => (function () use ($order, $rate): string {
                $kg = Unit::toKg($order['quantity'], (int) $order['unit_id']);
                if ($kg === null || (float) $kg <= 0) {
                    return dec($rate, 4);
                }
                $total = $order['price_basis'] === 'lot' ? $rate : $rate * (float) $order['quantity'];
                return dec($total / (float) $kg, 4);
            })(),
            default => dec($rate / 1000, 4),
        };
    }

    public static function accept(int $weighmentId, int $userId): array
    {
        $weighment = Database::instance()->first('SELECT * FROM weighments WHERE id = :id', ['id' => $weighmentId]);
        if ($weighment === null) {
            return ['ok' => false, 'error' => 'Weighment not found.'];
        }
        $order = Order::find((int) $weighment['order_id']);
        if ($order === null) {
            return ['ok' => false, 'error' => 'Order not found.'];
        }
        if ((int) $order['buyer_id'] !== $userId && (int) $order['seller_id'] !== $userId) {
            return ['ok' => false, 'error' => 'You are not part of this order.'];
        }
        if ((int) $weighment['recorded_by'] === $userId) {
            return ['ok' => false, 'error' => 'The other party must confirm the weighment you recorded.'];
        }

        Database::instance()->update('weighments', [
            'status' => 'accepted',
            'accepted_by' => $userId,
            'accepted_at' => now(),
            'updated_at' => now(),
        ], ['id' => $weighmentId]);

        AuditService::log('weighment_accepted', 'weighment', $weighmentId);
        return ['ok' => true, 'message' => 'Weighment confirmed.'];
    }

    public static function dispute(int $weighmentId, int $userId, string $reason): array
    {
        $weighment = Database::instance()->first('SELECT * FROM weighments WHERE id = :id', ['id' => $weighmentId]);
        if ($weighment === null) {
            return ['ok' => false, 'error' => 'Weighment not found.'];
        }
        Database::instance()->update('weighments', ['status' => 'disputed', 'updated_at' => now()], ['id' => $weighmentId]);
        AuditService::log('weighment_disputed', 'weighment', $weighmentId, null, ['reason' => $reason]);
        return ['ok' => true, 'message' => 'Weighment marked as disputed. Raise a formal dispute to involve support.'];
    }

    /** Preview the settlement without writing anything (used by the AJAX calculator). */
    public static function preview(array $order, string $actualWeightKg, string $deduction = '0'): array
    {
        $expected = Unit::toKg($order['quantity'], (int) $order['unit_id']) ?? dec($order['quantity'], 3);
        $actual = dec($actualWeightKg, 3);
        $difference = (float) $actual - (float) $expected;
        $ratePerKg = self::ratePerKg($order);
        $settled = max(0, ((float) $actual * (float) $ratePerKg) - (float) dec($deduction, 2));
        $taxable = max(0, $settled - (float) $order['discount']);
        $gst = ($taxable * (float) $order['gst_rate']) / 100;
        $charges = (float) $order['transport_charges'] + (float) $order['loading_charges'] + (float) $order['other_charges'];

        return [
            'expected_kg' => $expected,
            'actual_kg' => $actual,
            'difference_kg' => dec($difference, 3),
            'difference_percent' => dec((float) $expected > 0 ? ($difference / (float) $expected) * 100 : 0, 3),
            'rate_per_kg' => $ratePerKg,
            'material_value' => dec($settled, 2),
            'gst_amount' => dec($gst, 2),
            'charges' => dec($charges, 2),
            'final_amount' => dec($taxable + $gst + $charges, 2),
            'final_amount_display' => money($taxable + $gst + $charges),
        ];
    }
}
