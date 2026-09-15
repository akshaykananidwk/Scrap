<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Auction;
use App\Models\Category;
use App\Services\AuctionService;
use App\Services\BidService;

final class AuctionController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(Request $request): Response
    {
        $filters = array_filter([
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'auction_type' => (string) $request->query('auction_type', ''),
            'category_id' => $request->int('category_id'),
            'sort' => (string) $request->query('sort', 'newest'),
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        return $this->view('admin/auctions', [
            'title' => 'Auctions',
            'auctions' => Auction::paginate($filters, $request->page(), 25),
            'filters' => $filters,
            'counts' => Auction::counts(),
            'categories' => Category::tree(false),
        ]);
    }

    public function show(Request $request): Response
    {
        $auction = Auction::detail($request->paramInt('id'));
        if ($auction === null) {
            throw new HttpException(404, 'Auction not found.');
        }

        return $this->view('admin/auction_show', [
            'title' => 'Auction ' . $auction['reference'],
            'auction' => $auction,
            'state' => BidService::liveState((int) $auction['id'], null),
            // Administrators see full bidder identity, with IP, for fraud review.
            'bids' => Auction::bidHistory((int) $auction['id'], false, 200),
            'bidders' => Auction::bidders((int) $auction['id']),
            'events' => Auction::events((int) $auction['id'], 100),
            'raw_bids' => \App\Core\Database::instance()->select(
                'SELECT b.*, u.full_name, u.mobile FROM bids b
                 INNER JOIN users u ON u.id = b.user_id
                 WHERE b.auction_id = :a ORDER BY b.id DESC LIMIT 200',
                ['a' => (int) $auction['id']]
            ),
        ]);
    }

    public function cancel(Request $request): Response
    {
        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            return $this->fail('Give the bidders a reason for cancelling.');
        }

        $result = AuctionService::cancel($request->paramInt('id'), $reason, $this->userId());
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->back('/admin/auctions');
    }

    /** Force-close a live auction (e.g. the seller asked support to end it early). */
    public function close(Request $request): Response
    {
        $result = AuctionService::close(
            $request->paramInt('id'),
            'Closed early by an administrator'
        );

        if ($result['ok']) {
            flash('success', 'Auction closed as ' . label((string) $result['status']) . '.');
        } else {
            flash('danger', (string) $result['error']);
        }
        return $this->back('/admin/auctions');
    }
}
