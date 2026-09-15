<?php

use App\Core\View;

View::section('content');
?>
<section class="py-5 bg-teal-soft">
    <div class="container text-center">
        <h1 class="h3 mb-2">Plans &amp; pricing</h1>
        <p class="text-muted mb-0">Listing and buying are free. You pay only when a deal completes.</p>
    </div>
</section>

<div class="container py-5">
    <?php if (!$commission['enabled']): ?>
        <div class="alert alert-secondary">
            <strong>Commission is currently switched off</strong> on this installation — deals complete
            with no platform fee. The commission engine is fully built and an administrator can enable it
            at any time under <em>Admin → Settings → Commission</em>.
        </div>
    <?php else: ?>
        <div class="row g-3 mb-5">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-value fs-5"><?= e((string) $commission['percentage']) ?>%</div>
                    <div class="stat-label">Commission per completed deal</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-value fs-5"><?= money($commission['fixed']) ?></div>
                    <div class="stat-label">Fixed fee per deal</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-value fs-5"><?= e(label((string) $commission['party'])) ?></div>
                    <div class="stat-label">Who pays the commission</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-value fs-5"><?= e((string) $commission['gst']) ?>%</div>
                    <div class="stat-label">GST on the commission</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-5">
            <?php if ((float) $commission['auction_fee'] > 0): ?>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6>Auction success fee</h6>
                            <p class="small text-muted mb-0">
                                <?= e((string) $commission['auction_fee']) ?>% of the winning bid, charged only when an auction is awarded.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ((float) $commission['featured_fee'] > 0): ?>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6>Featured listing</h6>
                            <p class="small text-muted mb-0">
                                <?= money($commission['featured_fee']) ?> for 30 days at the top of search results
                                and on the home page.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($plans !== []): ?>
        <h2 class="h5 mb-4 text-center">Subscription plans</h2>
        <div class="row g-4 justify-content-center">
            <?php foreach ($plans as $plan): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="mb-1"><?= e((string) $plan['name']) ?></h5>
                            <p class="small text-muted"><?= e((string) ($plan['description'] ?? '')) ?></p>

                            <div class="my-3">
                                <span class="display-6 fw-bold"><?= money($plan['price']) ?></span>
                                <span class="text-muted small">/ <?= e(label((string) ($plan['billing_period'] ?? 'monthly'))) ?></span>
                            </div>

                            <ul class="list-unstyled small flex-grow-1">
                                <?php foreach ($plan['features'] as $feature): ?>
                                    <li class="mb-2">
                                        <i class="bi bi-<?= (int) ($feature['is_included'] ?? 1) === 1 ? 'check2 text-success' : 'x text-muted' ?> me-2"></i>
                                        <?= e((string) $feature['feature_label']) ?>
                                        <?php if (!empty($feature['feature_value'])): ?>
                                            <strong><?= e((string) $feature['feature_value']) ?></strong>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <a class="btn btn-outline-teal mt-auto" href="<?= e(url('register')) ?>">Get started</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="small text-muted text-center mt-4 mb-0">
            Subscription billing is handled manually by our team today — sign up on the free tier and
            contact us to move onto a paid plan. Online subscription payment is switched on once a
            payment gateway is configured for this installation.
        </p>
    <?php endif; ?>

    <div class="text-center mt-5">
        <a class="btn btn-teal btn-lg" href="<?= e(url('register')) ?>">Create a free account</a>
        <div class="small text-muted mt-2">No card required. Listing and browsing cost nothing.</div>
    </div>
</div>
<?php View::endSection(); ?>
