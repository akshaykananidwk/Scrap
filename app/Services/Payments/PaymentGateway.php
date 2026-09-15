<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * A payment gateway. The platform does not hold user money by default: the
 * ManualGateway simply records what the two parties settled between themselves.
 * Turning on a real gateway is a settings change, not a code change.
 */
interface PaymentGateway
{
    public function name(): string;

    public function isConfigured(): bool;

    /** Does this gateway redirect/checkout, or is it a record-only method? */
    public function isOnline(): bool;

    /**
     * Start a payment.
     * @return array{ok: bool, gateway_order_id?: string, checkout?: array, error?: string}
     */
    public function initiate(array $payment, array $context = []): array;

    /**
     * Verify a callback/webhook payload.
     * @return array{ok: bool, paid: bool, gateway_payment_id?: string, error?: string}
     */
    public function verify(array $payload): array;
}
