<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\PaymentService;
use App\Services\Payments\RazorpayGateway;

/**
 * Gateway callbacks.
 *
 * These endpoints are exempt from CSRF (they are called by the gateway, not by
 * a browser form) but every payload is signature-verified before anything is
 * marked paid — an unsigned or mis-signed callback can never settle an order.
 */
final class PaymentApiController extends BaseApiController
{
    public function callback(Request $request): Response
    {
        $paymentId = $request->paramInt('id');
        $result = PaymentService::handleCallback($paymentId, $request->all());

        if ($request->wantsJson()) {
            return $result['ok']
                ? $this->data(['paid' => true, 'message' => $result['message']])
                : $this->error((string) $result['error']);
        }

        $payment = Database::instance()->first('SELECT order_id FROM payments WHERE id = :id', ['id' => $paymentId]);
        $redirect = !empty($payment['order_id']) ? '/dashboard/orders/' . (int) $payment['order_id'] : '/dashboard/payments';

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return Response::redirect($redirect);
    }

    /** Razorpay server-to-server webhook. */
    public function webhook(Request $request): Response
    {
        $raw = file_get_contents('php://input') ?: '';
        $signature = (string) ($request->header('X-Razorpay-Signature') ?? '');

        $gateway = new RazorpayGateway();
        if (!$gateway->isConfigured() || !$gateway->verifyWebhook($raw, $signature)) {
            AuditService::log('payment_webhook_rejected', 'payment', null, null, ['reason' => 'signature mismatch']);
            return $this->error('Invalid webhook signature.', 401);
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return $this->error('Malformed webhook payload.');
        }

        $event = (string) ($payload['event'] ?? '');
        $entity = $payload['payload']['payment']['entity'] ?? [];
        $gatewayOrderId = (string) ($entity['order_id'] ?? '');
        $gatewayPaymentId = (string) ($entity['id'] ?? '');

        if ($gatewayOrderId === '') {
            return $this->data(['ignored' => true, 'event' => $event]);
        }

        $payment = Database::instance()->first(
            'SELECT * FROM payments WHERE gateway_order_id = :o LIMIT 1',
            ['o' => $gatewayOrderId]
        );
        if ($payment === null) {
            return $this->data(['ignored' => true, 'reason' => 'unknown order']);
        }

        if (in_array($event, ['payment.captured', 'payment.authorized'], true) && $payment['status'] !== 'paid') {
            Database::instance()->update('payments', [
                'status' => 'paid',
                'gateway_payment_id' => $gatewayPaymentId,
                'paid_at' => now(),
                'verified_at' => now(),
                'updated_at' => now(),
            ], ['id' => (int) $payment['id']]);

            if (!empty($payment['order_id'])) {
                PaymentService::syncOrderPaymentStatus((int) $payment['order_id']);
            }
            AuditService::log('payment_webhook_paid', 'payment', (int) $payment['id'], null, ['event' => $event]);
        }

        if ($event === 'payment.failed') {
            Database::instance()->update('payments', [
                'status' => 'failed',
                'failure_reason' => substr((string) ($entity['error_description'] ?? 'Payment failed'), 0, 255),
                'updated_at' => now(),
            ], ['id' => (int) $payment['id']]);
            AuditService::log('payment_webhook_failed', 'payment', (int) $payment['id']);
        }

        return $this->data(['processed' => true, 'event' => $event]);
    }
}
