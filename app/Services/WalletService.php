<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Model;
use App\Core\Paginator;

/**
 * Wallet with an immutable ledger.
 *
 * Balances are NEVER updated in isolation: every change writes a
 * `wallet_transactions` row carrying balance_before and balance_after, inside a
 * transaction that locks the wallet row. The stored balance is therefore always
 * reproducible from the ledger, and reconcile() proves it.
 */
final class WalletService
{
    public static function wallet(int $userId): array
    {
        $db = Database::instance();
        $wallet = $db->first('SELECT * FROM wallets WHERE user_id = :u', ['u' => $userId]);
        if ($wallet !== null) {
            return $wallet;
        }
        $id = $db->insert('wallets', [
            'user_id' => $userId,
            'balance' => '0.00',
            'locked_balance' => '0.00',
            'total_credited' => '0.00',
            'total_debited' => '0.00',
            'currency' => 'INR',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $db->first('SELECT * FROM wallets WHERE id = :id', ['id' => $id]) ?? [];
    }

    public static function balance(int $userId): string
    {
        return dec(self::wallet($userId)['balance'] ?? 0, 2);
    }

    /**
     * Post a ledger entry.
     *
     * @param string $type credit|debit|refund|commission|deposit|withdrawal|adjustment|payout|hold|release
     */
    public static function post(int $userId, string $direction, string $type, string $amount, array $context = []): array
    {
        $amount = dec($amount, 2);
        if ((float) $amount <= 0) {
            return ['ok' => false, 'error' => 'Amount must be greater than zero.'];
        }
        if (!in_array($direction, ['credit', 'debit'], true)) {
            return ['ok' => false, 'error' => 'Invalid ledger direction.'];
        }

        $db = Database::instance();

        try {
            return $db->transaction(static function (Database $db) use ($userId, $direction, $type, $amount, $context): array {
                $wallet = self::wallet($userId);
                $locked = $db->lockRow('wallets', (int) $wallet['id']);
                if ($locked === null) {
                    return ['ok' => false, 'error' => 'Wallet not found.'];
                }
                if ($locked['status'] !== 'active') {
                    return ['ok' => false, 'error' => 'This wallet is ' . $locked['status'] . '.'];
                }

                $before = (float) $locked['balance'];
                $delta = $direction === 'credit' ? (float) $amount : -((float) $amount);
                $after = $before + $delta;

                if ($after < 0) {
                    return ['ok' => false, 'error' => 'Insufficient wallet balance (available ' . money($before) . ').'];
                }

                $transactionId = $db->insert('wallet_transactions', [
                    'wallet_id' => (int) $locked['id'],
                    'user_id' => $userId,
                    'reference' => 'WTX' . gmdate('ymd') . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8)),
                    'direction' => $direction,
                    'type' => $type,
                    'amount' => $amount,
                    'balance_before' => dec($before, 2),
                    'balance_after' => dec($after, 2),
                    'order_id' => $context['order_id'] ?? null,
                    'payment_id' => $context['payment_id'] ?? null,
                    'commission_id' => $context['commission_id'] ?? null,
                    'description' => !empty($context['description']) ? substr((string) $context['description'], 0, 255) : null,
                    'created_by' => \App\Core\Auth::id(),
                    'created_at' => now(),
                ]);

                $db->update('wallets', [
                    'balance' => dec($after, 2),
                    'total_credited' => dec((float) $locked['total_credited'] + ($direction === 'credit' ? (float) $amount : 0), 2),
                    'total_debited' => dec((float) $locked['total_debited'] + ($direction === 'debit' ? (float) $amount : 0), 2),
                    'updated_at' => now(),
                ], ['id' => (int) $locked['id']]);

                return [
                    'ok' => true,
                    'transaction_id' => $transactionId,
                    'balance' => dec($after, 2),
                    'message' => ucfirst($direction) . ' of ' . money($amount) . ' posted.',
                ];
            });
        } catch (\Throwable $e) {
            logger()->error('Wallet post failed: ' . $e->getMessage(), ['user' => $userId, 'type' => $type]);
            return ['ok' => false, 'error' => 'The wallet entry could not be posted.'];
        }
    }

    public static function credit(int $userId, string $amount, string $type = 'credit', array $context = []): array
    {
        return self::post($userId, 'credit', $type, $amount, $context);
    }

    public static function debit(int $userId, string $amount, string $type = 'debit', array $context = []): array
    {
        return self::post($userId, 'debit', $type, $amount, $context);
    }

    public static function transactions(int $userId, int $page, int $perPage = 25): Paginator
    {
        return Model::paginateQuery(
            'SELECT t.*, o.reference AS order_reference FROM wallet_transactions t
             LEFT JOIN orders o ON o.id = t.order_id
             WHERE t.user_id = :u ORDER BY t.id DESC',
            ['u' => $userId],
            $page,
            $perPage,
            'SELECT COUNT(*) FROM wallet_transactions WHERE user_id = :u'
        );
    }

    /**
     * Prove the stored balance matches the ledger.
     * @return array{ok: bool, stored: string, computed: string, difference: string}
     */
    public static function reconcile(int $userId): array
    {
        $wallet = self::wallet($userId);
        $computed = (float) Database::instance()->scalar(
            "SELECT COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0)
             FROM wallet_transactions WHERE wallet_id = :w",
            ['w' => (int) $wallet['id']],
            0
        );
        $stored = (float) $wallet['balance'];
        return [
            'ok' => abs($stored - $computed) < 0.01,
            'stored' => dec($stored, 2),
            'computed' => dec($computed, 2),
            'difference' => dec($stored - $computed, 2),
        ];
    }

    public static function reconcileAll(): array
    {
        $wallets = Database::instance()->select('SELECT user_id FROM wallets');
        $mismatches = [];
        foreach ($wallets as $wallet) {
            $result = self::reconcile((int) $wallet['user_id']);
            if (!$result['ok']) {
                $mismatches[] = ['user_id' => (int) $wallet['user_id']] + $result;
            }
        }
        return ['checked' => count($wallets), 'mismatches' => $mismatches];
    }

    public static function isEnabled(): bool
    {
        return SettingsService::bool('wallet_enabled', false);
    }
}
