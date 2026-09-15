<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Model;
use App\Core\Paginator;

/**
 * Risk scoring.
 *
 * Deliberately rule-based and transparent: each rule explains itself in the
 * admin dashboard, and a flag never auto-suspends anyone — a human decides.
 */
final class FraudService
{
    /** rule => [label, severity, score] */
    public const RULES = [
        'duplicate_mobile' => ['Same mobile number as another account', 'high', 30],
        'shared_ip_signup' => ['Multiple accounts registered from one IP', 'medium', 15],
        'shared_gstin' => ['GSTIN already used by another business', 'critical', 40],
        'rapid_bidding' => ['Abnormal bidding velocity', 'medium', 20],
        'self_bidding_ring' => ['Bids only ever on one seller', 'high', 30],
        'repeated_cancellations' => ['Repeatedly cancels orders', 'high', 25],
        'failed_payments' => ['Multiple failed payments', 'medium', 15],
        'won_not_paid' => ['Wins auctions but does not complete', 'critical', 35],
        'new_account_high_value' => ['Very new account transacting at high value', 'low', 10],
        'kyc_mismatch' => ['KYC documents rejected more than once', 'high', 25],
    ];

    /** Run every rule for one user and persist the flags. */
    public static function evaluate(int $userId): array
    {
        $db = Database::instance();
        $user = \App\Models\User::find($userId);
        if ($user === null) {
            return ['score' => 0, 'flags' => []];
        }

        $flags = [];

        // Same mobile on another (non-deleted) account.
        $duplicateMobile = (int) $db->scalar(
            'SELECT COUNT(*) FROM users WHERE mobile = :m AND id <> :u AND deleted_at IS NULL',
            ['m' => $user['mobile'], 'u' => $userId],
            0
        );
        if ($duplicateMobile > 0) {
            $flags[] = self::flag('duplicate_mobile', "{$duplicateMobile} other account(s) share this mobile number.");
        }

        // Registration IP shared with several accounts.
        if (!empty($user['created_ip'])) {
            $sharedIp = (int) $db->scalar(
                'SELECT COUNT(*) FROM users WHERE created_ip = :ip AND id <> :u AND deleted_at IS NULL',
                ['ip' => $user['created_ip'], 'u' => $userId],
                0
            );
            if ($sharedIp >= 3) {
                $flags[] = self::flag('shared_ip_signup', "{$sharedIp} other accounts registered from IP {$user['created_ip']}.");
            }
        }

        // GSTIN reused across businesses.
        $business = \App\Models\Business::forUser($userId);
        if ($business !== null && !empty($business['gstin'])) {
            $sharedGstin = (int) $db->scalar(
                'SELECT COUNT(*) FROM businesses WHERE gstin = :g AND id <> :b AND deleted_at IS NULL',
                ['g' => $business['gstin'], 'b' => (int) $business['id']],
                0
            );
            if ($sharedGstin > 0) {
                $flags[] = self::flag('shared_gstin', "GSTIN {$business['gstin']} is used by {$sharedGstin} other business(es).");
            }
        }

        // Bidding velocity in the last hour.
        $recentBids = (int) $db->scalar(
            'SELECT COUNT(*) FROM bids WHERE user_id = :u AND created_at >= :since',
            ['u' => $userId, 'since' => gmdate('Y-m-d H:i:s', time() - 3600)],
            0
        );
        if ($recentBids > 60) {
            $flags[] = self::flag('rapid_bidding', "{$recentBids} bids placed in the last hour.");
        }

        // Bids concentrated on a single seller (possible shill ring).
        $bidSpread = $db->first(
            'SELECT COUNT(*) AS total, COUNT(DISTINCT a.owner_id) AS sellers
             FROM bids b INNER JOIN auctions a ON a.id = b.auction_id WHERE b.user_id = :u',
            ['u' => $userId]
        ) ?? ['total' => 0, 'sellers' => 0];
        if ((int) $bidSpread['total'] >= 15 && (int) $bidSpread['sellers'] === 1) {
            $flags[] = self::flag('self_bidding_ring', "All {$bidSpread['total']} bids were placed on auctions from a single seller.");
        }

        // Cancellation behaviour.
        $cancelled = (int) $db->scalar(
            "SELECT COUNT(*) FROM orders WHERE cancelled_by = :u AND status = 'cancelled'",
            ['u' => $userId],
            0
        );
        if ($cancelled >= 3) {
            $flags[] = self::flag('repeated_cancellations', "{$cancelled} orders cancelled by this account.");
        }

        // Failed payments.
        $failed = (int) $db->scalar(
            "SELECT COUNT(*) FROM payments WHERE payer_id = :u AND status = 'failed'",
            ['u' => $userId],
            0
        );
        if ($failed >= 3) {
            $flags[] = self::flag('failed_payments', "{$failed} failed payment attempts.");
        }

        // Won auctions that never turned into a completed order.
        $wonUnpaid = (int) $db->scalar(
            "SELECT COUNT(*) FROM auctions a
             INNER JOIN orders o ON o.auction_id = a.id
             WHERE a.winning_user_id = :u AND o.status = 'cancelled'",
            ['u' => $userId],
            0
        );
        if ($wonUnpaid >= 2) {
            $flags[] = self::flag('won_not_paid', "{$wonUnpaid} won auctions ended in a cancelled order.");
        }

        // KYC rejected repeatedly.
        $kycRejections = (int) $db->scalar(
            "SELECT COUNT(*) FROM kyc_verifications WHERE user_id = :u AND status = 'rejected'",
            ['u' => $userId],
            0
        );
        if ($kycRejections >= 2) {
            $flags[] = self::flag('kyc_mismatch', "KYC rejected {$kycRejections} times.");
        }

        // New account transacting at scale.
        $accountAgeDays = (int) ((time() - strtotime((string) $user['created_at'] . ' UTC')) / 86400);
        if ($accountAgeDays <= 7) {
            $value = (float) $db->scalar(
                'SELECT COALESCE(SUM(final_amount), 0) FROM orders WHERE buyer_id = :u OR seller_id = :u2',
                ['u' => $userId, 'u2' => $userId],
                0
            );
            if ($value > 500000) {
                $flags[] = self::flag('new_account_high_value', 'Account is ' . $accountAgeDays . ' days old with ' . money($value) . ' in orders.');
            }
        }

        // Persist: refresh the open flag set for this user.
        $score = 0.0;
        foreach ($flags as $flag) {
            $score += $flag['score'];
            $existing = $db->first(
                "SELECT id FROM fraud_flags WHERE user_id = :u AND rule = :r AND status = 'open'",
                ['u' => $userId, 'r' => $flag['rule']]
            );
            if ($existing === null) {
                $db->insert('fraud_flags', [
                    'user_id' => $userId,
                    'rule' => $flag['rule'],
                    'severity' => $flag['severity'],
                    'score' => dec($flag['score'], 2),
                    'details' => $flag['details'],
                    'status' => 'open',
                    'created_at' => now(),
                ]);
            } else {
                $db->update('fraud_flags', ['details' => $flag['details']], ['id' => (int) $existing['id']]);
            }
        }

        $db->update('users', ['risk_score' => dec(min(100, $score), 2), 'updated_at' => now()], ['id' => $userId]);

        return ['score' => dec(min(100, $score), 2), 'flags' => $flags];
    }

