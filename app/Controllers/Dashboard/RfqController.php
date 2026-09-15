<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Business;
use App\Models\Category;
use App\Models\City;
use App\Models\Rfq;
use App\Models\State;
use App\Models\Unit;
use App\Services\RfqService;

final class RfqController extends Controller
{
    protected string $layout = 'layouts/dashboard';

    public function index(Request $request): Response
    {
        return $this->view('dashboard/rfqs', [
            'title' => 'My RFQs',
            'rfqs' => Rfq::paginate(['buyer_id' => $this->userId()], $request->page(), 15),
            'statuses' => Rfq::STATUSES,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('dashboard/rfq_form', [
            'title' => 'Create an RFQ',
            'categories' => Category::tree(),
            'units' => Unit::active(),
            'states' => State::active(),
            'cities' => City::major(30),
            'payment_terms' => \App\Models\Listing::PAYMENT_TERMS,
            'business' => Business::forUser($this->userId()),
            'suggested_sellers' => $this->suggestedSellers(null),
        ]);
    }

    public function store(Request $request): Response
    {
        $validator = $this->validate($request, [
            'title' => 'required|min:8|max:190',
            'closes_at' => 'required|date',
            'visibility' => 'required|in:public,invited',
            'description' => 'nullable|max:5000',
        ], ['title' => 'RFQ title', 'closes_at' => 'Quote deadline']);

        if ($validator->fails()) {
            return $validator->failResponse($request, '/dashboard/rfq/create');
        }

        $items = [];
        foreach ($request->array('items') as $item) {
            if (!is_array($item) || empty($item['item_name']) || empty($item['quantity'])) {
                continue;
            }
            $items[] = [
                'item_name' => $item['item_name'],
                'material_id' => $item['material_id'] ?? null,
                'grade_id' => $item['grade_id'] ?? null,
                'specification' => $item['specification'] ?? null,
                'quantity' => $item['quantity'],
                'unit_id' => (int) ($item['unit_id'] ?? Unit::defaultId()),
                'target_price' => $item['target_price'] ?? null,
            ];
        }

        if ($items === []) {
            flash('danger', 'Add at least one line item with a name and quantity.');
            return $this->back('/dashboard/rfq/create');
        }

        $closesAt = to_utc((string) $request->input('closes_at'));
        if ($closesAt === null || strtotime($closesAt) <= time()) {
            flash('danger', 'The quote deadline must be in the future.');
            return $this->back('/dashboard/rfq/create');
        }

        $city = $request->int('city_id') ? City::withState($request->int('city_id')) : null;

        $result = RfqService::create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'category_id' => $request->int('category_id') ?: null,
            'delivery_address' => $request->input('delivery_address'),
            'city_id' => $city['id'] ?? null,
            'state_id' => $request->int('state_id') ?: null,
            'city_name' => $city['name'] ?? null,
            'payment_terms' => $request->input('payment_terms', 'on_delivery'),
            'delivery_required_by' => $request->input('delivery_required_by') ?: null,
            'visibility' => $request->input('visibility', 'public'),
            'closes_at' => $closesAt,
            'status' => 'open',
        ], $items, $this->userId());

        if (!$result['ok']) {
            flash('danger', $result['error']);
            return $this->back('/dashboard/rfq/create');
        }

        $invites = array_map('intval', $request->array('invite_sellers'));
        if ($invites !== []) {
            $invited = RfqService::invite($result['rfq_id'], $invites, $this->userId());
            flash('info', $invited . ' supplier(s) invited to quote.');
        }

        flash('success', $result['message']);
        return $this->redirect('/dashboard/rfq/' . $result['rfq_id']);
    }

    public function show(Request $request): Response
    {
        $rfq = $this->ownedRfq($request->paramInt('id'));
        $quotes = Rfq::quotes((int) $rfq['id']);
        foreach ($quotes as &$quote) {
            $quote['items'] = Rfq::quoteItems((int) $quote['id']);
        }

        return $this->view('dashboard/rfq_show', [
            'title' => 'RFQ ' . $rfq['reference'],
            'rfq' => $rfq,
            'items' => Rfq::items((int) $rfq['id']),
            'quotes' => $quotes,
            'invites' => Rfq::invites((int) $rfq['id']),
            'suggested_sellers' => $this->suggestedSellers($rfq['category_id'] !== null ? (int) $rfq['category_id'] : null),
        ]);
    }

