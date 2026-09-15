<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Auction;
use App\Models\Listing;

/**
 * Auction lifecycle: schedule → live → ended → awarded/unsold.
 *
 * Every transition is driven by the scheduler (or a web-triggered scheduler run)
 * so an auction closes on time even if nobody has the page open.
 */
final class AuctionService
{
    /** Start auctions whose start time has passed. */
    public static function startDue(): int
    {
        $db = Database::instance();
        $due = $db->select(
            "SELECT id, owner_id, title FROM auctions
             WHERE status = 'scheduled' AND starts_at <= :now AND ends_at > :now2 AND deleted_at IS NULL
             LIMIT 100",
            ['now' => now(), 'now2' => now()]
        );

        foreach ($due as $auction) {
            $db->update('auctions', ['status' => 'live', 'updated_at' => now()], ['id' => (int) $auction['id']]);
            Auction::logEvent((int) $auction['id'], null, 'started', 'Auction opened by scheduler');

            $watchers = self::watchers((int) $auction['id']);
            NotificationService::dispatchMany($watchers, 'auction_starting', [
                'body' => 'Bidding is now open for ' . $auction['title'] . '.',
                'link' => '/auctions/' . $auction['id'],
                'entity_type' => 'auction',
                'entity_id' => (int) $auction['id'],
                'vars' => ['title' => $auction['title']],
            ]);
        }

        return count($due);
    }

    /** Close auctions whose end time has passed and decide the outcome. */
    public static function closeDue(): int
    {
        $db = Database::instance();
        $due = $db->select(
            "SELECT id FROM auctions WHERE status = 'live' AND ends_at <= :now AND deleted_at IS NULL LIMIT 50",
            ['now' => now()]
        );

        $closed = 0;
        foreach ($due as $row) {
            if (self::close((int) $row['id'])['ok']) {
                $closed++;
            }
        }
        return $closed;
    }

