<?php

declare(strict_types=1);

/**
 * Public + authenticated web routes.
 *
 * Middleware reference:
 *   auth, guest, admin, verified, kyc,
 *   role:<slug,...>, permission:<slug,...>, account:<buyer|seller>,
 *   throttle:<max>,<seconds>, no_csrf
 */

use App\Controllers\Admin;
use App\Controllers\Auth as AuthControllers;
use App\Controllers\Dashboard;
use App\Core\Kernel;

$router = Kernel::router();

// ---------------------------------------------------------------- public ----
$router->get('/', [App\Controllers\HomeController::class, 'index'])->name('home');
$router->get('/how-it-works', [App\Controllers\HomeController::class, 'howItWorks'])->name('how_it_works');
$router->get('/search', [App\Controllers\MarketplaceController::class, 'index'])->name('search');
$router->get('/sitemap.xml', [App\Controllers\HomeController::class, 'sitemap']);
$router->get('/robots.txt', [App\Controllers\HomeController::class, 'robots']);
$router->get('/manifest.webmanifest', [App\Controllers\HomeController::class, 'manifest']);
$router->get('/service-worker.js', [App\Controllers\HomeController::class, 'serviceWorker']);
$router->get('/offline', [App\Controllers\HomeController::class, 'offline']);
$router->post('/language', [App\Controllers\HomeController::class, 'switchLanguage'])->name('language');

// Marketplace — buy
$router->get('/buy', [App\Controllers\MarketplaceController::class, 'index'])->name('buy');
$router->get('/scrap/{slug}', [App\Controllers\MarketplaceController::class, 'category'])->name('category');
$router->get('/material/{slug}', [App\Controllers\MarketplaceController::class, 'material'])->name('material');
$router->get('/listing/{slug}', [App\Controllers\ListingController::class, 'show'])->name('listing');
$router->get('/listings/{id:\d+}/contact', [App\Controllers\ListingController::class, 'contact'])->middleware('auth');

// Auctions
$router->get('/auctions', [App\Controllers\AuctionController::class, 'index'])->name('auctions');
$router->get('/auctions/{id:\d+}', [App\Controllers\AuctionController::class, 'show'])->name('auction');
$router->get('/auctions/{id:\d+}/state', [App\Controllers\AuctionController::class, 'state']);
$router->post('/auctions/{id:\d+}/bid', [App\Controllers\AuctionController::class, 'bid'])->middleware(['auth', 'verified', 'throttle:40,60']);
$router->post('/auctions/{id:\d+}/register', [App\Controllers\AuctionController::class, 'register'])->middleware('auth');

// Buyer requirements (wanted)
$router->get('/wanted', [App\Controllers\RequirementController::class, 'index'])->name('wanted');
$router->get('/wanted/{slug}', [App\Controllers\RequirementController::class, 'show'])->name('requirement');
$router->post('/wanted/{id:\d+}/offer', [App\Controllers\RequirementController::class, 'submitOffer'])->middleware(['auth', 'verified']);

// RFQ
$router->get('/rfq', [App\Controllers\RfqController::class, 'index'])->name('rfq');
$router->get('/rfq/{id:\d+}', [App\Controllers\RfqController::class, 'show'])->name('rfq_show');
$router->post('/rfq/{id:\d+}/quote', [App\Controllers\RfqController::class, 'submitQuote'])->middleware(['auth', 'verified']);

// Market rates
$router->get('/market-rates', [App\Controllers\MarketRateController::class, 'index'])->name('market_rates');
$router->get('/market-rates/{slug}', [App\Controllers\MarketRateController::class, 'material'])->name('market_rate_material');
$router->get('/market-rates/{id:\d+}/history', [App\Controllers\MarketRateController::class, 'history']);

// Businesses
$router->get('/businesses', [App\Controllers\BusinessController::class, 'index'])->name('businesses');
$router->get('/sellers', [App\Controllers\BusinessController::class, 'sellers'])->name('sellers');
$router->get('/buyers', [App\Controllers\BusinessController::class, 'buyers'])->name('buyers');
$router->get('/business/{slug}', [App\Controllers\BusinessController::class, 'show'])->name('business');
$router->post('/business/{id:\d+}/follow', [App\Controllers\BusinessController::class, 'follow'])->middleware('auth');
$router->post('/report', [App\Controllers\BusinessController::class, 'report'])->middleware('auth');

