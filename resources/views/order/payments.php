<?php

use App\Core\View;

View::section('content');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h4 mb-0">Payments</h1>
    <form method="get">
        <select name="status" class="form-select form-select-sm" data-auto-submit>
            <option value="">All payments</option>
            <?php foreach (['pending' => 'Pending confirmation', 'confirmed' => 'Confirmed', 'failed' => 'Failed', 'refunded' => 'Refunded'] as $key => $label): ?>
                <option value="<?= e($key) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($payments->isEmpty()): ?>
    <div class="empty-state bg-white rounded shadow-sm">
        <i class="bi bi-cash-coin"></i>
        <h5>No payments recorded</h5>
        <p class="text-muted small mb-0">Payments you make or receive on orders are listed here.</p>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr><th>Reference</th><th>Order</th><th>Method</th><th>UTR / Ref</th>
                    <th class="text-end">Amount</th><th>Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($payments->items as $payment): ?>
                    <tr>
                        <td class="small"><?= e((string) $payment['reference']) ?></td>
                        <td class="small">
                            <a href="<?= e(url('dashboard/orders/' . $payment['order_id'])) ?>">
                                <?= e((string) ($payment['order_reference'] ?? '—')) ?>
                            </a>
                        </td>
                        <td class="small"><?= e($methods[$payment['method']] ?? label((string) $payment['method'])) ?></td>
                        <td class="small"><?= e((string) ($payment['utr_number'] ?? '—')) ?></td>
                        <td class="text-end fw-semibold"><?= money($payment['amount']) ?></td>
                        <td class="small text-nowrap"><?= e(fmt_dt($payment['created_at'])) ?></td>
                        <td><?= status_badge((string) $payment['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center"><?= $payments->links() ?></div>
<?php endif; ?>
<?php View::endSection(); ?>
