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
use App\Models\Auction;
use App\Models\Business;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\Material;
use App\Models\State;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\CommissionService;
use App\Services\NotificationService;
use App\Services\SettingsService;

final class ListingController extends Controller
{
    protected string $layout = 'layouts/dashboard';

    public function index(Request $request): Response
    {
        $filters = array_filter([
            'status' => (string) $request->query('status', 'any'),
            'q' => trim((string) $request->query('q', '')),
            'listing_type' => (string) $request->query('listing_type', ''),
            'sort' => (string) $request->query('sort', 'newest'),
        ], static fn ($v): bool => $v !== '');

        return $this->view('dashboard/listings', [
            'title' => 'My listings',
            'listings' => Listing::forUser($this->userId(), $filters, $request->page(), 20),
            'filters' => $filters,
            'counts' => $this->statusCounts(),
            'types' => Listing::TYPES,
        ]);
    }

    /** The 9-step sell wizard. */
    public function create(Request $request): Response
    {
        return $this->view('dashboard/listing_form', [
            'title' => 'Sell scrap',
            'listing' => null,
            'categories' => Category::tree(),
            'materials' => [],
            'grades' => [],
            'units' => Unit::active(),
            'states' => State::active(),
            'cities' => City::major(30),
            'types' => Listing::TYPES,
            'conditions' => Listing::CONDITIONS,
            'sources' => Listing::SOURCES,
            'payment_terms' => Listing::PAYMENT_TERMS,
            'responsibility' => Listing::RESPONSIBILITY,
            'business' => Business::forUser($this->userId()),
            'max_images' => SettingsService::int('max_images_per_listing', 10),
            'requires_approval' => SettingsService::bool('listing_requires_approval', true),
        ]);
    }