// Pricing / plans
$router->get('/pricing', [App\Controllers\HomeController::class, 'pricing'])->name('pricing');

// CMS + contact
$router->get('/contact', [App\Controllers\PageController::class, 'contact'])->name('contact');
$router->post('/contact', [App\Controllers\PageController::class, 'submitContact'])->middleware('throttle:5,600');
$router->get('/faq', [App\Controllers\PageController::class, 'faq'])->name('faq');
$router->get('/page/{slug}', [App\Controllers\PageController::class, 'show'])->name('page');

// ------------------------------------------------------------------ auth ----
$router->get('/login', [AuthControllers\LoginController::class, 'show'])->middleware('guest')->name('login');
$router->post('/login', [AuthControllers\LoginController::class, 'login'])->middleware(['guest', 'throttle:20,600']);
$router->post('/logout', [AuthControllers\LoginController::class, 'logout'])->middleware('auth')->name('logout');

$router->get('/register', [AuthControllers\RegisterController::class, 'show'])->middleware('guest')->name('register');
$router->post('/register', [AuthControllers\RegisterController::class, 'register'])->middleware(['guest', 'throttle:10,600']);

$router->get('/verify/mobile', [AuthControllers\VerificationController::class, 'show'])->middleware('auth')->name('verify_mobile');
$router->post('/verify/send', [AuthControllers\VerificationController::class, 'send'])->middleware(['auth', 'throttle:6,3600']);
$router->post('/verify/confirm', [AuthControllers\VerificationController::class, 'confirm'])->middleware(['auth', 'throttle:15,600']);

$router->get('/forgot-password', [AuthControllers\PasswordController::class, 'showForgot'])->middleware('guest')->name('forgot_password');
$router->post('/forgot-password', [AuthControllers\PasswordController::class, 'sendReset'])->middleware(['guest', 'throttle:6,3600']);
$router->get('/reset-password/{token}', [AuthControllers\PasswordController::class, 'showReset'])->middleware('guest')->name('reset_password');
$router->post('/reset-password', [AuthControllers\PasswordController::class, 'reset'])->middleware(['guest', 'throttle:10,600']);

// ------------------------------------------------------------- scheduler ----
// Web fallback for hosts without system cron. Protected by a secret key.
$router->get('/cron/run', [App\Controllers\CronController::class, 'run'])->middleware('no_csrf');
$router->post('/cron/run', [App\Controllers\CronController::class, 'run'])->middleware('no_csrf');