    public function invite(Request $request): Response
    {
        $rfq = $this->ownedRfq($request->paramInt('id'));
        $sellers = array_map('intval', $request->array('seller_ids'));
        if ($sellers === []) {
            return $this->fail('Select at least one supplier to invite.');
        }

        $invited = RfqService::invite((int) $rfq['id'], $sellers, $this->userId());
        flash('success', $invited . ' supplier(s) invited.');
        return $this->back('/dashboard/rfq/' . $rfq['id']);
    }

    public function award(Request $request): Response
    {
        $rfq = $this->ownedRfq($request->paramInt('id'));
        $quoteId = $request->int('quote_id');
        if ($quoteId <= 0) {
            return $this->fail('Choose the quote to award.');
        }

        $result = RfqService::award((int) $rfq['id'], $quoteId, $this->userId());
        if ($result['ok']) {
            flash('success', $result['message']);
            return $this->redirect('/dashboard/orders/' . $result['order_id']);
        }
        flash('danger', $result['error']);
        return $this->back('/dashboard/rfq/' . $rfq['id']);
    }

    public function close(Request $request): Response
    {
        $rfq = $this->ownedRfq($request->paramInt('id'));
        $status = $request->input('status') === 'cancelled' ? 'cancelled' : 'closed';
        $result = RfqService::close((int) $rfq['id'], $this->userId(), $status);

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);
        return $this->back('/dashboard/rfq');
    }

    public function shortlist(Request $request): Response
    {
        $result = RfqService::shortlist($request->paramInt('id'), $this->userId());
        if ($request->wantsJson()) {
            return $result['ok'] ? $this->ok($result, $result['message']) : $this->fail($result['error']);
        }
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);
        return $this->back();
    }

    /** RFQs this seller has quoted on. */
    public function myQuotes(Request $request): Response
    {
        return $this->view('dashboard/rfq_quotes', [
            'title' => 'My quotes',
            'quotes' => \App\Core\Model::paginateQuery(
                'SELECT q.*, r.title, r.reference, r.status AS rfq_status, r.closes_at, r.awarded_quote_id
                 FROM rfq_quotes q
                 INNER JOIN rfqs r ON r.id = q.rfq_id
                 WHERE q.seller_id = :u ORDER BY q.id DESC',
                ['u' => $this->userId()],
                $request->page(),
                20,
                'SELECT COUNT(*) FROM rfq_quotes WHERE seller_id = :u'
            ),
            'open_rfqs' => Rfq::paginate([
                'status' => ['open', 'closing_soon'],
                'invited_seller_id' => $this->userId(),
            ], 1, 6)->items,
        ]);
    }

    private function suggestedSellers(?int $categoryId): array
    {
        $sql = 'SELECT DISTINCT u.id, u.full_name, b.name AS business_name, b.city_name, b.kyc_verified, b.rating_avg
                FROM users u
                INNER JOIN businesses b ON b.user_id = u.id AND b.deleted_at IS NULL
                INNER JOIN listings l ON l.user_id = u.id AND l.status = "active" AND l.deleted_at IS NULL
                WHERE u.status = "active" AND u.id <> :me';
        $params = ['me' => $this->userId()];

        if ($categoryId !== null) {
            $sql .= ' AND (l.category_id = :c OR l.subcategory_id = :c2)';
            $params['c'] = $categoryId;
            $params['c2'] = $categoryId;
        }

        return Database::instance()->select(
            $sql . ' ORDER BY b.kyc_verified DESC, b.rating_avg DESC LIMIT 40',
            $params
        );
    }

    private function ownedRfq(int $id): array
    {
        $rfq = Rfq::detail($id);
        if ($rfq === null) {
            throw new HttpException(404, 'RFQ not found.');
        }
        $this->authorize(
            (int) $rfq['buyer_id'] === $this->userId() || Auth::isStaff(),
            'That RFQ belongs to another account.'
        );
        return $rfq;
    }
}
