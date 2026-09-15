<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploader;
use App\Models\Business;
use App\Models\Category;
use App\Models\City;
use App\Models\Material;
use App\Models\State;
use App\Models\Unit;
use App\Models\WantedRequirement;
use App\Services\AuditService;
use App\Services\RequirementService;
use App\Services\SettingsService;

final class RequirementController extends Controller
{
    protected string $layout = 'layouts/dashboard';

    public function index(Request $request): Response
    {
        $filters = array_filter([
            'user_id' => $this->userId(),
            'status' => (string) $request->query('status', 'any'),
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        return $this->view('dashboard/requirements', [
            'title' => 'My requirements',
            'requirements' => WantedRequirement::paginate($filters, $request->page(), 15),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('dashboard/requirement_form', [
            'title' => 'Post a requirement',
            'requirement' => null,
            'categories' => Category::tree(),
            'materials' => [],
            'units' => Unit::active(),
            'states' => State::active(),
            'cities' => City::major(30),
            'frequencies' => WantedRequirement::FREQUENCIES,
            'basis' => WantedRequirement::BASIS,
            'payment_terms' => \App\Models\Listing::PAYMENT_TERMS,
            'business' => Business::forUser($this->userId()),
        ]);
    }

    public function store(Request $request): Response
    {
        $validator = $this->validate($request, [
            'title' => 'required|min:8|max:190',
            'category_id' => 'required|integer|exists:categories,id',
            'quantity' => 'required|numeric|gt:0',
            'unit_id' => 'required|integer|exists:units,id',
            'min_quantity' => 'nullable|numeric|min_value:0',
            'max_quantity' => 'nullable|numeric|min_value:0',
            'target_price' => 'nullable|numeric|min_value:0',
            'state_id' => 'required|integer|exists:states,id',
            'pincode' => 'nullable|pincode',
            'frequency' => 'required|in:' . implode(',', array_keys(WantedRequirement::FREQUENCIES)),
            'description' => 'nullable|max:5000',
        ], ['title' => 'Requirement title', 'category_id' => 'Category', 'unit_id' => 'Unit', 'state_id' => 'State']);

        if ($validator->fails()) {
            return $validator->failResponse($request, '/dashboard/requirements/create');
        }

        $min = $request->input('min_quantity');
        $max = $request->input('max_quantity');
        if ($min !== null && $min !== '' && $max !== null && $max !== '' && (float) $min > (float) $max) {
            flash('danger', 'Minimum quantity cannot be greater than the maximum.');
            return $this->back('/dashboard/requirements/create');
        }

        $category = Category::find($request->int('category_id'));
        $city = $request->int('city_id') ? City::withState($request->int('city_id')) : null;
        $state = State::find($request->int('state_id'));
        $business = Business::forUser($this->userId());
        $title = (string) $request->input('title');
        $expiryDays = SettingsService::int('requirement_default_expiry_days', 30);

        $requirementId = WantedRequirement::create([
            'reference' => WantedRequirement::generateReference(),
            'user_id' => $this->userId(),
            'business_id' => $business['id'] ?? null,
            'title' => $title,
            'slug' => WantedRequirement::uniqueSlug($title),
            'category_id' => (int) $category['id'],
            'material_id' => $request->int('material_id') ?: null,
            'grade_id' => $request->int('grade_id') ?: null,
            'grade_text' => $request->input('grade_text') ?: null,
            'description' => $request->input('description'),
            'quantity' => dec($request->input('quantity'), 3),
            'unit_id' => $request->int('unit_id'),
            'min_quantity' => $min !== '' && $min !== null ? dec($min, 3) : null,
            'max_quantity' => $max !== '' && $max !== null ? dec($max, 3) : null,
            'target_price' => $request->input('target_price') ? dec($request->input('target_price'), 2) : null,
            'price_basis' => (string) $request->input('price_basis', 'per_mt'),
            'frequency' => (string) $request->input('frequency', 'one_time'),
            'delivery_required' => $request->bool('delivery_required') ? 1 : 0,
            'delivery_address' => $request->input('delivery_address') ?: ($business['address_line1'] ?? null),
            'city_id' => $city['id'] ?? null,
            'state_id' => $state['id'] ?? null,
            'city_name' => $city['name'] ?? null,
            'state_name' => $state['name'] ?? null,
            'pincode' => $request->input('pincode') ?: ($business['pincode'] ?? null),
            'required_by' => $request->input('required_by') ?: null,
            'payment_terms' => (string) $request->input('payment_terms', 'on_delivery'),
            'status' => 'open',
            'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+' . $expiryDays . ' days')),
        ]);

        foreach ($request->fileList('documents') as $file) {
            $uploader = Uploader::documents('requirements');
            $path = $uploader->store($file, 1600);
            if ($path !== null) {
                Database::instance()->insert('requirement_documents', [
                    'requirement_id' => $requirementId,
                    'file_path' => $path,
                    'title' => substr((string) ($file['name'] ?? 'Attachment'), 0, 190),
                    'is_image' => str_starts_with((string) ($file['type'] ?? ''), 'image/') ? 1 : 0,
                    'created_at' => now(),
                ]);
            }
        }

        AuditService::log('requirement_created', 'requirement', $requirementId, null, ['title' => $title]);
        $this->notifyMatchingSellers($requirementId);

        flash('success', 'Requirement posted. Matching sellers have been notified.');
        return $this->redirect('/dashboard/requirements/' . $requirementId);
    }

    public function show(Request $request): Response
    {
        $requirement = $this->ownedRequirement($request->paramInt('id'));

        return $this->view('dashboard/requirement_show', [
            'title' => $requirement['title'],
            'requirement' => $requirement,
            'offers' => WantedRequirement::offers((int) $requirement['id']),
            'documents' => WantedRequirement::documents((int) $requirement['id']),
        ]);
    }

    public function changeStatus(Request $request): Response
    {
        $requirement = $this->ownedRequirement($request->paramInt('id'));
        $status = (string) $request->input('status', '');

        if (!in_array($status, ['open', 'closed', 'fulfilled', 'cancelled'], true)) {
            return $this->fail('Invalid status.');
        }

        WantedRequirement::updateById((int) $requirement['id'], ['status' => $status], true);
        AuditService::log('requirement_status', 'requirement', (int) $requirement['id'], null, ['status' => $status]);

        flash('success', 'Requirement marked as ' . label($status) . '.');
        return $this->back('/dashboard/requirements');
    }

    public function acceptOffer(Request $request): Response
    {
        $result = RequirementService::acceptOffer($request->paramInt('id'), $this->userId());
        if ($result['ok']) {
            flash('success', $result['message']);
            return $this->redirect('/dashboard/orders/' . $result['order_id']);
        }
        flash('danger', $result['error']);
        return $this->back('/dashboard/requirements');
    }

    public function rejectOffer(Request $request): Response
    {
        $result = RequirementService::rejectOffer($request->paramInt('id'), $this->userId());
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);
        return $this->back();
    }

    public function shortlistOffer(Request $request): Response
    {
        $result = RequirementService::shortlistOffer($request->paramInt('id'), $this->userId());
        if ($request->wantsJson()) {
            return $result['ok'] ? $this->ok($result, $result['message']) : $this->fail($result['error']);
        }
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);
        return $this->back();
    }

