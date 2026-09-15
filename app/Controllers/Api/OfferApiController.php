<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Listing;
use App\Services\OfferService;

final class OfferApiController extends BaseApiController
{
    public function index(Request $request): Response
    {
        $filters = array_filter([
            'user_id' => Auth::id(),
            'role' => (string) $request->query('role', ''),
            'status' => (string) $request->query('status', ''),
        ], static fn ($v): bool => $v !== '' && $v !== null);

        return $this->paginated(
            OfferService::paginate($filters, $request->page(), min(50, max(5, $request->int('per_page', 20)))),
            fn (array $offer): array => $this->present($offer)
        );
    }

    public function store(Request $request): Response
    {
        $listing = Listing::find($request->int('listing_id'));
        if ($listing === null) {
            return $this->error('Listing not found.', 404);
        }

        $result = OfferService::create([
            'listing_id' => (int) $listing['id'],
            'buyer_id' => (int) Auth::id(),
            'seller_id' => (int) $listing['user_id'],
            'created_by' => (int) Auth::id(),
            'amount' => (string) $request->input('amount', '0'),
            'price_basis' => (string) $request->input('price_basis', 'per_mt'),
            'quantity' => (string) $request->input('quantity', '0'),
            'unit_id' => (int) $listing['unit_id'],
            'gst_included' => $request->bool('gst_included'),
            'transport_included' => $request->bool('transport_included'),
            'payment_terms' => (string) $request->input('payment_terms', $listing['payment_terms']),
            'message' => $request->input('message'),
        ]);

        if (!$result['ok']) {
            return $this->error((string) $result['error']);
        }

        return Response::json([
            'success' => true,
            'data' => $this->present(OfferService::detail($result['offer_id']) ?? []),
        ], 201);
    }

    public function accept(Request $request): Response
    {
        $result = OfferService::accept($request->paramInt('id'), (int) Auth::id());
        return $result['ok']
            ? $this->data(['order_id' => $result['order_id'], 'message' => $result['message']])
            : $this->error((string) $result['error']);
    }

    public function reject(Request $request): Response
    {
        $result = OfferService::reject(
            $request->paramInt('id'),
            (int) Auth::id(),
            (string) $request->input('reason', '')
        );
        return $result['ok'] ? $this->data(['message' => $result['message']]) : $this->error((string) $result['error']);
    }

    private function present(array $offer): array
    {
        return [
            'id' => (int) ($offer['id'] ?? 0),
            'reference' => $offer['reference'] ?? null,
            'status' => $offer['status'] ?? null,
            'direction' => $offer['direction'] ?? null,
            'amount' => (float) ($offer['amount'] ?? 0),
            'price_basis' => $offer['price_basis'] ?? null,
            'quantity' => (float) ($offer['quantity'] ?? 0),
            'unit' => $offer['unit_code'] ?? null,
            'message' => $offer['message'] ?? null,
            'listing' => !empty($offer['listing_title']) ? [
                'id' => isset($offer['listing_id']) ? (int) $offer['listing_id'] : null,
                'title' => $offer['listing_title'],
                'url' => !empty($offer['listing_slug']) ? base_url('listing/' . $offer['listing_slug']) : null,
            ] : null,
            'buyer' => $offer['buyer_business'] ?? ($offer['buyer_name'] ?? null),
            'seller' => $offer['seller_business'] ?? ($offer['seller_name'] ?? null),
            'expires_at' => $offer['expires_at'] ?? null,
            'order_reference' => $offer['order_reference'] ?? null,
            'created_at' => $offer['created_at'] ?? null,
        ];
    }
}
