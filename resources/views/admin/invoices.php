<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Invoices</h1>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Invoices raised', (int) ($totals['total'] ?? 0)],
        ['Invoiced value', money($totals['value'] ?? 0)],
        ['Paid value', money($totals['paid'] ?? 0)],
    ] as [$label, $value]): ?>
        <div class="col-4">
            <div class="stat-card text-center">
                <div class="stat-value fs-6"><?= e((string) $value) ?></div>
                <div class="stat-label"><?= e($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="search" name="q" class="form-control" placeholder="Invoice number, GSTIN or party"
               value="<?= e((string) ($filters['q'] ?? '')) ?>">
    </div>
    <div class="col-md-3">
        <select name="payment_status" class="form-select" data-auto-submit>
            <option value="">Any payment state</option>
            <?php foreach (['pending', 'paid', 'partial', 'cancelled'] as $status): ?>
                <option value="<?= e($status) ?>" <?= ($filters['payment_status'] ?? '') === $status ? 'selected' : '' ?>>
                    <?= e(label($status)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-teal" type="submit">Filter</button></div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
            <tr><th>Invoice</th><th>Date</th><th>Supplier</th><th>Recipient</th>
                <th class="text-end">Taxable</th><th class="text-end">Tax</th><th class="text-end">Total</th>
                <th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($invoices->items as $invoice): ?>
                <tr>
                    <td class="small">
                        <a href="<?= e(url('admin/invoices/' . $invoice['id'])) ?>"><?= e((string) $invoice['invoice_number']) ?></a>
                    </td>
                    <td class="small text-nowrap"><?= e(fmt_date($invoice['invoice_date'])) ?></td>
                    <td class="small text-truncate" style="max-width:160px"><?= e((string) $invoice['seller_name']) ?></td>
                    <td class="small text-truncate" style="max-width:160px"><?= e((string) $invoice['buyer_name']) ?></td>
                    <td class="text-end small"><?= money($invoice['subtotal']) ?></td>
                    <td class="text-end small">
                        <?= money((float) $invoice['cgst'] + (float) $invoice['sgst'] + (float) $invoice['igst']) ?>
                        <div class="text-muted"><?= (float) $invoice['igst'] > 0 ? 'IGST' : 'CGST+SGST' ?></div>
                    </td>
                    <td class="text-end fw-semibold"><?= money($invoice['total']) ?></td>
                    <td><?= status_badge((string) $invoice['payment_status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-teal" href="<?= e(url('admin/invoices/' . $invoice['id'])) ?>">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4 d-flex justify-content-center"><?= $invoices->links() ?></div>
<?php View::endSection(); ?>
