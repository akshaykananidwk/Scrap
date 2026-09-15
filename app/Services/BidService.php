<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\RateLimiter;
use App\Models\Auction;
use App\Models\User;

/**
 * The bidding engine.
 *
 * Every bid is validated **on the server** inside a transaction that holds a
 * row-level lock on the auction. The client-supplied amount is never trusted:
 * it is re-checked against the locked current price, the increment, the reserve,
 * the auction window and the bidder's eligibility before anything is written.
 *
 * Concurrency: two simultaneous bids both read the auction row with
 * `SELECT … FOR UPDATE`, so the second one blocks until the first commits and
 * then sees the new current price. Without the lock both could pass the
 * "higher than current" check and produce two winning bids.
 */
final class BidService
{
    public const ERR_NOT_LIVE = 'auction_not_live';
    public const ERR_ENDED = 'auction_ended';
    public const ERR_NOT_STARTED = 'auction_not_started';
    public const ERR_OWN_AUCTION = 'own_auction';
    public const ERR_INELIGIBLE = 'ineligible';
    public const ERR_TOO_LOW = 'bid_too_low';
    public const ERR_TOO_HIGH = 'bid_too_high';
    public const ERR_INCREMENT = 'invalid_increment';
    public const ERR_DUPLICATE = 'duplicate_bid';
    public const ERR_RATE_LIMIT = 'rate_limited';
    public const ERR_MAX_BIDDERS = 'max_bidders_reached';

