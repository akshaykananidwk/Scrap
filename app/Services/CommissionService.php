<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Order;

/**
 * Platform monetisation. Fees are derived from settings and recorded as
 * `commissions` rows so revenue is auditable line by line, never inferred.
 */
final class CommissionService
{
    /** Recompute the fees for an order. Idempotent: pending rows are replaced. */
    public static function bookForOrder(int $orderId): array
    {
        if (!SettingsService::bool('commission_enabled', true)) {
            return [];
        }
        $order = Order::find($orderId);
        if ($order === null) {
            return [];
        }

        $db = Database::instance();
        // Only pending rows are recalculated — invoiced or paid fees are immutable.
        $db->statement("DELETE FROM commissions WHERE order_id = :o AND status = 'pending'", ['o' => $orderId]);

        $base = (float) $order['subtotal'];
        if ($base <= 0) {
            return [];
        }

        $gstRate = (float) SettingsService::get('commission_gst_rate', '18.00');
        $party = (string) SettingsService::get('commission_party', 'seller');
        $booked = [];

        $percentage = (float) SettingsService::get('commission_percentage', '0');
        $fixed = (float) SettingsService::get('commission_fixed', '0');
        if ($percentage > 0 || $fixed > 0) {
            $parties = $party === 'both' ? ['seller', 'buyer'] : [$party];
            foreach ($parties as $forParty) {
                $share = $party === 'both' ? 0.5 : 1.0;
                $booked[] = self::record($orderId, $order, $forParty, 'sale_commission', $base, $percentage * $share, $fixed * $share, $gstRate);
            }
        }

        $buyerFee = (float) SettingsService::get('buyer_fee_percentage', '0');
        if ($buyerFee > 0) {
            $booked[] = self::record($orderId, $order, 'buyer', 'buyer_fee', $base, $buyerFee, 0, $gstRate);
        }

        $sellerFee = (float) SettingsService::get('seller_fee_percentage', '0');
        if ($sellerFee > 0) {
            $booked[] = self::record($orderId, $order, 'seller', 'seller_fee', $base, $sellerFee, 0, $gstRate);
        }

        if ($order['source_type'] === 'auction') {
            $auctionFee = (float) SettingsService::get('auction_fee_percentage', '0');
            if ($auctionFee > 0) {
                $booked[] = self::record($orderId, $order, 'seller', 'auction_fee', $base, $auctionFee, 0, $gstRate);
            }
        }

        return array_filter($booked);
    }

