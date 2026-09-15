<?php

use App\Core\View;

View::section('content');
?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= e(url('rfq')) ?>">RFQs</a></li>
            <li class="breadcrumb-item active"><?= e((string) $rfq['reference']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex gap-2 mb-2 flex-wrap">
                        <?= status_badge((string) $rfq['status']) ?>
                        <span class="badge text-bg-light border">Ref <?= e((string) $rfq['reference']) ?></span>
                        <?php if ($rfq['visibility'] === 'invited'): ?>
                            <span class="badge badge-soft-warning">Invited suppliers only</span>
                        <?php endif; ?>
                    </div>
                    <h1 class="h4 mb-2"><?= e((string) $rfq['title']) ?></h1>
                    <div class="small text-muted mb-3">
                        Quotes close <strong><?= e(fmt_dt($rfq['closes_at'])) ?></strong>
                        <?php if ($rfq['closes_at']): ?>
                            · <span class="js-countdown" data-seconds="<?= countdown_seconds($rfq['closes_at']) ?>">—</span> left
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($rfq['description'])): ?>
                        <p class="small" style="white-space:pre-line"><?= e((string) $rfq['description']) ?></p>
                    <?php endif; ?>

                    <div class="row g-3 small mt-2">
                        <div class="col-md-4">
                            <div class="text-muted">Delivery to</div>
                            <div class="fw-semibold"><?= e((string) ($rfq['city_name'] ?: 'As agreed')) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted">Payment terms</div>
                            <div class="fw-semibold"><?= e(label((string) $rfq['payment_terms'])) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted">Required by</div>
                            <div class="fw-semibold"><?= $rfq['delivery_required_by'] ? e(fmt_date($rfq['delivery_required_by'])) : 'Flexible' ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3">Requested items</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr><th>#</th><th>Item</th><th>Specification</th><th class="text-end">Quantity</th><th class="text-end">Target</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $index => $item): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) $item['item_name']) ?></div>
                                        <?php if (!empty($item['material_name'])): ?>
                                            <div class="small text-muted"><?= e((string) $item['material_name']) ?><?= !empty($item['grade_name']) ? ' · ' . e((string) $item['grade_name']) : '' ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= e(mb_strimwidth((string) ($item['specification'] ?? '—'), 0, 60, '…')) ?></td>
                                    <td class="text-end"><?= e(qty($item['quantity'], (string) $item['unit_code'])) ?></td>
                                    <td class="text-end"><?= $item['target_price'] ? e(money($item['target_price'])) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="mb-2">Buyer</h6>
                    <div class="fw-semibold">
                        <?php if (!empty($rfq['business_slug'])): ?>
                            <a class="text-decoration-none" href="<?= e(url('business/' . $rfq['business_slug'])) ?>">
                                <?= e((string) ($rfq['business_name'] ?: $rfq['buyer_name'])) ?>
                            </a>
                        <?php else: ?>
                            <?= e((string) $rfq['buyer_name']) ?>
                        <?php endif; ?>
                    </div>
                    <?php if ((int) ($rfq['kyc_verified'] ?? 0) === 1): ?>
                        <span class="badge badge-soft-success kyc-badge mt-1"><i class="bi bi-patch-check-fill me-1"></i>KYC verified</span>
                    <?php endif; ?>
                    <div class="small text-muted mt-2"><?= (int) $quote_count ?> quote(s) received</div>
                </div>
            </div>

            <?php if ($is_buyer): ?>
                <a class="btn btn-teal w-100" href="<?= e(url('dashboard/rfq/' . $rfq['id'])) ?>">
                    <i class="bi bi-table me-1"></i>Compare quotes (<?= (int) $quote_count ?>)
                </a>
            <?php elseif (!$can_quote): ?>
                <div class="alert alert-secondary small mb-0">
                    <?= auth_id() ? 'This RFQ is no longer accepting quotes.' : 'Sign in to submit a quote.' ?>
                    <?php if (!auth_id()): ?>
                        <a class="btn btn-teal btn-sm w-100 mt-2" href="<?= e(url('login')) ?>">Sign in</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="mb-3"><?= $my_quote ? 'Update your quote' : 'Submit your quote' ?></h6>
                        <form method="post" action="<?= e(url('rfq/' . $rfq['id'] . '/quote')) ?>" enctype="multipart/form-data">
                            <?= csrf_field() ?>

                            <?php foreach ($items as $index => $item): ?>
                                <?php
                                $line = null;
                                foreach ($my_quote['items'] ?? [] as $quoteItem) {
                                    if ((int) $quoteItem['rfq_item_id'] === (int) $item['id']) { $line = $quoteItem; break; }
                                }
                                ?>
                                <div class="border rounded p-2 mb-2">
                                    <div class="small fw-semibold mb-2"><?= $index + 1 ?>. <?= e((string) $item['item_name']) ?></div>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <label class="form-label small mb-0">Quantity</label>
                                            <input type="number" class="form-control form-control-sm" step="0.001" min="0"
                                                   name="items[<?= (int) $item['id'] ?>][offered_quantity]"
                                                   value="<?= e((string) ($line['offered_quantity'] ?? $item['quantity'])) ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small mb-0">Rate (₹)</label>
                                            <input type="number" class="form-control form-control-sm" step="0.0001" min="0"
                                                   name="items[<?= (int) $item['id'] ?>][rate]"
                                                   value="<?= e((string) ($line['rate'] ?? '')) ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small mb-0">GST %</label>
                                            <input type="number" class="form-control form-control-sm" step="0.01" min="0" max="50"
                                                   name="items[<?= (int) $item['id'] ?>][gst_rate]"
                                                   value="<?= e((string) ($line['gst_rate'] ?? '18')) ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small mb-0">Remarks</label>
                                            <input class="form-control form-control-sm"
                                                   name="items[<?= (int) $item['id'] ?>][remarks]"
                                                   value="<?= e((string) ($line['remarks'] ?? '')) ?>">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small">Delivery charges (₹)</label>
                                    <input type="number" name="delivery_charges" class="form-control form-control-sm"
                                           step="0.01" min="0" value="<?= e((string) ($my_quote['delivery_charges'] ?? '0')) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Delivery in (days)</label>
                                    <input type="number" name="delivery_days" class="form-control form-control-sm"
                                           min="0" value="<?= e((string) ($my_quote['delivery_days'] ?? '')) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Quote valid (days)</label>
                                    <input type="number" name="validity_days" class="form-control form-control-sm"
                                           min="1" max="90" value="<?= e((string) ($my_quote['validity_days'] ?? '7')) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Payment terms</label>
                                    <input name="payment_terms" class="form-control form-control-sm"
                                           value="<?= e((string) ($my_quote['payment_terms'] ?? '')) ?>" placeholder="50% advance">
                                </div>
                            </div>

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="delivery_included" value="1" id="dl_inc"
                                    <?= !empty($my_quote['delivery_included']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="dl_inc">Delivery included in the rate</label>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small">Notes</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2"><?= e((string) ($my_quote['notes'] ?? '')) ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small">Attach a quotation PDF (optional)</label>
                                <input type="file" name="attachment" class="form-control form-control-sm" accept="application/pdf,image/*">
                            </div>

                            <button class="btn btn-teal w-100" type="submit">
                                <?= $my_quote ? 'Update quote' : 'Submit quote' ?>
                            </button>
                            <p class="small text-muted mt-2 mb-0">
                                Your quote is private — only the buyer can see it.
                            </p>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
