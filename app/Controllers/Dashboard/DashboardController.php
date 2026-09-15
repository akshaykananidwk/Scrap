<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Auction;
use App\Models\Business;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use App\Models\WantedRequirement;
use App\Services\AnalyticsService;
use App\Services\ChatService;
use App\Services\NotificationService;
use App\Services\ReviewService;
use App\Services\WalletService;

final class DashboardController extends Controller
{
    protected string $layout = 'layouts/dashboard';

    public function index(Request $request): Response
    {
        $user = $this->user();
        $userId = (int) $user['id'];
        $stats = User::stats($userId);
        $business = Business::forUser($userId);

        $isSeller = Auth::isSeller();
        $isBuyer = Auth::isBuyer();

        return $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'user' => $user,
            'business' => $business,
            'stats' => $stats,
            'is_seller' => $isSeller,
            'is_buyer' => $isBuyer,
            'recent_orders' => Order::paginate(['user_id' => $userId], 1, 5)->items,
            'my_listings' => $isSeller ? Listing::search(['user_id' => $userId, 'status' => 'any'], 1, 5)->items : [],
            'my_auctions' => $isSeller ? Auction::paginate(['owner_id' => $userId], 1, 4)->items : [],
            'my_bids' => $isBuyer ? \App\Services\BidService::userBids($userId, [], 1, 5)->items : [],
            'my_requirements' => $isBuyer ? WantedRequirement::paginate(['user_id' => $userId, 'status' => 'any'], 1, 4)->items : [],
            'requirement_matches' => $isSeller ? WantedRequirement::matchingForSeller($userId, 4) : [],
            'pending_offers' => Database::instance()->select(
                "SELECT o.*, l.title AS listing_title, l.slug AS listing_slug, un.code AS unit_code,
                        buyer.full_name AS buyer_name, seller.full_name AS seller_name
                 FROM offers o
                 LEFT JOIN listings l ON l.id = o.listing_id
                 INNER JOIN units un ON un.id = o.unit_id
                 INNER JOIN users buyer ON buyer.id = o.buyer_id
                 INNER JOIN users seller ON seller.id = o.seller_id
                 WHERE o.status = 'pending' AND ((o.seller_id = :u AND o.direction = 'buyer_to_seller')
                                              OR (o.buyer_id = :u2 AND o.direction = 'seller_to_buyer'))
                 ORDER BY o.id DESC LIMIT 5",
                ['u' => $userId, 'u2' => $userId]
            ),
            'live_auctions_bidding' => $isBuyer ? Auction::paginate(['bidder_id' => $userId, 'status' => 'live'], 1, 4)->items : [],
            'pending_reviews' => ReviewService::pendingForUser($userId),
            'notifications' => NotificationService::recent($userId, 6),
            'unread_messages' => ChatService::unreadCount($userId),
            'kyc' => \App\Services\KycService::statusFor($userId),
            'wallet' => WalletService::isEnabled() ? WalletService::wallet($userId) : null,
        ]);
    }

    public function analytics(Request $request): Response
    {
        $userId = $this->userId();
        $days = min(365, max(7, $request->int('days', 30)));

        return $this->view('dashboard/analytics', [
            'title' => 'Business analytics',
            'days' => $days,
            'seller' => Auth::isSeller() ? AnalyticsService::sellerAnalytics($userId, $days) : null,
            'buyer' => Auth::isBuyer() ? AnalyticsService::buyerAnalytics($userId, $days) : null,
            'is_seller' => Auth::isSeller(),
            'is_buyer' => Auth::isBuyer(),
        ]);
    }

    public function saved(Request $request): Response
    {
        $userId = $this->userId();
        $db = Database::instance();

        $listings = $db->select(
            "SELECT f.id AS favorite_id, f.created_at AS saved_at, l.*, un.code AS unit_code,
                    (SELECT file_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.is_primary DESC, li.id LIMIT 1) AS image,
                    b.name AS business_name, b.slug AS business_slug
             FROM favorites f
             INNER JOIN listings l ON l.id = f.favoritable_id AND l.deleted_at IS NULL
             INNER JOIN units un ON un.id = l.unit_id
             LEFT JOIN businesses b ON b.id = l.business_id
             WHERE f.user_id = :u AND f.favoritable_type = 'listing'
             ORDER BY f.id DESC LIMIT 100",
            ['u' => $userId]
        );

        $auctions = $db->select(
            "SELECT f.id AS favorite_id, a.*, un.code AS unit_code
             FROM favorites f
             INNER JOIN auctions a ON a.id = f.favoritable_id AND a.deleted_at IS NULL
             INNER JOIN units un ON un.id = a.unit_id
             WHERE f.user_id = :u AND f.favoritable_type = 'auction'
             ORDER BY f.id DESC LIMIT 100",
            ['u' => $userId]
        );

        $follows = $db->select(
            "SELECT fo.*, b.name, b.slug, b.logo, b.city_name, b.kyc_verified, b.rating_avg, b.total_listings
             FROM follows fo
             INNER JOIN businesses b ON b.id = fo.followable_id AND b.deleted_at IS NULL
             WHERE fo.user_id = :u AND fo.followable_type = 'business'
             ORDER BY fo.id DESC LIMIT 100",
            ['u' => $userId]
        );

        return $this->view('dashboard/saved', [
            'title' => 'Saved items',
            'listings' => $listings,
            'auctions' => $auctions,
            'follows' => $follows,
        ]);
    }

    public function toggleFavorite(Request $request): Response
    {
        $type = (string) $request->input('type', 'listing');
        $id = $request->int('id');

        if (!in_array($type, ['listing', 'auction', 'requirement', 'rfq'], true) || $id <= 0) {
            return $this->fail('Invalid item.');
        }

        $db = Database::instance();
        $userId = $this->userId();
        $existing = $db->first(
            'SELECT id FROM favorites WHERE user_id = :u AND favoritable_type = :t AND favoritable_id = :i',
            ['u' => $userId, 't' => $type, 'i' => $id]
        );

        if ($existing !== null) {
            $db->delete('favorites', ['id' => (int) $existing['id']]);
            $saved = false;
        } else {
            $db->insert('favorites', [
                'user_id' => $userId,
                'favoritable_type' => $type,
                'favoritable_id' => $id,
                'created_at' => now(),
            ]);
            $saved = true;
        }

        if ($type === 'listing') {
            $count = (int) $db->scalar(
                "SELECT COUNT(*) FROM favorites WHERE favoritable_type = 'listing' AND favoritable_id = :i",
                ['i' => $id],
                0
            );
            $db->update('listings', ['favorite_count' => $count], ['id' => $id]);
        }

        if ($request->wantsJson()) {
            return $this->ok(['saved' => $saved], $saved ? 'Saved.' : 'Removed from saved items.');
        }
        flash('success', $saved ? 'Saved.' : 'Removed from saved items.');
        return $this->back('/dashboard/saved');
    }

    public function notifications(Request $request): Response
    {
        $userId = $this->userId();
        $notifications = \App\Core\Model::paginateQuery(
            'SELECT * FROM notifications WHERE user_id = :u ORDER BY id DESC',
            ['u' => $userId],
            $request->page(),
            30,
            'SELECT COUNT(*) FROM notifications WHERE user_id = :u'
        );

        return $this->view('dashboard/notifications', [
            'title' => 'Notifications',
            'notifications' => $notifications,
            'unread' => NotificationService::unreadCount($userId),
        ]);
    }

    public function markNotificationsRead(Request $request): Response
    {
        $id = $request->int('id') ?: null;
        $count = NotificationService::markRead($this->userId(), $id);

        if ($request->wantsJson()) {
            return $this->ok(['marked' => $count, 'unread' => NotificationService::unreadCount($this->userId())]);
        }
        return $this->back('/dashboard/notifications');
    }

    public function wallet(Request $request): Response
    {
        $userId = $this->userId();

        if (!WalletService::isEnabled()) {
            return $this->view('dashboard/wallet', [
                'title' => 'Wallet',
                'enabled' => false,
                'wallet' => null,
                'transactions' => null,
                'reconciliation' => null,
            ]);
        }

        return $this->view('dashboard/wallet', [
            'title' => 'Wallet',
            'enabled' => true,
            'wallet' => WalletService::wallet($userId),
            'transactions' => WalletService::transactions($userId, $request->page(), 25),
            'reconciliation' => WalletService::reconcile($userId),
        ]);
    }
}
