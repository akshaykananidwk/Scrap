<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Listing;
use App\Services\BidService;
use App\Services\SettingsService;

final class ListingController extends Controller
{
    public function show(Request $request): Response
    {
        $listing = Listing::findBySlug((string) $request->param('slug'));
        if ($listing === null) {
            throw new HttpException(404, 'That listing is no longer available.');
        }

        $isOwner = Auth::id() === (int) $listing['user_id'];
        $visible = $listing['status'] === 'active' || $isOwner || Auth::isStaff();
        if (!$visible) {
            throw new HttpException(404, 'That listing is not currently published.');
        }

        if (!$isOwner) {
            Listing::recordView((int) $listing['id'], Auth::id(), $request->ip());
        }

        $auction = null;
        if (!empty($listing['auction_id'])) {
            $auction = \App\Models\Auction::detail((int) $listing['auction_id']);
        }

        $isFavorite = false;
        if (Auth::check()) {
            $isFavorite = Database::instance()->first(
                "SELECT id FROM favorites WHERE user_id = :u AND favoritable_type = 'listing' AND favoritable_id = :l",
                ['u' => Auth::id(), 'l' => (int) $listing['id']]
            ) !== null;
        }

        return $this->view('listing/show', [
            'title' => $listing['meta_title'] ?: $listing['title'],
            'meta_description' => $listing['meta_description']
                ?: mb_substr(strip_tags((string) $listing['description']), 0, 280),
            'canonical' => base_url('listing/' . $listing['slug']),
            'og_image' => Listing::primaryImage((int) $listing['id']),
            'listing' => $listing,
            'images' => Listing::images((int) $listing['id']),
            'videos' => Listing::videos((int) $listing['id']),
            'documents' => Listing::documents((int) $listing['id']),
            'auction' => $auction,
            'auction_state' => $auction !== null ? BidService::liveState((int) $auction['id'], Auth::id()) : null,
            'related' => Listing::related($listing, 4),
            'is_owner' => $isOwner,
            'is_favorite' => $isFavorite,
            'can_contact' => Auth::check(),
            'seller_listings' => Listing::search(
                ['user_id' => (int) $listing['user_id'], 'status' => 'active'],
                1,
                4
            )->items,
            'reviews' => !empty($listing['business_id'])
                ? \App\Services\ReviewService::forBusiness((int) $listing['business_id'], 3)
                : [],
            'structured_data' => $this->structuredData($listing),
        ]);
    }

    /** Reveal seller contact details — account required, and the view is logged. */
    public function contact(Request $request): Response
    {
        $listing = Listing::findDetail($request->paramInt('id'));
        if ($listing === null) {
            throw new HttpException(404);
        }

        Database::instance()->statement(
            'UPDATE listings SET enquiry_count = enquiry_count + 1 WHERE id = :id',
            ['id' => (int) $listing['id']]
        );
        \App\Services\AuditService::log('seller_contact_viewed', 'listing', (int) $listing['id']);

        $payload = [
            'success' => true,
            'seller' => [
                'name' => $listing['business_name'] ?: $listing['seller_name'],
                'contact_person' => $listing['seller_name'],
                'mobile' => $listing['seller_mobile'],
                'email' => $listing['seller_email'],
                'city' => $listing['city_name'],
                'verified' => (int) ($listing['kyc_verified'] ?? 0) === 1,
                'profile_url' => $listing['business_slug'] ? base_url('business/' . $listing['business_slug']) : null,
            ],
            'whatsapp' => $listing['seller_mobile']
                ? 'https://wa.me/91' . preg_replace('/\D/', '', (string) $listing['seller_mobile'])
                    . '?text=' . rawurlencode('Hi, I am interested in your listing "' . $listing['title'] . '" on ' . SettingsService::get('site_name', 'ScrapX') . ': ' . base_url('listing/' . $listing['slug']))
                : null,
        ];

        if ($request->wantsJson()) {
            return $this->json($payload);
        }
        return $this->view('listing/contact', [
            'title' => 'Contact ' . ($listing['business_name'] ?: $listing['seller_name']),
            'listing' => $listing,
            'contact' => $payload['seller'],
            'whatsapp' => $payload['whatsapp'],
        ]);
    }

    /** schema.org Product markup so listings can appear as rich results. */
    private function structuredData(array $listing): array
    {
        $image = Listing::primaryImage((int) $listing['id']);
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $listing['title'],
            'description' => mb_substr(strip_tags((string) $listing['description']), 0, 500),
            'sku' => $listing['reference'],
            'category' => $listing['category_name'] ?? 'Scrap',
            'url' => base_url('listing/' . $listing['slug']),
        ];
        if ($image !== null) {
            $data['image'] = upload_url($image);
        }
        if (!empty($listing['material_name'])) {
            $data['material'] = $listing['material_name'];
        }
        if ($listing['price_per_kg'] !== null && (int) $listing['show_price'] === 1) {
            $data['offers'] = [
                '@type' => 'Offer',
                'priceCurrency' => 'INR',
                'price' => dec($listing['price_per_mt'] ?? $listing['price'], 2),
                'availability' => $listing['status'] === 'active'
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => base_url('listing/' . $listing['slug']),
                'seller' => [
                    '@type' => 'Organization',
                    'name' => $listing['business_name'] ?: $listing['seller_name'],
                ],
            ];
        }
        if (!empty($listing['rating_count']) && (int) $listing['rating_count'] > 0) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $listing['rating_avg'],
                'reviewCount' => (string) $listing['rating_count'],
            ];
        }
        return $data;
    }
}
