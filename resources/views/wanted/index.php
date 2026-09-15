<?php

use App\Core\View;

View::section('content');
$query = $filters ?? [];
?>
<div class="bg-white border-bottom py-3">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-1">Buyer requirements</h1>
            <p class="text-muted small mb-0">
                Buyers post what they need. Sellers respond with price, quantity and delivery.
            </p>
        </div>
        <a class="btn btn-teal" href="<?= e(url('dashboard/requirements/create')) ?>">
            <i class="bi bi-megaphone me-1"></i>Post your requirement
        </a>
    </div>
</div>

<div class="container py-4">
    <form method="get" class="row g-2 mb-4">
        <div class="col-md-4">
            <input type="search" name="q" class="form-control" placeholder="Material, grade…"
                   value="<?= e((string) ($query['q'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <select name="category_id" class="form-select" data-auto-submit>
                <option value="">All categories</option>
                <?php foreach ($categories as $root): ?>
                    <option value="<?= (int) $root['id'] ?>" <?= (int) ($query['category_id'] ?? 0) === (int) $root['id'] ? 'selected' : '' ?>><?= e($root['name']) ?></option>
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

    <?php if ($requirements->isEmpty()): ?>
        <div class="empty-state bg-white rounded shadow-sm">
            <i class="bi bi-card-checklist"></i>
            <h5>No open requirements right now</h5>
            <a class="btn btn-teal btn-sm" href="<?= e(url('dashboard/requirements/create')) ?>">Post the first one</a>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($requirements->items as $requirement): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="badge text-bg-info"><?= e(label((string) $requirement['frequency'])) ?></span>
                                <?php if (!empty($requirement['kyc_verified'])): ?>
                                    <span class="badge badge-soft-success kyc-badge"><i class="bi bi-patch-check-fill me-1"></i>Verified</span>
                                <?php endif; ?>
                            </div>
                            <h6>
                                <a class="text-decoration-none text-dark" href="<?= e(url('wanted/' . $requirement['slug'])) ?>">
                                    <?= e(mb_strimwidth((string) $requirement['title'], 0, 70, '…')) ?>
                                </a>
                            </h6>
                            <div class="small text-muted mb-2">
                                <i class="bi bi-geo-alt me-1"></i><?= e((string) ($requirement['city_name'] ?? 'India')) ?>
                                · <?= e((string) ($requirement['material_name'] ?? $requirement['category_name'])) ?>
                            </div>
                            <div class="d-flex justify-content-between small border-top pt-2">
                                <span>Needs <strong><?= e(qty($requirement['quantity'], (string) $requirement['unit_code'])) ?></strong></span>
                                <?php if (!empty($requirement['target_price'])): ?>
                                    <span class="text-teal fw-semibold">~<?= e(money($requirement['target_price'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted mt-1">
                                <?= (int) $requirement['offer_count'] ?> offers · <?= e(time_ago($requirement['created_at'])) ?>
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
        <div class="mt-4 d-flex justify-content-center"><?= $requirements->links() ?></div>
    <?php endif; ?>
</div>
<?php View::endSection(); ?>
