<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Business;
use App\Models\Category;
use App\Models\Listing;
use App\Models\State;
use App\Services\SettingsService;

final class ListingApiController extends BaseApiController
{
    public function index(Request $request): Response
    {
        $filters = array_filter([
            'q' => trim((string) $request->query('q', '')),
            'category_id' => $request->int('category_id'),
            'material_id' => $request->int('material_id'),
            'state_id' => $request->int('state_id'),
            'city_id' => $request->int('city_id'),
            'listing_type' => (string) $request->query('listing_type', ''),
            'verified_only' => $request->query('verified_only') ? 1 : 0,
            'min_price' => $request->query('min_price', ''),
            'max_price' => $request->query('max_price', ''),
            'sort' => (string) $request->query('sort', 'newest'),
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        $perPage = min(50, max(5, $request->int('per_page', 20)));
        $listings = Listing::search($filters, $request->page(), $perPage);

        return $this->paginated($listings, fn (array $listing): array => $this->presentListing($listing));
    }

    public function show(Request $request): Response
    {
        $listing = Listing::findDetail($request->paramInt('id'));
        if ($listing === null || ($listing['status'] !== 'active' && Auth::id() !== (int) $listing['user_id'])) {
            return $this->error('Listing not found.', 404);
        }

        $data = $this->presentListing($listing);
        $data['description'] = $listing['description'];
        $data['condition'] = $listing['material_condition'];
        $data['source'] = $listing['material_source'];
        $data['min_order_quantity'] = $listing['min_order_quantity'] !== null ? (float) $listing['min_order_quantity'] : null;
        $data['gst_rate'] = (float) $listing['gst_rate'];
        $data['hsn_code'] = $listing['hsn_code'];
        $data['payment_terms'] = $listing['payment_terms'];
        $data['loading_by'] = $listing['loading_by'];
        $data['transport_by'] = $listing['transport_by'];
        $data['delivery_available'] = (int) $listing['delivery_available'] === 1;
        $data['inspection_available'] = (int) $listing['inspection_available'] === 1;
        $data['view_count'] = (int) $listing['view_count'];
        $data['images'] = array_map(
            static fn (array $image): string => upload_url($image['file_path']),
            Listing::images((int) $listing['id'])
        );
        // Contact details require an authenticated token, exactly like the web UI.
        $data['seller']['contact'] = Auth::check() ? [
            'name' => $listing['seller_name'],
            'mobile' => $listing['seller_mobile'],
            'email' => $listing['seller_email'],
        ] : null;

        return $this->data($data);
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return $this->error('Unauthenticated.', 401);
        }
        if (!Auth::isSeller()) {
            return $this->error('Your account type cannot create listings. Switch to seller or both.', 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|min:8|max:190',
            'category_id' => 'required|integer|exists:categories,id',
            'quantity' => 'required|numeric|gt:0',
            'unit_id' => 'required|integer|exists:units,id',
            'state_id' => 'required|integer|exists:states,id',
            'listing_type' => 'required|in:' . implode(',', array_keys(Listing::TYPES)),
            'price' => 'nullable|numeric|min_value:0',
        ]);
        if ($validator->fails()) {
            return $this->error((string) $validator->firstError(), 422, ['errors' => $validator->errors()]);
        }

        $category = Category::find($request->int('category_id'));
        $state = State::find($request->int('state_id'));
        $business = Business::forUser((int) $user['id']);
        $unitId = $request->int('unit_id');

        $pricing = Listing::computePricing([
            'price' => $request->input('price'),
            'price_basis' => $request->input('price_basis', 'per_mt'),
            'quantity' => $request->input('quantity'),
        ], $unitId);

        $autoApprove = !SettingsService::bool('listing_requires_approval', true)
            || ($user['kyc_status'] ?? '') === 'verified';
        $title = (string) $request->input('title');

        $listingId = Listing::create([
            'reference' => Listing::generateReference(),
            'user_id' => (int) $user['id'],
            'business_id' => $business['id'] ?? null,
            'title' => $title,
            'slug' => Listing::uniqueSlug($title),
            'category_id' => $category['parent_id'] ? (int) $category['parent_id'] : (int) $category['id'],
            'subcategory_id' => $category['parent_id'] ? (int) $category['id'] : null,
            'material_id' => $request->int('material_id') ?: null,
            'grade_id' => $request->int('grade_id') ?: null,
            'grade_text' => $request->input('grade_text') ?: null,
            'description' => $request->input('description'),
            'listing_type' => (string) $request->input('listing_type'),
            'quantity' => dec($request->input('quantity'), 3),
            'unit_id' => $unitId,
            'min_order_quantity' => $request->input('min_order_quantity') ? dec($request->input('min_order_quantity'), 3) : null,
            'estimated_weight_kg' => $pricing['estimated_weight_kg'],
            'price' => $pricing['price'],
            'price_per_kg' => $pricing['price_per_kg'],
            'price_per_mt' => $pricing['price_per_mt'],
            'total_price' => $pricing['total_price'],
            'is_negotiable' => $request->bool('is_negotiable') ? 1 : 0,
            'gst_applicable' => 1,
            'gst_rate' => dec($request->input('gst_rate', 18), 2),
            'material_condition' => (string) $request->input('material_condition', 'as_is'),
            'material_source' => (string) $request->input('material_source', 'industrial'),
            'city_id' => $request->int('city_id') ?: null,
            'state_id' => (int) $state['id'],
            'state_name' => $state['name'],
            'city_name' => $request->input('city_name') ?: null,
            'pincode' => $request->input('pincode') ?: null,
            'payment_terms' => (string) $request->input('payment_terms', 'advance'),
            'status' => $autoApprove ? 'active' : 'pending',
            'published_at' => $autoApprove ? now() : null,
            'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+' . SettingsService::int('listing_default_expiry_days', 30) . ' days')),
        ]);

        \App\Services\AuditService::log('listing_created_api', 'listing', $listingId);

        $listing = Listing::findDetail($listingId);
        return Response::json([
            'success' => true,
            'data' => $this->presentListing($listing ?? []),
            'meta' => ['status' => $autoApprove ? 'active' : 'pending_approval'],
        ], 201);
    }

    public function changeStatus(Request $request): Response
    {
        $listing = Listing::find($request->paramInt('id'));
        if ($listing === null) {
            return $this->error('Listing not found.', 404);
        }
        if ((int) $listing['user_id'] !== Auth::id()) {
            return $this->error('That listing belongs to another account.', 403);
        }

        $status = (string) $request->input('status', '');
        if (!in_array($status, ['active', 'paused', 'sold', 'archived'], true)) {
            return $this->error('Invalid status.');
        }
        if ($listing['status'] === 'pending' && $status === 'active') {
            return $this->error('This listing is awaiting approval.', 403);
        }

        Listing::updateById((int) $listing['id'], [
            'status' => $status,
            'sold_at' => $status === 'sold' ? now() : null,
        ], true);

        return $this->data(['id' => (int) $listing['id'], 'status' => $status]);
    }
}
