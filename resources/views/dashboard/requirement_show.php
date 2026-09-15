<?php

use App\Core\View;

View::section('content');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1"><?= e((string) $requirement['title']) ?></h1>
        <p class="text-muted small mb-0">
            <?= e((string) $requirement['reference']) ?> · posted <?= e(time_ago($requirement['created_at'])) ?>
            · <?= status_badge((string) $requirement['status']) ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('wanted/' . $requirement['slug'])) ?>">Public page</a>
        <div class="dropdown">
            <button class="btn btn-outline-teal dropdown-toggle" data-bs-toggle="dropdown" type="button">Change status</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach (['open' => 'Reopen', 'closed' => 'Close', 'fulfilled' => 'Mark fulfilled', 'cancelled' => 'Cancel'] as $status => $label): ?>
                    <?php if ($requirement['status'] !== $status): ?>
                        <li>
                            <form method="post" action="<?= e(url('dashboard/requirements/' . $requirement['id'] . '/status')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="<?= e($status) ?>">
                                <button class="dropdown-item" type="submit"><?= e($label) ?></button>
                            </form>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Requirement</h6></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Quantity</span>
                    <strong><?= e(qty($requirement['quantity'], (string) $requirement['unit_code'])) ?></strong>
                </li>
                <?php if ($requirement['min_quantity'] !== null || $requirement['max_quantity'] !== null): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Acceptable range</span>
                        <strong>
                            <?= $requirement['min_quantity'] !== null ? e(qty($requirement['min_quantity'])) : '—' ?>
                            to <?= $requirement['max_quantity'] !== null ? e(qty($requirement['max_quantity'])) : '—' ?>
                        </strong>
                    </li>
                <?php endif; ?>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Target price</span>
                    <strong><?= $requirement['target_price'] !== null ? money($requirement['target_price']) : 'Open' ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Frequency</span><strong><?= e(label((string) $requirement['frequency'])) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Payment terms</span><strong><?= e(label((string) $requirement['payment_terms'])) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Delivery to</span>
                    <strong><?= e(trim(((string) ($requirement['city_name'] ?? '')) . ', ' . ((string) ($requirement['state_name'] ?? '')), ', ')) ?></strong>
                </li>
                <?php if (!empty($requirement['required_by'])): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Required by</span><strong><?= e(fmt_date($requirement['required_by'])) ?></strong>
                    </li>
                <?php endif; ?>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Views</span><strong><?= (int) ($requirement['view_count'] ?? 0) ?></strong>
                </li>
            </ul>
        </div>

        <?php if (!empty($requirement['description'])): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6>Details</h6>
                    <p class="small mb-0" style="white-space:pre-line"><?= e((string) $requirement['description']) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($documents !== []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Attachments</h6></div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($documents as $document): ?>
                        <li class="list-group-item small">
                            <a href="<?= e(upload_url((string) $document['file_path'])) ?>" target="_blank" rel="noopener">
                                <i class="bi bi-paperclip me-1"></i><?= e((string) $document['title']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <h5 class="mb-3">Supplier offers (<?= count($offers) ?>)</h5>

        <?php if ($offers === []): ?>
            <div class="empty-state bg-white rounded shadow-sm">
                <i class="bi bi-inbox"></i>
                <p class="mb-0">No offers yet. Matching sellers have been notified.</p>
            </div>
        <?php else: ?>
            <?php foreach ($offers as $offer): ?>
                <div class="card border-0 shadow-sm mb-3 <?= $offer['status'] === 'accepted' ? 'border-start border-4 border-success' : '' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                            <div>
                                <h6 class="mb-1">
                                    <?= e((string) ($offer['business_name'] ?: $offer['seller_name'])) ?>
                                    <?php if ((int) ($offer['seller_kyc'] ?? 0) === 1 || ($offer['kyc_status'] ?? '') === 'verified'): ?>
                                        <i class="bi bi-patch-check-fill text-teal" title="KYC verified"></i>
                                    <?php endif; ?>
                                </h6>
                                <div class="small text-muted"><?= e(time_ago($offer['created_at'])) ?></div>
                            </div>
                            <div class="text-end">
                                <div class="h5 mb-0"><?= money($offer['offered_price']) ?></div>
                                <div class="small text-muted"><?= e(qty($offer['available_quantity'], (string) $requirement['unit_code'])) ?></div>
                            </div>
                        </div>

                        <?php if (!empty($offer['message'])): ?>
                            <p class="small mt-2 mb-2"><?= e((string) $offer['message']) ?></p>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
                            <?= status_badge((string) $offer['status']) ?>
                            <?php if (in_array($offer['status'], ['pending', 'shortlisted'], true) && $requirement['status'] === 'open'): ?>
                                <div class="d-flex gap-1">
                                    <?php if ($offer['status'] !== 'shortlisted'): ?>
                                        <form method="post" action="<?= e(url('dashboard/requirements/offers/' . $offer['id'] . '/shortlist')) ?>">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">Shortlist</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="<?= e(url('dashboard/requirements/offers/' . $offer['id'] . '/reject')) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Reject</button>
                                    </form>
                                    <form method="post" action="<?= e(url('dashboard/requirements/offers/' . $offer['id'] . '/accept')) ?>"
                                          data-confirm="Accept this offer? An order is created and the requirement is closed.">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-teal" type="submit">Accept &amp; create order</button>
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
