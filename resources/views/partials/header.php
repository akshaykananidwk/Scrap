<?php

use App\Core\Auth;
use App\Core\Database;
use App\Core\Lang;
use App\Services\ChatService;
use App\Services\NotificationService;
use App\Services\SettingsService;

$siteName = (string) SettingsService::get('site_name', 'ScrapX');
$logo = SettingsService::get('site_logo');
$user = Auth::user();
$unreadNotifications = $user !== null ? NotificationService::unreadCount((int) $user['id']) : 0;
$unreadMessages = $user !== null ? ChatService::unreadCount((int) $user['id']) : 0;

// Top-level categories power the "Buy Scrap" mega menu.
$navCategories = App\Models\Category::roots();
$headerPages = Database::instance()->select(
    'SELECT slug, title FROM cms_pages WHERE is_published = 1 AND show_in_header = 1 ORDER BY sort_order LIMIT 4'
);
?>
<header class="sticky-top bg-white border-bottom shadow-sm">
    <div class="bg-dark text-white-50 small d-none d-lg-block">
        <div class="container d-flex justify-content-between align-items-center py-1">
            <div class="d-flex gap-3">
                <?php if ($phone = SettingsService::get('contact_phone')): ?>
                    <a class="link-light text-decoration-none" href="tel:<?= e((string) $phone) ?>">
                        <i class="bi bi-telephone me-1"></i><?= e((string) $phone) ?>
                    </a>
                <?php endif; ?>
                <?php if ($email = SettingsService::get('contact_email')): ?>
                    <a class="link-light text-decoration-none" href="mailto:<?= e((string) $email) ?>">
                        <i class="bi bi-envelope me-1"></i><?= e((string) $email) ?>
                    </a>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-3 align-items-center">
                <a class="link-light text-decoration-none" href="<?= e(url('market-rates')) ?>">
                    <i class="bi bi-graph-up-arrow me-1"></i>Today's Scrap Rates
                </a>
                <form method="post" action="<?= e(url('language')) ?>" class="d-flex align-items-center gap-1">
                    <?= csrf_field() ?>
                    <i class="bi bi-translate"></i>
                    <select name="locale" class="form-select form-select-sm bg-dark text-white border-0 py-0"
                            style="width:auto" onchange="this.form.submit()">
                        <?php foreach (Lang::SUPPORTED as $code => $label): ?>
                            <option value="<?= e($code) ?>" <?= Lang::locale() === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg navbar-light bg-white py-2">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= e(url('/')) ?>">
                <?php if ($logo): ?>
                    <img src="<?= e(upload_url((string) $logo)) ?>" alt="<?= e($siteName) ?>" height="36">
                <?php else: ?>
                    <span class="brand-mark"><i class="bi bi-recycle"></i></span>
                <?php endif; ?>
                <span class="text-teal"><?= e($siteName) ?></span>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= active_nav('/buy') ?><?= active_nav('/scrap') ?>" href="#" data-bs-toggle="dropdown">
                            <?= e(__('nav.buy')) ?>
                        </a>
                        <div class="dropdown-menu mega-menu shadow">
                            <div class="row g-2 px-2">
                                <?php foreach ($navCategories as $category): ?>
                                    <div class="col-6 col-lg-3">
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 py-2"
                                           href="<?= e(url('scrap/' . $category['slug'])) ?>">
                                            <i class="bi <?= e($category['icon'] ?: 'bi-box') ?> text-teal"></i>
                                            <span>
                                                <span class="d-block fw-semibold small"><?= e($category['name']) ?></span>
                                                <span class="text-muted" style="font-size:.75rem"><?= (int) $category['listing_count'] ?> listings</span>
                                            </span>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <hr class="my-2">
                            <a class="dropdown-item fw-semibold text-teal" href="<?= e(url('buy')) ?>">
                                Browse all listings <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= active_nav('/dashboard/listings/create') ?>" href="<?= e(url('dashboard/listings/create')) ?>">
                            <?= e(__('nav.sell')) ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= active_nav('/auctions') ?>" href="<?= e(url('auctions')) ?>">
                            <?= e(__('nav.auctions')) ?>
                            <span class="badge rounded-pill text-bg-danger blink-dot ms-1">LIVE</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= active_nav('/wanted') ?>" href="<?= e(url('wanted')) ?>"><?= e(__('nav.wanted')) ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= active_nav('/rfq') ?>" href="<?= e(url('rfq')) ?>"><?= e(__('nav.rfq')) ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= active_nav('/market-rates') ?>" href="<?= e(url('market-rates')) ?>"><?= e(__('nav.rates')) ?></a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">More</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= e(url('businesses')) ?>">Verified Businesses</a></li>
                            <li><a class="dropdown-item" href="<?= e(url('how-it-works')) ?>">How It Works</a></li>
                            <li><a class="dropdown-item" href="<?= e(url('pricing')) ?>">Pricing</a></li>
                            <li><a class="dropdown-item" href="<?= e(url('faq')) ?>">FAQ</a></li>
                            <?php foreach ($headerPages as $page): ?>
                                <li><a class="dropdown-item" href="<?= e(url('page/' . $page['slug'])) ?>"><?= e($page['title']) ?></a></li>
                            <?php endforeach; ?>
                            <li><a class="dropdown-item" href="<?= e(url('contact')) ?>">Contact</a></li>
                        </ul>
                    </li>
                </ul>

                <form class="d-flex me-lg-3 my-2 my-lg-0" action="<?= e(url('buy')) ?>" method="get" role="search">
                    <div class="input-group input-group-sm search-box">
                        <input class="form-control" type="search" name="q" placeholder="Search copper, HMS 1, PET…"
                               value="<?= e((string) ($_GET['q'] ?? '')) ?>" aria-label="Search listings">
                        <button class="btn btn-teal" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </form>

                <ul class="navbar-nav align-items-lg-center">
                    <?php if ($user === null): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= e(url('login')) ?>"><?= e(__('nav.login')) ?></a>
                        </li>
                        <li class="nav-item ms-lg-2">
                            <a class="btn btn-teal btn-sm px-3" href="<?= e(url('register')) ?>"><?= e(__('nav.register')) ?></a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="<?= e(url('dashboard/messages')) ?>" title="Messages">
                                <i class="bi bi-chat-dots fs-5"></i>
                                <?php if ($unreadMessages > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">
                                        <?= $unreadMessages > 99 ? '99+' : $unreadMessages ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="<?= e(url('dashboard/notifications')) ?>" title="Notifications">
                                <i class="bi bi-bell fs-5"></i>
                                <?php if ($unreadNotifications > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">
                                        <?= $unreadNotifications > 99 ? '99+' : $unreadNotifications ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item dropdown ms-lg-2">
                            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
                                <?php if (!empty($user['avatar'])): ?>
                                    <img src="<?= e(upload_url((string) $user['avatar'])) ?>" class="rounded-circle" width="28" height="28" alt="">
                                <?php else: ?>
                                    <span class="avatar-initial"><?= e(mb_strtoupper(mb_substr((string) $user['full_name'], 0, 1))) ?></span>
                                <?php endif; ?>
                                <span class="d-none d-xl-inline"><?= e(mb_strimwidth((string) $user['full_name'], 0, 16, '…')) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><a class="dropdown-item" href="<?= e(url('dashboard')) ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                                <?php if (Auth::isSeller()): ?>
                                    <li><a class="dropdown-item" href="<?= e(url('dashboard/listings')) ?>"><i class="bi bi-box-seam me-2"></i>My Listings</a></li>
                                    <li><a class="dropdown-item" href="<?= e(url('dashboard/auctions')) ?>"><i class="bi bi-hammer me-2"></i>My Auctions</a></li>
                                <?php endif; ?>
                                <?php if (Auth::isBuyer()): ?>
                                    <li><a class="dropdown-item" href="<?= e(url('dashboard/bids')) ?>"><i class="bi bi-lightning me-2"></i>My Bids</a></li>
                                    <li><a class="dropdown-item" href="<?= e(url('dashboard/requirements')) ?>"><i class="bi bi-card-checklist me-2"></i>My Requirements</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="<?= e(url('dashboard/orders')) ?>"><i class="bi bi-bag-check me-2"></i>Orders</a></li>
                                <li><a class="dropdown-item" href="<?= e(url('dashboard/saved')) ?>"><i class="bi bi-bookmark-heart me-2"></i>Saved</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= e(url('dashboard/profile')) ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="<?= e(url('dashboard/kyc')) ?>">
                                    <i class="bi bi-patch-check me-2"></i>KYC
                                    <?php if (($user['kyc_status'] ?? '') === 'verified'): ?>
                                        <span class="badge text-bg-success ms-1">Verified</span>
                                    <?php elseif (($user['kyc_status'] ?? '') === 'pending'): ?>
                                        <span class="badge text-bg-warning ms-1">Pending</span>
                                    <?php endif; ?>
                                </a></li>
                                <?php if (Auth::isStaff()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-teal fw-semibold" href="<?= e(url('admin')) ?>">
                                        <i class="bi bi-shield-lock me-2"></i>Admin Panel
                                    </a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="post" action="<?= e(url('logout')) ?>" class="px-3 py-1">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-secondary w-100" type="submit">
                                            <i class="bi bi-box-arrow-right me-1"></i>Sign out
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>

<?php if (SettingsService::bool('maintenance_mode', false) && Auth::isStaff()): ?>
    <div class="alert alert-warning rounded-0 border-0 mb-0 text-center small">
        <i class="bi bi-cone-striped me-1"></i>
        <strong>Maintenance mode is ON.</strong> Visitors see the maintenance page; you can still browse as staff.
        <a href="<?= e(url('admin/settings?group=security')) ?>" class="alert-link">Turn it off</a>
    </div>
<?php endif; ?>