    /**
     * Close one auction. Runs in a transaction with the auction row locked so a
     * bid landing at the same instant either makes it in before the close or is
     * rejected as "auction ended" — never both.
     */
    public static function close(int $auctionId, ?string $reason = null): array
    {
        $db = Database::instance();

        try {
            $result = $db->transaction(static function (Database $db) use ($auctionId, $reason): array {
                $auction = $db->lockRow('auctions', $auctionId);
                if ($auction === null) {
                    return ['ok' => false, 'error' => 'Auction not found.'];
                }
                if (!in_array($auction['status'], ['live', 'scheduled'], true)) {
                    return ['ok' => false, 'error' => 'Auction is already ' . $auction['status'] . '.'];
                }

                $isReverse = $auction['auction_type'] === 'reverse';
                $winningBid = $db->first(
                    $isReverse
                        ? 'SELECT * FROM bids WHERE auction_id = :a AND status <> "retracted" ORDER BY amount ASC, placed_at ASC LIMIT 1'
                        : 'SELECT * FROM bids WHERE auction_id = :a AND status <> "retracted" ORDER BY amount DESC, placed_at ASC LIMIT 1',
                    ['a' => $auctionId]
                );

                $reserve = $auction['reserve_price'];
                $reserveMet = $winningBid !== null && (
                    $reserve === null
                    || ($isReverse ? (float) $winningBid['amount'] <= (float) $reserve : (float) $winningBid['amount'] >= (float) $reserve)
                );

                $status = $winningBid === null ? 'unsold' : ($reserveMet ? 'ended' : 'unsold');

                $db->update('auctions', [
                    'status' => $status,
                    'closed_at' => now(),
                    'winning_user_id' => $reserveMet && $winningBid !== null ? (int) $winningBid['user_id'] : null,
                    'current_bid_id' => $winningBid !== null ? (int) $winningBid['id'] : null,
                    'reserve_met' => $reserveMet ? 1 : 0,
                    'cancel_reason' => $reason,
                    'updated_at' => now(),
                ], ['id' => $auctionId]);

                // Mark every bid's final state.
                if ($winningBid !== null) {
                    $db->statement(
                        "UPDATE bids SET status = 'lost' WHERE auction_id = :a AND status <> 'retracted'",
                        ['a' => $auctionId]
                    );
                    $db->update('bids', ['status' => $reserveMet ? 'won' : 'lost'], ['id' => (int) $winningBid['id']]);
                }

                Auction::logEvent(
                    $auctionId,
                    null,
                    $status === 'unsold' ? 'unsold' : 'ended',
                    $winningBid === null
                        ? 'Closed with no bids'
                        : ($reserveMet
                            ? 'Closed. Winning bid ' . money($winningBid['amount'])
                            : 'Closed below reserve at ' . money($winningBid['amount']))
                );

                return [
                    'ok' => true,
                    'status' => $status,
                    'auction' => $auction,
                    'winning_bid' => $winningBid,
                    'reserve_met' => $reserveMet,
                ];
            });
        } catch (\Throwable $e) {
            logger()->error('Auction close failed: ' . $e->getMessage(), ['auction' => $auctionId]);
            return ['ok' => false, 'error' => 'Could not close the auction.'];
        }

        if (!$result['ok']) {
            return $result;
        }

        $auction = $result['auction'];
        $winningBid = $result['winning_bid'];

        // ---- Outcome notifications + order creation happen outside the lock.
        if ($winningBid !== null && $result['reserve_met']) {
            $orderId = null;
            if (SettingsService::bool('auction_auto_create_order', true)) {
                $orderId = self::createOrderFromAuction($auctionId, $auction, $winningBid);
            }

            NotificationService::dispatch((int) $winningBid['user_id'], 'auction_won', [
                'body' => 'You won ' . $auction['title'] . ' at ' . money($winningBid['amount']) . '.',
                'link' => $orderId ? '/dashboard/orders/' . $orderId : '/auctions/' . $auctionId,
                'entity_type' => 'auction',
                'entity_id' => $auctionId,
                'vars' => [
                    'title' => $auction['title'],
                    'amount' => money($winningBid['amount']),
                    'order' => $orderId ? (\App\Models\Order::find($orderId)['reference'] ?? '') : '',
                ],
            ]);

            $losers = Database::instance()->select(
                'SELECT DISTINCT user_id FROM bids WHERE auction_id = :a AND user_id <> :w',
                ['a' => $auctionId, 'w' => (int) $winningBid['user_id']]
            );
            NotificationService::dispatchMany(
                array_map(static fn (array $r): int => (int) $r['user_id'], $losers),
                'auction_lost',
                [
                    'body' => $auction['title'] . ' has closed. Your bid was not the winning one.',
                    'link' => '/auctions',
                    'entity_type' => 'auction',
                    'entity_id' => $auctionId,
                    'vars' => ['title' => $auction['title']],
                ]
            );

            NotificationService::dispatch((int) $auction['owner_id'], 'auction_won', [
                'title' => 'Your auction closed successfully',
                'body' => $auction['title'] . ' sold at ' . money($winningBid['amount']) . '.',
                'link' => $orderId ? '/dashboard/orders/' . $orderId : '/auctions/' . $auctionId,
                'entity_type' => 'auction',
                'entity_id' => $auctionId,
            ]);
        } else {
            NotificationService::dispatch((int) $auction['owner_id'], 'auction_unsold', [
                'body' => $winningBid === null
                    ? $auction['title'] . ' closed without any bids.'
                    : $auction['title'] . ' closed below your reserve price.',
                'link' => '/auctions/' . $auctionId,
                'entity_type' => 'auction',
                'entity_id' => $auctionId,
            ]);
            if (!empty($auction['listing_id'])) {
                Database::instance()->statement(
                    "UPDATE listings SET status = 'active', updated_at = :n WHERE id = :l AND status = 'active'",
                    ['n' => now(), 'l' => (int) $auction['listing_id']]
                );
            }
        }

        return $result;
    }

