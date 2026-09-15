<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Services\SettingsService;

/**
 * Razorpay Orders API.
 *
 * Activates only when a key id and secret are saved in Admin → Settings →
 * Payments. Until then isConfigured() is false and the UI offers manual
 * settlement instead of showing a dead "Pay online" button.
 *
 * Signature verification follows Razorpay's documented scheme:
 *   expected = HMAC_SHA256(razorpay_order_id + "|" + razorpay_payment_id, key_secret)
 */
final class RazorpayGateway implements PaymentGateway
{
    private const API_BASE = 'https://api.razorpay.com/v1';

    public function name(): string
    {
        return 'razorpay';
    }

    public function isConfigured(): bool
    {
        return SettingsService::configured('razorpay_key_id', 'razorpay_key_secret');
    }

    public function isOnline(): bool
    {
        return true;
    }

    public function initiate(array $payment, array $context = []): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Razorpay is not configured.'];
        }

        // Razorpay works in paise.
        $amountPaise = (int) round(((float) $payment['amount']) * 100);
        if ($amountPaise < 100) {
            return ['ok' => false, 'error' => 'Minimum online payment is ₹1.'];
        }

        $response = $this->request('POST', '/orders', [
            'amount' => $amountPaise,
            'currency' => 'INR',
            'receipt' => substr((string) $payment['reference'], 0, 40),
            'notes' => [
                'order_id' => (string) ($payment['order_id'] ?? ''),
                'payer_id' => (string) ($payment['payer_id'] ?? ''),
                'purpose' => (string) ($payment['purpose'] ?? 'order'),
            ],
        ]);

        if (!$response['ok']) {
            return ['ok' => false, 'error' => $response['error']];
        }

        return [
            'ok' => true,
            'gateway_order_id' => (string) ($response['data']['id'] ?? ''),
            'checkout' => [
                'type' => 'razorpay',
                'key' => (string) SettingsService::get('razorpay_key_id', ''),
                'amount' => $amountPaise,
                'currency' => 'INR',
                'order_id' => (string) ($response['data']['id'] ?? ''),
                'name' => (string) SettingsService::get('site_name', 'ScrapX'),
                'description' => substr((string) ($context['description'] ?? 'Marketplace payment'), 0, 120),
                'prefill' => [
                    'name' => (string) ($context['name'] ?? ''),
                    'email' => (string) ($context['email'] ?? ''),
                    'contact' => (string) ($context['mobile'] ?? ''),
                ],
            ],
        ];
    }

    public function verify(array $payload): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'paid' => false, 'error' => 'Razorpay is not configured.'];
        }

        $orderId = (string) ($payload['razorpay_order_id'] ?? '');
        $paymentId = (string) ($payload['razorpay_payment_id'] ?? '');
        $signature = (string) ($payload['razorpay_signature'] ?? '');

        if ($orderId === '' || $paymentId === '' || $signature === '') {
            return ['ok' => false, 'paid' => false, 'error' => 'Incomplete payment response.'];
        }

        $secret = (string) SettingsService::get('razorpay_key_secret', '');
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);

        if (!hash_equals($expected, $signature)) {
            return ['ok' => false, 'paid' => false, 'error' => 'Payment signature verification failed.'];
        }

        // Signature proves the response is genuine; confirm the captured state too.
        $fetched = $this->request('GET', '/payments/' . rawurlencode($paymentId));
        if (!$fetched['ok']) {
            return ['ok' => false, 'paid' => false, 'error' => $fetched['error']];
        }
        $status = (string) ($fetched['data']['status'] ?? '');

        return [
            'ok' => true,
            'paid' => in_array($status, ['captured', 'authorized'], true),
            'gateway_payment_id' => $paymentId,
            'status' => $status,
            'method' => (string) ($fetched['data']['method'] ?? ''),
        ];
    }

    /** Verify a webhook body against the configured webhook secret. */
    public function verifyWebhook(string $rawBody, string $signature): bool
    {
        $secret = (string) SettingsService::get('razorpay_webhook_secret', '');
        if ($secret === '' || $signature === '') {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    /** @return array{ok: bool, data?: array, error?: string} */
    private function request(string $method, string $path, array $body = []): array
    {
        $ch = curl_init(self::API_BASE . $path);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => SettingsService::get('razorpay_key_id', '') . ':' . SettingsService::get('razorpay_key_secret', ''),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => 'Razorpay request failed: ' . $error];
        }
        $data = json_decode((string) $response, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'Unexpected Razorpay response.'];
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => (string) ($data['error']['description'] ?? ('Razorpay HTTP ' . $status))];
        }
        return ['ok' => true, 'data' => $data];
    }
}
