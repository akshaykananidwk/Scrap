<?php

use App\Core\View;
use App\Services\SettingsService;

View::section('content');
?>

<!-- Hero -->
<section class="hero py-5">
    <div class="container py-lg-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <h1 class="display-5 fw-bold mb-3"><?= e((string) SettingsService::get('site_tagline', "India's B2B Scrap Trading Marketplace")) ?></h1>
                <p class="lead opacity-90 mb-4">
                    List material, run live auctions, answer buyer requirements and settle on real
                    weighbridge weight — with verified scrap businesses across India.
                </p>

                <form action="<?= e(url('buy')) ?>" method="get" class="bg-white rounded-3 p-2 shadow-sm">
                    <div class="row g-2">
                        <div class="col-12 col-md-5">
                            <input type="search" name="q" class="form-control form-control-lg"
                                   placeholder="Copper, HMS 1, PET bales…" aria-label="Search material">
                        </div>
                        <div class="col-6 col-md-3">
                            <select name="category_id" class="form-select form-select-lg" aria-label="Category">
                                <option value="">All categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <select name="state_id" class="form-select form-select-lg" aria-label="State">
                                <option value="">All India</option>
                                <?php foreach (App\Models\State::active() as $state): ?>
                                    <option value="<?= (int) $state['id'] ?>"><?= e($state['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2 d-grid">
                            <button class="btn btn-teal btn-lg" type="submit">
                                <i class="bi bi-search me-1"></i>Search
                            </button>
                        </div>
                    </div>
                </form>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a class="btn btn-light btn-sm" href="<?= e(url('buy')) ?>"><i class="bi bi-box-seam me-1"></i>Buy Scrap</a>
                    <a class="btn btn-light btn-sm" href="<?= e(url('dashboard/listings/create')) ?>"><i class="bi bi-plus-circle me-1"></i>Sell Scrap</a>
                    <a class="btn btn-light btn-sm" href="<?= e(url('dashboard/requirements/create')) ?>"><i class="bi bi-megaphone me-1"></i>Post Requirement</a>
                    <a class="btn btn-outline-light btn-sm" href="<?= e(url('auctions')) ?>"><i class="bi bi-hammer me-1"></i>Live Auctions</a>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="row g-2">
                    <?php
                    $heroStats = [
                        ['Verified businesses', $stats['businesses'], 'bi-building-check'],
                        ['Live listings', $stats['listings'], 'bi-box-seam'],
                        ['Live auctions', $stats['live_auctions'], 'bi-hammer'],
                        ['Open requirements', $stats['requirements'], 'bi-card-checklist'],
                        ['Cities covered', $stats['cities'], 'bi-geo-alt'],
                        ['Material types', $stats['materials'], 'bi-tags'],
                    ];
                    ?>
                    <?php foreach ($heroStats as [$label, $value, $icon]): ?>
                        <div class="col-6">
                            <div class="hero-stat d-flex align-items-center gap-2">
                                <i class="bi <?= e($icon) ?> fs-4 opacity-75"></i>
                                <div>
                                    <div class="fw-bold fs-5"><?= number_format((int) $value) ?></div>
                                    <div class="small opacity-75"><?= e($label) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Rate ticker -->
<?php if ($movers !== []): ?>
    <section class="bg-white border-bottom py-2">
        <div class="container d-flex align-items-center gap-3 overflow-auto">
            <span class="badge text-bg-dark flex-shrink-0"><i class="bi bi-graph-up-arrow me-1"></i>Today's rates</span>
            <?php foreach ($movers as $rate): ?>
                <a class="text-decoration-none small flex-shrink-0 text-dark"
                   href="<?= e(url('market-rates/' . $rate['material_slug'])) ?>">
                    <span class="fw-semibold"><?= e((string) $rate['material_name']) ?></span>
                    <span class="text-muted"><?= e((string) ($rate['city_name'] ?? '')) ?></span>
                    <span class="ms-1"><?= e(money($rate['rate'])) ?>/<?= e((string) $rate['unit_code']) ?></span>
                    <?php $change = (float) $rate['change_percent']; ?>
                    <span class="<?= $change >= 0 ? 'rate-up' : 'rate-down' ?>">
                        <i class="bi bi-caret-<?= $change >= 0 ? 'up' : 'down' ?>-fill"></i><?= e(number_format(abs($change), 2)) ?>%
                    </span>
                </a>
            <?php endforeach; ?>
            <a class="small flex-shrink-0" href="<?= e(url('market-rates')) ?>">All rates →</a>
        </div>
    </section>
<?php endif; ?>

<!-- Live auctions -->
<?php if ($live_auctions !== []): ?>
    <section class="py-5">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h4 mb-1">
                        <span class="badge text-bg-danger blink-dot align-middle">LIVE</span>
                        Auctions closing soon
                    </h2>
                    <p class="text-muted small mb-0">Bids are binding. Late bids automatically extend the close time.</p>
                </div>
                <a class="btn btn-sm btn-outline-teal" href="<?= e(url('auctions')) ?>">All auctions</a>
            </div>

            <div class="row g-3">
                <?php foreach ($live_auctions as $auction): ?>
                    <div class="col-6 col-lg-4 col-xl-2">
                        <div class="card h-100 border-0 shadow-sm listing-card">
                            <a href="<?= e(url('auctions/' . $auction['id'])) ?>" class="listing-thumb">
                                <?php if (!empty($auction['image'])): ?>
                                    <img src="<?= e(upload_url((string) $auction['image'])) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="listing-thumb-placeholder"><i class="bi bi-hammer"></i></span>
                                <?php endif; ?>
                            </a>
                            <div class="card-body p-2">
                                <h6 class="small mb-1">
                                    <a class="text-decoration-none text-dark" href="<?= e(url('auctions/' . $auction['id'])) ?>">
                                        <?= e(mb_strimwidth((string) $auction['title'], 0, 44, '…')) ?>
                                    </a>
                                </h6>
                                <div class="fw-bold text-danger"><?= e(money($auction['current_price'] ?? $auction['starting_price'])) ?></div>
                                <div class="small text-muted"><?= (int) $auction['bid_count'] ?> bids</div>
                                <div class="small mt-1">
                                    <i class="bi bi-clock text-danger"></i>
                                    <span class="js-countdown fw-semibold" data-seconds="<?= countdown_seconds($auction['ends_at']) ?>">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- Categories -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="h4 mb-1">Scrap categories</h2>
            <p class="text-muted small mb-0">Metals, e-waste, plastic, paper, vehicle, industrial and construction scrap.</p>
        </div>

        <div class="row g-3">
            <?php foreach ($categories as $category): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <a class="category-tile text-center" href="<?= e(url('scrap/' . $category['slug'])) ?>">
                        <i class="bi <?= e($category['icon'] ?: 'bi-box') ?> d-block mb-2"></i>
                        <div class="fw-semibold"><?= e($category['name']) ?></div>
                        <div class="small text-muted"><?= (int) $category['listing_count'] ?> listings</div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Popular materials -->
<?php if ($popular_materials !== []): ?>
    <section class="py-4">
        <div class="container">
            <h2 class="h5 mb-3">Popular materials</h2>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($popular_materials as $material): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('material/' . $material['slug'])) ?>">
                        <?= e($material['name']) ?>
                        <?php if ((int) $material['listing_count'] > 0): ?>
                            <span class="badge text-bg-light ms-1"><?= (int) $material['listing_count'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- Latest listings -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">Latest listings</h2>
            <a class="btn btn-sm btn-outline-teal" href="<?= e(url('buy')) ?>">Browse all</a>
        </div>

        <?php if ($latest_listings === []): ?>
            <div class="empty-state">
                <i class="bi bi-box"></i>
                <p class="mb-2">No listings published yet.</p>
                <a class="btn btn-teal btn-sm" href="<?= e(url('dashboard/listings/create')) ?>">Be the first to list</a>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($latest_listings as $listing): ?>
                    <div class="col-6 col-lg-3">
                        <?= View::partial('partials/listing_card', ['listing' => $listing]) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Buyer requirements -->
<?php if ($requirements !== []): ?>
    <section class="py-5 bg-white">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h4 mb-1">Buyers looking for material</h2>
                    <p class="text-muted small mb-0">Sellers: respond with your price, available quantity and delivery.</p>
                </div>
                <a class="btn btn-sm btn-outline-teal" href="<?= e(url('wanted')) ?>">All requirements</a>
            </div>

            <div class="row g-3">
                <?php foreach ($requirements as $requirement): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge text-bg-info"><?= e(label((string) $requirement['frequency'])) ?></span>
                                    <?php if (!empty($requirement['kyc_verified'])): ?>
                                        <span class="badge badge-soft-success kyc-badge"><i class="bi bi-patch-check-fill me-1"></i>Verified buyer</span>
                                    <?php endif; ?>
                                </div>
                                <h6>
                                    <a class="text-decoration-none text-dark" href="<?= e(url('wanted/' . $requirement['slug'])) ?>">
                                        <?= e(mb_strimwidth((string) $requirement['title'], 0, 64, '…')) ?>
                                    </a>
                                </h6>
                                <div class="small text-muted mb-2">
                                    <i class="bi bi-geo-alt me-1"></i><?= e((string) ($requirement['city_name'] ?? 'India')) ?>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span>Needs <strong><?= e(qty($requirement['quantity'], (string) $requirement['unit_code'])) ?></strong></span>
                                    <?php if (!empty($requirement['target_price'])): ?>
                                        <span class="text-teal fw-semibold">~<?= e(money($requirement['target_price'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-0 pt-0">
                                <a class="btn btn-sm btn-outline-teal w-100" href="<?= e(url('wanted/' . $requirement['slug'])) ?>">
                                    Submit an offer
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- How it works -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="h4 mb-1">How ScrapX works</h2>
            <p class="text-muted small mb-0">The same deal flow every serious scrap trade follows.</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-teal mb-3"><i class="bi bi-box-arrow-up me-1"></i>If you are selling</h6>
                        <ol class="small mb-0 ps-3">
                            <li class="mb-2">List your material with photos, quantity and grade.</li>
                            <li class="mb-2">Choose fixed price, negotiable, make-offer or auction.</li>
                            <li class="mb-2">Verified buyers bid or send offers.</li>
                            <li class="mb-2">Accept the best one — an order is created automatically.</li>
                            <li class="mb-2">Record weighment, arrange transport, collect payment.</li>
                            <li>Raise the GST invoice and get reviewed.</li>
                        </ol>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-teal mb-3"><i class="bi bi-cart-check me-1"></i>If you are buying</h6>
                        <ol class="small mb-0 ps-3">
                            <li class="mb-2">Search live listings or post what you need.</li>
                            <li class="mb-2">Bid in auctions, make an offer or request a quotation.</li>
                            <li class="mb-2">Negotiate in chat and confirm the order.</li>
                            <li class="mb-2">Lift the material and weigh it at a weighbridge.</li>
                            <li class="mb-2">Settlement recalculates on actual weight.</li>
                            <li>Pay, get the invoice, leave a review.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <a class="btn btn-teal" href="<?= e(url('how-it-works')) ?>">Read the full guide</a>
        </div>
    </div>
</section>

<!-- Verified businesses -->
<?php if ($verified_businesses !== []): ?>
    <section class="py-5 bg-white">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h4 mb-0">Verified businesses</h2>
                <a class="btn btn-sm btn-outline-teal" href="<?= e(url('businesses')) ?>">View directory</a>
            </div>

            <div class="row g-3">
                <?php foreach ($verified_businesses as $business): ?>
                    <div class="col-6 col-md-3">
                        <a class="category-tile d-flex align-items-center gap-2 text-decoration-none"
                           href="<?= e(url('business/' . $business['slug'])) ?>">
                            <?php if (!empty($business['logo'])): ?>
                                <img src="<?= e(upload_url((string) $business['logo'])) ?>" class="rounded" width="40" height="40" style="object-fit:cover" alt="">
                            <?php else: ?>
                                <span class="avatar-initial" style="width:40px;height:40px;font-size:1rem">
                                    <?= e(mb_strtoupper(mb_substr((string) $business['name'], 0, 1))) ?>
                                </span>
                            <?php endif; ?>
                            <span class="flex-grow-1 min-w-0">
                                <span class="d-block fw-semibold small text-truncate"><?= e($business['name']) ?></span>
                                <span class="d-block text-muted" style="font-size:.72rem">
                                    <?= e((string) ($business['city_name'] ?? '')) ?>
                                    <?php if ((int) $business['rating_count'] > 0): ?>
                                        · <span class="star-rating">★</span> <?= e(number_format((float) $business['rating_avg'], 1)) ?>
                                    <?php endif; ?>
                                </span>
                            </span>
                            <?php if ((int) $business['kyc_verified'] === 1): ?>
                                <i class="bi bi-patch-check-fill text-teal"></i>
                            <?php endif; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- Reviews -->
<?php if ($reviews !== []): ?>
    <section class="py-5">
        <div class="container">
            <h2 class="h4 mb-3 text-center">What traders say</h2>
            <div class="row g-3">
                <?php foreach (array_slice($reviews, 0, 3) as $review): ?>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="star-rating mb-2"><?= str_repeat('★', (int) $review['overall_rating']) ?><span class="text-muted"><?= str_repeat('☆', 5 - (int) $review['overall_rating']) ?></span></div>
                                <?php if (!empty($review['title'])): ?>
                                    <h6 class="mb-1"><?= e((string) $review['title']) ?></h6>
                                <?php endif; ?>
                                <p class="small text-muted mb-3"><?= e(mb_strimwidth((string) $review['comment'], 0, 180, '…')) ?></p>
                                <div class="small">
                                    <strong><?= e((string) ($review['business_name'] ?? 'Verified trader')) ?></strong>
                                    <?php if (!empty($review['city_name'])): ?>
                                        <span class="text-muted">· <?= e((string) $review['city_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- FAQ -->
<?php if ($faqs !== []): ?>
    <section class="py-5 bg-white">
        <div class="container" style="max-width:820px">
            <h2 class="h4 mb-3 text-center">Common questions</h2>
            <div class="accordion" id="homeFaq">
                <?php foreach ($faqs as $index => $faq): ?>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#faq<?= (int) $faq['id'] ?>">
                                <?= e((string) $faq['question']) ?>
                            </button>
                        </h3>
                        <div id="faq<?= (int) $faq['id'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>"
                             data-bs-parent="#homeFaq">
                            <div class="accordion-body small"><?= strip_tags((string) $faq['answer'], '<p><br><ul><ol><li><strong><em><a>') ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-3">
                <a class="btn btn-sm btn-outline-teal" href="<?= e(url('faq')) ?>">All FAQs</a>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- CTA -->
<section class="py-5 bg-teal text-white">
    <div class="container text-center">
        <h2 class="h3 mb-2">Ready to trade scrap the professional way?</h2>
        <p class="opacity-90 mb-4">Free to register. Verified businesses. Real weighbridge settlement.</p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a class="btn btn-light btn-lg" href="<?= e(url('register')) ?>">Create free account</a>
            <a class="btn btn-outline-light btn-lg" href="<?= e(url('buy')) ?>">Browse listings</a>
            <button class="btn btn-outline-light btn-lg d-none" data-pwa-install>
                <i class="bi bi-download me-1"></i>Install app
            </button>
        </div>
    </div>
</section>

<?php View::endSection(); ?>
