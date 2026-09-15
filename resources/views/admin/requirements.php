<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Buyer requirements</h1>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['All', (int) ($counts['total'] ?? 0)],
        ['Open', (int) ($counts['open'] ?? 0)],
        ['Fulfilled', (int) ($counts['fulfilled'] ?? 0)],
        ['New today', (int) ($counts['today'] ?? 0)],
    ] as [$label, $value]): ?>
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-value fs-6"><?= $value ?></div>
                <div class="stat-label"><?= e($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-5">
        <input type="search" name="q" class="form-control" placeholder="Title or reference"
               value="<?= e((string) ($filters['q'] ?? '')) ?>">
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select" data-auto-submit>
            <option value="">Any status</option>
            <?php foreach (['open', 'closed', 'fulfilled', 'cancelled'] as $status): ?>
                <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(label($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-teal" type="submit">Filter</button></div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
            <tr><th>Requirement</th><th>Buyer</th><th class="text-end">Quantity</th>
                <th class="text-end">Offers</th><th>Posted</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($requirements->items as $requirement): ?>
                <tr>
                    <td>
                        <a class="text-decoration-none d-block text-truncate" style="max-width:260px"
                           href="<?= e(url('wanted/' . $requirement['slug'])) ?>" target="_blank" rel="noopener">
                            <?= e((string) $requirement['title']) ?>
                        </a>
                        <span class="small text-muted"><?= e((string) $requirement['reference']) ?></span>
                    </td>
                    <td class="small">
                        <a href="<?= e(url('admin/users/' . $requirement['user_id'])) ?>">
                            <?= e((string) ($requirement['business_name'] ?? $requirement['buyer_name'] ?? '—')) ?>
                        </a>
                    </td>
                    <td class="text-end small text-nowrap"><?= e(qty($requirement['quantity'], (string) ($requirement['unit_code'] ?? ''))) ?></td>
                    <td class="text-end small"><?= (int) ($requirement['offer_count'] ?? 0) ?></td>
                    <td class="small text-nowrap"><?= e(fmt_date($requirement['created_at'])) ?></td>
                    <td><?= status_badge((string) $requirement['status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('wanted/' . $requirement['slug'])) ?>"
                           target="_blank" rel="noopener">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4 d-flex justify-content-center"><?= $requirements->links() ?></div>
<?php View::endSection(); ?>
