<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Services\SettingsService;

/**
 * Off-platform settlement: UPI, NEFT/RTGS/IMPS, cash, cheque or credit terms.
 *
 * The buyer records what they paid (with a UTR and optional proof image) and the
 * seller confirms it. This is the default because most Indian scrap deals settle
 * bank-to-bank, and it keeps the platform out of money transmission.
 */
final class ManualGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function isOnline(): bool
    {
        return false;
    }

    public function initiate(array $payment, array $context = []): array
    {
        return [
            'ok' => true,
            'gateway_order_id' => 'manual-' . ($payment['reference'] ?? ''),
            'checkout' => [
                'type' => 'instructions',
                'upi_id' => (string) SettingsService::get('upi_id', ''),
                'bank' => [
                    'account_name' => (string) SettingsService::get('bank_account_name', ''),
                    'account_number' => (string) SettingsService::get('bank_account_number', ''),
                    'ifsc' => (string) SettingsService::get('bank_ifsc', ''),
                    'bank_name' => (string) SettingsService::get('bank_name', ''),
                ],
            ],
        ];
    }

    /** Manual payments are verified by a human (the seller or finance staff). */
    public function verify(array $payload): array
    {
        return ['ok' => true, 'paid' => false, 'error' => 'Manual payments are confirmed by the seller or platform finance team.'];
    }
}
