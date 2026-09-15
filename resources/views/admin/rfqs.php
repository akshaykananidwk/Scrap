<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">RFQs</h1>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['All', (int) ($counts['total'] ?? 0)],
        ['Open', (int) ($counts['open'] ?? 0)],
        ['Awarded', (int) ($counts['awarded'] ?? 0)],
    ] as [$label, $value]): ?>
        <div class="col-4">
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
            <?php foreach ($statuses as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-teal" type="submit">Filter</button></div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
            <tr><th>RFQ</th><th>Buyer</th><th class="text-end">Items</th><th class="text-end">Quotes</th>
                <th>Closes</th><th>Visibility</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rfqs->items as $rfq): ?>
                <tr>
                    <td>
                        <a class="text-decoration-none d-block text-truncate" style="max-width:240px"
                           href="<?= e(url('rfq/' . $rfq['id'])) ?>" target="_blank" rel="noopener">
                            <?= e((string) $rfq['title']) ?>
                        </a>
                        <span class="small text-muted"><?= e((string) $rfq['reference']) ?></span>
                    </td>
                    <td class="small">
                        <a href="<?= e(url('admin/users/' . $rfq['buyer_id'])) ?>">
                            <?= e((string) ($rfq['business_name'] ?? '—')) ?>
                        </a>
                    </td>
                    <td class="text-end small"><?= (int) ($rfq['item_count'] ?? 0) ?></td>
                    <td class="text-end small"><?= (int) ($rfq['quote_count'] ?? 0) ?></td>
                    <td class="small text-nowrap"><?= e(fmt_dt($rfq['closes_at'])) ?></td>
                    <td class="small"><?= e(label((string) $rfq['visibility'])) ?></td>
                    <td><?= status_badge((string) $rfq['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4 d-flex justify-content-center"><?= $rfqs->links() ?></div>
<?php View::endSection(); ?>
