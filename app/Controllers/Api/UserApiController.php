<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use App\Services\ChatService;
use App\Services\NotificationService;
use App\Services\ReviewService;

final class UserApiController extends BaseApiController
{
    public function stats(Request $request): Response
    {
        $userId = (int) Auth::id();
        return $this->data([
            'stats' => User::stats($userId),
            'unread_notifications' => NotificationService::unreadCount($userId),
            'unread_messages' => ChatService::unreadCount($userId),
            'reviews' => ReviewService::summary($userId),
        ]);
    }

    public function notifications(Request $request): Response
    {
        $userId = (int) Auth::id();
        $notifications = \App\Core\Model::paginateQuery(
            'SELECT * FROM notifications WHERE user_id = :u ORDER BY id DESC',
            ['u' => $userId],
            $request->page(),
            min(50, max(5, $request->int('per_page', 20))),
            'SELECT COUNT(*) FROM notifications WHERE user_id = :u'
        );

        return $this->paginated($notifications, static fn (array $row): array => [
            'id' => (int) $row['id'],
            'event' => $row['event'],
            'title' => $row['title'],
            'body' => $row['body'],
            'link' => $row['link'] !== null ? base_url(ltrim((string) $row['link'], '/')) : null,
            'level' => $row['level'],
            'read' => $row['read_at'] !== null,
            'created_at' => $row['created_at'],
        ]);
    }

    public function markRead(Request $request): Response
    {
        $count = NotificationService::markRead((int) Auth::id(), $request->int('id') ?: null);
        return $this->data([
            'marked' => $count,
            'unread' => NotificationService::unreadCount((int) Auth::id()),
        ]);
    }

    public function business(Request $request): Response
    {
        $business = Business::findBySlug((string) $request->param('slug'));
        if ($business === null || $business['user_status'] !== 'active') {
            return $this->error('Business not found.', 404);
        }

        return $this->data([
            'id' => (int) $business['id'],
            'name' => $business['name'],
            'slug' => $business['slug'],
            'type' => $business['business_type'],
            'about' => $business['about'],
            'logo' => !empty($business['logo']) ? upload_url($business['logo']) : null,
            'location' => array_filter([
                'city' => $business['city_name'],
                'state' => $business['state_name'],
                'pincode' => $business['pincode'],
            ]),
            'verified' => [
                'kyc' => (int) $business['kyc_verified'] === 1,
                'gst' => (int) $business['gst_verified'] === 1,
                'pan' => (int) $business['pan_verified'] === 1,
            ],
            'rating' => (float) $business['rating_avg'],
            'rating_count' => (int) $business['rating_count'],
            'total_listings' => (int) $business['total_listings'],
            'completed_orders' => (int) $business['completed_orders'],
            'response_rate' => (float) $business['response_rate'],
            'avg_response_minutes' => (int) $business['avg_response_minutes'],
            'member_since' => $business['member_since'],
            'url' => base_url('business/' . $business['slug']),
            'listings' => array_map(
                fn (array $listing): array => $this->presentListing($listing),
                Listing::search(['user_id' => (int) $business['user_id'], 'status' => 'active'], 1, 12)->items
            ),
        ]);
    }
}
