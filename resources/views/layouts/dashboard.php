<?php

use App\Core\Auth;
use App\Core\View;
use App\Services\ChatService;
use App\Services\NotificationService;
use App\Services\SettingsService;

$siteName = (string) SettingsService::get('site_name', 'ScrapX');
$user = Auth::user() ?? [];
$userId = (int) ($user['id'] ?? 0);
$unreadMessages = $userId > 0 ? ChatService::unreadCount($userId) : 0;
$unreadNotifications = $userId > 0 ? NotificationService::unreadCount($userId) : 0;

$nav = [
    ['Overview', [
        ['/dashboard', 'bi-speedometer2', 'Dashboard', null],
        ['/dashboard/analytics', 'bi-graph-up', 'Analytics', null],
    ]],
];

if (Auth::isSeller()) {
    $nav[] = ['Selling', [
        ['/dashboard/listings', 'bi-box-seam', 'My Listings', null],
        ['/dashboard/listings/create', 'bi-plus-circle', 'Sell Scrap', null],
        ['/dashboard/auctions', 'bi-hammer', 'My Auctions', null],
        ['/dashboard/requirement-matches', 'bi-bullseye', 'Buyer Requirements', null],
        ['/dashboard/rfq-quotes', 'bi-file-earmark-text', 'My Quotes', null],
    ]];
}

if (Auth::isBuyer()) {
    $nav[] = ['Buying', [
        ['/dashboard/bids', 'bi-lightning-charge', 'My Bids', null],
        ['/dashboard/requirements', 'bi-card-checklist', 'My Requirements', null],
        ['/dashboard/rfq', 'bi-clipboard-check', 'My RFQs', null],
        ['/dashboard/saved', 'bi-bookmark-heart', 'Saved Items', null],
    ]];
}

$nav[] = ['Trade', [
    ['/dashboard/offers', 'bi-tags', 'Offers', null],
    ['/dashboard/messages', 'bi-chat-dots', 'Messages', $unreadMessages ?: null],
    ['/dashboard/orders', 'bi-bag-check', 'Orders', null],
    ['/dashboard/payments', 'bi-cash-coin', 'Payments', null],
    ['/dashboard/transport', 'bi-truck', 'Transport', null],
]];

$nav[] = ['Account', [
    ['/dashboard/profile', 'bi-person', 'Profile', null],
    ['/dashboard/business', 'bi-building', 'Business', null],
    ['/dashboard/kyc', 'bi-patch-check', 'KYC', null],
    ['/dashboard/reviews', 'bi-star', 'Reviews', null],
    ['/dashboard/disputes', 'bi-shield-exclamation', 'Disputes', null],
    ['/dashboard/notifications', 'bi-bell', 'Notifications', $unreadNotifications ?: null],
    ['/dashboard/wallet', 'bi-wallet2', 'Wallet', null],
    ['/dashboard/security', 'bi-shield-lock', 'Security & API', null],
]];
?><!doctype html>
<html lang="<?= e(App\Core\Lang::locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($title ?? 'Dashboard') ?> | <?= e($siteName) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="theme-color" content="#0f766e">
    <link rel="manifest" href="<?= e(base_url('manifest.webmanifest')) ?>">
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
    <?= View::yieldSection('head') ?>
</head>
<body class="dashboard-body">

<?= View::partial('partials/header') ?>

<div class="container-fluid px-lg-4">
    <div class="row">
        <aside class="col-lg-3 col-xl-2 d-none d-lg-block sidebar py-4">
            <?php foreach ($nav as [$group, $links]): ?>
                <div class="sidebar-group">
                    <div class="sidebar-heading"><?= e($group) ?></div>
                    <ul class="nav flex-column">
                        <?php foreach ($links as [$href, $icon, $label, $badge]): ?>
                            <li class="nav-item">
                                <a class="nav-link d-flex align-items-center gap-2 <?= active_nav($href) ?>"
                                   href="<?= e(url(ltrim($href, '/'))) ?>">
                                    <i class="bi <?= e($icon) ?>"></i>
                                    <span class="flex-grow-1"><?= e($label) ?></span>
                                    <?php if ($badge): ?>
                                        <span class="badge rounded-pill text-bg-danger"><?= (int) $badge ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </aside>

        <div class="col-lg-9 col-xl-10 py-4">
            <!-- Mobile section switcher -->
            <div class="d-lg-none mb-3">
                <select class="form-select" onchange="if(this.value) window.location.href=this.value">
                    <?php foreach ($nav as [$group, $links]): ?>
                        <optgroup label="<?= e($group) ?>">
                            <?php foreach ($links as [$href, $icon, $label, $badge]): ?>
                                <option value="<?= e(url(ltrim($href, '/'))) ?>" <?= active_nav($href) ? 'selected' : '' ?>>
                                    <?= e($label) ?><?= $badge ? ' (' . (int) $badge . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>

            <?= View::partial('partials/flash', ['flashes' => $flashes ?? []]) ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Please correct the following:</strong>
                    <ul class="mb-0 mt-1">
                        <?php foreach ($errors as $field => $messages): ?>
                            <?php foreach ((array) $messages as $message): ?>
                                <li><?= e((string) $message) ?></li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?= View::yieldSection('content') ?>
        </div>
    </div>
</div>

<?= View::partial('partials/mobile_nav') ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?= View::yieldSection('scripts') ?>
</body>
</html>
