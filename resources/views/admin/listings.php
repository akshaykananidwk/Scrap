<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Listings</h1>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['All', (int) ($counts['total'] ?? 0), ''],
        ['Active', (int) ($counts['active'] ?? 0), 'active'],
        ['Pending', (int) ($counts['pending'] ?? 0), 'pending'],
        ['Sold', (int) ($counts['sold'] ?? 0), 'sold'],
        ['Expired', (int) ($counts['expired'] ?? 0), 'expired'],
        ['New today', (int) ($counts['today'] ?? 0), ''],
    ] as [$label, $value, $status]): ?>
        <div class="col-4 col-md-2">
            <a class="text-decoration-none" href="<?= e(url('admin/listings' . ($status !== '' ? '?status=' . $status : ''))) ?>">
                <div class="stat-card text-center">
                    <div class="stat-value fs-6"><?= $value ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="search" name="q" class="form-control" placeholder="Title, reference or seller"
               value="<?= e((string) ($filters['q'] ?? '')) ?>">
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select" data-auto-submit>
            <option value="">Any status</option>
            <?php foreach (['active', 'pending', 'paused', 'sold', 'expired', 'rejected', 'draft'] as $status): ?>
                <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(label($status)) ?></option>
            <?php endforeach; ?>
        </select>
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

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
            <tr><th>Listing</th><th>Seller</th><th class="text-end">Quantity</th>
                <th class="text-end">Price</th><th>Posted</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($listings->items as $listing): ?>
                <tr>
                    <td>
                        <a class="text-decoration-none d-block text-truncate" style="max-width:260px"
                           href="<?= e(url('listing/' . $listing['slug'])) ?>" target="_blank" rel="noopener">
                            <?= e((string) $listing['title']) ?>
                        </a>
                        <span class="small text-muted"><?= e((string) $listing['reference']) ?>
                            · <?= e(label((string) $listing['listing_type'])) ?></span>
                    </td>
                    <td class="small">
                        <a href="<?= e(url('admin/users/' . $listing['user_id'])) ?>">
                            <?= e((string) ($listing['business_name'] ?? $listing['seller_name'] ?? '—')) ?>
                        </a>
                    </td>
                    <td class="text-end small text-nowrap"><?= e(qty($listing['quantity'], (string) ($listing['unit_code'] ?? ''))) ?></td>
                    <td class="text-end small text-nowrap">
                        <?= (float) $listing['price'] > 0 ? money($listing['price']) : '—' ?>
                    </td>
                    <td class="small text-nowrap"><?= e(fmt_date($listing['created_at'])) ?></td>
                    <td><?= status_badge((string) $listing['status']) ?></td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" type="button">Act</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if ($listing['status'] !== 'active'): ?>
                                    <li>
                                        <form method="post" action="<?= e(url('admin/listings/' . $listing['id'] . '/approve')) ?>">
                                            <?= csrf_field() ?>
                                            <button class="dropdown-item" type="submit"><i class="bi bi-check2 me-2"></i>Approve</button>
                                        </form>
                                    </li>
                                <?php endif; ?>
                                <li>
                                    <form method="post" action="<?= e(url('admin/listings/' . $listing['id'] . '/reject')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="reason" value="Does not meet listing guidelines">
                                        <button class="dropdown-item" type="submit"><i class="bi bi-x me-2"></i>Reject</button>
                                    </form>
                                </li>
                                <li>
                                    <form method="post" action="<?= e(url('admin/listings/' . $listing['id'] . '/feature')) ?>">
                                        <?= csrf_field() ?>
                                        <button class="dropdown-item" type="submit">
                                            <i class="bi bi-star me-2"></i><?= (int) $listing['is_featured'] === 1 ? 'Unfeature' : 'Feature' ?>
                                        </button>
                                    </form>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="post" action="<?= e(url('admin/listings/' . $listing['id'] . '/delete')) ?>"
                                          data-confirm="Delete this listing?">
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
<?php View::endSection(); ?>
