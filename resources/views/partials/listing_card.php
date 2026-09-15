<?php
/** @var array $listing */

use App\Core\Auth;

$isAuction = !empty($listing['auction_id']) && ($listing['auction_status'] ?? '') === 'live';
$endsIn = $isAuction ? countdown_seconds($listing['auction_ends_at'] ?? null) : 0;
?>
<div class="card listing-card h-100 border-0 shadow-sm">
    <div class="position-relative">
        <a href="<?= e(url('listing/' . $listing['slug'])) ?>" class="d-block listing-thumb">
            <?php if (!empty($listing['image'])): ?>
                <img src="<?= e(upload_url((string) $listing['image'])) ?>" alt="<?= e((string) $listing['title']) ?>" loading="lazy">
            <?php else: ?>
                <span class="listing-thumb-placeholder"><i class="bi bi-image"></i></span>
            <?php endif; ?>
        </a>

        <div class="position-absolute top-0 start-0 p-2 d-flex flex-column gap-1">
            <?php if (!empty($listing['is_featured'])): ?>
                <span class="badge text-bg-warning"><i class="bi bi-star-fill me-1"></i>Featured</span>
            <?php endif; ?>
            <?php if ($isAuction): ?>
                <span class="badge text-bg-danger"><i class="bi bi-hammer me-1"></i>Live auction</span>
            <?php elseif (($listing['listing_type'] ?? '') === 'negotiable'): ?>
                <span class="badge text-bg-info">Negotiable</span>
            <?php elseif (($listing['listing_type'] ?? '') === 'make_offer'): ?>
                <span class="badge text-bg-secondary">Make an offer</span>
            <?php endif; ?>
        </div>

        <?php if (Auth::check()): ?>
            <button class="btn btn-sm btn-light rounded-circle position-absolute top-0 end-0 m-2 js-favorite"
                    data-type="listing" data-id="<?= (int) $listing['id'] ?>" title="Save listing" aria-label="Save listing">
                <i class="bi bi-bookmark"></i>
            </button>
        <?php endif; ?>
    </div>

    <div class="card-body d-flex flex-column">
        <div class="small text-muted mb-1 d-flex align-items-center gap-1">
            <i class="bi bi-tag"></i>
            <?= e((string) ($listing['material_name'] ?? $listing['category_name'] ?? 'Scrap')) ?>
            <?php if (!empty($listing['grade_text'])): ?>
                · <?= e((string) $listing['grade_text']) ?>
            <?php endif; ?>
        </div>

        <h6 class="card-title mb-2">
            <a class="stretched-link-title text-decoration-none text-dark" href="<?= e(url('listing/' . $listing['slug'])) ?>">
                <?= e(mb_strimwidth((string) $listing['title'], 0, 70, '…')) ?>
            </a>
        </h6>

        <div class="d-flex justify-content-between align-items-baseline mb-2">
            <div>
                <?php if ($isAuction): ?>
                    <div class="small text-muted">Current bid</div>
                    <div class="fw-bold text-danger"><?= e(money($listing['auction_current_price'] ?? 0)) ?></div>
                <?php elseif (!empty($listing['price']) && (int) ($listing['show_price'] ?? 1) === 1): ?>
                    <div class="fw-bold text-teal"><?= e(money($listing['price'])) ?></div>
                    <div class="small text-muted">
                        <?= e(money($listing['price_per_mt'] ?? 0)) ?>/MT
                    </div>
                <?php else: ?>
                    <div class="fw-semibold text-muted">Price on request</div>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <div class="small text-muted">Quantity</div>
                <div class="fw-semibold"><?= e(qty($listing['quantity'], $listing['unit_code'] ?? '')) ?></div>
            </div>
        </div>

        <div class="small text-muted mb-2">
            <i class="bi bi-geo-alt me-1"></i>
            <?= e(trim(((string) ($listing['city_name'] ?? '')) . ', ' . ((string) ($listing['state_name'] ?? '')), ', ') ?: 'India') ?>
        </div>

        <?php if ($isAuction && $endsIn > 0): ?>
            <div class="alert alert-danger py-1 px-2 small mb-2 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock me-1"></i>Ends in</span>
                <strong class="js-countdown" data-seconds="<?= $endsIn ?>">—</strong>
            </div>
        <?php endif; ?>

        <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center small">
            <span class="text-truncate" style="max-width:60%">
                <?php if (!empty($listing['kyc_verified'])): ?>
                    <i class="bi bi-patch-check-fill text-teal" title="KYC verified"></i>
                <?php endif; ?>
                <?= e(mb_strimwidth((string) ($listing['business_name'] ?? 'Seller'), 0, 24, '…')) ?>
            </span>
            <?php if (!empty($listing['auction_bid_count'])): ?>
                <span class="text-muted"><?= (int) $listing['auction_bid_count'] ?> bids</span>
            <?php else: ?>
                <span class="text-muted"><?= e(time_ago($listing['published_at'] ?? $listing['created_at'] ?? null)) ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>
