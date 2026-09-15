<?php

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\SettingsService;

$siteName = (string) SettingsService::get('site_name', 'ScrapX');

// Live counters so staff see what needs attention without navigating.
$pendingKyc = (int) Database::instance()->scalar("SELECT COUNT(*) FROM kyc_verifications WHERE status IN ('pending','under_review')", [], 0);
$pendingListings = (int) Database::instance()->scalar("SELECT COUNT(*) FROM listings WHERE status = 'pending' AND deleted_at IS NULL", [], 0);
$openDisputes = (int) Database::instance()->scalar("SELECT COUNT(*) FROM disputes WHERE status NOT IN ('resolved','rejected','closed')", [], 0);
$openReports = (int) Database::instance()->scalar("SELECT COUNT(*) FROM content_reports WHERE status = 'open'", [], 0);
$openFraud = (int) Database::instance()->scalar("SELECT COUNT(*) FROM fraud_flags WHERE status = 'open'", [], 0);

$nav = [
    ['Overview', [['/admin', 'bi-speedometer2', 'Dashboard', null, null]]],
    ['People', [
        ['/admin/users', 'bi-people', 'Users', null, 'view_users'],
        ['/admin/kyc', 'bi-patch-check', 'KYC', $pendingKyc ?: null, 'view_kyc'],
        ['/admin/fraud', 'bi-shield-exclamation', 'Risk & Fraud', $openFraud ?: null, 'view_users'],
    ]],
    ['Marketplace', [
        ['/admin/listings', 'bi-box-seam', 'Listings', $pendingListings ?: null, 'view_listings'],
        ['/admin/auctions', 'bi-hammer', 'Auctions', null, 'view_auctions'],
        ['/admin/requirements', 'bi-card-checklist', 'Requirements', null, 'view_listings'],
        ['/admin/rfqs', 'bi-clipboard-check', 'RFQs', null, 'view_listings'],
        ['/admin/catalog', 'bi-diagram-3', 'Categories', null, 'view_catalog'],
        ['/admin/catalog/materials', 'bi-boxes', 'Materials', null, 'view_catalog'],
        ['/admin/catalog/units', 'bi-rulers', 'Units & HSN', null, 'view_catalog'],
    ]],
    ['Commerce', [
        ['/admin/orders', 'bi-bag-check', 'Orders', null, 'view_orders'],
        ['/admin/payments', 'bi-cash-coin', 'Payments', null, 'view_finance'],
        ['/admin/commissions', 'bi-percent', 'Commission', null, 'view_finance'],
        ['/admin/invoices', 'bi-receipt', 'Invoices', null, 'view_finance'],
        ['/admin/wallets', 'bi-wallet2', 'Wallets', null, 'view_finance'],
        ['/admin/plans', 'bi-stars', 'Plans', null, 'manage_settings'],
    ]],
    ['Support', [
        ['/admin/disputes', 'bi-shield-check', 'Disputes', $openDisputes ?: null, 'view_disputes'],
        ['/admin/reports', 'bi-flag', 'Reports', $openReports ?: null, 'view_disputes'],
        ['/admin/reviews', 'bi-star', 'Reviews', null, 'view_disputes'],
        ['/admin/contact-messages', 'bi-envelope', 'Contact', null, 'view_disputes'],
    ]],
    ['Content', [
        ['/admin/pages', 'bi-file-earmark-richtext', 'Pages', null, 'manage_cms'],
        ['/admin/faqs', 'bi-question-circle', 'FAQs', null, 'manage_cms'],
        ['/admin/market-rates', 'bi-graph-up-arrow', 'Market Rates', null, 'manage_market_rates'],
        ['/admin/templates', 'bi-envelope-paper', 'Templates', null, 'manage_notifications'],
        ['/admin/notifications', 'bi-send', 'Queue', null, 'manage_notifications'],
    ]],
    ['System', [
        ['/admin/settings', 'bi-gear', 'Settings', null, 'view_settings'],
        ['/admin/roles', 'bi-person-badge', 'Roles', null, 'manage_roles'],
        ['/admin/updates', 'bi-cloud-download', 'Updates', null, 'manage_updates'],
        ['/admin/backups', 'bi-hdd', 'Backups', null, 'manage_backups'],
        ['/admin/cron', 'bi-clock-history', 'Scheduler', null, 'view_system_health'],
        ['/admin/health', 'bi-heart-pulse', 'Health', null, 'view_system_health'],
        ['/admin/logs', 'bi-journal-text', 'Logs', null, 'view_system_health'],
        ['/admin/audit', 'bi-list-check', 'Audit Log', null, 'view_audit_logs'],
        ['/admin/export', 'bi-download', 'Import / Export', null, 'view_users'],
    ]],
];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Admin') ?> | <?= e($siteName) ?> Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
    <?= View::yieldSection('head') ?>
</head>
<body class="admin-body">

<nav class="navbar navbar-dark bg-teal-dark sticky-top px-3 py-2">
    <div class="d-flex align-items-center gap-2 w-100">
        <button class="btn btn-sm btn-outline-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminNav">
            <i class="bi bi-list"></i>
        </button>
        <a class="navbar-brand fw-bold mb-0 d-flex align-items-center gap-2" href="<?= e(url('admin')) ?>">
            <i class="bi bi-shield-lock"></i>
            <span><?= e($siteName) ?> <span class="fw-normal opacity-75">Admin</span></span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <a class="btn btn-sm btn-outline-light" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right me-1"></i><span class="d-none d-md-inline">View site</span>
            </a>
            <div class="dropdown">
                <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i>
                    <span class="d-none d-md-inline"><?= e(mb_strimwidth((string) (Auth::user()['full_name'] ?? ''), 0, 14, '…')) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= e(url('dashboard')) ?>"><i class="bi bi-speedometer2 me-2"></i>My dashboard</a></li>
                    <li><a class="dropdown-item" href="<?= e(url('dashboard/profile')) ?>"><i class="bi bi-person me-2"></i>My profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="<?= e(url('logout')) ?>" class="px-3">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger w-100" type="submit">Sign out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="d-flex admin-shell">
    <aside class="admin-sidebar d-none d-lg-block">
        <?= View::partial('partials/admin_nav', ['nav' => $nav]) ?>
    </aside>

    <div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="adminNav">
        <div class="offcanvas-header">
            <h6 class="offcanvas-title">Admin menu</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body admin-sidebar p-0">
            <?= View::partial('partials/admin_nav', ['nav' => $nav]) ?>
        </div>
    </div>

    <main class="admin-main flex-grow-1 p-3 p-lg-4">
        <?= View::partial('partials/flash', ['flashes' => $flashes ?? []]) ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $messages): ?>
                        <?php foreach ((array) $messages as $message): ?>
                            <li><?= e((string) $message) ?></li>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?= View::yieldSection('content') ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?= View::yieldSection('scripts') ?>
</body>
</html>
