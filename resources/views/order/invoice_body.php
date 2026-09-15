<?php
/**
 * Shared invoice body — rendered both inside the dashboard and on the
 * print-only layout, so there is exactly one source of truth for the format.
 */

use App\Services\SettingsService;

$isInterState = (float) $invoice['igst'] > 0;
$items = $invoice['items'] ?? [];
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h2 class="h5 mb-1"><?= e((string) SettingsService::get('site_name', 'ScrapX')) ?></h2>
        <div class="small text-muted">
            <?= nl2br(e((string) SettingsService::get('invoice_from_address', ''))) ?>
        </div>
    </div>
    <div class="text-end">
        <div class="h5 mb-1">TAX INVOICE</div>
        <div class="small">
            <strong><?= e((string) $invoice['invoice_number']) ?></strong><br>
            Date: <?= e(fmt_date($invoice['invoice_date'])) ?><br>
            <?php if (!empty($invoice['due_date'])): ?>Due: <?= e(fmt_date($invoice['due_date'])) ?><?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <div class="small text-muted text-uppercase mb-1">Supplier</div>
            <strong><?= e((string) $invoice['seller_name']) ?></strong>
            <div class="small"><?= e((string) $invoice['seller_address']) ?></div>
            <?php if (!empty($invoice['seller_gstin'])): ?>
                <div class="small">GSTIN: <strong><?= e((string) $invoice['seller_gstin']) ?></strong></div>
            <?php endif; ?>
            <?php if (!empty($invoice['seller_state_code'])): ?>
                <div class="small">State code: <?= e((string) $invoice['seller_state_code']) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <div class="small text-muted text-uppercase mb-1">Recipient</div>
            <strong><?= e((string) $invoice['buyer_name']) ?></strong>
            <div class="small"><?= e((string) $invoice['buyer_address']) ?></div>
            <?php if (!empty($invoice['buyer_gstin'])): ?>
                <div class="small">GSTIN: <strong><?= e((string) $invoice['buyer_gstin']) ?></strong></div>
            <?php endif; ?>
            <?php if (!empty($invoice['buyer_state_code'])): ?>
                <div class="small">State code: <?= e((string) $invoice['buyer_state_code']) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered align-middle">
        <thead class="table-light">
        <tr>
            <th style="width:2.5rem">#</th>
            <th>Description</th>
            <th>HSN</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Rate</th>
            <th class="text-end">Taxable</th>
            <?php if ($isInterState): ?>
                <th class="text-end">IGST</th>
            <?php else: ?>
                <th class="text-end">CGST</th>
                <th class="text-end">SGST</th>
            <?php endif; ?>
            <th class="text-end">Total</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $index => $item): ?>
            <tr>
                <td class="small"><?= $index + 1 ?></td>
                <td class="small"><?= e((string) $item['description']) ?></td>
                <td class="small"><?= e((string) ($item['hsn_code'] ?? '—')) ?></td>
                <td class="text-end small text-nowrap">
                    <?= e(dec($item['quantity'], 3)) ?> <?= e((string) ($item['unit_code'] ?? '')) ?>
                </td>
                <td class="text-end small"><?= money($item['rate']) ?></td>
                <td class="text-end small"><?= money($item['amount']) ?></td>
                <?php if ($isInterState): ?>
                    <td class="text-end small">
                        <?= money($item['igst']) ?><br><span class="text-muted"><?= e(dec($item['gst_rate'], 2)) ?>%</span>
                    </td>
                <?php else: ?>
                    <td class="text-end small">
                        <?= money($item['cgst']) ?><br><span class="text-muted"><?= e(dec((float) $item['gst_rate'] / 2, 2)) ?>%</span>
                    </td>
                    <td class="text-end small">
                        <?= money($item['sgst']) ?><br><span class="text-muted"><?= e(dec((float) $item['gst_rate'] / 2, 2)) ?>%</span>
                    </td>
                <?php endif; ?>
                <td class="text-end small fw-semibold"><?= money($item['total']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="row g-3">
    <div class="col-md-7">
        <div class="border rounded p-3 h-100">
            <div class="small text-muted text-uppercase mb-1">Amount in words</div>
            <strong class="small"><?= e((string) $invoice['amount_in_words']) ?></strong>

            <?php if (!empty($invoice['notes'])): ?>
                <hr>
                <div class="small text-muted text-uppercase mb-1">Terms</div>
                <div class="small" style="white-space:pre-line"><?= e((string) $invoice['notes']) ?></div>
            <?php endif; ?>

            <?php if ($isInterState): ?>
                <p class="small text-muted mt-2 mb-0">
                    Inter-state supply — IGST charged under Section 7 of the IGST Act.
                </p>
            <?php else: ?>
                <p class="small text-muted mt-2 mb-0">
                    Intra-state supply — CGST and SGST charged in equal halves.
                </p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-5">
        <table class="table table-sm mb-0">
            <tr><th class="fw-normal text-muted">Taxable value</th><td class="text-end"><?= money($invoice['subtotal']) ?></td></tr>
            <?php if ($isInterState): ?>
                <tr><th class="fw-normal text-muted">IGST</th><td class="text-end"><?= money($invoice['igst']) ?></td></tr>
            <?php else: ?>
                <tr><th class="fw-normal text-muted">CGST</th><td class="text-end"><?= money($invoice['cgst']) ?></td></tr>
                <tr><th class="fw-normal text-muted">SGST</th><td class="text-end"><?= money($invoice['sgst']) ?></td></tr>
            <?php endif; ?>
            <?php if ((float) $invoice['other_charges'] > 0): ?>
                <tr><th class="fw-normal text-muted">Other charges</th><td class="text-end"><?= money($invoice['other_charges']) ?></td></tr>
            <?php endif; ?>
            <?php if ((float) $invoice['round_off'] != 0.0): ?>
                <tr><th class="fw-normal text-muted">Round off</th><td class="text-end"><?= money($invoice['round_off']) ?></td></tr>
            <?php endif; ?>
            <tr class="table-light">
                <th>Grand total</th><th class="text-end"><?= money($invoice['total']) ?></th>
            </tr>
            <tr>
                <th class="fw-normal text-muted">Payment status</th>
                <td class="text-end"><?= status_badge((string) $invoice['payment_status']) ?></td>
            </tr>
        </table>

        <div class="text-end mt-5 pt-4">
            <div style="border-top:1px solid #999;display:inline-block;padding-top:.35rem;min-width:11rem">
                <span class="small">Authorised signatory</span>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($invoice['cancelled_at'])): ?>
    <div class="alert alert-danger mt-3 mb-0 small">
        This invoice was cancelled on <?= e(fmt_dt($invoice['cancelled_at'])) ?>.
    </div>
<?php endif; ?>

<p class="small text-muted mt-4 mb-0">
    This is a computer-generated invoice raised through
    <?= e((string) SettingsService::get('site_name', 'ScrapX')) ?>. The marketplace facilitates the
    transaction; the supply contract is between the supplier and the recipient named above.
</p>
