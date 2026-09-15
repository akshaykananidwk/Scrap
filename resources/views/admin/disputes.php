<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Disputes</h1>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Open disputes', (int) ($counts['open_disputes'] ?? 0)],
        ['All disputes', (int) ($counts['total_disputes'] ?? 0)],
        ['Open reports', (int) ($counts['open_reports'] ?? 0)],
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
    <div class="col-md-4">
        <input type="search" name="q" class="form-control" placeholder="Reference or subject"
               value="<?= e((string) ($filters['q'] ?? '')) ?>">
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select" data-auto-submit>
            <option value="">Any status</option>
            <?php foreach (['open', 'under_review', 'awaiting_response', 'resolved', 'rejected', 'closed'] as $status): ?>
                <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(label($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="category" class="form-select" data-auto-submit>
            <option value="">Any category</option>
            <?php foreach ($categories as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= ($filters['category'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-teal" type="submit">Filter</button></div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
            <tr><th>Reference</th><th>Subject</th><th>Category</th><th>Order</th>
                <th class="text-end">Claimed</th><th>Raised</th><th>Priority</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($disputes->items as $dispute): ?>
                <tr>
                    <td class="small"><?= e((string) $dispute['reference']) ?></td>
                    <td class="small text-truncate" style="max-width:220px"><?= e((string) $dispute['subject']) ?></td>
                    <td class="small"><?= e($categories[$dispute['category']] ?? label((string) $dispute['category'])) ?></td>
                    <td class="small">
                        <?php if (!empty($dispute['order_id'])): ?>
                            <a href="<?= e(url('admin/orders/' . $dispute['order_id'])) ?>">
                                <?= e((string) ($dispute['order_reference'] ?? $dispute['order_id'])) ?>
                            </a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-end small"><?= $dispute['claimed_amount'] !== null ? money($dispute['claimed_amount']) : '—' ?></td>
                    <td class="small text-nowrap"><?= e(fmt_date($dispute['created_at'])) ?></td>
                    <td class="small"><?= e(label((string) ($dispute['priority'] ?? 'normal'))) ?></td>
                    <td><?= status_badge((string) $dispute['status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-teal" href="<?= e(url('admin/disputes/' . $dispute['id'])) ?>">Open</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4 d-flex justify-content-center"><?= $disputes->links() ?></div>
<?php View::endSection(); ?>