    private static function createOrderFromAuction(int $auctionId, array $auction, array $winningBid): ?int
    {
        try {
            $listing = !empty($auction['listing_id']) ? Listing::find((int) $auction['listing_id']) : null;
            $isReverse = $auction['auction_type'] === 'reverse';

            $buyerId = $isReverse ? (int) $auction['owner_id'] : (int) $winningBid['user_id'];
            $sellerId = $isReverse ? (int) $winningBid['user_id'] : (int) $auction['owner_id'];

            $orderId = OrderService::create([
                'buyer_id' => $buyerId,
                'seller_id' => $sellerId,
                'source_type' => 'auction',
                'listing_id' => $auction['listing_id'] ?? null,
                'auction_id' => $auctionId,
                'requirement_id' => $auction['requirement_id'] ?? null,
                'quantity' => $auction['quantity'],
                'unit_id' => (int) $auction['unit_id'],
                'rate' => $winningBid['amount'],
                'price_basis' => $auction['price_basis'],
                'gst_rate' => $listing['gst_rate'] ?? 0,
                'payment_terms' => $auction['payment_terms'],
                'description' => $auction['title'],
                'notes' => 'Created automatically from auction ' . $auction['reference'],
            ]);

            Database::instance()->update('auctions', [
                'status' => 'awarded',
                'order_id' => $orderId,
                'awarded_at' => now(),
                'updated_at' => now(),
            ], ['id' => $auctionId]);

            if (!empty($auction['listing_id'])) {
                Database::instance()->update('listings', [
                    'status' => 'sold',
                    'sold_at' => now(),
                    'updated_at' => now(),
                ], ['id' => (int) $auction['listing_id']]);
            }

            Auction::logEvent($auctionId, null, 'awarded', 'Order created automatically');
            return $orderId;
        } catch (\Throwable $e) {
            logger()->error('Auction order creation failed: ' . $e->getMessage(), ['auction' => $auctionId]);
            Auction::logEvent($auctionId, null, 'awarded', 'Order creation failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Manually award an ended auction (seller or admin). */
    public static function award(int $auctionId, ?int $bidId = null): array
    {
        $auction = Auction::find($auctionId);
        if ($auction === null) {
            return ['ok' => false, 'error' => 'Auction not found.'];
        }
        if (!in_array($auction['status'], ['ended', 'unsold'], true)) {
            return ['ok' => false, 'error' => 'Only a closed auction can be awarded.'];
        }
        if (!empty($auction['order_id'])) {
            return ['ok' => false, 'error' => 'This auction has already been awarded.'];
        }

        $bid = $bidId !== null
            ? Database::instance()->first('SELECT * FROM bids WHERE id = :id AND auction_id = :a', ['id' => $bidId, 'a' => $auctionId])
            : Database::instance()->first(
                $auction['auction_type'] === 'reverse'
                    ? 'SELECT * FROM bids WHERE auction_id = :a AND status <> "retracted" ORDER BY amount ASC LIMIT 1'
                    : 'SELECT * FROM bids WHERE auction_id = :a AND status <> "retracted" ORDER BY amount DESC LIMIT 1',
                ['a' => $auctionId]
            );

        if ($bid === null) {
            return ['ok' => false, 'error' => 'There is no bid to award.'];
        }

        $orderId = self::createOrderFromAuction($auctionId, $auction, $bid);
        if ($orderId === null) {
            return ['ok' => false, 'error' => 'Could not create the order.'];
        }

        Database::instance()->update('bids', ['status' => 'won'], ['id' => (int) $bid['id']]);
        NotificationService::dispatch((int) $bid['user_id'], 'auction_won', [
            'body' => 'The seller awarded ' . $auction['title'] . ' to you at ' . money($bid['amount']) . '.',
            'link' => '/dashboard/orders/' . $orderId,
            'entity_type' => 'auction',
            'entity_id' => $auctionId,
        ]);

        AuditService::log('auction_awarded', 'auction', $auctionId, null, ['bid_id' => (int) $bid['id'], 'order_id' => $orderId]);
        return ['ok' => true, 'order_id' => $orderId, 'message' => 'Auction awarded and order created.'];
    }

    public static function cancel(int $auctionId, string $reason, ?int $actorId): array
    {
        $auction = Auction::find($auctionId);
        if ($auction === null) {
            return ['ok' => false, 'error' => 'Auction not found.'];
        }
        if (in_array($auction['status'], ['awarded', 'cancelled'], true)) {
            return ['ok' => false, 'error' => 'This auction can no longer be cancelled.'];
        }

        Database::instance()->update('auctions', [
            'status' => 'cancelled',
            'cancel_reason' => substr($reason, 0, 255),
            'closed_at' => now(),
            'updated_at' => now(),
        ], ['id' => $auctionId]);

        Database::instance()->statement(
            "UPDATE bids SET status = 'invalid' WHERE auction_id = :a AND status IN ('active','winning')",
            ['a' => $auctionId]
        );

        if (!empty($auction['listing_id'])) {
            Database::instance()->update('listings', ['status' => 'active', 'updated_at' => now()], ['id' => (int) $auction['listing_id']]);
        }

        Auction::logEvent($auctionId, $actorId, 'cancelled', $reason);
        AuditService::log('auction_cancelled', 'auction', $auctionId, null, ['reason' => $reason]);

        $bidders = Database::instance()->select('SELECT DISTINCT user_id FROM bids WHERE auction_id = :a', ['a' => $auctionId]);
        NotificationService::dispatchMany(
            array_map(static fn (array $r): int => (int) $r['user_id'], $bidders),
            'auction_lost',
            [
                'title' => 'Auction cancelled',
                'body' => $auction['title'] . ' was cancelled. Reason: ' . $reason,
                'link' => '/auctions',
                'entity_type' => 'auction',
                'entity_id' => $auctionId,
            ]
        );

        return ['ok' => true, 'message' => 'Auction cancelled.'];
    }

    /** Alert watchers and bidders shortly before an auction closes. */
    public static function sendEndingAlerts(): int
    {
        $minutes = SettingsService::int('auction_ending_alert_minutes', 30);
        $db = Database::instance();
        $auctions = $db->select(
            "SELECT id, title, ends_at FROM auctions
             WHERE status = 'live' AND deleted_at IS NULL
               AND ends_at BETWEEN :now AND :soon
               AND id NOT IN (SELECT entity_id FROM notifications WHERE event = 'auction_ending' AND entity_type = 'auction' AND entity_id IS NOT NULL)
             LIMIT 25",
            ['now' => now(), 'soon' => gmdate('Y-m-d H:i:s', time() + ($minutes * 60))]
        );

        $sent = 0;
        foreach ($auctions as $auction) {
            $recipients = self::watchers((int) $auction['id']);
            if ($recipients === []) {
                continue;
            }
            NotificationService::dispatchMany($recipients, 'auction_ending', [
                'body' => $auction['title'] . ' closes at ' . fmt_dt($auction['ends_at']) . '.',
                'link' => '/auctions/' . $auction['id'],
                'entity_type' => 'auction',
                'entity_id' => (int) $auction['id'],
                'vars' => ['title' => $auction['title']],
            ]);
            $sent += count($recipients);
        }
        return $sent;
    }

    /** Everyone who should hear about an auction: bidders + people watching it. */
    private static function watchers(int $auctionId): array
    {
        $rows = Database::instance()->select(
            "SELECT DISTINCT user_id FROM (
                SELECT user_id FROM bids WHERE auction_id = :a
                UNION
                SELECT user_id FROM auction_bidders WHERE auction_id = :a2
                UNION
                SELECT user_id FROM favorites WHERE favoritable_type = 'auction' AND favoritable_id = :a3
             ) AS watchers",
            ['a' => $auctionId, 'a2' => $auctionId, 'a3' => $auctionId]
        );
        return array_map(static fn (array $r): int => (int) $r['user_id'], $rows);
    }

    /** Notify the previous leader that they have been outbid. */
    public static function notifyOutbid(int $auctionId, ?int $previousLeaderId, string $newAmount): void
    {
        if ($previousLeaderId === null || !SettingsService::bool('outbid_notification', true)) {
            return;
        }
        $auction = Auction::find($auctionId);
        if ($auction === null) {
            return;
        }
        NotificationService::dispatch($previousLeaderId, 'outbid', [
            'body' => 'The leading bid on ' . $auction['title'] . ' is now ' . money($newAmount) . '.',
            'link' => '/auctions/' . $auctionId,
            'entity_type' => 'auction',
            'entity_id' => $auctionId,
            'vars' => ['title' => $auction['title'], 'amount' => money($newAmount)],
        ]);
    }
}
