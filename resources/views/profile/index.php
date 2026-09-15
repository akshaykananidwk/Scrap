<?php

use App\Core\View;

View::section('content');
$query = $filters ?? [];
?>
<div class="bg-white border-bottom py-3">
    <div class="container">
        <h1 class="h4 mb-1"><?= e($heading) ?></h1>
        <p class="text-muted small mb-0">
            Traders, dealers, aggregators, recyclers, manufacturers and transporters on ScrapX.
        </p>
    </div>
</div>

<div class="container py-4">
    <form method="get" class="row g-2 mb-4">
        <div class="col-md-4">
            <input type="search" name="q" class="form-control" placeholder="Business name or city"
                   value="<?= e((string) ($query['q'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <select name="business_type" class="form-select" data-auto-submit>
                <option value="">All business types</option>
                <?php foreach ($types as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($query['business_type'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="state_id" class="form-select" data-auto-submit>
                <option value="">All India</option>
                <?php foreach ($states as $state): ?>
                    <option value="<?= (int) $state['id'] ?>" <?= (int) ($query['state_id'] ?? 0) === (int) $state['id'] ? 'selected' : '' ?>><?= e($state['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-grid"><button class="btn btn-outline-teal" type="submit">Filter</button></div>
    </form>

    <?php if ($businesses->isEmpty()): ?>
        <div class="empty-state bg-white rounded shadow-sm">
            <i class="bi bi-building"></i>
            <h5>No businesses matched</h5>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($businesses->items as $business): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex gap-2 mb-2">
                                <?php if (!empty($business['logo'])): ?>
                                    <img src="<?= e(upload_url((string) $business['logo'])) ?>" class="rounded" width="48" height="48" style="object-fit:cover" alt="">
                                <?php else: ?>
                                    <span class="avatar-initial" style="width:48px;height:48px;font-size:1.2rem">
                                        <?= e(mb_strtoupper(mb_substr((string) $business['name'], 0, 1))) ?>
                                    </span>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <h6 class="mb-0 text-truncate">
                                        <a class="text-decoration-none text-dark" href="<?= e(url('business/' . $business['slug'])) ?>">
                                            <?= e((string) $business['name']) ?>
                                        </a>
                                    </h6>
                                    <div class="small text-muted"><?= e(label((string) $business['business_type'])) ?></div>
                                </div>
                            </div>

                            <div class="small text-muted mb-2">
                                <i class="bi bi-geo-alt me-1"></i>
                                <?= e(trim(((string) ($business['city_name'] ?? '')) . ', ' . ((string) ($business['state_name'] ?? '')), ', ') ?: 'India') ?>
                            </div>

                            <div class="d-flex gap-1 flex-wrap mb-2">
                                <?php if ((int) $business['kyc_verified'] === 1): ?>
                                    <span class="badge badge-soft-success kyc-badge"><i class="bi bi-patch-check-fill me-1"></i>KYC</span>
                                <?php endif; ?>
                                <?php if ((int) $business['gst_verified'] === 1): ?>
                                    <span class="badge badge-soft-info kyc-badge">GST</span>
                                <?php endif; ?>
                            </div>

                            <div class="row g-1 text-center small border-top pt-2">
                                <div class="col-4">
                                    <div class="fw-bold"><?= (int) $business['total_listings'] ?></div>
                                    <div class="text-muted">Listings</div>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold"><?= (int) $business['completed_orders'] ?></div>
                                    <div class="text-muted">Deals</div>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold">
                                        <?php if ((int) $business['rating_count'] > 0): ?>
                                            <span class="star-rating">★</span><?= e(number_format((float) $business['rating_avg'], 1)) ?>
                                        <?php else: ?>—<?php endif; ?>
                                    </div>
                                    <div class="text-muted">Rating</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-4 d-flex justify-content-center"><?= $businesses->links() ?></div>
    <?php endif; ?>
</div>
<?php View::endSection(); ?>