    private static function record(
        int $orderId,
        array $order,
        string $party,
        string $feeType,
        float $base,
        float $percentage,
        float $fixed,
        float $gstRate
    ): ?int {
        $amount = (($base * $percentage) / 100) + $fixed;

        $minimum = (float) SettingsService::get('commission_min_amount', '0');
        $maximum = (float) SettingsService::get('commission_max_amount', '0');
        if ($minimum > 0 && $amount < $minimum) {
            $amount = $minimum;
        }
        if ($maximum > 0 && $amount > $maximum) {
            $amount = $maximum;
        }

        // An active subscription can discount the platform commission.
        $userId = $party === 'buyer' ? (int) $order['buyer_id'] : (int) $order['seller_id'];
        $discount = self::subscriptionDiscount($userId);
        if ($discount > 0 && $feeType === 'sale_commission') {
            $amount -= ($amount * $discount) / 100;
        }

        if ($amount <= 0) {
            return null;
        }

        $gstAmount = ($amount * $gstRate) / 100;

        return Database::instance()->insert('commissions', [
            'order_id' => $orderId,
            'auction_id' => $order['auction_id'] ?? null,
            'listing_id' => $order['listing_id'] ?? null,
            'user_id' => $userId,
            'party' => $party,
            'fee_type' => $feeType,
            'base_amount' => dec($base, 2),
            'percentage' => dec($percentage, 3),
            'fixed_amount' => dec($fixed, 2),
            'amount' => dec($amount, 2),
            'gst_amount' => dec($gstAmount, 2),
            'total_amount' => dec($amount + $gstAmount, 2),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function subscriptionDiscount(int $userId): float
    {
        $row = Database::instance()->first(
            "SELECT p.commission_discount FROM subscriptions s
             INNER JOIN plans p ON p.id = s.plan_id
             WHERE s.user_id = :u AND s.status = 'active' AND (s.ends_at IS NULL OR s.ends_at > :now)
             ORDER BY p.commission_discount DESC LIMIT 1",
            ['u' => $userId, 'now' => now()]
        );
        return (float) ($row['commission_discount'] ?? 0);
    }

    public static function forOrder(int $orderId): array
    {
        return Database::instance()->select(
            'SELECT c.*, u.full_name FROM commissions c LEFT JOIN users u ON u.id = c.user_id
             WHERE c.order_id = :o ORDER BY c.id',
            ['o' => $orderId]
        );
    }

    public static function totalForOrder(int $orderId): string
    {
        return dec(Database::instance()->scalar(
            "SELECT COALESCE(SUM(total_amount), 0) FROM commissions WHERE order_id = :o AND status <> 'cancelled'",
            ['o' => $orderId],
            0
        ), 2);
    }

    public static function markInvoiced(int $orderId): int
    {
        return Database::instance()->statement(
            "UPDATE commissions SET status = 'invoiced', updated_at = :n WHERE order_id = :o AND status = 'pending'",
            ['n' => now(), 'o' => $orderId]
        );
    }

    public static function cancelForOrder(int $orderId): int
    {
        return Database::instance()->statement(
            "UPDATE commissions SET status = 'cancelled', updated_at = :n WHERE order_id = :o AND status IN ('pending','invoiced')",
            ['n' => now(), 'o' => $orderId]
        );
    }

    /** One-off fee not tied to an order (featured listing, verification, lead). */
    public static function charge(int $userId, string $feeType, float $amount, ?int $listingId = null, string $notes = ''): int
    {
        $gstRate = (float) SettingsService::get('commission_gst_rate', '18.00');
        $gstAmount = ($amount * $gstRate) / 100;
        return Database::instance()->insert('commissions', [
            'listing_id' => $listingId,
            'user_id' => $userId,
            'party' => 'seller',
            'fee_type' => $feeType,
            'base_amount' => dec($amount, 2),
            'percentage' => '0.000',
            'fixed_amount' => dec($amount, 2),
            'amount' => dec($amount, 2),
            'gst_amount' => dec($gstAmount, 2),
            'total_amount' => dec($amount + $gstAmount, 2),
            'status' => 'pending',
            'notes' => substr($notes, 0, 255),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function revenueSummary(?string $from = null, ?string $to = null): array
    {
        $params = [];
        $where = "WHERE status <> 'cancelled'";
        if ($from !== null) {
            $where .= ' AND created_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }
        if ($to !== null) {
            $where .= ' AND created_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }
        $row = Database::instance()->first(
            "SELECT COUNT(*) AS entries,
                    COALESCE(SUM(amount), 0) AS commission,
                    COALESCE(SUM(gst_amount), 0) AS gst,
                    COALESCE(SUM(total_amount), 0) AS total,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) AS collected,
                    COALESCE(SUM(CASE WHEN status IN ('pending','invoiced') THEN total_amount ELSE 0 END), 0) AS outstanding
             FROM commissions {$where}",
            $params
        ) ?? [];
        return $row;
    }

    public static function byFeeType(?string $from = null, ?string $to = null): array
    {
        $params = [];
        $where = "WHERE status <> 'cancelled'";
        if ($from !== null) {
            $where .= ' AND created_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }
        if ($to !== null) {
            $where .= ' AND created_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }
        return Database::instance()->select(
            "SELECT fee_type, COUNT(*) AS entries, COALESCE(SUM(total_amount), 0) AS total
             FROM commissions {$where} GROUP BY fee_type ORDER BY total DESC",
            $params
        );
    }
}
