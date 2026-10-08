<?php

use App\Core\Auth;
use App\Core\View;

View::section('content');

/** @var list<array> $categories */
$audienceLabels = [
    'everyone' => ['Everyone', 'text-bg-light border'],
    'seller' => ['Sellers', 'text-bg-light border'],
    'buyer' => ['Buyers', 'text-bg-light border'],
    'operator' => ['Platform operators', 'text-bg-warning'],
];
$isStaff = Auth::check() && Auth::isStaff();
?>

<div class="bg-teal-dark text-white py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <p class="text-uppercase small fw-semibold mb-2 opacity-75">Help Centre</p>
                <h1 class="h2 mb-3">How to use <?= e(site_name()) ?></h1>
                <p class="lead mb-4 opacity-90">
                    <?= (int) $article_count ?> step-by-step guides covering everything from opening an
                    account to settling an order on weighbridge weight — and, for whoever runs the
                    platform, every setting and how to configure it.
                </p>

                <form method="get" action="<?= e(url('help')) ?>" role="search" class="mb-2">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input class="form-control border-0" type="search" name="q" autocomplete="off"
                               placeholder="Search: weighment, auction, cron, GST, KYC…"
                               aria-label="Search the Help Centre">
                        <button class="btn btn-light fw-semibold px-4" type="submit">Search</button>
                    </div>
                </form>
                <p class="small mb-0 opacity-75">
                    Or read the <a class="link-light" href="<?= e(url('help/print')) ?>">whole guide on one page</a>
                    — useful for printing or working through offline.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">

    <?php if (!empty($falling_back)): ?>
        <div class="alert alert-secondary d-flex gap-2">
            <i class="bi bi-translate flex-shrink-0 mt-1" aria-hidden="true"></i>
            <div class="min-w-0">
                The Help Centre has not been translated into your language yet, so it is shown in
                English. The rest of the site stays in the language you chose.
            </div>
        </div>
    <?php endif; ?>

    <?php
    // Operators get their two sections first: someone setting the platform up has
    // a different and more urgent problem than someone browsing listings.
    $trading = array_filter($categories, static fn (array $c): bool => $c['audience'] !== 'operator');
    $operator = array_filter($categories, static fn (array $c): bool => $c['audience'] === 'operator');
    $groups = [
        ['', $trading],
        ['Running the platform', $operator],
    ];
    if ($isStaff) {
        $groups = array_reverse($groups);
    }
    ?>

    <?php foreach ($groups as [$groupTitle, $groupCategories]): ?>
        <?php if ($groupCategories === []) { continue; } ?>

        <?php if ($groupTitle !== ''): ?>
            <h2 class="h5 fw-semibold mt-5 mb-1"><?= e($groupTitle) ?></h2>
            <p class="text-muted small mb-3">
                For whoever installs and administers <?= e(site_name()) ?>, not for traders using it.
            </p>
        <?php endif; ?>

        <div class="row g-4 <?= $groupTitle === '' ? 'mb-2' : '' ?>">
            <?php foreach ($groupCategories as $category): ?>
                <?php [$audienceLabel, $audienceClass] = $audienceLabels[$category['audience']] ?? $audienceLabels['everyone']; ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="guide-cat-icon flex-shrink-0">
                                    <i class="bi <?= e($category['icon']) ?>" aria-hidden="true"></i>
                                </span>
                                <div class="min-w-0">
                                    <h3 class="h6 mb-1">
                                        <a class="text-decoration-none link-dark fw-semibold"
                                           href="<?= e(url('help/' . $category['slug'])) ?>"><?= e($category['title']) ?></a>
                                    </h3>
                                    <span class="badge <?= $audienceClass ?> small"><?= e($audienceLabel) ?></span>
                                </div>
                            </div>
                            <p class="small text-muted"><?= e($category['summary']) ?></p>

                            <ul class="list-unstyled small mb-3 guide-cat-links">
                                <?php foreach (array_slice($category['articles'], 0, 4) as $article): ?>
                                    <li class="d-flex gap-2">
                                        <i class="bi bi-chevron-right text-teal flex-shrink-0 mt-1 small" aria-hidden="true"></i>
                                        <a class="text-decoration-none min-w-0"
                                           href="<?= e(url('help/' . $category['slug'] . '/' . $article['slug'])) ?>"><?= e($article['title']) ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <a class="mt-auto small fw-semibold text-decoration-none"
                               href="<?= e(url('help/' . $category['slug'])) ?>">
                                All <?= count($category['articles']) ?> guides
                                <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="row g-4 mt-5">
        <div class="col-lg-6">
            <div class="card border-0 bg-light h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2"><i class="bi bi-signpost-split me-2 text-teal" aria-hidden="true"></i>Not sure where to start?</h2>
                    <ul class="small mb-0 guide-list">
                        <li><strong>New here?</strong> Read <a href="<?= e(url('help/getting-started/what-this-platform-does')) ?>">What this platform does</a>, then open an account.</li>
                        <li><strong>Want to sell?</strong> <a href="<?= e(url('help/selling/post-your-first-listing')) ?>">Post your first listing</a>.</li>
                        <li><strong>Want to buy?</strong> <a href="<?= e(url('help/buying/find-the-material-you-need')) ?>">Find the material you need</a>.</li>
                        <li><strong>Mid-deal?</strong> <a href="<?= e(url('help/orders-and-payments/record-the-weighment')) ?>">How the final amount is decided</a>.</li>
                        <li><strong>Setting the platform up?</strong> <a href="<?= e(url('help/operator-setup/setup-checklist')) ?>">The setup checklist</a>.</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 bg-light h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2"><i class="bi bi-question-circle me-2 text-teal" aria-hidden="true"></i>Still stuck?</h2>
                    <p class="small text-muted">
                        The <a href="<?= e(url('faq')) ?>">FAQ</a> covers the questions asked most often.
                        If your answer is not here, get in touch and tell us what you were trying to do —
                        it also tells us which guide needs improving.
                    </p>
                    <a class="btn btn-sm btn-outline-teal" href="<?= e(url('contact')) ?>">
                        <i class="bi bi-envelope me-1" aria-hidden="true"></i>Contact us
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php View::endSection(); ?>
