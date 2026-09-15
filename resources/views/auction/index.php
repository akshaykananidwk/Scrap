<?php

use App\Core\View;

View::section('content');
$query = $filters ?? [];
?>
<div class="bg-white border-bottom py-3">
    <div class="container">
        <h1 class="h4 mb-1"><i class="bi bi-hammer text-teal me-1"></i>Scrap auctions</h1>
        <p class="text-muted small mb-3">
            Forward auctions (buyers bid up) and reverse auctions (sellers bid down).
            Late bids extend the close time automatically.
        </p>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach (['live' => 'Live now', 'scheduled' => 'Upcoming', 'ended' => 'Closed', 'awarded' => 'Awarded', 'any' => 'All'] as $key => $label): ?>
                <a class="btn btn-sm <?= ($query['status'] ?? 'live') === $key ? 'btn-teal' : 'btn-outline-secondary' ?>"
                   href="<?= e(url('auctions?status=' . $key)) ?>">
                    <?= e($label) ?>
                    <?php if (isset($counts[$key])): ?><span class="badge text-bg-light ms-1"><?= (int) $counts[$key] ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="container py-4">
    <form method="get" class="row g-2 mb-4">
        <input type="hidden" name="status" value="<?= e((string) ($query['status'] ?? 'live')) ?>">
        <div class="col-md-4">
            <input type="search" name="q" class="form-control" placeholder="Search auctions"
                   value="<?= e((string) ($query['q'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <select name="category_id" class="form-select" data-auto-submit>
                <option value="">All categories</option>
                <?php foreach ($categories as $root): ?>
                    <option value="<?= (int) $root['id'] ?>" <?= (int) ($query['category_id'] ?? 0) === (int) $root['id'] ? 'selected' : '' ?>>
                        <?= e($root['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="auction_type" class="form-select" data-auto-submit>
                <option value="">Forward &amp; reverse</option>
                <option value="forward" <?= ($query['auction_type'] ?? '') === 'forward' ? 'selected' : '' ?>>Forward only</option>
                <option value="reverse" <?= ($query['auction_type'] ?? '') === 'reverse' ? 'selected' : '' ?>>Reverse only</option>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-outline-teal" type="submit">Filter</button>
        </div>
    </form>

    <?php if ($auctions->isEmpty()): ?>
        <div class="empty-state bg-white rounded shadow-sm">
            <i class="bi bi-hammer"></i>
            <h5>No auctions here right now</h5>
            <p class="mb-3">Sellers can convert any listing into a live auction in a couple of clicks.</p>
            <a class="btn btn-teal btn-sm" href="<?= e(url('dashboard/listings/create')) ?>">Create an auction</a>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($auctions->items as $auction): ?>
                <?php $isLive = $auction['status'] === 'live'; ?>
                <div class="col-6 col-lg-4 col-xl-3">
                    <div class="card h-100 border-0 shadow-sm listing-card">
                        <a href="<?= e(url('auctions/' . $auction['id'])) ?>" class="listing-thumb">
                            <?php if (!empty($auction['image'])): ?>
                                <img src="<?= e(upload_url((string) $auction['image'])) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span class="listing-thumb-placeholder"><i class="bi bi-hammer"></i></span>
                            <?php endif; ?>
                        </a>
                        <div class="card-body">
                            <div class="d-flex gap-1 mb-2">
                                <span class="badge text-bg-<?= $isLive ? 'danger' : 'secondary' ?>">
                                    <?= $isLive ? 'LIVE' : e(label((string) $auction['status'])) ?>
                                </span>
                                <?php if ($auction['auction_type'] === 'reverse'): ?>
                                    <span class="badge text-bg-primary">Reverse</span>
                                <?php endif; ?>
                            </div>
                            <h6 class="small mb-2">
                                <a class="text-decoration-none text-dark" href="<?= e(url('auctions/' . $auction['id'])) ?>">
                                    <?= e(mb_strimwidth((string) $auction['title'], 0, 56, '…')) ?>
                                </a>
                            </h6>
                            <div class="d-flex justify-content-between align-items-baseline">
                                <div>
                                    <div class="small text-muted"><?= $auction['auction_type'] === 'reverse' ? 'Lowest' : 'Current' ?></div>
                                    <div class="fw-bold text-danger"><?= e(money($auction['current_price'] ?? $auction['starting_price'])) ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="small text-muted">Bids</div>
                                    <div class="fw-semibold"><?= (int) $auction['bid_count'] ?></div>
                                </div>
                            </div>
                            <div class="small text-muted mt-2">
                                <i class="bi bi-box-seam me-1"></i><?= e(qty($auction['quantity'], (string) $auction['unit_code'])) ?>
                                <?php if (!empty($auction['city_name'])): ?>
                                    · <i class="bi bi-geo-alt"></i> <?= e((string) $auction['city_name']) ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($isLive): ?>
                                <div class="alert alert-danger py-1 px-2 small mt-2 mb-0 d-flex justify-content-between">
                                    <span><i class="bi bi-clock"></i> Ends in</span>
                                    <strong class="js-countdown" data-seconds="<?= countdown_seconds($auction['ends_at']) ?>">—</strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-4 d-flex justify-content-center"><?= $auctions->links() ?></div>
    <?php endif; ?>
</div>
<?php View::endSection(); ?>
