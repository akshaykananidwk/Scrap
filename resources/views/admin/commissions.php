<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Commission ledger</h1>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Entries', (int) ($summary['entries'] ?? 0)],
        ['Commission', money($summary['commission'] ?? 0)],
        ['GST on commission', money($summary['gst'] ?? 0)],
        ['Total billed', money($summary['total'] ?? 0)],
        ['Collected', money($summary['collected'] ?? 0)],
        ['Outstanding', money($summary['outstanding'] ?? 0)],
    ] as [$label, $value]): ?>
        <div class="col-6 col-md-2">
            <div class="stat-card text-center">
                <div class="stat-value fs-6"><?= e((string) $value) ?></div>
                <div class="stat-label"><?= e($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><h6 class="mb-0">By fee type</h6></div>
            <?php if ($by_type === []): ?>
                <div class="card-body small text-muted">No commission recorded in this period.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($by_type as $row): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted"><?= e(label((string) $row['fee_type'])) ?>
                                (<?= (int) $row['entries'] ?>)</span>
                            <strong><?= money($row['total']) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="mb-3">Filter the period</h6>
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small" for="from">From</label>
                        <input id="from" name="from" type="date" class="form-control" value="<?= e((string) ($filters['from'] ?? '')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="to">To</label>
                        <input id="to" name="to" type="date" class="form-control" value="<?= e((string) ($filters['to'] ?? '')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="status">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">Any</option>
                            <?php foreach (['pending', 'invoiced', 'paid', 'waived', 'cancelled'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>>
                                    <?= e(label($status)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid"><button class="btn btn-outline-teal" type="submit">Apply</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
            <tr><th>Reference</th><th>Type</th><th>Payable by</th><th>Order</th>
                <th class="text-end">Base</th><th class="text-end">Commission</th><th class="text-end">GST</th>
                <th class="text-end">Total</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($commissions->items as $commission): ?>
                <tr>
                    <td class="small"><?= e((string) ($commission['reference'] ?? $commission['id'])) ?></td>
                    <td class="small"><?= e(label((string) $commission['fee_type'])) ?></td>
                    <td class="small">
                        <a href="<?= e(url('admin/users/' . $commission['user_id'])) ?>">
                            <?= e((string) ($commission['user_name'] ?? '—')) ?>
                        </a>
                    </td>
                    <td class="small">
                        <?php if (!empty($commission['order_id'])): ?>
                            <a href="<?= e(url('admin/orders/' . $commission['order_id'])) ?>">
                                <?= e((string) ($commission['order_reference'] ?? $commission['order_id'])) ?>
                            </a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-end small"><?= money($commission['base_amount'] ?? 0) ?></td>
                    <td class="text-end small"><?= money($commission['amount']) ?></td>
                    <td class="text-end small"><?= money($commission['gst_amount']) ?></td>
                    <td class="text-end fw-semibold"><?= money($commission['total_amount']) ?></td>
                    <td><?= status_badge((string) $commission['status']) ?></td>
                    <td class="text-end">
                        <?php if (!in_array($commission['status'], ['paid', 'cancelled', 'waived'], true)): ?>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" type="button">Set</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php foreach (['invoiced' => 'Invoiced', 'paid' => 'Paid', 'waived' => 'Waived', 'cancelled' => 'Cancelled'] as $status => $label): ?>
                                        <li>
                                            <form method="post" action="<?= e(url('admin/commissions/' . $commission['id'] . '/status')) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="status" value="<?= e($status) ?>">
                                                <button class="dropdown-item" type="submit"><?= e($label) ?></button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4 d-flex justify-content-center"><?= $commissions->links() ?></div>
<?php View::endSection(); ?>
