<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Response;

/**
 * Shared response envelope for the v1 API:
 *   { "success": bool, "data": …, "meta": …, "error": string|null }
 */
abstract class BaseApiController extends Controller
{
    protected function data(mixed $data, array $meta = []): Response
    {
        $payload = ['success' => true, 'data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }
        return Response::json($payload);
    }

    protected function paginated(Paginator $paginator, callable $transform): Response
    {
        return Response::json([
            'success' => true,
            'data' => array_map($transform, $paginator->items),
            'meta' => [
                'total' => $paginator->total,
                'page' => $paginator->page,
                'per_page' => $paginator->perPage,
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    protected function error(string $message, int $status = 422, array $extra = []): Response
    {
        return Response::json(array_merge(['success' => false, 'error' => $message], $extra), $status);
    }

    /** Standard listing shape reused by several endpoints. */
    protected function presentListing(array $listing): array
    {
        return [
            'id' => (int) $listing['id'],
            'reference' => $listing['reference'] ?? null,
            'title' => $listing['title'],
            'slug' => $listing['slug'],
            'url' => base_url('listing/' . $listing['slug']),
            'type' => $listing['listing_type'],
            'status' => $listing['status'],
            'category' => $listing['category_name'] ?? null,
            'material' => $listing['material_name'] ?? null,
            'grade' => $listing['grade_name'] ?? ($listing['grade_text'] ?? null),
            'quantity' => (float) $listing['quantity'],
            'unit' => $listing['unit_code'] ?? null,
            'price' => $listing['price'] !== null ? (float) $listing['price'] : null,
            'price_per_kg' => $listing['price_per_kg'] !== null ? (float) $listing['price_per_kg'] : null,
            'price_per_mt' => $listing['price_per_mt'] !== null ? (float) $listing['price_per_mt'] : null,
            'negotiable' => (int) ($listing['is_negotiable'] ?? 0) === 1,
            'location' => array_filter([
                'city' => $listing['city_name'] ?? null,
                'state' => $listing['state_name'] ?? null,
                'pincode' => $listing['pincode'] ?? null,
            ]),
            'seller' => array_filter([
                'business' => $listing['business_name'] ?? null,
                'verified' => isset($listing['kyc_verified']) ? (int) $listing['kyc_verified'] === 1 : null,
                'rating' => isset($listing['rating_avg']) ? (float) $listing['rating_avg'] : null,
            ], static fn ($v): bool => $v !== null),
            'image' => !empty($listing['image']) ? upload_url($listing['image']) : null,
            'auction' => !empty($listing['auction_id']) ? [
                'id' => (int) $listing['auction_id'],
                'status' => $listing['auction_status'] ?? null,
                'current_price' => isset($listing['auction_current_price']) ? (float) $listing['auction_current_price'] : null,
                'bid_count' => (int) ($listing['auction_bid_count'] ?? 0),
                'ends_at' => $listing['auction_ends_at'] ?? null,
            ] : null,
            'created_at' => $listing['created_at'] ?? null,
        ];
    }

    protected function presentOrder(array $order): array
    {
        return [
            'id' => (int) $order['id'],
            'reference' => $order['reference'],
            'source' => $order['source_type'],
            'status' => $order['status'],
            'payment_status' => $order['payment_status'],
            'quantity' => (float) $order['quantity'],
            'unit' => $order['unit_code'] ?? null,
            'rate' => (float) $order['rate'],
            'subtotal' => (float) $order['subtotal'],
            'gst_amount' => (float) $order['gst_amount'],
            'final_amount' => (float) $order['final_amount'],
            'amount_paid' => (float) ($order['amount_paid'] ?? 0),
            'buyer' => $order['buyer_business'] ?? ($order['buyer_name'] ?? null),
            'seller' => $order['seller_business'] ?? ($order['seller_name'] ?? null),
            'created_at' => $order['created_at'],
            'url' => base_url('dashboard/orders/' . $order['id']),
        ];
    }
}