    /**
     * Place a bid.
     *
     * @return array{ok: bool, error?: string, message: string, bid_id?: int, amount?: string,
     *               extended?: bool, ends_at?: string, state?: array}
     */
    public static function place(int $auctionId, int $userId, string $rawAmount, string $ip, string $userAgent): array
    {
        // Cheap guards before we take a database lock.
        $limit = SettingsService::int('auction_bid_rate_limit', 20);
        if (RateLimiter::tooManyAttempts('bid:' . $userId, $limit, 60)) {
            return self::fail(self::ERR_RATE_LIMIT, 'You are bidding too quickly. Please wait a moment.');
        }
        RateLimiter::hit('bid:' . $userId, 60);

        if (!is_numeric(str_replace(',', '', $rawAmount))) {
            return self::fail(self::ERR_TOO_LOW, 'Enter a valid bid amount.');
        }
        $amount = dec(str_replace(',', '', $rawAmount), 2);
        if ((float) $amount <= 0) {
            return self::fail(self::ERR_TOO_LOW, 'A bid must be greater than zero.');
        }

        $db = Database::instance();

        try {
            return $db->transaction(static function (Database $db) use ($auctionId, $userId, $amount, $ip, $userAgent): array {
                // ---- Lock the auction row for the duration of this transaction.
                $auction = $db->lockRow('auctions', $auctionId);
                if ($auction === null || $auction['deleted_at'] !== null) {
                    return self::fail(self::ERR_NOT_LIVE, 'This auction is no longer available.');
                }

                $validation = self::validate($db, $auction, $userId, $amount);
                if ($validation !== null) {
                    Auction::logEvent($auctionId, $userId, 'bid_rejected', $validation['error'] . ': ' . $validation['message'], $ip);
                    return $validation;
                }

                $isReverse = $auction['auction_type'] === 'reverse';
                $now = now();
                $placedAt = gmdate('Y-m-d H:i:s.v');

                // ---- Demote the previous leader.
                if (!empty($auction['current_bid_id'])) {
                    $db->statement(
                        "UPDATE bids SET status = 'outbid' WHERE id = :id AND status IN ('active','winning')",
                        ['id' => (int) $auction['current_bid_id']]
                    );
                }

                // ---- Auto-extension: a late bid pushes the close time out.
                $extended = false;
                $endsAt = (string) $auction['ends_at'];
                $window = (int) $auction['extension_window_seconds'];
                $duration = (int) $auction['extension_duration_seconds'];
                $maxExtensions = (int) $auction['max_extensions'];
                $extensionCount = (int) $auction['extension_count'];
                $secondsLeft = strtotime($endsAt . ' UTC') - time();

                if ($window > 0 && $duration > 0 && $secondsLeft <= $window && $extensionCount < $maxExtensions) {
                    $endsAt = gmdate('Y-m-d H:i:s', strtotime($endsAt . ' UTC') + $duration);
                    $extensionCount++;
                    $extended = true;
                }

                // ---- Write the bid.
                $bidId = $db->insert('bids', [
                    'bid_uid' => bin2hex(random_bytes(16)),
                    'auction_id' => $auctionId,
                    'user_id' => $userId,
                    'amount' => $amount,
                    'quantity' => $auction['quantity'],
                    'is_auto' => 0,
                    'status' => 'winning',
                    'extended_auction' => $extended ? 1 : 0,
                    'ip' => $ip,
                    'user_agent' => substr($userAgent, 0, 255),
                    'placed_at' => $placedAt,
                    'created_at' => $now,
                ]);

                // ---- Register / update the bidder record.
                $existingBidder = $db->first(
                    'SELECT id, bidder_number FROM auction_bidders WHERE auction_id = :a AND user_id = :u',
                    ['a' => $auctionId, 'u' => $userId]
                );
                if ($existingBidder === null) {
                    $nextNumber = (int) $db->scalar(
                        'SELECT COALESCE(MAX(bidder_number), 0) + 1 FROM auction_bidders WHERE auction_id = :a',
                        ['a' => $auctionId],
                        1
                    );
                    $db->insert('auction_bidders', [
                        'auction_id' => $auctionId,
                        'user_id' => $userId,
                        'bidder_number' => $nextNumber,
                        'status' => 'approved',
                        'bid_count' => 1,
                        'last_bid_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    $db->statement(
                        'UPDATE auction_bidders SET bid_count = bid_count + 1, last_bid_at = :n, updated_at = :n2 WHERE id = :id',
                        ['n' => $now, 'n2' => $now, 'id' => (int) $existingBidder['id']]
                    );
                }

                // ---- Update the auction head.
                $reserve = $auction['reserve_price'];
                $reserveMet = $reserve === null
                    || ($isReverse ? (float) $amount <= (float) $reserve : (float) $amount >= (float) $reserve);

                $db->update('auctions', [
                    'current_price' => $amount,
                    'current_bid_id' => $bidId,
                    'winning_user_id' => $userId,
                    'bid_count' => (int) $auction['bid_count'] + 1,
                    'bidder_count' => (int) $db->scalar(
                        'SELECT COUNT(*) FROM auction_bidders WHERE auction_id = :a',
                        ['a' => $auctionId],
                        0
                    ),
                    'ends_at' => $endsAt,
                    'extension_count' => $extensionCount,
                    'reserve_met' => $reserveMet ? 1 : 0,
                    'updated_at' => $now,
                ], ['id' => $auctionId]);

                Auction::logEvent($auctionId, $userId, 'bid_placed', 'Bid ' . money($amount) . ($extended ? ' (auction extended)' : ''), $ip);
                if ($extended) {
                    Auction::logEvent($auctionId, $userId, 'extended', 'Extended to ' . $endsAt, $ip);
                }

                return [
                    'ok' => true,
                    'message' => $extended
                        ? 'Bid placed. The auction was extended because your bid arrived near the close.'
                        : 'Your bid has been placed.',
                    'bid_id' => $bidId,
                    'amount' => $amount,
                    'extended' => $extended,
                    'ends_at' => $endsAt,
                    'previous_leader' => $auction['winning_user_id'] !== null ? (int) $auction['winning_user_id'] : null,
                ];
            });
        } catch (\Throwable $e) {
            logger()->error('Bid failed: ' . $e->getMessage(), ['auction' => $auctionId, 'user' => $userId]);
            return self::fail('server_error', 'Your bid could not be recorded. Please try again.');
        }
    }

    /**
     * All bid rules in one place. Returns null when the bid is acceptable.
     * $auction is the LOCKED row.
     */
    private static function validate(Database $db, array $auction, int $userId, string $amount): ?array
    {
        $auctionId = (int) $auction['id'];

        if ($auction['status'] !== 'live') {
            return self::fail(self::ERR_NOT_LIVE, match ($auction['status']) {
                'scheduled' => 'This auction has not started yet.',
                'ended', 'awarded', 'unsold' => 'This auction has already closed.',
                'cancelled' => 'This auction was cancelled.',
                default => 'This auction is not open for bidding.',
            });
        }

        $nowTs = time();
        if (strtotime($auction['starts_at'] . ' UTC') > $nowTs) {
            return self::fail(self::ERR_NOT_STARTED, 'This auction has not started yet.');
        }
        if (strtotime($auction['ends_at'] . ' UTC') <= $nowTs) {
            return self::fail(self::ERR_ENDED, 'This auction has closed. Bidding is no longer possible.');
        }

        if ((int) $auction['owner_id'] === $userId) {
            return self::fail(self::ERR_OWN_AUCTION, 'You cannot bid on your own auction.');
        }

        $user = User::find($userId);
        if ($user === null || $user['status'] !== 'active') {
            return self::fail(self::ERR_INELIGIBLE, 'Your account is not permitted to bid.');
        }
        if ((int) $auction['requires_kyc'] === 1 && $user['kyc_status'] !== 'verified') {
            return self::fail(self::ERR_INELIGIBLE, 'This auction is restricted to KYC-verified businesses.');
        }

        $bidder = $db->first(
            'SELECT * FROM auction_bidders WHERE auction_id = :a AND user_id = :u',
            ['a' => $auctionId, 'u' => $userId]
        );
        if ($bidder !== null && in_array($bidder['status'], ['rejected', 'blocked'], true)) {
            return self::fail(self::ERR_INELIGIBLE, 'You are not approved to bid in this auction.');
        }
        if ((int) $auction['requires_approval'] === 1 && ($bidder === null || $bidder['status'] !== 'approved')) {
            return self::fail(self::ERR_INELIGIBLE, 'The seller must approve you before you can bid.');
        }
        if ((int) $auction['deposit_required'] === 1 && ($bidder === null || (int) $bidder['deposit_paid'] !== 1)) {
            return self::fail(self::ERR_INELIGIBLE, 'A bid deposit is required before you can bid in this auction.');
        }
        if (!empty($auction['max_bidders']) && $bidder === null) {
            $current = (int) $db->scalar('SELECT COUNT(*) FROM auction_bidders WHERE auction_id = :a', ['a' => $auctionId], 0);
            if ($current >= (int) $auction['max_bidders']) {
                return self::fail(self::ERR_MAX_BIDDERS, 'This auction has reached its maximum number of bidders.');
            }
        }

        $isReverse = $auction['auction_type'] === 'reverse';
        $increment = (float) $auction['bid_increment'];
        $hasBids = (int) $auction['bid_count'] > 0;
        $current = (float) ($auction['current_price'] ?? $auction['starting_price']);
        $value = (float) $amount;

        // The same bidder cannot replace their own leading bid with an identical one.
        if ((int) ($auction['winning_user_id'] ?? 0) === $userId && abs($value - $current) < 0.005) {
            return self::fail(self::ERR_DUPLICATE, 'You already hold the leading bid at this amount.');
        }

        if ($isReverse) {
            // Sellers compete downwards: every bid must be lower.
            $maximum = $hasBids ? $current - $increment : (float) $auction['starting_price'];
            if ($value > $maximum + 0.001) {
                return self::fail(
                    self::ERR_TOO_HIGH,
                    $hasBids
                        ? 'Your offer must be at most ' . money($maximum) . ' (current best minus the decrement).'
                        : 'Your offer must be at most the starting price of ' . money($maximum) . '.'
                );
            }
            if ($value <= 0) {
                return self::fail(self::ERR_TOO_LOW, 'Offer amount must be greater than zero.');
            }
        } else {
            $minimum = $hasBids ? $current + $increment : (float) $auction['starting_price'];
            if ($value < $minimum - 0.001) {
                return self::fail(
                    self::ERR_TOO_LOW,
                    $hasBids
                        ? 'Your bid must be at least ' . money($minimum) . ' (current bid plus the increment).'
                        : 'Your bid must be at least the starting price of ' . money($minimum) . '.'
                );
            }
            // Guard against a fat-finger bid 100× the going rate.
            if ($current > 0 && $value > $current * 100) {
                return self::fail(self::ERR_TOO_HIGH, 'That bid looks like a typing error. Please confirm the amount.');
            }
        }

        // Bids must land on an increment step from the starting price.
        if ($increment > 0) {
            $base = (float) $auction['starting_price'];
            $steps = abs($value - $base) / $increment;
            if (abs($steps - round($steps)) > 0.001) {
                $suggestion = $isReverse
                    ? $base - (floor(abs($value - $base) / $increment) * $increment)
                    : $base + (ceil(abs($value - $base) / $increment) * $increment);
                return self::fail(
                    self::ERR_INCREMENT,
                    'Bids must be in multiples of ' . money($increment) . ' from the starting price. Try ' . money($suggestion) . '.'
                );
            }
        }

        return null;
    }

    /** The minimum (forward) or maximum (reverse) amount a new bid may be. */
    public static function nextValidAmount(array $auction): string
    {
        $increment = (float) $auction['bid_increment'];
        $hasBids = (int) $auction['bid_count'] > 0;
        $current = (float) ($auction['current_price'] ?? $auction['starting_price']);

        if ($auction['auction_type'] === 'reverse') {
            return dec($hasBids ? max(0, $current - $increment) : (float) $auction['starting_price'], 2);
        }
        return dec($hasBids ? $current + $increment : (float) $auction['starting_price'], 2);
    }

    /** Snapshot for the AJAX live-bidding poller. */
    public static function liveState(int $auctionId, ?int $userId = null): array
    {
        $auction = Auction::find($auctionId);
        if ($auction === null) {
            return ['ok' => false, 'error' => 'Auction not found.'];
        }

        $mask = (int) $auction['mask_bidders'] === 1;
        $history = Auction::bidHistory($auctionId, $mask, 12);
        $position = $userId !== null ? Auction::userPosition($auctionId, $userId) : null;

        return [
            'ok' => true,
            'auction_id' => $auctionId,
            'status' => $auction['status'],
            'auction_type' => $auction['auction_type'],
            'current_price' => dec($auction['current_price'] ?? $auction['starting_price'], 2),
            'current_price_display' => money($auction['current_price'] ?? $auction['starting_price']),
            'starting_price_display' => money($auction['starting_price']),
            'bid_increment' => dec($auction['bid_increment'], 2),
            'bid_increment_display' => money($auction['bid_increment']),
            'next_valid_amount' => self::nextValidAmount($auction),
            'next_valid_display' => money(self::nextValidAmount($auction)),
            'bid_count' => (int) $auction['bid_count'],
            'bidder_count' => (int) $auction['bidder_count'],
            'ends_at' => $auction['ends_at'],
            'ends_at_display' => fmt_dt($auction['ends_at']),
            'seconds_remaining' => countdown_seconds($auction['ends_at']),
            'extension_count' => (int) $auction['extension_count'],
            'max_extensions' => (int) $auction['max_extensions'],
            'reserve_met' => (int) $auction['reserve_met'] === 1,
            'has_reserve' => $auction['reserve_price'] !== null,
            'is_winning' => $userId !== null && (int) ($auction['winning_user_id'] ?? 0) === $userId,
            'position' => $position,
            'server_time' => now(),
            'bids' => array_map(static fn (array $bid): array => [
                'id' => (int) $bid['id'],
                'bidder' => $bid['display_name'],
                'amount' => money($bid['amount']),
                'amount_raw' => dec($bid['amount'], 2),
                'placed_at' => fmt_dt($bid['placed_at'], 'd M, h:i:s A'),
                'ago' => time_ago(substr((string) $bid['placed_at'], 0, 19)),
                'status' => $bid['status'],
                'is_you' => $userId !== null && (int) $bid['user_id'] === $userId,
            ], $history),
        ];
    }

    public static function userBids(int $userId, array $filters, int $page, int $perPage = 20): \App\Core\Paginator
    {
        $sql = 'SELECT b.*, a.title, a.reference AS auction_reference, a.status AS auction_status,
                       a.ends_at, a.current_price, a.winning_user_id, a.auction_type,
                       l.slug AS listing_slug, un.code AS unit_code
                FROM bids b
                INNER JOIN auctions a ON a.id = b.auction_id
                INNER JOIN units un ON un.id = a.unit_id
                LEFT JOIN listings l ON l.id = a.listing_id
                WHERE b.user_id = :u';
        $count = 'SELECT COUNT(*) FROM bids b INNER JOIN auctions a ON a.id = b.auction_id WHERE b.user_id = :u';
        $params = ['u' => $userId];

        if (!empty($filters['status'])) {
            $sql .= ' AND a.status = :status';
            $count .= ' AND a.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['winning'])) {
            $sql .= ' AND a.winning_user_id = :u2';
            $count .= ' AND a.winning_user_id = :u2';
            $params['u2'] = $userId;
        }

        return \App\Core\Model::paginateQuery($sql . ' ORDER BY b.id DESC', $params, $page, $perPage, $count);
    }

    private static function fail(string $code, string $message): array
    {
        return ['ok' => false, 'error' => $code, 'message' => $message];
    }
}
