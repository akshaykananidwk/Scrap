<?php

use App\Core\View;

View::section('content');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h4 mb-0">My listings</h1>
    <a class="btn btn-teal" href="<?= e(url('dashboard/listings/create')) ?>"><i class="bi bi-plus-lg me-1"></i>Sell scrap</a>
</div>

<ul class="nav nav-pills flex-wrap gap-1 mb-3">
    <?php
    $tabs = ['' => 'All'] + ['active' => 'Active', 'pending' => 'Pending approval', 'sold' => 'Sold',
        'paused' => 'Paused', 'expired' => 'Expired', 'rejected' => 'Rejected', 'draft' => 'Draft'];
    $current = (string) ($filters['status'] ?? '');
    ?>
    <?php foreach ($tabs as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $current === $key ? 'active' : '' ?>"
               href="<?= e(url('dashboard/listings' . ($key !== '' ? '?status=' . $key : ''))) ?>">
                <?= e($label) ?>
                <?php if (isset($counts[$key]) && $key !== ''): ?>
                    <span class="badge text-bg-light text-dark ms-1"><?= (int) $counts[$key] ?></span>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<form method="get" class="row g-2 mb-3">
    <input type="hidden" name="status" value="<?= e($current) ?>">
    <div class="col-md-5">
        <input type="search" name="q" class="form-control" placeholder="Search my listings"
               value="<?= e((string) ($filters['q'] ?? '')) ?>">
    </div>
    <div class="col-md-3">
        <select name="listing_type" class="form-select" data-auto-submit>
            <option value="">Any sale method</option>
            <?php foreach ($types as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= ($filters['listing_type'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-teal" type="submit">Filter</button></div>
</form>

<?php if ($listings->isEmpty()): ?>
    <div class="empty-state bg-white rounded shadow-sm">
        <i class="bi bi-box-seam"></i>
        <h5>No listings here</h5>
        <p class="text-muted small">Post your first lot — it takes about two minutes.</p>
        <a class="btn btn-teal" href="<?= e(url('dashboard/listings/create')) ?>">Sell scrap</a>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Listing</th><th>Type</th><th class="text-end">Quantity</th>
                    <th class="text-end">Price</th><th class="text-end">Views</th><th>Status</th><th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($listings->items as $listing): ?>
                    <tr>
                        <td>
                            <div class="d-flex gap-2 align-items-center">
                                <?php if (!empty($listing['image'])): ?>
                                    <img src="<?= e(upload_url((string) $listing['image'])) ?>" width="44" height="44"
                                         class="rounded" style="object-fit:cover" alt="">
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <a class="text-decoration-none d-block text-truncate" style="max-width:280px"
                                       href="<?= e(url('listing/' . $listing['slug'])) ?>"><?= e((string) $listing['title']) ?></a>
                                    <span class="small text-muted"><?= e((string) $listing['reference']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="small"><?= e($types[$listing['listing_type']] ?? label((string) $listing['listing_type'])) ?></td>
                        <td class="text-end small text-nowrap"><?= e(qty($listing['quantity'], (string) ($listing['unit_code'] ?? ''))) ?></td>
                        <td class="text-end small text-nowrap">
                            <?= (float) $listing['price'] > 0 ? money($listing['price']) : '<span class="text-muted">On request</span>' ?>
                        </td>
                        <td class="text-end small"><?= (int) ($listing['view_count'] ?? 0) ?></td>
                        <td><?= status_badge((string) $listing['status']) ?></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" type="button">
                                    Actions
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?= e(url('dashboard/listings/' . $listing['id'] . '/edit')) ?>">
                                        <i class="bi bi-pencil me-2"></i>Edit</a></li>
                                    <li><a class="dropdown-item" href="<?= e(url('listing/' . $listing['slug'])) ?>">
                                        <i class="bi bi-eye me-2"></i>View public page</a></li>
                                    <?php if ($listing['listing_type'] !== 'auction'): ?>
                                        <li><a class="dropdown-item" href="<?= e(url('dashboard/listings/' . $listing['id'] . '/edit?tab=auction')) ?>">
                                            <i class="bi bi-hammer me-2"></i>Run an auction</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <?php foreach (['active' => 'Publish', 'paused' => 'Pause', 'sold' => 'Mark sold'] as $status => $label): ?>
                                        <?php if ($listing['status'] !== $status): ?>
                                            <li>
                                                <form method="post" action="<?= e(url('dashboard/listings/' . $listing['id'] . '/status')) ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="status" value="<?= e($status) ?>">
                                                    <button class="dropdown-item" type="submit"><?= e($label) ?></button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <li>
                                        <form method="post" action="<?= e(url('dashboard/listings/' . $listing['id'] . '/promote')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="tier" value="featured">
                                            <button class="dropdown-item" type="submit"><i class="bi bi-star me-2"></i>Feature listing</button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="post" action="<?= e(url('dashboard/listings/' . $listing['id'] . '/delete')) ?>"
                                              data-confirm="Delete this listing? Buyers will no longer see it.">
                                            <?= csrf_field() ?>
                                            <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash me-2"></i>Delete</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center"><?= $listings->links() ?></div>
<?php endif; ?>
<?php View::endSection(); ?>
