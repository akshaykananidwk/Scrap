<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Business;
use App\Models\WantedRequirement;
use App\Services\RequirementService;
use App\Services\SettingsService;

final class RequirementApiController extends BaseApiController
{
    public function index(Request $request): Response
    {
        $filters = array_filter([
            'q' => trim((string) $request->query('q', '')),
            'category_id' => $request->int('category_id'),
            'material_id' => $request->int('material_id'),
            'state_id' => $request->int('state_id'),
            'frequency' => (string) $request->query('frequency', ''),
            'status' => 'open',
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        $perPage = min(50, max(5, $request->int('per_page', 20)));
        return $this->paginated(
            WantedRequirement::paginate($filters, $request->page(), $perPage),
            fn (array $row): array => $this->present($row)
        );
    }

    public function show(Request $request): Response
    {
        $requirement = WantedRequirement::detail($request->paramInt('id'));
        if ($requirement === null) {
            return $this->error('Requirement not found.', 404);
        }

        $data = $this->present($requirement);
        $data['description'] = $requirement['description'];
        $data['min_quantity'] = $requirement['min_quantity'] !== null ? (float) $requirement['min_quantity'] : null;
        $data['max_quantity'] = $requirement['max_quantity'] !== null ? (float) $requirement['max_quantity'] : null;
        $data['required_by'] = $requirement['required_by'];
        $data['payment_terms'] = $requirement['payment_terms'];
        $data['delivery_required'] = (int) $requirement['delivery_required'] === 1;
        $data['buyer'] = [
            'business' => $requirement['business_name'] ?? null,
            'verified' => (int) ($requirement['kyc_verified'] ?? 0) === 1,
            'rating' => (float) ($requirement['rating_avg'] ?? 0),
        ];

        return $this->data($data);
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return $this->error('Unauthenticated.', 401);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|min:8|max:190',
            'category_id' => 'required|integer|exists:categories,id',
            'quantity' => 'required|numeric|gt:0',
            'unit_id' => 'required|integer|exists:units,id',
            'state_id' => 'required|integer|exists:states,id',
            'frequency' => 'required|in:' . implode(',', array_keys(WantedRequirement::FREQUENCIES)),
        ]);
        if ($validator->fails()) {
            return $this->error((string) $validator->firstError(), 422, ['errors' => $validator->errors()]);
        }

        $business = Business::forUser((int) $user['id']);
        $state = \App\Models\State::find($request->int('state_id'));
        $title = (string) $request->input('title');

        $requirementId = WantedRequirement::create([
            'reference' => WantedRequirement::generateReference(),
            'user_id' => (int) $user['id'],
            'business_id' => $business['id'] ?? null,
            'title' => $title,
            'slug' => WantedRequirement::uniqueSlug($title),
            'category_id' => $request->int('category_id'),
            'material_id' => $request->int('material_id') ?: null,
            'description' => $request->input('description'),
            'quantity' => dec($request->input('quantity'), 3),
            'unit_id' => $request->int('unit_id'),
            'min_quantity' => $request->input('min_quantity') ? dec($request->input('min_quantity'), 3) : null,
            'max_quantity' => $request->input('max_quantity') ? dec($request->input('max_quantity'), 3) : null,
            'target_price' => $request->input('target_price') ? dec($request->input('target_price'), 2) : null,
            'price_basis' => (string) $request->input('price_basis', 'per_mt'),
            'frequency' => (string) $request->input('frequency'),
            'delivery_required' => $request->bool('delivery_required') ? 1 : 0,
            'state_id' => (int) $state['id'],
            'state_name' => $state['name'],
            'city_id' => $request->int('city_id') ?: null,
            'city_name' => $request->input('city_name') ?: null,
            'pincode' => $request->input('pincode') ?: null,
            'required_by' => $request->input('required_by') ?: null,
            'payment_terms' => (string) $request->input('payment_terms', 'on_delivery'),
            'status' => 'open',
            'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+' . SettingsService::int('requirement_default_expiry_days', 30) . ' days')),
        ]);

        return Response::json([
            'success' => true,
            'data' => $this->present(WantedRequirement::detail($requirementId) ?? []),
        ], 201);
    }

    public function offer(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->error('Unauthenticated.', 401);
        }

        $result = RequirementService::submitOffer($request->paramInt('id'), (int) Auth::id(), [
            'available_quantity' => $request->input('available_quantity'),
            'offered_price' => $request->input('offered_price'),
            'price_basis' => $request->input('price_basis'),
            'listing_id' => $request->int('listing_id') ?: null,
            'gst_included' => $request->bool('gst_included'),
            'delivery_offered' => $request->bool('delivery_offered'),
            'delivery_days' => $request->int('delivery_days') ?: null,
            'message' => $request->input('message'),
        ]);

        return $result['ok']
            ? $this->data(['offer_id' => $result['offer_id'], 'message' => $result['message']])
            : $this->error((string) $result['error']);
    }

    private function present(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'reference' => $row['reference'] ?? null,
            'title' => $row['title'] ?? null,
            'slug' => $row['slug'] ?? null,
            'url' => !empty($row['slug']) ? base_url('wanted/' . $row['slug']) : null,
            'status' => $row['status'] ?? null,
            'category' => $row['category_name'] ?? null,
            'material' => $row['material_name'] ?? null,
            'quantity' => (float) ($row['quantity'] ?? 0),
            'unit' => $row['unit_code'] ?? null,
            'target_price' => isset($row['target_price']) && $row['target_price'] !== null ? (float) $row['target_price'] : null,
            'price_basis' => $row['price_basis'] ?? null,
            'frequency' => $row['frequency'] ?? null,
            'location' => array_filter([
                'city' => $row['city_name'] ?? null,
                'state' => $row['state_name'] ?? null,
            ]),
            'offer_count' => (int) ($row['offer_count'] ?? 0),
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}
