<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Listing;
use App\Models\State;
use App\Models\WantedRequirement;
use App\Services\RequirementService;

final class RequirementController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = array_filter([
            'q' => trim((string) $request->query('q', '')),
            'category_id' => $request->int('category_id'),
            'material_id' => $request->int('material_id'),
            'state_id' => $request->int('state_id'),
            'frequency' => (string) $request->query('frequency', ''),
            'sort' => (string) $request->query('sort', 'newest'),
            'status' => 'open',
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        $requirements = WantedRequirement::paginate($filters, $request->page(), 20);

        return $this->view('wanted/index', [
            'title' => 'Buyer Requirements — Wanted Scrap Material',
            'meta_description' => 'Live buyer requirements for scrap material across India. Sellers can respond with price, quantity and delivery.',
            'requirements' => $requirements,
            'filters' => $filters,
            'categories' => Category::tree(),
            'states' => State::active(),
            'frequencies' => WantedRequirement::FREQUENCIES,
        ]);
    }

    public function show(Request $request): Response
    {
        $requirement = WantedRequirement::findBySlug((string) $request->param('slug'));
        if ($requirement === null) {
            throw new HttpException(404, 'That requirement is no longer available.');
        }

        Database::instance()->statement(
            'UPDATE wanted_requirements SET view_count = view_count + 1 WHERE id = :id',
            ['id' => (int) $requirement['id']]
        );

        $userId = Auth::id();
        $isOwner = $userId === (int) $requirement['user_id'];
        $myOffer = null;
        if ($userId !== null && !$isOwner) {
            $myOffer = Database::instance()->first(
                'SELECT * FROM requirement_offers WHERE requirement_id = :r AND seller_id = :u',
                ['r' => (int) $requirement['id'], 'u' => $userId]
            );
        }

        return $this->view('wanted/show', [
            'title' => $requirement['title'],
            'meta_description' => 'Buyer needs ' . qty($requirement['quantity'], $requirement['unit_code'])
                . ' of ' . ($requirement['material_name'] ?? 'scrap material')
                . ($requirement['city_name'] ? ' in ' . $requirement['city_name'] : '') . '.',
            'canonical' => base_url('wanted/' . $requirement['slug']),
            'requirement' => $requirement,
            'documents' => WantedRequirement::documents((int) $requirement['id']),
            'is_owner' => $isOwner,
            'my_offer' => $myOffer,
            'offer_count' => (int) $requirement['offer_count'],
            'my_listings' => $userId !== null
                ? Listing::search(['user_id' => $userId, 'status' => 'active'], 1, 20)->items
                : [],
            'similar' => WantedRequirement::paginate([
                'status' => 'open',
                'category_id' => (int) $requirement['category_id'],
            ], 1, 4)->items,
        ]);
    }

    public function submitOffer(Request $request): Response
    {
        $requirementId = $request->paramInt('id');

        $validator = $this->validate($request, [
            'available_quantity' => 'required|numeric|gt:0',
            'offered_price' => 'required|numeric|gt:0',
            'delivery_days' => 'nullable|integer|min_value:0|max_value:365',
            'message' => 'nullable|max:2000',
        ], [
            'available_quantity' => 'Available quantity',
            'offered_price' => 'Your price',
        ]);
        if ($validator->fails()) {
            return $validator->failResponse($request);
        }

        $result = RequirementService::submitOffer($requirementId, (int) Auth::id(), [
            'available_quantity' => $request->input('available_quantity'),
            'offered_price' => $request->input('offered_price'),
            'price_basis' => $request->input('price_basis'),
            'listing_id' => $request->int('listing_id') ?: null,
            'gst_included' => $request->bool('gst_included'),
            'delivery_offered' => $request->bool('delivery_offered'),
            'delivery_days' => $request->int('delivery_days') ?: null,
            'message' => $request->input('message'),
        ]);

        if ($request->wantsJson()) {
            return $result['ok'] ? $this->ok($result, $result['message']) : $this->fail($result['error']);
        }

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);
        return $this->back();
    }
}
