<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Listing;
use App\Models\State;
use App\Services\AuctionService;
use App\Services\BidService;
use App\Services\SettingsService;

final class AuctionController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'live'),
            'auction_type' => (string) $request->query('auction_type', ''),
            'category_id' => $request->int('category_id'),
            'state_id' => $request->int('state_id'),
            'sort' => (string) $request->query('sort', 'ending_soon'),
        ];
        if (!in_array($filters['status'], ['live', 'scheduled', 'ended', 'awarded', 'any'], true)) {
            $filters['status'] = 'live';
        }
        if ($filters['status'] === 'any') {
            unset($filters['status']);
        }
        $filters = array_filter($filters, static fn ($v): bool => $v !== '' && $v !== 0);

        $auctions = Auction::paginate($filters, $request->page(), 12);

        return $this->view('auction/index', [
            'title' => 'Live Scrap Auctions — Bid Online',
            'meta_description' => 'Forward and reverse scrap auctions with live bidding, auto-extension and verified bidders.',
            'auctions' => $auctions,
            'filters' => $filters,
            'categories' => Category::tree(),
            'states' => State::active(),
            'counts' => Auction::counts(),
        ]);
    }

    public function show(Request $request): Response
    {
        $auction = Auction::detail($request->paramInt('id'));
        if ($auction === null) {
            throw new HttpException(404, 'That auction does not exist.');
        }

        Database::instance()->statement(
            'UPDATE auctions SET view_count = view_count + 1 WHERE id = :id',
            ['id' => (int) $auction['id']]
        );

        $userId = Auth::id();
        $bidder = null;
        if ($userId !== null) {
            $bidder = Database::instance()->first(
                'SELECT * FROM auction_bidders WHERE auction_id = :a AND user_id = :u',
                ['a' => (int) $auction['id'], 'u' => $userId]
            );
        }

        $user = Auth::user();
        $eligibility = $this->eligibility($auction, $user, $bidder);

        return $this->view('auction/show', [
            'title' => $auction['title'] . ' — Live Auction',
            'meta_description' => 'Current bid ' . money($auction['current_price'] ?? $auction['starting_price'])
                . ' · ' . (int) $auction['bid_count'] . ' bids · closes ' . fmt_dt($auction['ends_at']),
            'auction' => $auction,
            'state' => BidService::liveState((int) $auction['id'], $userId),
            'images' => !empty($auction['listing_id']) ? Listing::images((int) $auction['listing_id']) : [],
            'listing' => !empty($auction['listing_id']) ? Listing::findDetail((int) $auction['listing_id']) : null,
            'bidder' => $bidder,
            'eligibility' => $eligibility,
            'is_owner' => $userId === (int) $auction['owner_id'],
            'poll_interval' => SettingsService::int('auction_poll_interval_ms', 4000),
            'events' => Auction::events((int) $auction['id'], 20),
            'related' => Auction::paginate(['status' => 'live'], 1, 4)->items,
        ]);
    }

    /** AJAX endpoint polled by the live auction page. */
    public function state(Request $request): Response
    {
        $state = BidService::liveState($request->paramInt('id'), Auth::id());
        return $this->json($state, $state['ok'] ? 200 : 404);
    }

    public function bid(Request $request): Response
    {
        $auctionId = $request->paramInt('id');
        $amount = (string) $request->input('amount', '');

        $result = BidService::place(
            $auctionId,
            (int) Auth::id(),
            $amount,
            $request->ip(),
            $request->userAgent()
        );

        if ($result['ok']) {
            // Tell the previous leader outside the bid transaction.
            AuctionService::notifyOutbid($auctionId, $result['previous_leader'] ?? null, $result['amount']);

            $auction = Auction::find($auctionId);
            if ($auction !== null) {
                \App\Services\NotificationService::dispatch((int) $auction['owner_id'], 'new_bid', [
                    'body' => 'New bid of ' . money($result['amount']) . ' on ' . $auction['title'] . '.',
                    'link' => '/auctions/' . $auctionId,
                    'entity_type' => 'auction',
                    'entity_id' => $auctionId,
                    'vars' => ['title' => $auction['title'], 'amount' => money($result['amount'])],
                ]);
            }
        }

        if ($request->wantsJson()) {
            return $this->json(
                array_merge($result, ['state' => BidService::liveState($auctionId, Auth::id())]),
                $result['ok'] ? 200 : 422
            );
        }

        flash($result['ok'] ? 'success' : 'danger', $result['message']);
        return $this->redirect('/auctions/' . $auctionId);
    }

    /** Register interest in an auction that requires seller approval. */
    public function register(Request $request): Response
    {
        $auctionId = $request->paramInt('id');
        $auction = Auction::find($auctionId);
        if ($auction === null) {
            throw new HttpException(404);
        }
        $userId = (int) Auth::id();
        if ((int) $auction['owner_id'] === $userId) {
            return $this->fail('You cannot register for your own auction.');
        }

        $db = Database::instance();
        $existing = $db->first(
            'SELECT id, status FROM auction_bidders WHERE auction_id = :a AND user_id = :u',
            ['a' => $auctionId, 'u' => $userId]
        );
        if ($existing !== null) {
            $message = 'You are already registered (' . label((string) $existing['status']) . ').';
        } else {
            $next = (int) $db->scalar(
                'SELECT COALESCE(MAX(bidder_number), 0) + 1 FROM auction_bidders WHERE auction_id = :a',
                ['a' => $auctionId],
                1
            );
            $db->insert('auction_bidders', [
                'auction_id' => $auctionId,
                'user_id' => $userId,
                'bidder_number' => $next,
                'status' => (int) $auction['requires_approval'] === 1 ? 'registered' : 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $message = (int) $auction['requires_approval'] === 1
                ? 'Registered. The seller will approve you before you can bid.'
                : 'You are registered for this auction.';

            \App\Services\NotificationService::dispatch((int) $auction['owner_id'], 'new_bid', [
                'title' => 'New bidder registered',
                'body' => 'A buyer registered for ' . $auction['title'] . '.',
                'link' => '/dashboard/auctions/' . $auctionId,
                'entity_type' => 'auction',
                'entity_id' => $auctionId,
                'channels' => [],
            ]);
        }

        if ($request->wantsJson()) {
            return $this->ok([], $message);
        }
        flash('success', $message);
        return $this->back('/auctions/' . $auctionId);
    }

    /** Why can (or can't) this user bid right now? Drives the UI messaging. */
    private function eligibility(array $auction, ?array $user, ?array $bidder): array
    {
        if ($user === null) {
            return ['can_bid' => false, 'reason' => 'sign_in', 'message' => 'Sign in to place a bid.'];
        }
        if ((int) $auction['owner_id'] === (int) $user['id']) {
            return ['can_bid' => false, 'reason' => 'owner', 'message' => 'This is your own auction.'];
        }
        if ($auction['status'] !== 'live') {
            return [
                'can_bid' => false,
                'reason' => 'not_live',
                'message' => match ($auction['status']) {
                    'scheduled' => 'Bidding opens ' . fmt_dt($auction['starts_at']) . '.',
                    'ended', 'awarded' => 'This auction has closed.',
                    'cancelled' => 'This auction was cancelled.',
                    default => 'This auction is not open for bidding.',
                },
            ];
        }
        if ((int) $auction['requires_kyc'] === 1 && ($user['kyc_status'] ?? '') !== 'verified') {
            return [
                'can_bid' => false,
                'reason' => 'kyc',
                'message' => 'This auction is restricted to KYC-verified businesses. Complete KYC to bid.',
            ];
        }
        if (empty($user['mobile_verified_at']) && SettingsService::bool('require_mobile_verification', true)) {
            return ['can_bid' => false, 'reason' => 'verify', 'message' => 'Verify your mobile number to bid.'];
        }
        if ((int) $auction['requires_approval'] === 1 && ($bidder === null || $bidder['status'] !== 'approved')) {
            return [
                'can_bid' => false,
                'reason' => 'approval',
                'message' => $bidder === null
                    ? 'Register for this auction — the seller approves bidders.'
                    : 'Your registration is awaiting seller approval.',
            ];
        }
        if ((int) $auction['deposit_required'] === 1 && ($bidder === null || (int) $bidder['deposit_paid'] !== 1)) {
            return [
                'can_bid' => false,
                'reason' => 'deposit',
                'message' => 'A deposit of ' . money($auction['deposit_amount']) . ' is required before bidding.',
            ];
        }
        return ['can_bid' => true, 'reason' => 'ok', 'message' => ''];
    }
}