// ------------------------------------------------------------- dashboard ----
$router->group(['prefix' => 'dashboard', 'middleware' => ['auth']], static function ($router): void {
    $router->get('/', [Dashboard\DashboardController::class, 'index'])->name('dashboard');
    $router->get('/analytics', [Dashboard\DashboardController::class, 'analytics'])->name('analytics');

    // Profile & business
    $router->get('/profile', [Dashboard\ProfileController::class, 'edit'])->name('profile');
    $router->post('/profile', [Dashboard\ProfileController::class, 'update']);
    $router->post('/profile/password', [Dashboard\ProfileController::class, 'changePassword']);
    $router->post('/profile/notifications', [Dashboard\ProfileController::class, 'updateNotifications']);
    $router->get('/business', [Dashboard\ProfileController::class, 'business'])->name('business_profile');
    $router->post('/business', [Dashboard\ProfileController::class, 'updateBusiness']);
    $router->get('/security', [Dashboard\ProfileController::class, 'security'])->name('security');
    $router->post('/api-tokens', [Dashboard\ProfileController::class, 'createToken']);
    $router->post('/api-tokens/{id:\d+}/revoke', [Dashboard\ProfileController::class, 'revokeToken']);

    // KYC
    $router->get('/kyc', [Dashboard\KycController::class, 'index'])->name('kyc');
    $router->post('/kyc/upload', [Dashboard\KycController::class, 'upload']);
    $router->post('/kyc/submit', [Dashboard\KycController::class, 'submit']);
    $router->post('/kyc/documents/{id:\d+}/delete', [Dashboard\KycController::class, 'deleteDocument']);

    // Listings & sell wizard
    $router->get('/listings', [Dashboard\ListingController::class, 'index'])->name('my_listings');
    $router->get('/listings/create', [Dashboard\ListingController::class, 'create'])->middleware('verified')->name('sell');
    $router->post('/listings', [Dashboard\ListingController::class, 'store'])->middleware('verified');
    $router->get('/listings/{id:\d+}/edit', [Dashboard\ListingController::class, 'edit']);
    $router->post('/listings/{id:\d+}', [Dashboard\ListingController::class, 'update']);
    $router->post('/listings/{id:\d+}/status', [Dashboard\ListingController::class, 'changeStatus']);
    $router->post('/listings/{id:\d+}/delete', [Dashboard\ListingController::class, 'destroy']);
    $router->post('/listings/{id:\d+}/images', [Dashboard\ListingController::class, 'uploadImages']);
    $router->post('/listings/images/{id:\d+}/delete', [Dashboard\ListingController::class, 'deleteImage']);
    $router->post('/listings/{id:\d+}/promote', [Dashboard\ListingController::class, 'promote']);
    $router->post('/listings/{id:\d+}/auction', [Dashboard\ListingController::class, 'createAuction'])->middleware('verified');

    // Auctions I run / bid on
    $router->get('/auctions', [Dashboard\AuctionController::class, 'index'])->name('my_auctions');
    $router->get('/auctions/{id:\d+}', [Dashboard\AuctionController::class, 'show']);
    $router->post('/auctions/{id:\d+}/cancel', [Dashboard\AuctionController::class, 'cancel']);
    $router->post('/auctions/{id:\d+}/award', [Dashboard\AuctionController::class, 'award']);
    $router->post('/auctions/{id:\d+}/bidders/{userId:\d+}', [Dashboard\AuctionController::class, 'updateBidder']);
    $router->get('/bids', [Dashboard\AuctionController::class, 'bids'])->name('my_bids');

    // Requirements
    $router->get('/requirements', [Dashboard\RequirementController::class, 'index'])->name('my_requirements');
    $router->get('/requirements/create', [Dashboard\RequirementController::class, 'create'])->middleware('verified');
    $router->post('/requirements', [Dashboard\RequirementController::class, 'store'])->middleware('verified');
    $router->get('/requirements/{id:\d+}', [Dashboard\RequirementController::class, 'show']);
    $router->post('/requirements/{id:\d+}/status', [Dashboard\RequirementController::class, 'changeStatus']);
    $router->post('/requirements/offers/{id:\d+}/accept', [Dashboard\RequirementController::class, 'acceptOffer']);
    $router->post('/requirements/offers/{id:\d+}/reject', [Dashboard\RequirementController::class, 'rejectOffer']);
    $router->post('/requirements/offers/{id:\d+}/shortlist', [Dashboard\RequirementController::class, 'shortlistOffer']);
    $router->get('/requirement-matches', [Dashboard\RequirementController::class, 'matches'])->name('requirement_matches');

    // RFQ
    $router->get('/rfq', [Dashboard\RfqController::class, 'index'])->name('my_rfqs');
    $router->get('/rfq/create', [Dashboard\RfqController::class, 'create'])->middleware('verified');
    $router->post('/rfq', [Dashboard\RfqController::class, 'store'])->middleware('verified');
    $router->get('/rfq/{id:\d+}', [Dashboard\RfqController::class, 'show']);
    $router->post('/rfq/{id:\d+}/invite', [Dashboard\RfqController::class, 'invite']);
    $router->post('/rfq/{id:\d+}/award', [Dashboard\RfqController::class, 'award']);
    $router->post('/rfq/{id:\d+}/close', [Dashboard\RfqController::class, 'close']);
    $router->post('/rfq/quotes/{id:\d+}/shortlist', [Dashboard\RfqController::class, 'shortlist']);
    $router->get('/rfq-quotes', [Dashboard\RfqController::class, 'myQuotes'])->name('my_quotes');

    // Offers
    $router->get('/offers', [Dashboard\OfferController::class, 'index'])->name('my_offers');
    $router->get('/offers/{id:\d+}', [Dashboard\OfferController::class, 'show']);
    $router->post('/offers', [Dashboard\OfferController::class, 'store'])->middleware('verified');
    $router->post('/offers/{id:\d+}/accept', [Dashboard\OfferController::class, 'accept']);
    $router->post('/offers/{id:\d+}/reject', [Dashboard\OfferController::class, 'reject']);
    $router->post('/offers/{id:\d+}/counter', [Dashboard\OfferController::class, 'counter']);
    $router->post('/offers/{id:\d+}/cancel', [Dashboard\OfferController::class, 'cancel']);

    // Messages
    $router->get('/messages', [Dashboard\MessageController::class, 'index'])->name('messages');
    $router->get('/messages/{id:\d+}', [Dashboard\MessageController::class, 'show']);
    $router->post('/messages/{id:\d+}', [Dashboard\MessageController::class, 'send']);
    $router->get('/messages/{id:\d+}/poll', [Dashboard\MessageController::class, 'poll']);
    $router->post('/messages/start', [Dashboard\MessageController::class, 'start']);
    $router->post('/messages/{id:\d+}/block', [Dashboard\MessageController::class, 'block']);

    // Orders
    $router->get('/orders', [Dashboard\OrderController::class, 'index'])->name('orders');
    $router->get('/orders/{id:\d+}', [Dashboard\OrderController::class, 'show']);
    $router->post('/orders/{id:\d+}/status', [Dashboard\OrderController::class, 'changeStatus']);
    $router->post('/orders/{id:\d+}/charges', [Dashboard\OrderController::class, 'updateCharges']);
    $router->post('/orders/{id:\d+}/weighment', [Dashboard\OrderController::class, 'recordWeighment']);
    $router->post('/orders/weighment/{id:\d+}/accept', [Dashboard\OrderController::class, 'acceptWeighment']);
    $router->post('/orders/{id:\d+}/weighment/preview', [Dashboard\OrderController::class, 'previewWeighment']);
    $router->post('/orders/{id:\d+}/delivery', [Dashboard\OrderController::class, 'createDelivery']);
    $router->post('/orders/delivery/{id:\d+}/status', [Dashboard\OrderController::class, 'updateDelivery']);
    $router->post('/orders/{id:\d+}/payment', [Dashboard\OrderController::class, 'recordPayment']);
    $router->post('/orders/payment/{id:\d+}/confirm', [Dashboard\OrderController::class, 'confirmPayment']);
    $router->post('/orders/{id:\d+}/invoice', [Dashboard\OrderController::class, 'generateInvoice']);
    $router->get('/invoices/{id:\d+}', [Dashboard\OrderController::class, 'invoice']);
    $router->get('/invoices/{id:\d+}/print', [Dashboard\OrderController::class, 'printInvoice']);

    // Reviews & disputes
    $router->get('/reviews', [Dashboard\ReviewController::class, 'index'])->name('reviews');
    $router->post('/orders/{id:\d+}/review', [Dashboard\ReviewController::class, 'store']);
    $router->post('/reviews/{id:\d+}/respond', [Dashboard\ReviewController::class, 'respond']);
    $router->get('/disputes', [Dashboard\DisputeController::class, 'index'])->name('disputes');
    $router->get('/disputes/{id:\d+}', [Dashboard\DisputeController::class, 'show']);
    $router->post('/orders/{id:\d+}/dispute', [Dashboard\DisputeController::class, 'store']);
    $router->post('/disputes/{id:\d+}/message', [Dashboard\DisputeController::class, 'addMessage']);

    // Saved items, notifications, wallet, transport
    $router->get('/saved', [Dashboard\DashboardController::class, 'saved'])->name('saved');
    $router->post('/favorites/toggle', [Dashboard\DashboardController::class, 'toggleFavorite']);
    $router->get('/notifications', [Dashboard\DashboardController::class, 'notifications'])->name('notifications');
    $router->post('/notifications/read', [Dashboard\DashboardController::class, 'markNotificationsRead']);
    $router->get('/wallet', [Dashboard\DashboardController::class, 'wallet'])->name('wallet');
    $router->get('/payments', [Dashboard\OrderController::class, 'payments'])->name('payments');
    $router->get('/transport', [Dashboard\TransportController::class, 'index'])->name('transport');
    $router->post('/transport/transporters', [Dashboard\TransportController::class, 'storeTransporter']);
    $router->post('/transport/vehicles', [Dashboard\TransportController::class, 'storeVehicle']);
});

