<?php

use App\Core\Auth;
use App\Core\View;

View::section('content');
?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= e(url('wanted')) ?>">Requirements</a></li>
            <li class="breadcrumb-item active"><?= e((string) $requirement['reference']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex gap-2 mb-2 flex-wrap">
                        <?= status_badge((string) $requirement['status']) ?>
                        <span class="badge text-bg-info"><?= e(label((string) $requirement['frequency'])) ?></span>
                        <span class="badge text-bg-light border">Ref <?= e((string) $requirement['reference']) ?></span>
                    </div>

                    <h1 class="h4 mb-3"><?= e((string) $requirement['title']) ?></h1>

                    <div class="row g-3 mb-3">
                        <?php foreach ([
                            ['Quantity needed', qty($requirement['quantity'], (string) $requirement['unit_code'])],
                            ['Minimum accepted', $requirement['min_quantity'] ? qty($requirement['min_quantity'], (string) $requirement['unit_code']) : 'Any'],
                            ['Maximum', $requirement['max_quantity'] ? qty($requirement['max_quantity'], (string) $requirement['unit_code']) : '—'],
                            ['Target price', $requirement['target_price'] ? money($requirement['target_price']) . ' ' . label((string) $requirement['price_basis']) : 'Open'],
                            ['Material', $requirement['material_name'] ?? $requirement['category_name']],
                            ['Grade', $requirement['grade_name'] ?: ($requirement['grade_text'] ?: 'Any')],
                            ['Frequency', label((string) $requirement['frequency'])],
                            ['Required by', $requirement['required_by'] ? fmt_date($requirement['required_by']) : 'Flexible'],
                            ['Payment terms', label((string) $requirement['payment_terms'])],
                            ['Delivery', (int) $requirement['delivery_required'] === 1 ? 'Required to buyer location' : 'Buyer can collect'],
                            ['Location', trim(((string) ($requirement['city_name'] ?? '')) . ' ' . ((string) ($requirement['state_name'] ?? ''))) ?: 'India'],
                        ] as [$label, $value]): ?>
                            <div class="col-6 col-md-4">
                                <div class="small text-muted"><?= e($label) ?></div>
                                <div class="fw-semibold small"><?= e((string) $value) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($requirement['description'])): ?>
                        <h6 class="mt-4">Details</h6>
                        <p class="small" style="white-space:pre-line"><?= e((string) $requirement['description']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($documents)): ?>
                        <h6 class="mt-4">Attachments</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($documents as $document): ?>
                                <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                                   href="<?= e(upload_url((string) $document['file_path'])) ?>">
                                    <i class="bi bi-paperclip me-1"></i><?= e(mb_strimwidth((string) $document['title'], 0, 24, '…')) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="mb-3">Buyer</h6>
                    <div class="fw-semibold">
                        <?php if (!empty($requirement['business_slug'])): ?>
                            <a class="text-decoration-none" href="<?= e(url('business/' . $requirement['business_slug'])) ?>">
                                <?= e((string) ($requirement['business_name'] ?: $requirement['buyer_name'])) ?>
                            </a>
                        <?php else: ?>
                            <?= e((string) $requirement['buyer_name']) ?>
                        <?php endif; ?>
                    </div>
                    <?php if ((int) ($requirement['kyc_verified'] ?? 0) === 1): ?>
                        <span class="badge badge-soft-success kyc-badge mt-1"><i class="bi bi-patch-check-fill me-1"></i>KYC verified</span>
                    <?php endif; ?>
                    <div class="small text-muted mt-2">
                        <?= (int) $offer_count ?> seller offer(s) so far ·
                        Posted <?= e(time_ago($requirement['created_at'])) ?>
                    </div>
                </div>
            </div>

            <?php if ($is_owner): ?>
                <a class="btn btn-teal w-100" href="<?= e(url('dashboard/requirements/' . $requirement['id'])) ?>">
                    <i class="bi bi-inbox me-1"></i>Review offers (<?= (int) $offer_count ?>)
                </a>
            <?php elseif (!Auth::check()): ?>
                <a class="btn btn-teal w-100" href="<?= e(url('login')) ?>">Sign in to submit an offer</a>
            <?php elseif ($requirement['status'] !== 'open'): ?>
                <div class="alert alert-secondary small mb-0">
                    This requirement is <?= e(label((string) $requirement['status'])) ?> and no longer accepts offers.
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="mb-3"><?= $my_offer ? 'Update your offer' : 'Submit your offer' ?></h6>
                        <form method="post" action="<?= e(url('wanted/' . $requirement['id'] . '/offer')) ?>">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label required" for="available_quantity">Quantity you can supply</label>
                                <div class="input-group">
                                    <input id="available_quantity" name="available_quantity" type="number" class="form-control"
                                           step="0.001" min="0.001" required
                                           value="<?= e((string) ($my_offer['available_quantity'] ?? $requirement['quantity'])) ?>">
                                    <span class="input-group-text"><?= e((string) $requirement['unit_code']) ?></span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required" for="offered_price">Your price</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input id="offered_price" name="offered_price" type="number" class="form-control"
                                           step="0.01" min="0.01" required
                                           value="<?= e((string) ($my_offer['offered_price'] ?? $requirement['target_price'] ?? '')) ?>">
                                    <select name="price_basis" class="form-select" style="max-width:120px">
                                        <?php foreach (['per_mt' => 'per MT', 'per_kg' => 'per KG', 'per_unit' => 'per unit', 'lot' => 'lot'] as $key => $label): ?>
                                            <option value="<?= e($key) ?>" <?= ($my_offer['price_basis'] ?? $requirement['price_basis']) === $key ? 'selected' : '' ?>>
                                                <?= e($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <?php if (!empty($my_listings)): ?>
                                <div class="mb-3">
                                    <label class="form-label" for="listing_id">Link one of your listings</label>
                                    <select id="listing_id" name="listing_id" class="form-select form-select-sm">
                                        <option value="">Not linked</option>
                                        <?php foreach ($my_listings as $listing): ?>
                                            <option value="<?= (int) $listing['id'] ?>" <?= (int) ($my_offer['listing_id'] ?? 0) === (int) $listing['id'] ? 'selected' : '' ?>>
                                                <?= e(mb_strimwidth((string) $listing['title'], 0, 40, '…')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="gst_included" value="1" id="gst_inc"
                                            <?= !empty($my_offer['gst_included']) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="gst_inc">Includes GST</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="delivery_offered" value="1" id="del_off"
                                            <?= !empty($my_offer['delivery_offered']) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="del_off">I can deliver</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="delivery_days">Delivery in (days)</label>
                                <input id="delivery_days" name="delivery_days" type="number" class="form-control form-control-sm"
                                       min="0" max="365" value="<?= e((string) ($my_offer['delivery_days'] ?? '')) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="message">Message</label>
                                <textarea id="message" name="message" class="form-control form-control-sm" rows="3"><?= e((string) ($my_offer['message'] ?? '')) ?></textarea>
                            </div>
                            <button class="btn btn-teal w-100" type="submit">
                                <?= $my_offer ? 'Update offer' : 'Send offer to buyer' ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($similar)): ?>
        <h5 class="mt-5 mb-3">Similar requirements</h5>
        <div class="row g-3">
            <?php foreach ($similar as $other): ?>
                <?php if ((int) $other['id'] === (int) $requirement['id']) { continue; } ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <h6 class="small">
                                <a class="text-decoration-none text-dark" href="<?= e(url('wanted/' . $other['slug'])) ?>">
                                    <?= e(mb_strimwidth((string) $other['title'], 0, 52, '…')) ?>
                                </a>
                            </h6>
                            <div class="small text-muted"><?= e(qty($other['quantity'], (string) $other['unit_code'])) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php View::endSection(); ?>
