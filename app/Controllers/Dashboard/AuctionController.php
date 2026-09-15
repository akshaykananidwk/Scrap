<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Auction;
use App\Services\AuctionService;
use App\Services\BidService;

final class AuctionController extends Controller
{
    protected string $layout = 'layouts/dashboard';

    public function index(Request $request): Response
    {
        $filters = array_filter([
            'owner_id' => $this->userId(),
            'status' => (string) $request->query('status', ''),
            'sort' => (string) $request->query('sort', 'newest'),
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        return $this->view('dashboard/auctions', [
            'title' => 'My auctions',
            'auctions' => Auction::paginate($filters, $request->page(), 15),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request): Response
    {
        $auction = $this->ownedAuction($request->paramInt('id'));

        return $this->view('dashboard/auction_manage', [
            'title' => 'Auction ' . $auction['reference'],
            'auction' => $auction,
            'state' => BidService::liveState((int) $auction['id'], $this->userId()),
            // The seller sees real bidder identities on their own auction.
            'bids' => Auction::bidHistory((int) $auction['id'], false, 100),
            'bidders' => Auction::bidders((int) $auction['id']),
            'events' => Auction::events((int) $auction['id'], 50),
        ]);
    }

    public function cancel(Request $request): Response
    {
        $auction = $this->ownedAuction($request->paramInt('id'));
        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            return $this->fail('Give the bidders a reason for cancelling.');
        }

        $result = AuctionService::cancel((int) $auction['id'], $reason, $this->userId());
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);
        return $this->back('/dashboard/auctions');
    }

    public function award(Request $request): Response
    {
        $auction = $this->ownedAuction($request->paramInt('id'));
        $bidId = $request->int('bid_id') ?: null;

        $result = AuctionService::award((int) $auction['id'], $bidId);
        if ($result['ok']) {
            flash('success', $result['message']);
            return $this->redirect('/dashboard/orders/' . $result['order_id']);
        }
        flash('danger', $result['error']);
        return $this->back('/dashboard/auctions/' . $auction['id']);
    }

    /** Approve, reject or block a registered bidder. */
    public function updateBidder(Request $request): Response
    {
        $auction = $this->ownedAuction($request->paramInt('id'));
        $userId = $request->paramInt('userId');
        $status = (string) $request->input('status', '');

        if (!in_array($status, ['approved', 'rejected', 'blocked', 'registered'], true)) {
            return $this->fail('Invalid bidder status.');
        }

        $affected = Database::instance()->statement(
            'UPDATE auction_bidders SET status = :s, approved_by = :a, updated_at = :n
             WHERE auction_id = :au AND user_id = :u',
            ['s' => $status, 'a' => $this->userId(), 'n' => now(), 'au' => (int) $auction['id'], 'u' => $userId]
        );

        if ($affected > 0) {
            \App\Services\NotificationService::dispatch($userId, 'new_bid', [
                'title' => 'Auction registration ' . label($status),
                'body' => 'Your registration for "' . $auction['title'] . '" is now ' . label($status) . '.',
                'link' => '/auctions/' . $auction['id'],
                'entity_type' => 'auction',
                'entity_id' => (int) $auction['id'],
            ]);
            \App\Services\AuditService::log('auction_bidder_' . $status, 'auction', (int) $auction['id'], null, ['user_id' => $userId]);
        }

        if ($request->wantsJson()) {
            return $this->ok(['status' => $status], 'Bidder marked as ' . label($status) . '.');
        }
        flash('success', 'Bidder marked as ' . label($status) . '.');
        return $this->back('/dashboard/auctions/' . $auction['id']);
    }

    public function bids(Request $request): Response
    {
        $filters = array_filter([
            'status' => (string) $request->query('status', ''),
            'winning' => $request->query('winning') ? 1 : 0,
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        return $this->view('dashboard/bids', [
            'title' => 'My bids',
            'bids' => BidService::userBids($this->userId(), $filters, $request->page(), 20),
            'filters' => $filters,
            'watching' => Auction::paginate(['bidder_id' => $this->userId(), 'status' => 'live'], 1, 6)->items,
        ]);
    }

    private function ownedAuction(int $id): array
    {
        $auction = Auction::detail($id);
        if ($auction === null) {
            throw new HttpException(404, 'Auction not found.');
        }
        $this->authorize(
            (int) $auction['owner_id'] === $this->userId() || Auth::isStaff(),
            'That auction belongs to another account.'
        );
        return $auction;
    }
}
