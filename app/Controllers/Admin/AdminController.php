<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AnalyticsService;
use App\Services\CronService;
use App\Services\FraudService;
use App\Services\Updates\UpdateService;

final class AdminController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function dashboard(Request $request): Response
    {
        $db = Database::instance();
        $overview = AnalyticsService::platformOverview();

        return $this->view('admin/dashboard', [
            'title' => 'Admin dashboard',
            'overview' => $overview,
            'series' => [
                'registrations' => AnalyticsService::dailySeries('registrations', 30),
                'orders' => AnalyticsService::dailySeries('orders', 30),
                'gmv' => AnalyticsService::dailySeries('gmv', 30),
                'revenue' => AnalyticsService::dailySeries('revenue', 30),
            ],
            'categories' => AnalyticsService::categoryPerformance(8),
            'top_sellers' => AnalyticsService::topSellers(8),
            'recent_users' => $db->select(
                'SELECT u.id, u.full_name, u.mobile, u.account_type, u.status, u.created_at, b.name AS business_name
                 FROM users u LEFT JOIN businesses b ON b.user_id = u.id AND b.deleted_at IS NULL
                 WHERE u.deleted_at IS NULL ORDER BY u.id DESC LIMIT 8'
            ),
            'recent_orders' => $db->select(
                'SELECT o.id, o.reference, o.final_amount, o.status, o.created_at,
                        buyer.full_name AS buyer_name, seller.full_name AS seller_name
                 FROM orders o
                 INNER JOIN users buyer ON buyer.id = o.buyer_id
                 INNER JOIN users seller ON seller.id = o.seller_id
                 ORDER BY o.id DESC LIMIT 8'
            ),
            'pending_listings' => $db->select(
                "SELECT l.id, l.title, l.slug, l.created_at, u.full_name AS seller_name
                 FROM listings l INNER JOIN users u ON u.id = l.user_id
                 WHERE l.status = 'pending' AND l.deleted_at IS NULL ORDER BY l.id DESC LIMIT 8"
            ),
            'live_auctions' => $db->select(
                "SELECT a.id, a.reference, a.title, a.current_price, a.bid_count, a.ends_at
                 FROM auctions a WHERE a.status = 'live' AND a.deleted_at IS NULL
                 ORDER BY a.ends_at ASC LIMIT 8"
            ),
            'fraud' => FraudService::counts(),
            'cron' => CronService::isHealthy(),
            'update' => [
                'configured' => UpdateService::isConfigured(),
                'current_version' => UpdateService::currentVersion(),
                'latest_version' => \App\Services\SettingsService::get('update_latest_version', ''),
                'last_check' => \App\Services\SettingsService::get('update_last_check_at', ''),
            ],
            'queue_backlog' => (int) $db->scalar("SELECT COUNT(*) FROM notification_queue WHERE status = 'queued'", [], 0),
        ]);
    }

    /** JSON series for the dashboard charts. */
    public function chart(Request $request): Response
    {
        $metric = (string) $request->param('metric');
        $allowed = ['registrations', 'listings', 'orders', 'gmv', 'revenue', 'bids'];
        if (!in_array($metric, $allowed, true)) {
            return $this->fail('Unknown metric.');
        }

        $days = min(365, max(7, $request->int('days', 30)));
        return $this->json([
            'success' => true,
            'metric' => $metric,
            'days' => $days,
            'series' => AnalyticsService::dailySeries($metric, $days),
        ]);
    }
}
