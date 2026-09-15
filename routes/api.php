<?php

declare(strict_types=1);

/**
 * REST API v1.
 *
 * Read endpoints are public (same data the marketplace shows). Anything that
 * writes requires a bearer token issued from Dashboard → Security, so a mobile
 * app can be built against this without touching the web layer.
 */

use App\Controllers\Api;
use App\Core\Kernel;

$router = Kernel::router();

$router->group(['prefix' => 'api/v1'], static function ($router): void {
    // Meta
    $router->get('/', [Api\ApiController::class, 'index']);
    $router->get('/health', [Api\ApiController::class, 'health']);

    // Auth
    $router->post('/auth/login', [Api\AuthApiController::class, 'login'])->middleware('throttle:20,600');
    $router->post('/auth/register', [Api\AuthApiController::class, 'register'])->middleware('throttle:10,600');
    $router->post('/auth/otp/send', [Api\AuthApiController::class, 'sendOtp'])->middleware('throttle:6,3600');
    $router->post('/auth/otp/verify', [Api\AuthApiController::class, 'verifyOtp'])->middleware('throttle:15,600');
    $router->get('/auth/me', [Api\AuthApiController::class, 'me'])->middleware('api');
    $router->post('/auth/logout', [Api\AuthApiController::class, 'logout'])->middleware('api');

    // Catalog (public)
    $router->get('/categories', [Api\CatalogApiController::class, 'categories']);
    $router->get('/categories/{id:\d+}/materials', [Api\CatalogApiController::class, 'materials']);
    $router->get('/materials/{id:\d+}/grades', [Api\CatalogApiController::class, 'grades']);
    $router->get('/units', [Api\CatalogApiController::class, 'units']);
    $router->get('/states', [Api\CatalogApiController::class, 'states']);
    $router->get('/states/{id:\d+}/cities', [Api\CatalogApiController::class, 'cities']);
    $router->get('/cities/search', [Api\CatalogApiController::class, 'searchCities']);
    $router->get('/pincode/{pincode:\d{6}}', [Api\CatalogApiController::class, 'pincode']);
    $router->get('/market-rates', [Api\CatalogApiController::class, 'marketRates']);

    // Listings (public read)
    $router->get('/listings', [Api\ListingApiController::class, 'index']);
    $router->get('/listings/{id:\d+}', [Api\ListingApiController::class, 'show']);
    $router->post('/listings', [Api\ListingApiController::class, 'store'])->middleware('api');
    $router->post('/listings/{id:\d+}/status', [Api\ListingApiController::class, 'changeStatus'])->middleware('api');

    // Auctions & bids
    $router->get('/auctions', [Api\AuctionApiController::class, 'index']);
    $router->get('/auctions/{id:\d+}', [Api\AuctionApiController::class, 'show']);
    $router->get('/auctions/{id:\d+}/state', [Api\AuctionApiController::class, 'state']);
    $router->get('/auctions/{id:\d+}/bids', [Api\AuctionApiController::class, 'bids']);
    $router->post('/bids', [Api\AuctionApiController::class, 'placeBid'])->middleware(['api', 'throttle:40,60']);

    // Requirements & RFQ
    $router->get('/requirements', [Api\RequirementApiController::class, 'index']);
    $router->get('/requirements/{id:\d+}', [Api\RequirementApiController::class, 'show']);
    $router->post('/requirements', [Api\RequirementApiController::class, 'store'])->middleware('api');
    $router->post('/requirements/{id:\d+}/offers', [Api\RequirementApiController::class, 'offer'])->middleware('api');

    // Offers
    $router->get('/offers', [Api\OfferApiController::class, 'index'])->middleware('api');
    $router->post('/offers', [Api\OfferApiController::class, 'store'])->middleware('api');
    $router->post('/offers/{id:\d+}/accept', [Api\OfferApiController::class, 'accept'])->middleware('api');
    $router->post('/offers/{id:\d+}/reject', [Api\OfferApiController::class, 'reject'])->middleware('api');

    // Orders
    $router->get('/orders', [Api\OrderApiController::class, 'index'])->middleware('api');
    $router->get('/orders/{id:\d+}', [Api\OrderApiController::class, 'show'])->middleware('api');
    $router->post('/orders/{id:\d+}/status', [Api\OrderApiController::class, 'changeStatus'])->middleware('api');
    $router->post('/orders/{id:\d+}/weighment', [Api\OrderApiController::class, 'weighment'])->middleware('api');

    // Users & notifications
    $router->get('/users/me/stats', [Api\UserApiController::class, 'stats'])->middleware('api');
    $router->get('/notifications', [Api\UserApiController::class, 'notifications'])->middleware('api');
    $router->post('/notifications/read', [Api\UserApiController::class, 'markRead'])->middleware('api');
    $router->get('/businesses/{slug}', [Api\UserApiController::class, 'business']);
});

// Payment gateway callback (signature-verified inside the controller).
$router->post('/payments/callback/{id:\d+}', [Api\PaymentApiController::class, 'callback'])->middleware('no_csrf');
$router->post('/payments/webhook', [Api\PaymentApiController::class, 'webhook'])->middleware('no_csrf');