    public function store(Request $request): Response
    {
        $validator = $this->validate($request, $this->rules(), $this->labels());
        if ($validator->fails()) {
            return $validator->failResponse($request, '/dashboard/listings/create');
        }

        $userId = $this->userId();
        $business = Business::forUser($userId);
        $unitId = $request->int('unit_id');
        $category = Category::find($request->int('category_id'));
        if ($category === null) {
            return $this->fail('Choose a valid category.');
        }

        $pricing = Listing::computePricing([
            'price' => $request->input('price'),
            'price_basis' => $request->input('price_basis', 'per_mt'),
            'quantity' => $request->input('quantity'),
        ], $unitId);

        $material = $request->int('material_id') ? Material::find($request->int('material_id')) : null;
        $city = $request->int('city_id') ? City::withState($request->int('city_id')) : null;
        $state = State::find($request->int('state_id'));

        $listingType = (string) $request->input('listing_type', 'fixed');
        $autoApprove = !SettingsService::bool('listing_requires_approval', true)
            || (SettingsService::bool('auto_approve_verified_sellers', true) && ($this->user()['kyc_status'] ?? '') === 'verified');

        $title = (string) $request->input('title');
        $expiryDays = SettingsService::int('listing_default_expiry_days', 30);

        $listingId = Listing::create([
            'reference' => Listing::generateReference(),
            'user_id' => $userId,
            'business_id' => $business['id'] ?? null,
            'title' => $title,
            'slug' => Listing::uniqueSlug($title),
            'category_id' => $category['parent_id'] ? (int) $category['parent_id'] : (int) $category['id'],
            'subcategory_id' => $category['parent_id'] ? (int) $category['id'] : null,
            'material_id' => $material['id'] ?? null,
            'grade_id' => $request->int('grade_id') ?: null,
            'grade_text' => $request->input('grade_text') ?: null,
            'description' => $request->input('description'),
            'listing_type' => $listingType,
            'quantity' => dec($request->input('quantity'), 3),
            'unit_id' => $unitId,
            'min_order_quantity' => $request->input('min_order_quantity') ? dec($request->input('min_order_quantity'), 3) : null,
            'estimated_weight_kg' => $pricing['estimated_weight_kg'],
            'price' => $pricing['price'],
            'price_per_kg' => $pricing['price_per_kg'],
            'price_per_mt' => $pricing['price_per_mt'],
            'total_price' => $pricing['total_price'],
            'is_negotiable' => $request->bool('is_negotiable') ? 1 : 0,
            'show_price' => $request->bool('show_price') || $request->input('price') ? 1 : 0,
            'gst_applicable' => $request->bool('gst_applicable') ? 1 : 0,
            'gst_rate' => dec($request->input('gst_rate', $material['default_gst_rate'] ?? 18), 2),
            'hsn_code' => $request->input('hsn_code') ?: null,
            'material_condition' => (string) $request->input('material_condition', 'as_is'),
            'material_source' => (string) $request->input('material_source', 'industrial'),
            'pickup_address' => $request->input('pickup_address') ?: ($business['address_line1'] ?? null),
            'city_id' => $city['id'] ?? null,
            'state_id' => $state['id'] ?? null,
            'city_name' => $city['name'] ?? null,
            'state_name' => $state['name'] ?? null,
            'pincode' => $request->input('pincode') ?: ($business['pincode'] ?? null),
            'delivery_available' => $request->bool('delivery_available') ? 1 : 0,
            'pickup_available' => $request->bool('pickup_available') ? 1 : 0,
            'loading_by' => (string) $request->input('loading_by', 'buyer'),
            'transport_by' => (string) $request->input('transport_by', 'buyer'),
            'payment_terms' => (string) $request->input('payment_terms', 'advance'),
            'inspection_available' => $request->bool('inspection_available') ? 1 : 0,
            'inspection_notes' => $request->input('inspection_notes') ?: null,
            'status' => $autoApprove ? 'active' : 'pending',
            'meta_title' => mb_substr($title, 0, 180),
            'meta_description' => mb_substr(strip_tags((string) $request->input('description', '')), 0, 280),
            'published_at' => $autoApprove ? now() : null,
            'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+' . $expiryDays . ' days')),
        ]);

        $this->storeImages($request, $listingId);
        $this->storeVideosAndDocuments($request, $listingId);

        // Optional listing fee, recorded as a commission entry.
        $listingFee = (float) SettingsService::get('listing_fee', '0');
        if ($listingFee > 0) {
            CommissionService::charge($userId, 'listing_fee', $listingFee, $listingId, 'Listing ' . $title);
        }

        if ($business !== null) {
            Business::refreshStats((int) $business['id']);
        }
        \Database\Seeders\CatalogSeeder::refreshCounts(Database::instance());
        AuditService::log('listing_created', 'listing', $listingId, null, ['title' => $title, 'type' => $listingType]);

        // An auction listing goes straight to auction setup.
        if ($listingType === 'auction') {
            flash('success', $autoApprove
                ? 'Listing published. Now set up the auction.'
                : 'Listing submitted for approval. Set up the auction — it will go live once approved.');
            return $this->redirect('/dashboard/listings/' . $listingId . '/edit?tab=auction');
        }

        flash('success', $autoApprove
            ? 'Your listing is live.'
            : 'Listing submitted. Our team will approve it shortly.');
        return $this->redirect('/dashboard/listings');
    }

    public function edit(Request $request): Response
    {
        $listing = $this->ownedListing($request->paramInt('id'));
        $auction = Database::instance()->first(
            'SELECT * FROM auctions WHERE listing_id = :l AND deleted_at IS NULL ORDER BY id DESC LIMIT 1',
            ['l' => (int) $listing['id']]
        );

        return $this->view('dashboard/listing_form', [
            'title' => 'Edit listing',
            'listing' => $listing,
            'images' => Listing::images((int) $listing['id']),
            'videos' => Listing::videos((int) $listing['id']),
            'documents' => Listing::documents((int) $listing['id']),
            'auction' => $auction,
            'tab' => (string) $request->query('tab', 'details'),
            'categories' => Category::tree(),
            'materials' => $listing['category_id'] ? Material::forCategory((int) $listing['category_id']) : [],
            'grades' => $listing['material_id'] ? Material::grades((int) $listing['material_id']) : [],
            'units' => Unit::active(),
            'states' => State::active(),
            'cities' => $listing['state_id'] ? City::forState((int) $listing['state_id']) : City::major(30),
            'types' => Listing::TYPES,
            'conditions' => Listing::CONDITIONS,
            'sources' => Listing::SOURCES,
            'payment_terms' => Listing::PAYMENT_TERMS,
            'responsibility' => Listing::RESPONSIBILITY,
            'business' => Business::forUser($this->userId()),
            'max_images' => SettingsService::int('max_images_per_listing', 10),
            'requires_approval' => SettingsService::bool('listing_requires_approval', true),
        ]);
    }

    public function update(Request $request): Response
    {
        $listing = $this->ownedListing($request->paramInt('id'));

        $validator = $this->validate($request, $this->rules(), $this->labels());
        if ($validator->fails()) {
            return $validator->failResponse($request, '/dashboard/listings/' . $listing['id'] . '/edit');
        }

        $unitId = $request->int('unit_id');
        $pricing = Listing::computePricing([
            'price' => $request->input('price'),
            'price_basis' => $request->input('price_basis', 'per_mt'),
            'quantity' => $request->input('quantity'),
        ], $unitId);

        $category = Category::find($request->int('category_id'));
        $city = $request->int('city_id') ? City::withState($request->int('city_id')) : null;
        $state = State::find($request->int('state_id'));

        // Edits to a live listing go back for approval when the site requires it.
        $needsReapproval = SettingsService::bool('listing_requires_approval', true)
            && $listing['status'] === 'active'
            && ($this->user()['kyc_status'] ?? '') !== 'verified';

        Listing::updateById((int) $listing['id'], [
            'title' => (string) $request->input('title'),
            'category_id' => $category['parent_id'] ? (int) $category['parent_id'] : (int) $category['id'],
            'subcategory_id' => $category['parent_id'] ? (int) $category['id'] : null,
            'material_id' => $request->int('material_id') ?: null,
            'grade_id' => $request->int('grade_id') ?: null,
            'grade_text' => $request->input('grade_text') ?: null,
            'description' => $request->input('description'),
            'listing_type' => (string) $request->input('listing_type', $listing['listing_type']),
            'quantity' => dec($request->input('quantity'), 3),
            'unit_id' => $unitId,
            'min_order_quantity' => $request->input('min_order_quantity') ? dec($request->input('min_order_quantity'), 3) : null,
            'estimated_weight_kg' => $pricing['estimated_weight_kg'],
            'price' => $pricing['price'],
            'price_per_kg' => $pricing['price_per_kg'],
            'price_per_mt' => $pricing['price_per_mt'],
            'total_price' => $pricing['total_price'],
            'is_negotiable' => $request->bool('is_negotiable') ? 1 : 0,
            'gst_applicable' => $request->bool('gst_applicable') ? 1 : 0,
            'gst_rate' => dec($request->input('gst_rate', 18), 2),
            'hsn_code' => $request->input('hsn_code') ?: null,
            'material_condition' => (string) $request->input('material_condition', 'as_is'),
            'material_source' => (string) $request->input('material_source', 'industrial'),
            'pickup_address' => $request->input('pickup_address') ?: null,
            'city_id' => $city['id'] ?? null,
            'state_id' => $state['id'] ?? null,
            'city_name' => $city['name'] ?? null,
            'state_name' => $state['name'] ?? null,
            'pincode' => $request->input('pincode') ?: null,
            'delivery_available' => $request->bool('delivery_available') ? 1 : 0,
            'pickup_available' => $request->bool('pickup_available') ? 1 : 0,
            'loading_by' => (string) $request->input('loading_by', 'buyer'),
            'transport_by' => (string) $request->input('transport_by', 'buyer'),
            'payment_terms' => (string) $request->input('payment_terms', 'advance'),
            'inspection_available' => $request->bool('inspection_available') ? 1 : 0,
            'inspection_notes' => $request->input('inspection_notes') ?: null,
            'status' => $needsReapproval ? 'pending' : $listing['status'],
        ]);

        $this->storeImages($request, (int) $listing['id']);
        AuditService::log('listing_updated', 'listing', (int) $listing['id']);

        flash('success', $needsReapproval
            ? 'Listing updated and resubmitted for approval.'
            : 'Listing updated.');
        return $this->redirect('/dashboard/listings');
    }

    public function changeStatus(Request $request): Response
    {
        $listing = $this->ownedListing($request->paramInt('id'));
        $status = (string) $request->input('status', '');

        $allowed = ['active', 'paused', 'sold', 'archived'];
        if (!in_array($status, $allowed, true)) {
            return $this->fail('Invalid status.');
        }
        // Only an administrator can move a listing out of pending.
        if ($listing['status'] === 'pending' && $status === 'active') {
            return $this->fail('This listing is awaiting approval by our team.');
        }

        Listing::updateById((int) $listing['id'], [
            'status' => $status,
            'sold_at' => $status === 'sold' ? now() : null,
        ], true);

        AuditService::log('listing_status', 'listing', (int) $listing['id'], ['status' => $listing['status']], ['status' => $status]);
        \Database\Seeders\CatalogSeeder::refreshCounts(Database::instance());

        if ($request->wantsJson()) {
            return $this->ok(['status' => $status], 'Listing marked as ' . label($status) . '.');
        }
        flash('success', 'Listing marked as ' . label($status) . '.');
        return $this->back('/dashboard/listings');
    }

    public function destroy(Request $request): Response
    {
        $listing = $this->ownedListing($request->paramInt('id'));

        $activeAuction = Database::instance()->first(
            "SELECT id FROM auctions WHERE listing_id = :l AND status = 'live' AND deleted_at IS NULL",
            ['l' => (int) $listing['id']]
        );
        if ($activeAuction !== null) {
            flash('danger', 'Cancel the live auction before deleting this listing.');
            return $this->back('/dashboard/listings');
        }

        $openOrders = (int) Database::instance()->scalar(
            "SELECT COUNT(*) FROM orders WHERE listing_id = :l AND status NOT IN ('completed','cancelled')",
            ['l' => (int) $listing['id']],
            0
        );
        if ($openOrders > 0) {
            flash('danger', 'This listing has open orders and cannot be deleted.');
            return $this->back('/dashboard/listings');
        }

        Listing::destroy((int) $listing['id']);
        AuditService::log('listing_deleted', 'listing', (int) $listing['id']);
        flash('success', 'Listing deleted.');
        return $this->redirect('/dashboard/listings');
    }

    public function uploadImages(Request $request): Response
    {
        $listing = $this->ownedListing($request->paramInt('id'));
        $stored = $this->storeImages($request, (int) $listing['id']);

        if ($request->wantsJson()) {
            return $this->ok(['uploaded' => $stored, 'images' => Listing::images((int) $listing['id'])], $stored . ' image(s) uploaded.');
        }
        flash($stored > 0 ? 'success' : 'warning', $stored > 0 ? $stored . ' image(s) uploaded.' : 'No images were uploaded.');
        return $this->back('/dashboard/listings/' . $listing['id'] . '/edit');
    }

    public function deleteImage(Request $request): Response
    {
        $imageId = $request->paramInt('id');
        $image = Database::instance()->first(
            'SELECT i.*, l.user_id FROM listing_images i INNER JOIN listings l ON l.id = i.listing_id WHERE i.id = :id',
            ['id' => $imageId]
        );
        if ($image === null) {
            throw new HttpException(404);
        }
        $this->authorize((int) $image['user_id'] === $this->userId() || Auth::isStaff());

        Uploader::delete((string) $image['file_path']);
        Database::instance()->delete('listing_images', ['id' => $imageId]);

        if ($request->wantsJson()) {
            return $this->ok([], 'Image removed.');
        }
        flash('success', 'Image removed.');
        return $this->back();
    }

    /** Promote a listing to featured; recorded as a fee. */
    public function promote(Request $request): Response
    {
        $listing = $this->ownedListing($request->paramInt('id'));
        $tier = (string) $request->input('tier', 'featured');
        if (!in_array($tier, ['featured', 'premium', 'sponsored'], true)) {
            return $this->fail('Invalid promotion tier.');
        }

        $fee = (float) SettingsService::get('featured_listing_fee', '0');
        $days = 30;

        Listing::updateById((int) $listing['id'], [
            'is_featured' => 1,
            'promotion_tier' => $tier,
            'featured_until' => gmdate('Y-m-d H:i:s', strtotime('+' . $days . ' days')),
        ], true);

        if ($fee > 0) {
            CommissionService::charge(
                $this->userId(),
                'featured_fee',
                $fee,
                (int) $listing['id'],
                label($tier) . ' promotion for ' . $days . ' days'
            );
        }

        AuditService::log('listing_promoted', 'listing', (int) $listing['id'], null, ['tier' => $tier, 'fee' => $fee]);
        flash('success', $fee > 0
            ? 'Listing promoted. A fee of ' . money($fee) . ' plus GST has been added to your account.'
            : 'Listing promoted.');
        return $this->back('/dashboard/listings');
    }

    /** Convert a listing into a live/scheduled auction. */
    public function createAuction(Request $request): Response
    {
        $listing = $this->ownedListing($request->paramInt('id'));

        $existing = Database::instance()->first(
            "SELECT id FROM auctions WHERE listing_id = :l AND status IN ('draft','scheduled','live') AND deleted_at IS NULL",
            ['l' => (int) $listing['id']]
        );
        if ($existing !== null) {
            flash('warning', 'This listing already has an active auction.');
            return $this->redirect('/dashboard/auctions/' . $existing['id']);
        }

        $validator = $this->validate($request, [
            'starting_price' => 'required|numeric|gt:0',
            'bid_increment' => 'required|numeric|gt:0',
            'reserve_price' => 'nullable|numeric|min_value:0',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date',
            'auction_type' => 'required|in:forward,reverse',
        ], [
            'starting_price' => 'Starting price',
            'bid_increment' => 'Bid increment',
            'ends_at' => 'End time',
        ]);
        if ($validator->fails()) {
            return $validator->failResponse($request, '/dashboard/listings/' . $listing['id'] . '/edit?tab=auction');
        }

        $startsAt = to_utc((string) $request->input('starts_at')) ?? now();
        $endsAt = to_utc((string) $request->input('ends_at'));

        if ($endsAt === null || strtotime($endsAt) <= strtotime($startsAt)) {
            flash('danger', 'The auction must end after it starts.');
            return $this->back('/dashboard/listings/' . $listing['id'] . '/edit?tab=auction');
        }
        if (strtotime($endsAt) <= time()) {
            flash('danger', 'The auction end time is already in the past.');
            return $this->back('/dashboard/listings/' . $listing['id'] . '/edit?tab=auction');
        }

        $reserve = $request->input('reserve_price');
        if ($reserve !== null && $reserve !== '' && (float) $reserve > 0) {
            $isReverse = $request->input('auction_type') === 'reverse';
            $starting = (float) $request->input('starting_price');
            if (!$isReverse && (float) $reserve < $starting) {
                flash('danger', 'For a forward auction the reserve price cannot be below the starting price.');
                return $this->back('/dashboard/listings/' . $listing['id'] . '/edit?tab=auction');
            }
        }

        $status = strtotime($startsAt) <= time() ? 'live' : 'scheduled';

        $auctionId = Auction::create([
            'reference' => Auction::generateReference(),
            'listing_id' => (int) $listing['id'],
            'owner_id' => $this->userId(),
            'auction_type' => (string) $request->input('auction_type', 'forward'),
            'title' => (string) $listing['title'],
            'description' => $request->input('description') ?: $listing['description'],
            'quantity' => $listing['quantity'],
            'unit_id' => (int) $listing['unit_id'],
            'price_basis' => (string) $request->input('price_basis', 'per_mt'),
            'starting_price' => dec($request->input('starting_price'), 2),
            'reserve_price' => $reserve !== '' && $reserve !== null ? dec($reserve, 2) : null,
            'bid_increment' => dec($request->input('bid_increment'), 2),
            'current_price' => dec($request->input('starting_price'), 2),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'original_ends_at' => $endsAt,
            'extension_window_seconds' => $request->int('extension_window_seconds', SettingsService::int('auction_extension_window_seconds', 120)),
            'extension_duration_seconds' => $request->int('extension_duration_seconds', SettingsService::int('auction_extension_duration_seconds', 120)),
            'max_extensions' => $request->int('max_extensions', SettingsService::int('auction_max_extensions', 5)),
            'max_bidders' => $request->int('max_bidders') ?: null,
            'requires_kyc' => $request->bool('requires_kyc') ? 1 : 0,
            'requires_approval' => $request->bool('requires_approval') ? 1 : 0,
            'deposit_required' => $request->bool('deposit_required') ? 1 : 0,
            'deposit_amount' => dec($request->input('deposit_amount', 0), 2),
            'payment_terms' => (string) $request->input('payment_terms', 'advance'),
            'mask_bidders' => ($request->bool('mask_bidders') || SettingsService::bool('auction_mask_bidders', true)) ? 1 : 0,
            'status' => $status,
        ]);

        Listing::updateById((int) $listing['id'], ['listing_type' => 'auction'], true);
        Auction::logEvent($auctionId, $this->userId(), $status === 'live' ? 'started' : 'scheduled', 'Auction created by seller');
        AuditService::log('auction_created', 'auction', $auctionId, null, ['listing_id' => (int) $listing['id']]);

        flash('success', $status === 'live'
            ? 'Auction is live. Buyers can bid now.'
            : 'Auction scheduled for ' . fmt_dt($startsAt) . '.');
        return $this->redirect('/dashboard/auctions/' . $auctionId);
    }

    // ------------------------------------------------------------- helpers --

    private function rules(): array
    {
        return [
            'title' => 'required|min:8|max:190',
            'category_id' => 'required|integer|exists:categories,id',
            'material_id' => 'nullable|integer',
            'quantity' => 'required|numeric|gt:0',
            'unit_id' => 'required|integer|exists:units,id',
            'min_order_quantity' => 'nullable|numeric|min_value:0',
            'price' => 'nullable|numeric|min_value:0',
            'description' => 'nullable|max:10000',
            'state_id' => 'required|integer|exists:states,id',
            'pincode' => 'nullable|pincode',
            'gst_rate' => 'nullable|numeric|min_value:0|max_value:50',
            'listing_type' => 'required|in:' . implode(',', array_keys(Listing::TYPES)),
        ];
    }

    private function labels(): array
    {
        return [
            'title' => 'Listing title',
            'category_id' => 'Category',
            'unit_id' => 'Unit',
            'state_id' => 'State',
            'min_order_quantity' => 'Minimum order quantity',
            'listing_type' => 'Sale method',
        ];
    }

    private function ownedListing(int $id): array
    {
        $listing = Listing::find($id);
        if ($listing === null) {
            throw new HttpException(404, 'Listing not found.');
        }
        $this->authorize(
            (int) $listing['user_id'] === $this->userId() || Auth::isStaff(),
            'That listing belongs to another account.'
        );
        return $listing;
    }

    private function storeImages(Request $request, int $listingId): int
    {
        $files = $request->fileList('images');
        if ($files === []) {
            return 0;
        }

        $existing = (int) Database::instance()->scalar(
            'SELECT COUNT(*) FROM listing_images WHERE listing_id = :l',
            ['l' => $listingId],
            0
        );
        $max = SettingsService::int('max_images_per_listing', 10);
        $uploader = Uploader::images('listings');
        $width = SettingsService::int('upload_image_max_width', 1600);

        $stored = 0;
        foreach ($files as $file) {
            if ($existing + $stored >= $max) {
                flash('warning', 'Only ' . $max . ' images are allowed per listing.');
                break;
            }
            $path = $uploader->store($file, $width);
            if ($path === null) {
                flash('warning', (string) $uploader->firstError());
                continue;
            }
            Database::instance()->insert('listing_images', [
                'listing_id' => $listingId,
                'file_path' => $path,
                'is_primary' => ($existing + $stored) === 0 ? 1 : 0,
                'sort_order' => $existing + $stored,
                'file_size' => (int) ($file['size'] ?? 0),
                'created_at' => now(),
            ]);
            $stored++;
        }
        return $stored;
    }

    private function storeVideosAndDocuments(Request $request, int $listingId): void
    {
        foreach ($request->fileList('videos') as $file) {
            $uploader = Uploader::videos('listings');
            $path = $uploader->store($file, null);
            if ($path !== null) {
                Database::instance()->insert('listing_videos', [
                    'listing_id' => $listingId,
                    'file_path' => $path,
                    'file_size' => (int) ($file['size'] ?? 0),
                    'created_at' => now(),
                ]);
            }
        }

        $videoUrl = $request->input('video_url');
        if ($videoUrl && filter_var($videoUrl, FILTER_VALIDATE_URL)) {
            Database::instance()->insert('listing_videos', [
                'listing_id' => $listingId,
                'external_url' => substr((string) $videoUrl, 0, 255),
                'created_at' => now(),
            ]);
        }

        foreach ($request->fileList('documents') as $file) {
            $uploader = Uploader::documents('listings');
            $path = $uploader->store($file, null);
            if ($path !== null) {
                Database::instance()->insert('listing_documents', [
                    'listing_id' => $listingId,
                    'file_path' => $path,
                    'title' => substr((string) ($file['name'] ?? 'Document'), 0, 190),
                    'file_size' => (int) ($file['size'] ?? 0),
                    'created_at' => now(),
                ]);
            }
        }
    }

    private function statusCounts(): array
    {
        $rows = Database::instance()->select(
            'SELECT status, COUNT(*) AS total FROM listings WHERE user_id = :u AND deleted_at IS NULL GROUP BY status',
            ['u' => $this->userId()]
        );
        $counts = ['all' => 0];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
            $counts['all'] += (int) $row['total'];
        }
        return $counts;
    }
}