    /** Requirements a seller can supply, based on what they already list. */
    public function matches(Request $request): Response
    {
        return $this->view('dashboard/requirement_matches', [
            'title' => 'Requirements you can supply',
            'matches' => WantedRequirement::matchingForSeller($this->userId(), 40),
            'my_offers' => Database::instance()->select(
                'SELECT o.*, r.title, r.slug, r.status AS requirement_status, un.code AS unit_code
                 FROM requirement_offers o
                 INNER JOIN wanted_requirements r ON r.id = o.requirement_id
                 INNER JOIN units un ON un.id = r.unit_id
                 WHERE o.seller_id = :u ORDER BY o.id DESC LIMIT 50',
                ['u' => $this->userId()]
            ),
        ]);
    }

    private function notifyMatchingSellers(int $requirementId): void
    {
        $requirement = WantedRequirement::find($requirementId);
        if ($requirement === null) {
            return;
        }

        $sellers = Database::instance()->select(
            'SELECT DISTINCT l.user_id FROM listings l
             WHERE l.status = "active" AND l.deleted_at IS NULL AND l.user_id <> :buyer
               AND (l.material_id = :material OR l.category_id = :category)
             LIMIT 50',
            [
                'buyer' => (int) $requirement['user_id'],
                'material' => $requirement['material_id'] !== null ? (int) $requirement['material_id'] : 0,
                'category' => (int) $requirement['category_id'],
            ]
        );

        \App\Services\NotificationService::dispatchMany(
            array_map(static fn (array $r): int => (int) $r['user_id'], $sellers),
            'requirement_offer',
            [
                'title' => 'New buyer requirement you can supply',
                'body' => $requirement['title'],
                'link' => '/wanted/' . $requirement['slug'],
                'entity_type' => 'requirement',
                'entity_id' => $requirementId,
                'vars' => ['title' => $requirement['title'], 'seller' => '', 'quantity' => qty($requirement['quantity']), 'amount' => money($requirement['target_price'] ?? 0)],
            ]
        );
    }

    private function ownedRequirement(int $id): array
    {
        $requirement = WantedRequirement::detail($id);
        if ($requirement === null) {
            throw new HttpException(404, 'Requirement not found.');
        }
        $this->authorize(
            (int) $requirement['user_id'] === $this->userId() || Auth::isStaff(),
            'That requirement belongs to another account.'
        );
        return $requirement;
    }
}
