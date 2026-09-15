<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Model;
use App\Core\Paginator;
use App\Models\Order;
use App\Services\Payments\ManualGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\RazorpayGateway;

/**
 * Payment recording and gateway orchestration.
 *
 * The platform tracks payment *status* for every order whether the money moved
 * through a gateway or bank-to-bank. A payment only becomes "paid" when either
 * a gateway verified it or a human (seller / finance staff) confirmed it.
 */
final class PaymentService
{
    public const METHODS = [
        'upi' => 'UPI', 'bank_transfer' => 'Bank Transfer', 'neft' => 'NEFT', 'rtgs' => 'RTGS',
        'imps' => 'IMPS', 'cash' => 'Cash', 'cheque' => 'Cheque', 'credit' => 'Credit Terms',
        'razorpay' => 'Online (Razorpay)', 'wallet' => 'Wallet',
    ];

    public static function gateway(?string $name = null): PaymentGateway
    {
        $name ??= (string) SettingsService::get('payment_gateway', 'manual');
        return match ($name) {
            'razorpay' => new RazorpayGateway(),
            default => new ManualGateway(),
        };
    }

    public static function onlineAvailable(): bool
    {
        $gateway = self::gateway();
        return $gateway->isOnline() && $gateway->isConfigured();
    }

    /** Record a payment against an order (the common, manual path). */
    public static function record(int $orderId, int $payerId, array $data): array
    {
        $order = Order::find($orderId);
        if ($order === null) {
            return ['ok' => false, 'error' => 'Order not found.'];
        }
        if ((int) $order['buyer_id'] !== $payerId && (int) $order['seller_id'] !== $payerId && !\App\Core\Auth::isStaff()) {
            return ['ok' => false, 'error' => 'You are not part of this order.'];
        }

        $amount = dec($data['amount'] ?? 0, 2);
        if ((float) $amount <= 0) {
            return ['ok' => false, 'error' => 'Payment amount must be greater than zero.'];
        }

        $alreadyPaid = (float) self::totalPaid($orderId);
        $due = (float) $order['final_amount'] - $alreadyPaid;
        if ((float) $amount > $due + 0.01) {
            return [
                'ok' => false,
                'error' => 'That is more than the outstanding balance of ' . money($due) . '.',
            ];
        }

        $method = (string) ($data['method'] ?? 'bank_transfer');
        if (!isset(self::METHODS[$method])) {
            return ['ok' => false, 'error' => 'Unknown payment method.'];
        }

        $paymentId = Database::instance()->insert('payments', [
            'reference' => self::generateReference(),
            'order_id' => $orderId,
            'payer_id' => $payerId,
            'payee_id' => (int) $order['seller_id'],
            'purpose' => 'order',
            'amount' => $amount,
            'currency' => 'INR',
            'method' => $method,
            'gateway' => 'manual',
            'utr_number' => !empty($data['utr_number']) ? substr((string) $data['utr_number'], 0, 60) : null,
            'proof_path' => $data['proof_path'] ?? null,
            // Recorded by the payer is "processing" until the payee confirms.
            'status' => \App\Core\Auth::isStaff() ? 'paid' : 'processing',
            'paid_at' => \App\Core\Auth::isStaff() ? now() : null,
            'verified_by' => \App\Core\Auth::isStaff() ? \App\Core\Auth::id() : null,
            'verified_at' => \App\Core\Auth::isStaff() ? now() : null,
            'notes' => !empty($data['notes']) ? substr((string) $data['notes'], 0, 255) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::syncOrderPaymentStatus($orderId);

        NotificationService::dispatch((int) $order['seller_id'], 'payment_received', [
            'body' => money($amount) . ' recorded against order ' . $order['reference'] . '. Please confirm receipt.',
            'link' => '/dashboard/orders/' . $orderId,
            'entity_type' => 'order',
            'entity_id' => $orderId,
            'vars' => ['order' => $order['reference'], 'amount' => money($amount)],
        ]);

        AuditService::log('payment_recorded', 'payment', $paymentId, null, [
            'order_id' => $orderId,
            'amount' => $amount,
            'method' => $method,
        ]);

        return ['ok' => true, 'payment_id' => $paymentId, 'message' => 'Payment recorded. The seller will confirm receipt.'];
    }

    /** The payee (or finance staff) confirms the money actually arrived. */
    public static function confirm(int $paymentId, int $actorId): array
    {
        $payment = Database::instance()->first('SELECT * FROM payments WHERE id = :id', ['id' => $paymentId]);
        if ($payment === null) {
            return ['ok' => false, 'error' => 'Payment not found.'];
        }
        if ($payment['status'] === 'paid') {
            return ['ok' => false, 'error' => 'This payment is already confirmed.'];
        }

        $isStaff = \App\Core\Auth::isStaff();
        if (!$isStaff && (int) ($payment['payee_id'] ?? 0) !== $actorId) {
            return ['ok' => false, 'error' => 'Only the recipient can confirm this payment.'];
        }

        Database::instance()->update('payments', [
            'status' => 'paid',
            'paid_at' => now(),
            'verified_by' => $actorId,
            'verified_at' => now(),
            'updated_at' => now(),
        ], ['id' => $paymentId]);

        if (!empty($payment['order_id'])) {
            self::syncOrderPaymentStatus((int) $payment['order_id']);
            NotificationService::dispatch((int) $payment['payer_id'], 'payment_received', [
                'title' => 'Payment confirmed',
                'body' => 'Your payment of ' . money($payment['amount']) . ' was confirmed.',
                'link' => '/dashboard/orders/' . (int) $payment['order_id'],
                'entity_type' => 'order',
                'entity_id' => (int) $payment['order_id'],
            ]);
        }

        AuditService::log('payment_confirmed', 'payment', $paymentId);
        return ['ok' => true, 'message' => 'Payment confirmed.'];
    }

    public static function reject(int $paymentId, int $actorId, string $reason): array
    {
        $payment = Database::instance()->first('SELECT * FROM payments WHERE id = :id', ['id' => $paymentId]);
        if ($payment === null) {
            return ['ok' => false, 'error' => 'Payment not found.'];
        }
        if (!\App\Core\Auth::isStaff() && (int) ($payment['payee_id'] ?? 0) !== $actorId) {
            return ['ok' => false, 'error' => 'Only the recipient can reject this payment.'];
        }
        Database::instance()->update('payments', [
            'status' => 'failed',
            'failure_reason' => substr($reason, 0, 255),
            'updated_at' => now(),
        ], ['id' => $paymentId]);

        if (!empty($payment['order_id'])) {
            self::syncOrderPaymentStatus((int) $payment['order_id']);
        }
        AuditService::log('payment_rejected', 'payment', $paymentId, null, ['reason' => $reason]);
        return ['ok' => true, 'message' => 'Payment marked as failed.'];
    }

    /** Start an online payment through the active gateway. */
    public static function initiateOnline(int $orderId, int $payerId, string $amount): array
    {
        $gateway = self::gateway();
        if (!$gateway->isOnline() || !$gateway->isConfigured()) {
            return ['ok' => false, 'error' => 'Online payment is not enabled on this platform.'];
        }

        $order = Order::find($orderId);
        if ($order === null || (int) $order['buyer_id'] !== $payerId) {
            return ['ok' => false, 'error' => 'Order not found.'];
        }

        $amount = dec($amount, 2);
        $due = (float) $order['final_amount'] - (float) self::totalPaid($orderId);
        if ((float) $amount <= 0 || (float) $amount > $due + 0.01) {
            return ['ok' => false, 'error' => 'Enter an amount up to the outstanding balance of ' . money($due) . '.'];
        }

        $reference = self::generateReference();
        $paymentId = Database::instance()->insert('payments', [
            'reference' => $reference,
            'order_id' => $orderId,
            'payer_id' => $payerId,
            'payee_id' => (int) $order['seller_id'],
            'purpose' => 'order',
            'amount' => $amount,
            'method' => 'razorpay',
            'gateway' => $gateway->name(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = \App\Models\User::find($payerId) ?? [];
        $result = $gateway->initiate(
            ['reference' => $reference, 'amount' => $amount, 'order_id' => $orderId, 'payer_id' => $payerId, 'purpose' => 'order'],
            [
                'description' => 'Order ' . $order['reference'],
                'name' => $user['full_name'] ?? '',
                'email' => $user['email'] ?? '',
                'mobile' => $user['mobile'] ?? '',
            ]
        );

        if (!$result['ok']) {
            Database::instance()->update('payments', [
                'status' => 'failed',
                'failure_reason' => substr((string) $result['error'], 0, 255),
                'updated_at' => now(),
            ], ['id' => $paymentId]);
            return $result;
        }

        Database::instance()->update('payments', [
            'gateway_order_id' => $result['gateway_order_id'] ?? null,
            'status' => 'processing',
            'updated_at' => now(),
        ], ['id' => $paymentId]);

        return ['ok' => true, 'payment_id' => $paymentId, 'checkout' => $result['checkout']];
    }

    /** Handle the gateway callback after checkout. */
    public static function handleCallback(int $paymentId, array $payload): array
    {
        $payment = Database::instance()->first('SELECT * FROM payments WHERE id = :id', ['id' => $paymentId]);
        if ($payment === null) {
            return ['ok' => false, 'error' => 'Payment not found.'];
        }

        $gateway = self::gateway((string) $payment['gateway']);
        $verification = $gateway->verify($payload);

        if (!$verification['ok'] || !$verification['paid']) {
            Database::instance()->update('payments', [
                'status' => 'failed',
                'failure_reason' => substr((string) ($verification['error'] ?? 'Verification failed'), 0, 255),
                'updated_at' => now(),
            ], ['id' => $paymentId]);
            AuditService::log('payment_verification_failed', 'payment', $paymentId, null, ['error' => $verification['error'] ?? '']);
            return ['ok' => false, 'error' => $verification['error'] ?? 'Payment verification failed.'];
        }

        Database::instance()->update('payments', [
            'status' => 'paid',
            'gateway_payment_id' => $verification['gateway_payment_id'] ?? null,
            'paid_at' => now(),
            'verified_at' => now(),
            'updated_at' => now(),
        ], ['id' => $paymentId]);

        if (!empty($payment['order_id'])) {
            self::syncOrderPaymentStatus((int) $payment['order_id']);
        }

        AuditService::log('payment_paid', 'payment', $paymentId, null, ['gateway' => $payment['gateway']]);
        return ['ok' => true, 'message' => 'Payment successful.'];
    }

    public static function totalPaid(int $orderId): string
    {
        return dec(Database::instance()->scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = :o AND status = 'paid'",
            ['o' => $orderId],
            0
        ), 2);
    }

    /** Keep orders.payment_status consistent with the payments ledger. */
    public static function syncOrderPaymentStatus(int $orderId): void
    {
        $order = Order::find($orderId);
        if ($order === null) {
            return;
        }
        $paid = (float) self::totalPaid($orderId);
        $total = (float) $order['final_amount'];
        $processing = (int) Database::instance()->scalar(
            "SELECT COUNT(*) FROM payments WHERE order_id = :o AND status = 'processing'",
            ['o' => $orderId],
            0
        );

        $status = match (true) {
            $total > 0 && $paid >= $total - 0.01 => 'paid',
            $paid > 0 => 'partial',
            $processing > 0 => 'processing',
            default => 'pending',
        };

        Database::instance()->update('orders', [
            'payment_status' => $status,
            'amount_paid' => dec($paid, 2),
            'updated_at' => now(),
        ], ['id' => $orderId]);

        // A fully paid order that has been delivered can move itself on.
        if ($status === 'paid' && in_array($order['status'], ['payment_pending', 'delivered', 'weighment'], true)) {
            OrderService::changeStatus($orderId, 'paid', null, 'Payment completed in full');
        }
    }

    public static function paginate(array $filters, int $page, int $perPage = 25): Paginator
    {
        $sql = 'SELECT p.*, o.reference AS order_reference, payer.full_name AS payer_name,
                       payee.full_name AS payee_name
                FROM payments p
                LEFT JOIN orders o ON o.id = p.order_id
                INNER JOIN users payer ON payer.id = p.payer_id
                LEFT JOIN users payee ON payee.id = p.payee_id
                WHERE 1 = 1';
        $count = 'SELECT COUNT(*) FROM payments p WHERE 1 = 1';
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= ' AND (p.payer_id = :u OR p.payee_id = :u2)';
            $count .= ' AND (p.payer_id = :u OR p.payee_id = :u2)';
            $params['u'] = (int) $filters['user_id'];
            $params['u2'] = (int) $filters['user_id'];
        }
        if (!empty($filters['order_id'])) {
            $sql .= ' AND p.order_id = :order_id';
            $count .= ' AND p.order_id = :order_id';
            $params['order_id'] = (int) $filters['order_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND p.status = :status';
            $count .= ' AND p.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['method'])) {
            $sql .= ' AND p.method = :method';
            $count .= ' AND p.method = :method';
            $params['method'] = $filters['method'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (p.reference LIKE :q OR p.utr_number LIKE :q2 OR o.reference LIKE :q3)';
            $count .= ' AND p.reference LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
            $params['q3'] = '%' . $filters['q'] . '%';
        }

        return Model::paginateQuery($sql . ' ORDER BY p.id DESC', $params, $page, $perPage, $count);
    }

    public static function generateReference(): string
    {
        return 'PAY' . gmdate('ymd') . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
    }

    public static function summary(): array
    {
        $row = Database::instance()->first(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS paid,
                    COALESCE(SUM(CASE WHEN status IN ('pending','processing') THEN amount ELSE 0 END), 0) AS pending,
                    COALESCE(SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END), 0) AS failed
             FROM payments"
        ) ?? [];
        return $row;
    }
}
