<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Rfq;
use App\Models\WantedRequirement;
use App\Services\AuditService;
use App\Services\NotificationService;
use Database\Seeders\CatalogSeeder;

final class ListingController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(Request $request): Response
    {
        $filters = array_filter([
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'pending'),
            'listing_type' => (string) $request->query('listing_type', ''),
            'category_id' => $request->int('category_id'),
            'sort' => (string) $request->query('sort', 'newest'),
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        return $this->view('admin/listings', [
            'title' => 'Listings',
            'listings' => Listing::search($filters, $request->page(), 25),
            'filters' => $filters,
            'counts' => Listing::counts(),
            'categories' => Category::tree(false),
            'types' => Listing::TYPES,
        ]);
    }

    public function approve(Request $request): Response
    {
        $listing = $this->find($request->paramInt('id'));

        Listing::updateById((int) $listing['id'], [
            'status' => 'active',
            'published_at' => $listing['published_at'] ?? now(),
            'rejection_reason' => null,
        ], true);

        CatalogSeeder::refreshCounts(Database::instance());
        AuditService::log('listing_approved', 'listing', (int) $listing['id']);

        NotificationService::dispatch((int) $listing['user_id'], 'listing_approved', [
            'body' => 'Your listing "' . $listing['title'] . '" is now live.',
            'link' => '/listing/' . $listing['slug'],
            'entity_type' => 'listing',
            'entity_id' => (int) $listing['id'],
            'vars' => ['title' => $listing['title'], 'link' => base_url('listing/' . $listing['slug'])],
        ]);

        if ($request->wantsJson()) {
            return $this->ok([], 'Listing approved.');
        }
        flash('success', 'Listing approved and published.');
        return $this->back('/admin/listings');
    }

    public function reject(Request $request): Response
    {
        $listing = $this->find($request->paramInt('id'));
        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            return $this->fail('Tell the seller why the listing was rejected.');
        }

        Listing::updateById((int) $listing['id'], [
            'status' => 'rejected',
            'rejection_reason' => substr($reason, 0, 255),
        ], true);

        AuditService::log('listing_rejected', 'listing', (int) $listing['id'], null, ['reason' => $reason]);

        NotificationService::dispatch((int) $listing['user_id'], 'listing_rejected', [
            'body' => '"' . $listing['title'] . '" was not approved. Reason: ' . $reason,
            'link' => '/dashboard/listings/' . $listing['id'] . '/edit',
            'entity_type' => 'listing',
            'entity_id' => (int) $listing['id'],
        ]);

        flash('success', 'Listing rejected and the seller has been told why.');
        return $this->back('/admin/listings');
    }

    public function feature(Request $request): Response
    {
        $listing = $this->find($request->paramInt('id'));
        $tier = (string) $request->input('tier', 'featured');
        $enable = $request->bool('enable');

        Listing::updateById((int) $listing['id'], [
            'is_featured' => $enable ? 1 : 0,
            'promotion_tier' => $enable && in_array($tier, ['featured', 'premium', 'sponsored'], true) ? $tier : 'none',
            'featured_until' => $enable ? gmdate('Y-m-d H:i:s', strtotime('+30 days')) : null,
        ], true);

        AuditService::log('listing_featured', 'listing', (int) $listing['id'], null, ['enabled' => $enable, 'tier' => $tier]);

        flash('success', $enable ? 'Listing promoted.' : 'Promotion removed.');
        return $this->back('/admin/listings');
    }

    public function destroy(Request $request): Response
    {
        $listing = $this->find($request->paramInt('id'));

        $openOrders = (int) Database::instance()->scalar(
            "SELECT COUNT(*) FROM orders WHERE listing_id = :l AND status NOT IN ('completed','cancelled')",
            ['l' => (int) $listing['id']],
            0
        );
        if ($openOrders > 0) {
            flash('danger', 'This listing has open orders and cannot be deleted.');
            return $this->back('/admin/listings');
        }

        Listing::destroy((int) $listing['id']);
        CatalogSeeder::refreshCounts(Database::instance());
        AuditService::log('listing_deleted_admin', 'listing', (int) $listing['id']);

        flash('success', 'Listing deleted.');
        return $this->back('/admin/listings');
    }

    public function requirements(Request $request): Response
    {
        $filters = array_filter([
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'any'),
        ], static fn ($v): bool => $v !== '');

        return $this->view('admin/requirements', [
            'title' => 'Buyer requirements',
            'requirements' => WantedRequirement::paginate($filters, $request->page(), 25),
            'filters' => $filters,
            'counts' => WantedRequirement::counts(),
        ]);
    }

    public function rfqs(Request $request): Response
    {
        $filters = array_filter([
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
        ], static fn ($v): bool => $v !== '');

        return $this->view('admin/rfqs', [
            'title' => 'RFQs',
            'rfqs' => Rfq::paginate($filters, $request->page(), 25),
            'filters' => $filters,
            'counts' => Rfq::counts(),
            'statuses' => Rfq::STATUSES,
        ]);
    }

    private function find(int $id): array
    {
        $listing = Listing::find($id);
        if ($listing === null) {
            throw new HttpException(404, 'Listing not found.');
        }
        return $listing;
    }
}
