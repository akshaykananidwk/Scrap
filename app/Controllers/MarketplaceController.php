<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\Material;
use App\Models\State;
use App\Services\SettingsService;

final class MarketplaceController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $perPage = $this->perPage();
        $listings = Listing::search($filters, $request->page(), $perPage);

        return $this->view('marketplace/index', array_merge($this->sharedData($filters), [
            'title' => $this->buildTitle($filters),
            'meta_description' => 'Browse ' . number_format($listings->total) . ' live scrap listings from verified Indian businesses. Filter by material, grade, city, price and quantity.',
            'listings' => $listings,
            'filters' => $filters,
            'heading' => 'Buy Scrap',
            'subheading' => number_format($listings->total) . ' listings available',
        ]));
    }

    /** SEO landing page: /scrap/copper-scrap */
    public function category(Request $request): Response
    {
        $category = Category::findBySlug((string) $request->param('slug'));
        if ($category === null || (int) $category['is_active'] !== 1) {
            throw new HttpException(404, 'That scrap category does not exist.');
        }

        $filters = $this->filters($request);
        $filters['category_id'] = (int) $category['id'];
        $listings = Listing::search($filters, $request->page(), $this->perPage());

        return $this->view('marketplace/index', array_merge($this->sharedData($filters), [
            'title' => ($category['meta_title'] ?: $category['name'] . ' Scrap — Buy & Sell'),
            'meta_description' => $category['meta_description'] ?: ('Live ' . $category['name'] . ' scrap listings, auctions and buyer requirements across India.'),
            'canonical' => base_url('scrap/' . $category['slug']),
            'listings' => $listings,
            'filters' => $filters,
            'category' => $category,
            'subcategories' => Category::children((int) $category['id']),
            'breadcrumbs' => Category::breadcrumb((int) $category['id']),
            'heading' => $category['name'] . ' Scrap',
            'subheading' => number_format($listings->total) . ' listings · ' . ($category['description'] ?? ''),
        ]));
    }

    /** SEO landing page: /material/hms-1 */
    public function material(Request $request): Response
    {
        $material = Material::findBySlug((string) $request->param('slug'));
        if ($material === null || (int) $material['is_active'] !== 1) {
            throw new HttpException(404, 'That material does not exist.');
        }

        $filters = $this->filters($request);
        $filters['material_id'] = (int) $material['id'];
        $listings = Listing::search($filters, $request->page(), $this->perPage());

        $rates = \App\Models\MarketRate::latest(['material_id' => (int) $material['id']], 8);

        return $this->view('marketplace/index', array_merge($this->sharedData($filters), [
            'title' => ($material['meta_title'] ?: $material['name'] . ' Scrap — Price & Listings'),
            'meta_description' => $material['meta_description'] ?: ('Current ' . $material['name'] . ' scrap rates and live listings from verified sellers across India.'),
            'canonical' => base_url('material/' . $material['slug']),
            'listings' => $listings,
            'filters' => $filters,
            'material' => $material,
            'material_rates' => $rates,
            'grades' => Material::grades((int) $material['id']),
            'heading' => $material['name'] . ' Scrap',
            'subheading' => number_format($listings->total) . ' listings'
                . ($rates !== [] ? ' · Today ' . money($rates[0]['rate']) . ' / ' . $rates[0]['unit_code'] : ''),
        ]));
    }

    /** Read and sanitise every supported filter from the query string. */
    private function filters(Request $request): array
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'category_id' => $request->int('category_id'),
            'material_id' => $request->int('material_id'),
            'grade_id' => $request->int('grade_id'),
            'listing_type' => (string) $request->query('listing_type', ''),
            'state_id' => $request->int('state_id'),
            'city_id' => $request->int('city_id'),
            'condition' => (string) $request->query('condition', ''),
            'min_price' => $request->query('min_price', ''),
            'max_price' => $request->query('max_price', ''),
            'min_quantity' => $request->query('min_quantity', ''),
            'max_quantity' => $request->query('max_quantity', ''),
            'verified_only' => $request->query('verified_only') ? 1 : 0,
            'delivery_available' => $request->query('delivery_available') ? 1 : 0,
            'ending_soon' => $request->query('ending_soon') ? 1 : 0,
            'posted_within_days' => $request->int('posted_within_days'),
            'pincode_prefix' => preg_replace('/\D/', '', (string) $request->query('pincode', '')),
            'sort' => (string) $request->query('sort', 'newest'),
        ];

        $allowedSorts = ['newest', 'oldest', 'price_low', 'price_high', 'quantity', 'ending_soon', 'most_bids', 'most_viewed'];
        if (!in_array($filters['sort'], $allowedSorts, true)) {
            $filters['sort'] = 'newest';
        }
        if ($filters['listing_type'] !== '' && !isset(Listing::TYPES[$filters['listing_type']])) {
            $filters['listing_type'] = '';
        }
        if ($filters['condition'] !== '' && !isset(Listing::CONDITIONS[$filters['condition']])) {
            $filters['condition'] = '';
        }

        return array_filter($filters, static fn ($v): bool => $v !== '' && $v !== 0 && $v !== null);
    }

    private function sharedData(array $filters): array
    {
        return [
            'categories' => Category::tree(),
            'states' => State::active(),
            'cities' => !empty($filters['state_id']) ? City::forState((int) $filters['state_id']) : City::major(20),
            'materials' => !empty($filters['category_id'])
                ? Material::forCategory((int) $filters['category_id'])
                : Material::popular(20),
            'listing_types' => Listing::TYPES,
            'conditions' => Listing::CONDITIONS,
            'sorts' => [
                'newest' => 'Newest first',
                'price_low' => 'Price: low to high',
                'price_high' => 'Price: high to low',
                'quantity' => 'Largest quantity',
                'ending_soon' => 'Auction ending soon',
                'most_bids' => 'Most bids',
                'most_viewed' => 'Most viewed',
                'oldest' => 'Oldest first',
            ],
        ];
    }

    private function perPage(): int
    {
        $perPage = SettingsService::int('listings_per_page', 20);
        // Signed-out visitors see a limited preview; the full list needs an account.
        if (!Auth::check() && SettingsService::bool('guest_can_view_listings', true)) {
            return min($perPage, SettingsService::int('guest_listing_limit', 12));
        }
        return $perPage;
    }

    private function buildTitle(array $filters): string
    {
        if (!empty($filters['q'])) {
            return $filters['q'] . ' — Scrap Listings';
        }
        return 'Buy Scrap Online — Live Listings from Verified Sellers';
    }
}
