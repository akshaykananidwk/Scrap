<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Auction;
use App\Services\AuctionService;
use App\Services\BidService;

final class AuctionApiController extends BaseApiController
{
    public function index(Request $request): Response
    {
        $filters = array_filter([
            'status' => (string) $request->query('status', 'live'),
            'auction_type' => (string) $request->query('auction_type', ''),
            'category_id' => $request->int('category_id'),
            'state_id' => $request->int('state_id'),
            'q' => trim((string) $request->query('q', '')),
            'sort' => (string) $request->query('sort', 'ending_soon'),
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        $perPage = min(50, max(5, $request->int('per_page', 20)));
        return $this->paginated(
            Auction::paginate($filters, $request->page(), $perPage),
            fn (array $auction): array => $this->present($auction)
        );
    }

    public function show(Request $request): Response
    {
        $auction = Auction::detail($request->paramInt('id'));
        if ($auction === null) {
            return $this->error('Auction not found.', 404);
        }

        $data = $this->present($auction);
        $data['description'] = $auction['description'];
        $data['reserve_price'] = $auction['reserve_price'] !== null ? (float) $auction['reserve_price'] : null;
        $data['reserve_met'] = (int) $auction['reserve_met'] === 1;
        $data['requires_kyc'] = (int) $auction['requires_kyc'] === 1;
        $data['requires_approval'] = (int) $auction['requires_approval'] === 1;
        $data['deposit_required'] = (int) $auction['deposit_required'] === 1;
        $data['deposit_amount'] = (float) $auction['deposit_amount'];
        $data['extension'] = [
            'window_seconds' => (int) $auction['extension_window_seconds'],
            'duration_seconds' => (int) $auction['extension_duration_seconds'],
            'used' => (int) $auction['extension_count'],
            'max' => (int) $auction['max_extensions'],
        ];
        $data['next_valid_amount'] = (float) BidService::nextValidAmount($auction);

        return $this->data($data);
    }

    /** Poll target for a mobile app's live auction screen. */
    public function state(Request $request): Response
    {
        $state = BidService::liveState($request->paramInt('id'), Auth::id());
        if (!$state['ok']) {
            return $this->error((string) ($state['error'] ?? 'Auction not found.'), 404);
        }
        return $this->data($state);
    }

    public function bids(Request $request): Response
    {
        $auction = Auction::find($request->paramInt('id'));
        if ($auction === null) {
            return $this->error('Auction not found.', 404);
        }

        $mask = (int) $auction['mask_bidders'] === 1 && Auth::id() !== (int) $auction['owner_id'];
        $bids = Auction::bidHistory((int) $auction['id'], $mask, 50);

        return $this->data(array_map(static fn (array $bid): array => [
            'id' => (int) $bid['id'],
            'bidder' => $bid['display_name'],
            'amount' => (float) $bid['amount'],
            'status' => $bid['status'],
            'placed_at' => $bid['placed_at'],
            'is_you' => Auth::id() !== null && (int) $bid['user_id'] === Auth::id(),
        ], $bids));
    }

    public function placeBid(Request $request): Response
    {
        $auctionId = $request->int('auction_id');
        $amount = (string) $request->input('amount', '');

        if ($auctionId <= 0) {
            return $this->error('auction_id is required.');
        }

        $result = BidService::place(
            $auctionId,
            (int) Auth::id(),
            $amount,
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            return $this->error($result['message'], 422, ['code' => $result['error']]);
        }

        AuctionService::notifyOutbid($auctionId, $result['previous_leader'] ?? null, $result['amount']);

        return $this->data([
            'bid_id' => $result['bid_id'],
            'amount' => (float) $result['amount'],
            'extended' => $result['extended'],
            'ends_at' => $result['ends_at'],
            'message' => $result['message'],
            'state' => BidService::liveState($auctionId, Auth::id()),
        ]);
    }

    private function present(array $auction): array
    {
        return [
            'id' => (int) $auction['id'],
            'reference' => $auction['reference'],
            'title' => $auction['title'],
            'type' => $auction['auction_type'],
            'status' => $auction['status'],
            'quantity' => (float) $auction['quantity'],
            'unit' => $auction['unit_code'] ?? null,
            'price_basis' => $auction['price_basis'],
            'starting_price' => (float) $auction['starting_price'],
            'current_price' => (float) ($auction['current_price'] ?? $auction['starting_price']),
            'bid_increment' => (float) $auction['bid_increment'],
            'bid_count' => (int) $auction['bid_count'],
            'bidder_count' => (int) $auction['bidder_count'],
            'starts_at' => $auction['starts_at'],
            'ends_at' => $auction['ends_at'],
            'seconds_remaining' => countdown_seconds($auction['ends_at']),
            'seller' => $auction['business_name'] ?? null,
            'location' => array_filter([
                'city' => $auction['city_name'] ?? null,
                'state' => $auction['state_name'] ?? null,
            ]),
            'image' => !empty($auction['image']) ? upload_url($auction['image']) : null,
            'url' => base_url('auctions/' . $auction['id']),
        ];
    }
}