    private static function flag(string $rule, string $details): array
    {
        [$labelText, $severity, $score] = self::RULES[$rule];
        return ['rule' => $rule, 'label' => $labelText, 'severity' => $severity, 'score' => $score, 'details' => $details];
    }

    public static function flagsFor(int $userId): array
    {
        return Database::instance()->select(
            'SELECT * FROM fraud_flags WHERE user_id = :u ORDER BY FIELD(severity, "critical","high","medium","low"), id DESC',
            ['u' => $userId]
        );
    }

    public static function paginate(array $filters, int $page, int $perPage = 25): Paginator
    {
        $sql = 'SELECT f.*, u.full_name, u.mobile, u.email, u.status AS user_status, u.risk_score,
                       b.name AS business_name
                FROM fraud_flags f
                INNER JOIN users u ON u.id = f.user_id
                LEFT JOIN businesses b ON b.user_id = u.id AND b.deleted_at IS NULL
                WHERE 1 = 1';
        $count = 'SELECT COUNT(*) FROM fraud_flags f WHERE 1 = 1';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' AND f.status = :status';
            $count .= ' AND f.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['severity'])) {
            $sql .= ' AND f.severity = :severity';
            $count .= ' AND f.severity = :severity';
            $params['severity'] = $filters['severity'];
        }

        return Model::paginateQuery(
            $sql . ' ORDER BY FIELD(f.severity, "critical","high","medium","low"), f.id DESC',
            $params,
            $page,
            $perPage,
            $count
        );
    }

    public static function resolve(int $flagId, int $reviewerId, string $status): array
    {
        if (!in_array($status, ['reviewed', 'cleared', 'confirmed'], true)) {
            return ['ok' => false, 'error' => 'Invalid status.'];
        }
        Database::instance()->update('fraud_flags', [
            'status' => $status,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ], ['id' => $flagId]);
        AuditService::log('fraud_flag_' . $status, 'fraud_flag', $flagId);
        return ['ok' => true, 'message' => 'Flag marked as ' . label($status) . '.'];
    }

    /** Scheduler sweep over recently active accounts. */
    public static function evaluateRecent(int $limit = 50): int
    {
        $users = Database::instance()->select(
            "SELECT DISTINCT id FROM users
             WHERE deleted_at IS NULL AND status = 'active'
               AND (last_login_at >= :since OR created_at >= :since2)
             ORDER BY id DESC LIMIT " . max(1, $limit),
            ['since' => gmdate('Y-m-d H:i:s', strtotime('-2 days')), 'since2' => gmdate('Y-m-d H:i:s', strtotime('-2 days'))]
        );
        foreach ($users as $user) {
            self::evaluate((int) $user['id']);
        }
        return count($users);
    }

    public static function counts(): array
    {
        $db = Database::instance();
        return [
            'open' => (int) $db->scalar("SELECT COUNT(*) FROM fraud_flags WHERE status = 'open'", [], 0),
            'critical' => (int) $db->scalar("SELECT COUNT(*) FROM fraud_flags WHERE status = 'open' AND severity = 'critical'", [], 0),
            'high_risk_users' => (int) $db->scalar('SELECT COUNT(*) FROM users WHERE risk_score >= 40 AND deleted_at IS NULL', [], 0),
        ];
    }
}
