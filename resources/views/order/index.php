<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Orders</h1>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['All orders', (int) ($counts['all'] ?? 0), ''],
        ['Buying', (int) ($counts['buying'] ?? 0), ''],
        ['Awaiting payment', (int) ($counts['payment_pending'] ?? 0), 'payment_pending'],
        ['Completed', (int) ($counts['completed'] ?? 0), 'completed'],
    ] as [$label, $value, $status]): ?>
        <div class="col-6 col-md-3">
            <a class="text-decoration-none" href="<?= e(url('dashboard/orders' . ($status !== '' ? '?status=' . $status : ''))) ?>">
                <div class="stat-card">
                    <div class="stat-value fs-5"><?= $value ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="search" name="q" class="form-control" placeholder="Order reference or item"
               value="<?= e((string) ($filters['q'] ?? '')) ?>">
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select" data-auto-submit>
            <option value="">Any status</option>
            <?php foreach ($statuses as $key => $flow): ?>
                <option value="<?= e($key) ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= e($flow[0]) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="role" class="form-select" data-auto-submit>
            <option value="">Buying and selling</option>
            <option value="buyer" <?= $role === 'buyer' ? 'selected' : '' ?>>Orders I am buying</option>
            <option value="seller" <?= $role === 'seller' ? 'selected' : '' ?>>Orders I am selling</option>
        </select>
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-teal" type="submit">Filter</button></div>
</form>

<?php if ($orders->isEmpty()): ?>
    <div class="empty-state bg-white rounded shadow-sm">
        <i class="bi bi-bag"></i>
        <h5>No orders yet</h5>
        <p class="text-muted small">Orders appear here when an offer is accepted, an auction is awarded, or an RFQ is won.</p>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr><th>Order</th><th>Counterparty</th><th class="text-end">Amount</th>
                    <th>Payment</th><th>Placed</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($orders->items as $order): ?>
                    <tr>
                        <td>
                            <a class="text-decoration-none d-block text-truncate" style="max-width:240px"
                               href="<?= e(url('dashboard/orders/' . $order['id'])) ?>"><?= e((string) $order['reference']) ?></a>
                            <span class="small text-muted text-truncate d-block" style="max-width:240px">
                                <?= e((string) ($order['listing_title'] ?? $order['title'] ?? '—')) ?>
                            </span>
                        </td>
                        <td class="small">
                            <?= e((string) ($order['seller_business'] ?? $order['seller_name'] ?? '—')) ?>
                            <div class="text-muted">Seller</div>
                        </td>
                        <td class="text-end fw-semibold"><?= money($order['final_amount']) ?></td>
                        <td><?= status_badge((string) $order['payment_status']) ?></td>
                        <td class="small text-nowrap"><?= e(fmt_date($order['created_at'])) ?></td>
                        <td><?= status_badge((string) $order['status']) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-teal" href="<?= e(url('dashboard/orders/' . $order['id'])) ?>">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center"><?= $orders->links() ?></div>
<?php endif; ?>
<?php View::endSection(); ?>
