<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Orders</h1>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Total orders', (int) ($counts['total'] ?? 0)],
        ['Completed', (int) ($counts['completed'] ?? 0)],
        ['Disputed', (int) ($counts['disputed'] ?? 0)],
        ['Cancelled', (int) ($counts['cancelled'] ?? 0)],
        ['GMV', money($counts['gmv'] ?? 0)],
        ['Completed value', money($counts['completed_value'] ?? 0)],
    ] as [$label, $value]): ?>
        <div class="col-6 col-md-2">
            <div class="stat-card text-center">
                <div class="stat-value fs-6"><?= e((string) $value) ?></div>
                <div class="stat-label"><?= e($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="search" name="q" class="form-control" placeholder="Reference, buyer or seller"
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
        <select name="payment_status" class="form-select" data-auto-submit>
            <option value="">Any payment state</option>
            <?php foreach ($payment_statuses as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= ($filters['payment_status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-teal" type="submit">Filter</button></div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
            <tr><th>Order</th><th>Buyer</th><th>Seller</th><th class="text-end">Amount</th>
                <th>Payment</th><th>Placed</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($orders->items as $order): ?>
                <tr>
                    <td>
                        <a class="text-decoration-none" href="<?= e(url('admin/orders/' . $order['id'])) ?>">
                            <?= e((string) $order['reference']) ?>
                        </a>
                    </td>
                    <td class="small text-truncate" style="max-width:160px">
                        <a href="<?= e(url('admin/users/' . $order['buyer_id'])) ?>">
                            <?= e((string) ($order['buyer_business'] ?? $order['buyer_name'])) ?>
                        </a>
                    </td>
                    <td class="small text-truncate" style="max-width:160px">
                        <a href="<?= e(url('admin/users/' . $order['seller_id'])) ?>">
                            <?= e((string) ($order['seller_business'] ?? $order['seller_name'])) ?>
                        </a>
                    </td>
                    <td class="text-end fw-semibold"><?= money($order['final_amount']) ?></td>
                    <td><?= status_badge((string) $order['payment_status']) ?></td>
                    <td class="small text-nowrap"><?= e(fmt_date($order['created_at'])) ?></td>
                    <td><?= status_badge((string) $order['status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-teal" href="<?= e(url('admin/orders/' . $order['id'])) ?>">Open</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4 d-flex justify-content-center"><?= $orders->links() ?></div>
<?php View::endSection(); ?>
