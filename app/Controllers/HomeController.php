<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Auction;
use App\Models\Business;
use App\Models\Category;
use App\Models\Listing;
use App\Models\MarketRate;
use App\Models\Material;
use App\Models\WantedRequirement;
use App\Services\AnalyticsService;
use App\Services\SettingsService;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('home/index', [
            'title' => (string) SettingsService::get('site_name', 'ScrapX') . ' — ' . SettingsService::get('site_tagline', "India's B2B Scrap Trading Marketplace"),
            'meta_description' => (string) SettingsService::get('seo_meta_description', ''),
            'stats' => AnalyticsService::publicStats(),
            'categories' => Category::featured(8),
            'popular_materials' => Material::popular(12),
            'live_auctions' => Auction::paginate(['status' => 'live', 'sort' => 'ending_soon'], 1, 6)->items,
            'latest_listings' => Listing::search(['sort' => 'newest'], 1, 8)->items,
            'featured_listings' => Listing::search(['sort' => 'newest'], 1, 4)->items,
            'requirements' => WantedRequirement::paginate(['status' => 'open'], 1, 6)->items,
            'market_rates' => MarketRate::latest([], 10),
            'movers' => MarketRate::movers(8),
            'verified_businesses' => Business::paginate(['verified' => 1, 'sort' => 'rating'], 1, 8)->items,
            'faqs' => Database::instance()->select('SELECT * FROM faqs WHERE is_published = 1 ORDER BY sort_order LIMIT 6'),
            'reviews' => Database::instance()->select(
                "SELECT r.overall_rating, r.title, r.comment, r.created_at,
                        b.name AS business_name, b.city_name, b.logo
                 FROM reviews r
                 LEFT JOIN businesses b ON b.user_id = r.reviewer_id AND b.deleted_at IS NULL
                 WHERE r.status = 'published' AND r.overall_rating >= 4 AND r.comment IS NOT NULL
                 ORDER BY r.id DESC LIMIT 6"
            ),
        ]);
    }

    public function howItWorks(Request $request): Response
    {
        $page = Database::instance()->first("SELECT * FROM cms_pages WHERE slug = 'how-it-works' AND is_published = 1");
        return $this->view('home/how_it_works', [
            'title' => 'How ScrapX Works',
            'meta_description' => 'From listing to weighment to payment — how buying and selling scrap works on ScrapX.',
            'page' => $page,
        ]);
    }

    public function pricing(Request $request): Response
    {
        $plans = Database::instance()->select('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order, price');
        foreach ($plans as &$plan) {
            $plan['features'] = Database::instance()->select(
                'SELECT * FROM subscription_features WHERE plan_id = :p ORDER BY sort_order',
                ['p' => (int) $plan['id']]
            );
        }
        return $this->view('home/pricing', [
            'title' => 'Plans & Pricing',
            'meta_description' => 'Subscription plans, commission rates and platform fees on ScrapX.',
            'plans' => $plans,
            'commission' => [
                'percentage' => SettingsService::get('commission_percentage', '0'),
                'fixed' => SettingsService::get('commission_fixed', '0'),
                'party' => SettingsService::get('commission_party', 'seller'),
                'auction_fee' => SettingsService::get('auction_fee_percentage', '0'),
                'featured_fee' => SettingsService::get('featured_listing_fee', '0'),
                'gst' => SettingsService::get('commission_gst_rate', '18'),
                'enabled' => SettingsService::bool('commission_enabled', true),
            ],
        ]);
    }

    public function switchLanguage(Request $request): Response
    {
        $locale = (string) $request->input('locale', 'en');
        if (isset(Lang::SUPPORTED[$locale])) {
            Session::put('locale', $locale);
            if (auth_id() !== null) {
                \App\Models\User::updateById((int) auth_id(), ['preferred_language' => $locale], true);
            }
        }
        return $this->back();
    }

    // ------------------------------------------------------------------ SEO --

    public function sitemap(Request $request): Response
    {
        $db = Database::instance();
        $urls = [];

        $add = static function (string $path, string $changefreq, string $priority, ?string $lastmod = null) use (&$urls): void {
            $urls[] = [
                'loc' => base_url(ltrim($path, '/')),
                'changefreq' => $changefreq,
                'priority' => $priority,
                'lastmod' => $lastmod !== null ? gmdate('Y-m-d', strtotime($lastmod)) : gmdate('Y-m-d'),
            ];
        };

        foreach (['/', '/buy', '/auctions', '/wanted', '/rfq', '/market-rates', '/businesses', '/sellers', '/buyers', '/how-it-works', '/pricing', '/faq', '/contact'] as $path) {
            $add($path, 'daily', $path === '/' ? '1.0' : '0.8');
        }

        foreach ($db->select('SELECT slug, updated_at FROM categories WHERE is_active = 1') as $row) {
            $add('/scrap/' . $row['slug'], 'daily', '0.9', $row['updated_at']);
        }
        foreach ($db->select('SELECT slug, updated_at FROM materials WHERE is_active = 1') as $row) {
            $add('/material/' . $row['slug'], 'daily', '0.8', $row['updated_at']);
        }
        foreach ($db->select("SELECT slug, updated_at FROM listings WHERE status = 'active' AND deleted_at IS NULL ORDER BY id DESC LIMIT 2000") as $row) {
            $add('/listing/' . $row['slug'], 'daily', '0.7', $row['updated_at']);
        }
        foreach ($db->select("SELECT slug, updated_at FROM wanted_requirements WHERE status = 'open' AND deleted_at IS NULL ORDER BY id DESC LIMIT 1000") as $row) {
            $add('/wanted/' . $row['slug'], 'weekly', '0.6', $row['updated_at']);
        }
        foreach ($db->select('SELECT b.slug, b.updated_at FROM businesses b INNER JOIN users u ON u.id = b.user_id WHERE b.deleted_at IS NULL AND u.status = "active" LIMIT 2000') as $row) {
            $add('/business/' . $row['slug'], 'weekly', '0.6', $row['updated_at']);
        }
        foreach ($db->select('SELECT slug, updated_at FROM cms_pages WHERE is_published = 1') as $row) {
            $add('/page/' . $row['slug'], 'monthly', '0.4', $row['updated_at']);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            $xml .= "  <url>\n"
                . '    <loc>' . e($url['loc']) . "</loc>\n"
                . '    <lastmod>' . $url['lastmod'] . "</lastmod>\n"
                . '    <changefreq>' . $url['changefreq'] . "</changefreq>\n"
                . '    <priority>' . $url['priority'] . "</priority>\n"
                . "  </url>\n";
        }
        $xml .= '</urlset>';

        return Response::make($xml)->withHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(Request $request): Response
    {
        $allowIndexing = SettingsService::bool('seo_indexing_enabled', true);

        $body = "User-agent: *\n";
        if ($allowIndexing) {
            $body .= "Allow: /\n"
                . "Disallow: /dashboard/\n"
                . "Disallow: /admin/\n"
                . "Disallow: /install\n"
                . "Disallow: /api/\n"
                . "Disallow: /cron/\n"
                . "Disallow: /login\n"
                . "Disallow: /register\n\n"
                . 'Sitemap: ' . base_url('sitemap.xml') . "\n";
        } else {
            $body .= "Disallow: /\n";
        }

        return Response::make($body)->withHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    // ------------------------------------------------------------------ PWA --

    public function manifest(Request $request): Response
    {
        $name = (string) SettingsService::get('site_name', 'ScrapX');
        $logo = SettingsService::get('site_logo', '');

        $manifest = [
            'name' => $name . ' — ' . SettingsService::get('site_tagline', 'B2B Scrap Marketplace'),
            'short_name' => $name,
            'description' => (string) SettingsService::get('seo_meta_description', ''),
            'start_url' => base_url('/'),
            'scope' => base_url('/'),
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#f8fafc',
            'theme_color' => '#0f766e',
            'lang' => Lang::locale(),
            'categories' => ['business', 'shopping', 'productivity'],
            'icons' => [
                ['src' => $logo ? upload_url($logo) : asset('img/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => $logo ? upload_url($logo) : asset('img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Buy Scrap', 'url' => base_url('buy'), 'description' => 'Browse live scrap listings'],
                ['name' => 'Sell Scrap', 'url' => base_url('dashboard/listings/create'), 'description' => 'Post material for sale'],
                ['name' => 'Live Auctions', 'url' => base_url('auctions'), 'description' => 'Bid on live auctions'],
                ['name' => 'Post Requirement', 'url' => base_url('dashboard/requirements/create'), 'description' => 'Tell sellers what you need'],
            ],
        ];

        return Response::json($manifest)->withHeader('Content-Type', 'application/manifest+json');
    }

    public function serviceWorker(Request $request): Response
    {
        $file = ROOT_PATH . '/public/js/service-worker.js';
        $contents = is_file($file) ? (string) file_get_contents($file) : '';
        // Served from the root so its scope covers the whole origin.
        return Response::make($contents)
            ->withHeader('Content-Type', 'application/javascript; charset=UTF-8')
            ->withHeader('Service-Worker-Allowed', '/')
            ->withHeader('Cache-Control', 'no-cache');
    }

    public function offline(Request $request): Response
    {
        return $this->view('errors/offline', ['title' => 'You are offline']);
    }
}