// ----------------------------------------------------------------- admin ----
$router->group(['prefix' => 'admin', 'middleware' => ['admin']], static function ($router): void {
    $router->get('/', [Admin\AdminController::class, 'dashboard'])->name('admin');
    $router->get('/chart/{metric}', [Admin\AdminController::class, 'chart']);

    // Users & KYC
    $router->get('/users', [Admin\UserController::class, 'index'])->middleware('permission:view_users');
    $router->get('/users/{id:\d+}', [Admin\UserController::class, 'show'])->middleware('permission:view_users');
    $router->post('/users/{id:\d+}/status', [Admin\UserController::class, 'changeStatus'])->middleware('permission:manage_users');
    $router->post('/users/{id:\d+}/roles', [Admin\UserController::class, 'updateRoles'])->middleware('permission:manage_roles');
    $router->post('/users/{id:\d+}/password', [Admin\UserController::class, 'resetPassword'])->middleware('permission:reset_user_password');
    $router->post('/users/{id:\d+}/risk', [Admin\UserController::class, 'evaluateRisk'])->middleware('permission:view_users');
    $router->get('/kyc', [Admin\KycController::class, 'index'])->middleware('permission:view_kyc');
    $router->get('/kyc/{id:\d+}', [Admin\KycController::class, 'show'])->middleware('permission:view_kyc');
    $router->post('/kyc/{id:\d+}/approve', [Admin\KycController::class, 'approve'])->middleware('permission:manage_kyc');
    $router->post('/kyc/{id:\d+}/reject', [Admin\KycController::class, 'reject'])->middleware('permission:manage_kyc');
    $router->post('/kyc/documents/{id:\d+}/review', [Admin\KycController::class, 'reviewDocument'])->middleware('permission:manage_kyc');
    $router->get('/kyc/documents/{id:\d+}/view', [Admin\KycController::class, 'viewDocument'])->middleware('permission:view_kyc');

    // Catalog
    $router->get('/catalog', [Admin\CatalogController::class, 'index'])->middleware('permission:view_catalog');
    $router->post('/catalog/categories', [Admin\CatalogController::class, 'saveCategory'])->middleware('permission:manage_catalog');
    $router->post('/catalog/categories/{id:\d+}/delete', [Admin\CatalogController::class, 'deleteCategory'])->middleware('permission:manage_catalog');
    $router->get('/catalog/materials', [Admin\CatalogController::class, 'materials'])->middleware('permission:view_catalog');
    $router->post('/catalog/materials', [Admin\CatalogController::class, 'saveMaterial'])->middleware('permission:manage_catalog');
    $router->post('/catalog/materials/{id:\d+}/delete', [Admin\CatalogController::class, 'deleteMaterial'])->middleware('permission:manage_catalog');
    $router->post('/catalog/grades', [Admin\CatalogController::class, 'saveGrade'])->middleware('permission:manage_catalog');
    $router->post('/catalog/grades/{id:\d+}/delete', [Admin\CatalogController::class, 'deleteGrade'])->middleware('permission:manage_catalog');
    $router->get('/catalog/units', [Admin\CatalogController::class, 'units'])->middleware('permission:view_catalog');
    $router->post('/catalog/units', [Admin\CatalogController::class, 'saveUnit'])->middleware('permission:manage_catalog');
    $router->post('/catalog/hsn', [Admin\CatalogController::class, 'saveHsn'])->middleware('permission:manage_catalog');

    // Marketplace moderation
    $router->get('/listings', [Admin\ListingController::class, 'index'])->middleware('permission:view_listings');
    $router->post('/listings/{id:\d+}/approve', [Admin\ListingController::class, 'approve'])->middleware('permission:manage_listings');
    $router->post('/listings/{id:\d+}/reject', [Admin\ListingController::class, 'reject'])->middleware('permission:manage_listings');
    $router->post('/listings/{id:\d+}/feature', [Admin\ListingController::class, 'feature'])->middleware('permission:manage_listings');
    $router->post('/listings/{id:\d+}/delete', [Admin\ListingController::class, 'destroy'])->middleware('permission:manage_listings');
    $router->get('/auctions', [Admin\AuctionController::class, 'index'])->middleware('permission:view_auctions');
    $router->get('/auctions/{id:\d+}', [Admin\AuctionController::class, 'show'])->middleware('permission:view_auctions');
    $router->post('/auctions/{id:\d+}/cancel', [Admin\AuctionController::class, 'cancel'])->middleware('permission:manage_auctions');
    $router->post('/auctions/{id:\d+}/close', [Admin\AuctionController::class, 'close'])->middleware('permission:manage_auctions');
    $router->get('/requirements', [Admin\ListingController::class, 'requirements'])->middleware('permission:view_listings');
    $router->get('/rfqs', [Admin\ListingController::class, 'rfqs'])->middleware('permission:view_listings');

    // Orders & finance
    $router->get('/orders', [Admin\OrderController::class, 'index'])->middleware('permission:view_orders');
    $router->get('/orders/{id:\d+}', [Admin\OrderController::class, 'show'])->middleware('permission:view_orders');
    $router->post('/orders/{id:\d+}/status', [Admin\OrderController::class, 'changeStatus'])->middleware('permission:manage_orders');
    $router->get('/payments', [Admin\FinanceController::class, 'payments'])->middleware('permission:view_finance');
    $router->post('/payments/{id:\d+}/confirm', [Admin\FinanceController::class, 'confirmPayment'])->middleware('permission:manage_finance');
    $router->get('/commissions', [Admin\FinanceController::class, 'commissions'])->middleware('permission:view_finance');
    $router->post('/commissions/{id:\d+}/status', [Admin\FinanceController::class, 'updateCommission'])->middleware('permission:manage_finance');
    $router->get('/invoices', [Admin\FinanceController::class, 'invoices'])->middleware('permission:view_finance');
    $router->get('/invoices/{id:\d+}', [Admin\FinanceController::class, 'invoice'])->middleware('permission:view_finance');
    $router->get('/wallets', [Admin\FinanceController::class, 'wallets'])->middleware('permission:view_finance');
    $router->post('/wallets/adjust', [Admin\FinanceController::class, 'adjustWallet'])->middleware('permission:manage_finance');

    // Support
    $router->get('/disputes', [Admin\SupportController::class, 'disputes'])->middleware('permission:view_disputes');
    $router->get('/disputes/{id:\d+}', [Admin\SupportController::class, 'dispute'])->middleware('permission:view_disputes');
    $router->post('/disputes/{id:\d+}/status', [Admin\SupportController::class, 'updateDispute'])->middleware('permission:manage_disputes');
    $router->post('/disputes/{id:\d+}/message', [Admin\SupportController::class, 'replyDispute'])->middleware('permission:manage_disputes');
    $router->get('/reports', [Admin\SupportController::class, 'reports'])->middleware('permission:view_disputes');
    $router->post('/reports/{id:\d+}/handle', [Admin\SupportController::class, 'handleReport'])->middleware('permission:manage_disputes');
    $router->get('/reviews', [Admin\SupportController::class, 'reviews'])->middleware('permission:view_disputes');
    $router->post('/reviews/{id:\d+}/moderate', [Admin\SupportController::class, 'moderateReview'])->middleware('permission:manage_disputes');
    $router->get('/fraud', [Admin\SupportController::class, 'fraud'])->middleware('permission:view_users');
    $router->post('/fraud/{id:\d+}/resolve', [Admin\SupportController::class, 'resolveFlag'])->middleware('permission:manage_users');
    $router->get('/contact-messages', [Admin\SupportController::class, 'contactMessages'])->middleware('permission:view_disputes');

    // Content
    $router->get('/pages', [Admin\ContentController::class, 'pages'])->middleware('permission:manage_cms');
    $router->post('/pages', [Admin\ContentController::class, 'savePage'])->middleware('permission:manage_cms');
    $router->post('/pages/{id:\d+}/delete', [Admin\ContentController::class, 'deletePage'])->middleware('permission:manage_cms');
    $router->get('/faqs', [Admin\ContentController::class, 'faqs'])->middleware('permission:manage_cms');
    $router->post('/faqs', [Admin\ContentController::class, 'saveFaq'])->middleware('permission:manage_cms');
    $router->post('/faqs/{id:\d+}/delete', [Admin\ContentController::class, 'deleteFaq'])->middleware('permission:manage_cms');
    $router->get('/market-rates', [Admin\ContentController::class, 'marketRates'])->middleware('permission:manage_market_rates');
    $router->post('/market-rates', [Admin\ContentController::class, 'saveMarketRate'])->middleware('permission:manage_market_rates');
    $router->post('/market-rates/{id:\d+}/delete', [Admin\ContentController::class, 'deleteMarketRate'])->middleware('permission:manage_market_rates');
    $router->get('/templates', [Admin\ContentController::class, 'templates'])->middleware('permission:manage_notifications');
    $router->post('/templates/{id:\d+}', [Admin\ContentController::class, 'saveTemplate'])->middleware('permission:manage_notifications');
    $router->get('/notifications', [Admin\ContentController::class, 'notificationQueue'])->middleware('permission:manage_notifications');
    $router->post('/notifications/retry/{id:\d+}', [Admin\ContentController::class, 'retryNotification'])->middleware('permission:manage_notifications');
    $router->get('/plans', [Admin\ContentController::class, 'plans'])->middleware('permission:manage_settings');
    $router->post('/plans', [Admin\ContentController::class, 'savePlan'])->middleware('permission:manage_settings');

    // System
    $router->get('/settings', [Admin\SettingsController::class, 'index'])->middleware('permission:view_settings');
    $router->post('/settings', [Admin\SettingsController::class, 'save'])->middleware('permission:manage_settings');
    $router->post('/settings/test-mail', [Admin\SettingsController::class, 'testMail'])->middleware('permission:manage_settings');
    $router->get('/roles', [Admin\SettingsController::class, 'roles'])->middleware('permission:manage_roles');
    $router->post('/roles/{id:\d+}/permissions', [Admin\SettingsController::class, 'savePermissions'])->middleware('permission:manage_roles');
    $router->get('/audit', [Admin\SystemController::class, 'audit'])->middleware('permission:view_audit_logs');
    $router->get('/logs', [Admin\SystemController::class, 'logs'])->middleware('permission:view_system_health');
    $router->post('/logs/{name}/delete', [Admin\SystemController::class, 'deleteLog'])->middleware('permission:view_system_health');
    $router->get('/health', [Admin\SystemController::class, 'health'])->middleware('permission:view_system_health');
    $router->get('/cron', [Admin\SystemController::class, 'cron'])->middleware('permission:view_system_health');
    $router->post('/cron/{job}/run', [Admin\SystemController::class, 'runJob'])->middleware('permission:view_system_health');
    $router->get('/backups', [Admin\BackupController::class, 'index'])->middleware('permission:manage_backups');
    $router->post('/backups', [Admin\BackupController::class, 'create'])->middleware('permission:manage_backups');
    $router->get('/backups/{id:\d+}/download', [Admin\BackupController::class, 'download'])->middleware('permission:manage_backups');
    $router->post('/backups/{id:\d+}/restore', [Admin\BackupController::class, 'restore'])->middleware('permission:manage_backups');
    $router->post('/backups/{id:\d+}/delete', [Admin\BackupController::class, 'destroy'])->middleware('permission:manage_backups');

    // Updates
    $router->get('/updates', [Admin\UpdateController::class, 'index'])->middleware('permission:manage_updates');
    $router->post('/updates/settings', [Admin\UpdateController::class, 'saveSettings'])->middleware('permission:manage_updates');
    $router->post('/updates/test', [Admin\UpdateController::class, 'testConnection'])->middleware('permission:manage_updates');
    $router->post('/updates/check', [Admin\UpdateController::class, 'check'])->middleware('permission:manage_updates');
    $router->post('/updates/apply', [Admin\UpdateController::class, 'apply'])->middleware('permission:manage_updates');
    $router->get('/updates/{id:\d+}', [Admin\UpdateController::class, 'show'])->middleware('permission:manage_updates');
    $router->post('/updates/{id:\d+}/rollback', [Admin\UpdateController::class, 'rollback'])->middleware('permission:manage_updates');
    $router->post('/updates/clear-cache', [Admin\UpdateController::class, 'clearCache'])->middleware('permission:manage_updates');

    // Import / export / demo data
    $router->get('/export', [Admin\DataController::class, 'index'])->middleware('permission:view_users');
    $router->get('/export/{dataset}', [Admin\DataController::class, 'export'])->middleware('permission:view_users');
    $router->post('/import', [Admin\DataController::class, 'import'])->middleware('permission:manage_catalog');
    $router->get('/import/template/{type}', [Admin\DataController::class, 'template'])->middleware('permission:manage_catalog');
    $router->post('/demo-data/purge', [Admin\DataController::class, 'purgeDemo'])->middleware('permission:manage_settings');
});
