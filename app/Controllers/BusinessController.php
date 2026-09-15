<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Business;
use App\Models\Listing;
use App\Models\State;
use App\Models\WantedRequirement;
use App\Services\DisputeService;
use App\Services\ReviewService;

final class BusinessController extends Controller
{
    public function index(Request $request, string $heading = 'Verified Businesses', string $intent = 'all'): Response
    {
        $filters = array_filter([
            'q' => trim((string) $request->query('q', '')),
            'business_type' => (string) $request->query('business_type', ''),
            'state_id' => $request->int('state_id'),
            'city_id' => $request->int('city_id'),
            'verified' => $request->query('verified') ? 1 : 0,
            'sort' => (string) $request->query('sort', 'rating'),
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        $businesses = Business::paginate($filters, $request->page(), 24);

        return $this->view('profile/index', [
            'title' => $heading . ' — Scrap Buyers, Sellers & Traders',
            'meta_description' => 'Directory of verified scrap businesses across India: traders, dealers, aggregators, recyclers and manufacturers.',
            'businesses' => $businesses,
            'filters' => $filters,
            'types' => Business::TYPES,
            'states' => State::active(),
            'heading' => $heading,
            'intent' => $intent,
        ]);
    }

    public function sellers(Request $request): Response
    {
        return $this->index($request, 'Scrap Sellers', 'sellers');
    }

    public function buyers(Request $request): Response
    {
        return $this->index($request, 'Scrap Buyers', 'buyers');
    }

    public function show(Request $request): Response
    {
        $business = Business::findBySlug((string) $request->param('slug'));
        if ($business === null || $business['user_status'] !== 'active') {
            throw new HttpException(404, 'That business profile is not available.');
        }

        Database::instance()->statement(
            'UPDATE businesses SET profile_views = profile_views + 1 WHERE id = :id',
            ['id' => (int) $business['id']]
        );

        $userId = (int) $business['user_id'];
        $isFollowing = false;
        if (Auth::check()) {
            $isFollowing = Database::instance()->first(
                "SELECT id FROM follows WHERE user_id = :u AND followable_type = 'business' AND followable_id = :b",
                ['u' => Auth::id(), 'b' => (int) $business['id']]
            ) !== null;
        }

        return $this->view('profile/show', [
            'title' => $business['name'] . ' — ' . label((string) $business['business_type'])
                . ($business['city_name'] ? ' in ' . $business['city_name'] : ''),
            'meta_description' => mb_substr(
                strip_tags((string) ($business['about'] ?: $business['name'] . ' is a verified scrap business on ScrapX.')),
                0,
                280
            ),
            'canonical' => base_url('business/' . $business['slug']),
            'og_image' => $business['logo'],
            'business' => $business,
            'listings' => Listing::search(['user_id' => $userId, 'status' => 'active'], $request->page(), 12),
            'requirements' => WantedRequirement::paginate(['user_id' => $userId, 'status' => 'open'], 1, 6)->items,
            'reviews' => ReviewService::forBusiness((int) $business['id'], 10),
            'review_summary' => ReviewService::summary($userId),
            'is_following' => $isFollowing,
            'is_self' => Auth::id() === $userId,
            'can_contact' => Auth::check(),
            'report_reasons' => DisputeService::REPORT_REASONS,
            'structured_data' => [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $business['name'],
                'url' => base_url('business/' . $business['slug']),
                'logo' => $business['logo'] ? upload_url($business['logo']) : null,
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => $business['city_name'],
                    'addressRegion' => $business['state_name'],
                    'postalCode' => $business['pincode'],
                    'addressCountry' => 'IN',
                ],
                'aggregateRating' => (int) $business['rating_count'] > 0 ? [
                    '@type' => 'AggregateRating',
                    'ratingValue' => (string) $business['rating_avg'],
                    'reviewCount' => (string) $business['rating_count'],
                ] : null,
            ],
        ]);
    }

    public function follow(Request $request): Response
    {
        $businessId = $request->paramInt('id');
        $business = Business::find($businessId);
        if ($business === null) {
            throw new HttpException(404);
        }
        if ((int) $business['user_id'] === (int) Auth::id()) {
            return $this->fail('You cannot follow your own business.');
        }

        $db = Database::instance();
        $existing = $db->first(
            "SELECT id FROM follows WHERE user_id = :u AND followable_type = 'business' AND followable_id = :b",
            ['u' => Auth::id(), 'b' => $businessId]
        );

        if ($existing !== null) {
            $db->delete('follows', ['id' => (int) $existing['id']]);
            $following = false;
        } else {
            $db->insert('follows', [
                'user_id' => Auth::id(),
                'followable_type' => 'business',
                'followable_id' => $businessId,
                'created_at' => now(),
            ]);
            $following = true;
        }

        $count = (int) $db->scalar(
            "SELECT COUNT(*) FROM follows WHERE followable_type = 'business' AND followable_id = :b",
            ['b' => $businessId],
            0
        );
        $db->update('businesses', ['follower_count' => $count], ['id' => $businessId]);

        if ($request->wantsJson()) {
            return $this->ok(
                ['following' => $following, 'followers' => $count],
                $following ? 'You are now following this business.' : 'Unfollowed.'
            );
        }
        flash('success', $following ? 'You are now following this business.' : 'Unfollowed.');
        return $this->back();
    }

    public function report(Request $request): Response
    {
        $validator = $this->validate($request, [
            'type' => 'required|in:listing,user,business,auction,message,requirement,rfq',
            'id' => 'required|integer',
            'reason' => 'required',
            'details' => 'nullable|max:2000',
        ]);
        if ($validator->fails()) {
            return $validator->failResponse($request);
        }

        $evidence = null;
        $file = $request->file('evidence');
        if ($file !== null) {
            $uploader = \App\Core\Uploader::documents('reports');
            $evidence = $uploader->store($file, 1200);
        }

        $result = DisputeService::report(
            (int) Auth::id(),
            (string) $request->input('type'),
            $request->int('id'),
            (string) $request->input('reason'),
            (string) $request->input('details', ''),
            $evidence
        );

        if ($request->wantsJson()) {
            return $result['ok'] ? $this->ok($result, $result['message']) : $this->fail($result['error']);
        }
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);
        return $this->back();
    }
}
