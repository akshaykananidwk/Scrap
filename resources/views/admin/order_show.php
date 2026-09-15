<?php

use App\Core\View;
use App\Models\Order;

View::section('content');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1">Order <?= e((string) $order['reference']) ?></h1>
        <p class="text-muted small mb-0">
            Placed <?= e(fmt_dt($order['created_at'])) ?>
            · <?= status_badge((string) $order['status']) ?>
            · payment <?= status_badge((string) $order['payment_status']) ?>
        </p>
    </div>
    <?php if ($next_statuses !== []): ?>
        <form method="post" action="<?= e(url('admin/orders/' . $order['id'] . '/status')) ?>" class="d-flex gap-2">
            <?= csrf_field() ?>
            <select name="status" class="form-select form-select-sm" style="width:auto">
                <?php foreach ($next_statuses as $status): ?>
                    <option value="<?= e($status) ?>"><?= e(Order::FLOW[$status][0] ?? label($status)) ?></option>
                <?php endforeach; ?>
            </select>
            <input name="note" class="form-control form-control-sm" placeholder="Reason (audited)">
            <button class="btn btn-sm btn-outline-teal" type="submit">Override status</button>
        </form>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Items &amp; amounts</h6></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr><th>Description</th><th class="text-end">Quantity</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="small"><?= e((string) $item['description']) ?></td>
                            <td class="text-end small"><?= e(qty($item['quantity'], (string) ($item['unit_code'] ?? ''))) ?></td>
                            <td class="text-end small"><?= money($item['rate']) ?></td>
                            <td class="text-end small"><?= money($item['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr><th colspan="3" class="text-end fw-normal text-muted">Subtotal</th><th class="text-end"><?= money($order['subtotal']) ?></th></tr>
                    <tr><th colspan="3" class="text-end fw-normal text-muted">GST @ <?= e(dec($order['gst_rate'], 2)) ?>%</th>
                        <th class="text-end"><?= money($order['gst_amount']) ?></th></tr>
                    <tr class="table-light"><th colspan="3" class="text-end">Total</th><th class="text-end"><?= money($order['final_amount']) ?></th></tr>
                    <tr><th colspan="3" class="text-end fw-normal text-muted">Paid</th><th class="text-end text-success"><?= money($total_paid) ?></th></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Weighments</h6></div>
            <?php if ($weighments === []): ?>
                <div class="card-body small text-muted">None recorded.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                        <tr><th class="text-end">Expected</th><th class="text-end">Actual</th><th class="text-end">Diff</th>
                            <th class="text-end">Deduction</th><th class="text-end">Settled</th><th>Weighbridge</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($weighments as $weighment): ?>
                            <tr>
                                <td class="text-end small"><?= e(dec($weighment['expected_weight_kg'], 3)) ?></td>
                                <td class="text-end small"><?= e(dec($weighment['actual_weight_kg'], 3)) ?></td>
                                <td class="text-end small"><?= e(dec($weighment['difference_kg'], 3)) ?>
                                    (<?= e(dec($weighment['difference_percent'], 2)) ?>%)</td>
                                <td class="text-end small"><?= money($weighment['deduction_amount']) ?></td>
                                <td class="text-end small"><?= money($weighment['settled_amount']) ?></td>
                                <td class="small"><?= e((string) ($weighment['weighbridge_name'] ?? '—')) ?></td>
                                <td><?= status_badge((string) $weighment['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Payments</h6></div>
            <?php if ($payments === []): ?>
                <div class="card-body small text-muted">No payments recorded.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light"><tr><th>Reference</th><th>Method</th><th>UTR</th>
                            <th class="text-end">Amount</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td class="small"><?= e((string) $payment['reference']) ?></td>
                                <td class="small"><?= e(label((string) $payment['method'])) ?></td>
                                <td class="small"><?= e((string) ($payment['utr_number'] ?? '—')) ?></td>
                                <td class="text-end"><?= money($payment['amount']) ?></td>
                                <td><?= status_badge((string) $payment['status']) ?></td>
                                <td class="text-end">
                                    <?php if ($payment['status'] === 'pending'): ?>
                                        <form method="post" action="<?= e(url('admin/payments/' . $payment['id'] . '/confirm')) ?>">
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
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Deliveries</h6></div>
            <?php if ($deliveries === []): ?>
                <div class="card-body small text-muted">No vehicle assigned.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($deliveries as $delivery): ?>
                        <li class="list-group-item small d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= e((string) $delivery['vehicle_number']) ?></strong>
                                <span class="text-muted">· <?= e((string) ($delivery['driver_name'] ?? '—')) ?></span>
                                <?php if (!empty($delivery['eway_bill_number'])): ?>
                                    <div class="text-muted">E-way bill <?= e((string) $delivery['eway_bill_number']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?= status_badge((string) $delivery['status']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Parties</h6></div>
            <div class="card-body small">
                <div class="mb-3">
                    <div class="text-muted">Seller</div>
                    <a href="<?= e(url('admin/users/' . $order['seller_id'])) ?>">
                        <?= e((string) ($order['seller_business'] ?? $order['seller_name'])) ?>
                    </a>
                    <div><?= e((string) ($order['seller_mobile'] ?? '')) ?></div>
                </div>
                <div>
                    <div class="text-muted">Buyer</div>
                    <a href="<?= e(url('admin/users/' . $order['buyer_id'])) ?>">
                        <?= e((string) ($order['buyer_business'] ?? $order['buyer_name'])) ?>
                    </a>
                    <div><?= e((string) ($order['buyer_mobile'] ?? '')) ?></div>
                </div>
            </div>
        </div>

        <?php if ($commissions !== []): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><h6 class="mb-0">Commission</h6></div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($commissions as $commission): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted"><?= e(label((string) $commission['fee_type'])) ?></span>
                            <span><?= money($commission['total_amount']) ?> <?= status_badge((string) $commission['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($invoices !== []): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><h6 class="mb-0">Invoices</h6></div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($invoices as $invoice): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <a href="<?= e(url('admin/invoices/' . $invoice['id'])) ?>"><?= e((string) $invoice['invoice_number']) ?></a>
                            <span><?= money($invoice['total']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Status history</h6></div>
            <ul class="list-group list-group-flush" style="max-height:340px;overflow:auto">
                <?php foreach ($history as $entry): ?>
                    <li class="list-group-item small">
                        <?= status_badge((string) $entry['to_status']) ?>
                        <span class="text-muted ms-1"><?= e(fmt_dt($entry['created_at'])) ?></span>
                        <?php if (!empty($entry['note'])): ?>
                            <div><?= e((string) $entry['note']) ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
