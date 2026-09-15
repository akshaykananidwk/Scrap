<?php

use App\Core\View;

View::section('content');
$isOpen = in_array($rfq['status'], ['open', 'closing_soon'], true);
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1"><?= e((string) $rfq['title']) ?></h1>
        <p class="text-muted small mb-0">
            <?= e((string) $rfq['reference']) ?> · closes <?= e(fmt_dt($rfq['closes_at'])) ?>
            · <?= status_badge((string) $rfq['status']) ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('rfq/' . $rfq['id'])) ?>">Public page</a>
        <?php if ($isOpen): ?>
            <form method="post" action="<?= e(url('dashboard/rfq/' . $rfq['id'] . '/close')) ?>"
                  data-confirm="Close this RFQ? Sellers can no longer submit quotes.">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger" type="submit">Close RFQ</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Requested items</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Item</th><th class="text-end">Quantity</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="small">
                                <?= e((string) $item['item_name']) ?>
                                <?php if (!empty($item['specification'])): ?>
                                    <div class="text-muted"><?= e((string) $item['specification']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end small text-nowrap"><?= e(qty($item['quantity'], (string) ($item['unit_code'] ?? ''))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Invited suppliers (<?= count($invites) ?>)</h6></div>
            <?php if ($invites === []): ?>
                <div class="card-body small text-muted">No direct invitations sent.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($invites as $invite): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center small">
                            <span class="text-truncate"><?= e((string) ($invite['business_name'] ?: $invite['full_name'])) ?></span>
                            <?= status_badge((string) $invite['status']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php if ($isOpen && $suggested_sellers !== []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Invite more suppliers</h6></div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('dashboard/rfq/' . $rfq['id'] . '/invite')) ?>">
                        <?= csrf_field() ?>
                        <div style="max-height:220px;overflow:auto" class="mb-2">
                            <?php foreach ($suggested_sellers as $seller): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="seller_ids[]"
                                           value="<?= (int) $seller['id'] ?>" id="inv-<?= (int) $seller['id'] ?>">
                                    <label class="form-check-label small" for="inv-<?= (int) $seller['id'] ?>">
                                        <?= e((string) ($seller['business_name'] ?: $seller['full_name'])) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button class="btn btn-sm btn-teal w-100" type="submit">Send invitations</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <h5 class="mb-3">Quotes received (<?= count($quotes) ?>)</h5>

        <?php if ($quotes === []): ?>
            <div class="empty-state bg-white rounded shadow-sm">
                <i class="bi bi-file-earmark-text"></i>
                <p class="mb-0">No quotes yet. They are listed cheapest first as they arrive.</p>
            </div>
        <?php else: ?>
            <?php foreach ($quotes as $index => $quote): ?>
                <div class="card border-0 shadow-sm mb-3 <?= (int) ($rfq['awarded_quote_id'] ?? 0) === (int) $quote['id'] ? 'border-start border-4 border-success' : '' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                            <div>
                                <h6 class="mb-1">
                                    <?php if ($index === 0): ?><span class="badge badge-soft-success me-1">Lowest</span><?php endif; ?>
                                    <?= e((string) ($quote['business_name'] ?: $quote['seller_name'])) ?>
                                    <?php if ((int) ($quote['kyc_verified'] ?? 0) === 1): ?>
                                        <i class="bi bi-patch-check-fill text-teal"></i>
                                    <?php endif; ?>
                                </h6>
                                <div class="small text-muted">
                                    <?= e((string) ($quote['city_name'] ?? '—')) ?>
                                    <?php if ((int) ($quote['rating_count'] ?? 0) > 0): ?>
                                        · <span class="star-rating">★</span><?= e(number_format((float) $quote['rating_avg'], 1)) ?>
                                    <?php endif; ?>
                                    · <?= e(time_ago($quote['created_at'])) ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="h5 mb-0"><?= money($quote['grand_total']) ?></div>
                                <div class="small text-muted">
                                    valid till <?= e($quote['expires_at'] ? fmt_date($quote['expires_at']) : '—') ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($quote['items'])): ?>
                            <div class="table-responsive mt-3">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                    <tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($quote['items'] as $item): ?>
                                        <tr>
                                            <td class="small"><?= e((string) $item['item_name']) ?></td>
                                            <td class="text-end small"><?= e(qty($item['offered_quantity'], (string) ($item['unit_code'] ?? ''))) ?></td>
                                            <td class="text-end small"><?= money($item['rate']) ?></td>
                                            <td class="text-end small"><?= money($item['amount']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($quote['notes'])): ?>
                            <p class="small text-muted mt-2 mb-0"><?= e((string) $quote['notes']) ?></p>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                            <?= status_badge((string) $quote['status']) ?>
                            <?php if ($rfq['status'] !== 'awarded'): ?>
                                <div class="d-flex gap-1">
                                    <form method="post" action="<?= e(url('dashboard/rfq/quotes/' . $quote['id'] . '/shortlist')) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Shortlist</button>
                                    </form>
                                    <form method="post" action="<?= e(url('dashboard/rfq/' . $rfq['id'] . '/award')) ?>"
                                          data-confirm="Award this RFQ to this supplier? An order is created.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                                        <button class="btn btn-sm btn-teal" type="submit">Award &amp; create order</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection(); ?>
