<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Payments</h1>

<?php if (!$online_available): ?>
    <div class="alert alert-secondary small">
        <strong>Online payment gateway is not configured.</strong>
        Payments are recorded manually (UPI, NEFT, RTGS, IMPS, cheque, cash) and confirmed by the seller
        or an administrator. Configure Razorpay keys under
        <a href="<?= e(url('admin/settings?group=payment')) ?>">Settings → Payments</a> to enable online collection.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Payments recorded', (int) ($summary['total'] ?? 0)],
        ['Confirmed value', money($summary['paid'] ?? 0)],
        ['Awaiting confirmation', money($summary['pending'] ?? 0)],
        ['Failed', money($summary['failed'] ?? 0)],
    ] as [$label, $value]): ?>
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-value fs-6"><?= e((string) $value) ?></div>
                <div class="stat-label"><?= e($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="search" name="q" class="form-control" placeholder="Reference, UTR or order"
               value="<?= e((string) ($filters['q'] ?? '')) ?>">
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select" data-auto-submit>
            <option value="">Any status</option>
            <?php foreach (['pending', 'processing', 'paid', 'failed', 'refunded'] as $status): ?>
                <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(label($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="method" class="form-select" data-auto-submit>
            <option value="">Any method</option>
            <?php foreach ($methods as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= ($filters['method'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-teal" type="submit">Filter</button></div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
            <tr><th>Reference</th><th>Order</th><th>Payer</th><th>Method</th><th>UTR</th>
                <th class="text-end">Amount</th><th>Date</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($payments->items as $payment): ?>
                <tr>
                    <td class="small"><?= e((string) $payment['reference']) ?></td>
                    <td class="small">
                        <a href="<?= e(url('admin/orders/' . $payment['order_id'])) ?>">
                            <?= e((string) ($payment['order_reference'] ?? '—')) ?>
                        </a>
                    </td>
                    <td class="small text-truncate" style="max-width:150px"><?= e((string) ($payment['payer_name'] ?? '—')) ?></td>
                    <td class="small"><?= e($methods[$payment['method']] ?? label((string) $payment['method'])) ?></td>
                    <td class="small font-monospace"><?= e((string) ($payment['utr_number'] ?? '—')) ?></td>
                    <td class="text-end fw-semibold"><?= money($payment['amount']) ?></td>
                    <td class="small text-nowrap"><?= e(fmt_dt($payment['created_at'])) ?></td>
                    <td><?= status_badge((string) $payment['status']) ?></td>
                    <td class="text-end">
                        <?php if ($payment['status'] === 'pending'): ?>
                            <form method="post" action="<?= e(url('admin/payments/' . $payment['id'] . '/confirm')) ?>"
                                  data-confirm="Confirm this payment as received?">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-teal" type="submit">Confirm</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4 d-flex justify-content-center"><?= $payments->links() ?></div>
<?php View::endSection(); ?>
