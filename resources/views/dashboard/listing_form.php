<?php

use App\Core\View;
use App\Services\SettingsService;

View::section('content');

$isEdit = $listing !== null;
$l = $listing ?? [];
$tab = $isEdit ? ($tab ?? 'details') : 'details';
$val = static fn (string $key, string $default = ''): string => (string) old($key, $l[$key] ?? $default);
$action = $isEdit ? url('dashboard/listings/' . $l['id']) : url('dashboard/listings');

$stepTitles = [
    'Category', 'Material & grade', 'Quantity & unit', 'Price & GST', 'Condition & source',
    'Location & pickup', 'Photos & documents', 'Terms & inspection', 'Review & publish',
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><?= $isEdit ? 'Edit listing' : 'Sell your scrap' ?></h1>
    <?php if ($isEdit): ?>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('listing/' . $l['slug'])) ?>">View public page</a>
            <?= status_badge((string) $l['status']) ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($business === null): ?>
    <div class="alert alert-warning">
        Add your business details first so buyers know who they are dealing with —
        <a href="<?= e(url('dashboard/business')) ?>">set up your business profile</a>.
    </div>
<?php endif; ?>

<?php if ($requires_approval && !$isEdit): ?>
    <div class="alert alert-info small">
        New listings are reviewed by our team before they appear publicly. Verified sellers are approved automatically.
    </div>
<?php endif; ?>

<?php if ($isEdit): ?>
    <ul class="nav nav-tabs mb-3">
        <?php foreach (['details' => 'Details', 'media' => 'Photos & documents', 'auction' => 'Auction'] as $key => $label): ?>
            <li class="nav-item">
                <a class="nav-link <?= $tab === $key ? 'active' : '' ?>"
                   href="<?= e(url('dashboard/listings/' . $l['id'] . '/edit?tab=' . $key)) ?>"><?= e($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if (!$isEdit || $tab === 'details'): ?>

<?php if (!$isEdit): ?>
    <div class="wizard-nav d-flex flex-wrap gap-2 mb-3">
        <?php foreach ($stepTitles as $i => $stepTitle): ?>
            <div class="step<?= $i === 0 ? ' active' : '' ?>">
                <span class="step-index"><?= $i + 1 ?></span>
                <span class="step-title d-none d-md-inline"><?= e($stepTitle) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form id="<?= $isEdit ? 'listing-edit' : 'sell-wizard' ?>" method="post" action="<?= e($action) ?>"
      enctype="multipart/form-data" class="<?= $isEdit ? '' : 'wizard' ?>">
    <?= csrf_field() ?>

    <!-- Step 1 — Category -->
    <section class="wizard-step active card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h6 class="text-teal mb-3">1. What are you selling?</h6>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label required" for="title">Listing title</label>
                    <input id="title" name="title" class="form-control form-control-lg" required minlength="8" maxlength="190"
                           placeholder="e.g. 20 MT MS Heavy Melting Scrap — Bhavnagar" value="<?= e($val('title')) ?>">
                    <div class="form-text">Include the material, quantity and location — those listings get the most enquiries.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="listing_type">Sale method</label>
                    <select id="listing_type" name="listing_type" class="form-select" required>
                        <?php foreach ($types as $key => $label): ?>
                            <?php if (in_array($key, ['wanted', 'rfq'], true)) { continue; } ?>
                            <option value="<?= e($key) ?>" <?= $val('listing_type', 'fixed') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-select" required data-load-materials="#material_id">
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $category): ?>
                            <optgroup label="<?= e((string) $category['name']) ?>">
                                <option value="<?= (int) $category['id'] ?>" <?= (int) $val('category_id') === (int) $category['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $category['name']) ?> (general)
                                </option>
                                <?php foreach ($category['children'] ?? [] as $child): ?>
                                    <option value="<?= (int) $child['id'] ?>"
                                        <?= (int) $val('category_id') === (int) $child['id'] || (int) $val('subcategory_id') === (int) $child['id'] ? 'selected' : '' ?>>
                                        <?= e((string) $child['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3" maxlength="10000"
                              placeholder="Condition, origin, loading facility, inspection timings…"><?= e($val('description')) ?></textarea>
                </div>
            </div>
        </div>
    </section>

    <!-- Step 2 — Material & grade -->
    <section class="wizard-step card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h6 class="text-teal mb-3">2. Material and grade</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="material_id">Material</label>
                    <select id="material_id" name="material_id" class="form-select" data-load-grades="#grade_id">
                        <option value="">Select material</option>
                        <?php foreach ($materials as $material): ?>
                            <option value="<?= (int) $material['id'] ?>"
                                    data-unit-id="<?= (int) ($material['default_unit_id'] ?? 0) ?>"
                                    data-gst-rate="<?= e((string) ($material['default_gst_rate'] ?? '18')) ?>"
                                <?= (int) $val('material_id') === (int) $material['id'] ? 'selected' : '' ?>>
                                <?= e((string) $material['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="grade_id">Grade</label>
                    <select id="grade_id" name="grade_id" class="form-select">
                        <option value="">Select grade (optional)</option>
                        <?php foreach ($grades as $grade): ?>
                            <option value="<?= (int) $grade['id'] ?>" <?= (int) $val('grade_id') === (int) $grade['id'] ? 'selected' : '' ?>>
                                <?= e((string) $grade['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="grade_text">Or describe the grade</label>
                    <input id="grade_text" name="grade_text" class="form-control" value="<?= e($val('grade_text')) ?>"
                           placeholder="e.g. HMS 1&2 (80:20)">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="hsn_code">HSN code</label>
                    <input id="hsn_code" name="hsn_code" class="form-control" maxlength="10" value="<?= e($val('hsn_code')) ?>">
                    <div class="form-text">Used on the GST invoice.</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Step 3 — Quantity -->
    <section class="wizard-step card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h6 class="text-teal mb-3">3. Quantity available</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label required" for="quantity">Quantity</label>
                    <input id="quantity" name="quantity" type="number" step="0.001" min="0.001" class="form-control form-control-lg"
                           required value="<?= e($val('quantity')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="unit_id">Unit</label>
                    <select id="unit_id" name="unit_id" class="form-select form-select-lg" required>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= (int) $unit['id'] ?>"
                                    data-unit="<?= e((string) $unit['code']) ?>"
                                <?= (int) $val('unit_id') === (int) $unit['id'] ? 'selected' : '' ?>>
                                <?= e((string) $unit['name']) ?> (<?= e((string) $unit['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="min_order_quantity">Minimum order quantity</label>
                    <input id="min_order_quantity" name="min_order_quantity" type="number" step="0.001" min="0"
                           class="form-control" value="<?= e($val('min_order_quantity')) ?>">
                </div>
            </div>
            <p class="small text-muted mt-3 mb-0">
                Final settlement is done on the <strong>actual weighbridge weight</strong>, not this figure —
                record the weighment on the order once the truck is weighed.
            </p>
        </div>
    </section>

    <!-- Step 4 — Price & GST -->
    <section class="wizard-step card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h6 class="text-teal mb-3">4. Price and GST</h6>

            <div id="auction-note" class="alert alert-info small d-none">
                You picked <strong>Auction</strong>. Set the starting price, increment and timings on the
                auction step after saving — the price below is only an indicative reserve.
            </div>

            <div class="row g-3" id="price-block">
                <div class="col-md-4">
                    <label class="form-label" for="price">Price</label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input id="price" name="price" type="number" step="0.01" min="0" class="form-control"
                               value="<?= e($val('price')) ?>">
                    </div>
                    <div class="form-text">Leave blank for "price on request".</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="price_basis">Price basis</label>
                    <select id="price_basis" name="price_basis" class="form-select">
                        <?php foreach (['per_kg' => 'Per kilogram', 'per_mt' => 'Per metric tonne', 'per_unit' => 'Per unit', 'lot' => 'For the whole lot'] as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $val('price_basis', 'per_mt') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="gst_rate">GST rate (%)</label>
                    <input id="gst_rate" name="gst_rate" type="number" step="0.01" min="0" max="50" class="form-control"
                           value="<?= e($val('gst_rate', '18')) ?>">
                </div>
                <div class="col-12 d-flex flex-wrap gap-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_negotiable" name="is_negotiable" value="1"
                            <?= old('is_negotiable', $l['is_negotiable'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_negotiable">Price is negotiable</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="show_price" name="show_price" value="1"
                            <?= old('show_price', $l['show_price'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_price">Show the price publicly</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="gst_applicable" name="gst_applicable" value="1"
                            <?= old('gst_applicable', $l['gst_applicable'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="gst_applicable">GST applicable</label>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Step 5 — Condition & source -->
    <section class="wizard-step card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h6 class="text-teal mb-3">5. Condition and source</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="material_condition">Condition</label>
                    <select id="material_condition" name="material_condition" class="form-select">
                        <?php foreach ($conditions as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $val('material_condition', 'as_is') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="material_source">Source</label>
                    <select id="material_source" name="material_source" class="form-select">
                        <?php foreach ($sources as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $val('material_source', 'industrial') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </section>

    <!-- Step 6 — Location -->
    <section class="wizard-step card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h6 class="text-teal mb-3">6. Where is the material?</h6>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="pickup_address">Pickup address</label>
                    <input id="pickup_address" name="pickup_address" class="form-control" value="<?= e($val('pickup_address')) ?>">
                    <div class="form-text">Shown only to buyers you deal with; the public page shows the city.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="pincode">Pincode</label>
                    <input id="pincode" name="pincode" class="form-control" maxlength="6" data-pincode-lookup value="<?= e($val('pincode')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="state_id">State</label>
                    <select id="state_id" name="state_id" class="form-select" required data-load-cities="#city_id">
                        <option value="">Select state</option>
                        <?php foreach ($states as $state): ?>
                            <option value="<?= (int) $state['id'] ?>" <?= (int) $val('state_id') === (int) $state['id'] ? 'selected' : '' ?>>
                                <?= e((string) $state['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="city_id">City</label>
                    <select id="city_id" name="city_id" class="form-select">
                        <option value="">Select city</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= (int) $city['id'] ?>" <?= (int) $val('city_id') === (int) $city['id'] ? 'selected' : '' ?>>
                                <?= e((string) $city['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex flex-wrap gap-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="pickup_available" name="pickup_available" value="1"
                            <?= old('pickup_available', $l['pickup_available'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="pickup_available">Buyer can pick up</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="delivery_available" name="delivery_available" value="1"
                            <?= old('delivery_available', $l['delivery_available'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="delivery_available">I can deliver</label>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Step 7 — Media -->
    <section class="wizard-step card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h6 class="text-teal mb-3">7. Photos and documents</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="images">Photos (up to <?= (int) $max_images ?>)</label>
                    <input id="images" name="images[]" type="file" class="form-control" multiple accept="image/jpeg,image/png,image/webp"
                           data-preview="#image-preview">
                    <div class="form-text">JPG, PNG or WebP. Listings with photos get roughly 3× the enquiries.</div>
                    <div id="image-preview" class="d-flex flex-wrap gap-2 mt-2"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="videos">Video links</label>
                    <textarea id="videos" name="video_urls" class="form-control" rows="3"
                              placeholder="One YouTube link per line"><?= e($val('video_urls')) ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="documents">Documents</label>
                    <input id="documents" name="documents[]" type="file" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png">
                    <div class="form-text">Test reports, weighbridge slips, invoices.</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Step 8 — Terms -->
    <section class="wizard-step card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h6 class="text-teal mb-3">8. Trade terms</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="payment_terms">Payment terms</label>
                    <select id="payment_terms" name="payment_terms" class="form-select">
                        <?php foreach ($payment_terms as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $val('payment_terms', 'advance') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="loading_by">Loading by</label>
                    <select id="loading_by" name="loading_by" class="form-select">
                        <?php foreach ($responsibility as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $val('loading_by', 'buyer') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="transport_by">Transport by</label>
                    <select id="transport_by" name="transport_by" class="form-select">
                        <?php foreach ($responsibility as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $val('transport_by', 'buyer') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="inspection_available" name="inspection_available" value="1"
                            <?= old('inspection_available', $l['inspection_available'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="inspection_available">Buyers may inspect before buying</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="inspection_notes">Inspection notes</label>
                    <input id="inspection_notes" name="inspection_notes" class="form-control"
                           placeholder="e.g. Mon–Sat, 10 AM to 6 PM, prior appointment" value="<?= e($val('inspection_notes')) ?>">
                </div>
            </div>
        </div>
    </section>

    <!-- Step 9 — Review -->
    <section class="wizard-step card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h6 class="text-teal mb-3">9. Review and publish</h6>
            <div id="wizard-preview" class="mb-3">
                <p class="small text-muted">
                    A summary appears here when JavaScript is available. Either way, everything you entered is
                    submitted when you press the button below.
                </p>
            </div>
            <p class="small text-muted mb-0">
                By publishing you confirm the material description is accurate. Misdescribed lots can be
                disputed by the buyer and repeated offences suspend the account.
            </p>
        </div>
    </section>

    <div class="d-flex justify-content-between gap-2 mb-5">
        <button class="btn btn-outline-secondary <?= $isEdit ? 'd-none' : 'invisible' ?>" type="button" id="wizard-back">
            <i class="bi bi-arrow-left me-1"></i>Back
        </button>
        <div class="d-flex gap-2">
            <button class="btn btn-teal <?= $isEdit ? 'd-none' : '' ?>" type="button" id="wizard-next">
                Next<i class="bi bi-arrow-right ms-1"></i>
            </button>
            <button class="btn btn-teal btn-lg <?= $isEdit ? '' : 'd-none' ?>" type="submit" id="wizard-submit">
                <?= $isEdit ? 'Save changes' : 'Publish listing' ?>
            </button>
        </div>
    </div>
</form>

<?php endif; ?>

<?php if ($isEdit && $tab === 'media'): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><h6 class="mb-0">Photos</h6></div>
        <div class="card-body">
            <?php if (empty($images)): ?>
                <p class="small text-muted">No photos yet.</p>
            <?php else: ?>
                <div class="row g-2 mb-3">
                    <?php foreach ($images as $image): ?>
                        <div class="col-6 col-md-3">
                            <div class="position-relative">
                                <img src="<?= e(upload_url((string) $image['file_path'])) ?>" class="img-fluid rounded" alt="">
                                <?php if ((int) $image['is_primary'] === 1): ?>
                                    <span class="badge bg-teal position-absolute top-0 start-0 m-1">Primary</span>
                                <?php endif; ?>
                                <form method="post" action="<?= e(url('dashboard/listings/images/' . $image['id'] . '/delete')) ?>"
                                      class="position-absolute top-0 end-0 m-1" data-confirm="Delete this photo?">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-danger" type="submit"><i class="bi bi-x"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('dashboard/listings/' . $l['id'] . '/images')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="row g-2">
                    <div class="col-md-8">
                        <input name="images[]" type="file" class="form-control" multiple required accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="col-md-4 d-grid">
                        <button class="btn btn-teal" type="submit">Upload photos</button>
                    </div>
                </div>
                <div class="form-text">Maximum <?= (int) $max_images ?> photos per listing.</div>
            </form>
        </div>
    </div>

    <?php if (!empty($documents)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Documents</h6></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($documents as $document): ?>
                    <li class="list-group-item small">
                        <i class="bi bi-file-earmark me-1"></i><?= e((string) $document['original_name']) ?>
                        <span class="text-muted">· <?= e(human_bytes((int) $document['file_size'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($isEdit && $tab === 'auction'): ?>
    <?php if ($auction !== null): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="mb-3">Auction <?= e((string) $auction['reference']) ?></h6>
                <p class="small text-muted">
                    This listing already has an auction (<?= status_badge((string) $auction['status']) ?>).
                    Manage bids, extensions and awarding from the auction console.
                </p>
                <a class="btn btn-teal" href="<?= e(url('dashboard/auctions/' . $auction['id'])) ?>">Open auction console</a>
            </div>
        </div>
    <?php else: ?>
        <form method="post" action="<?= e(url('dashboard/listings/' . $l['id'] . '/auction')) ?>">
            <?= csrf_field() ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><h6 class="mb-0">Auction setup</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label required" for="auction_type">Auction type</label>
                            <select id="auction_type" name="auction_type" class="form-select" required>
                                <option value="forward">Forward — buyers bid the price up</option>
                                <option value="reverse">Reverse — suppliers bid the price down</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="starting_price">Starting price (₹)</label>
                            <input id="starting_price" name="starting_price" type="number" step="0.01" min="0.01"
                                   class="form-control" required value="<?= e((string) old('starting_price', $l['price'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="bid_increment">Bid increment (₹)</label>
                            <input id="bid_increment" name="bid_increment" type="number" step="0.01" min="0.01"
                                   class="form-control" required value="<?= e((string) old('bid_increment', '100')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="reserve_price">Reserve price (₹)</label>
                            <input id="reserve_price" name="reserve_price" type="number" step="0.01" min="0" class="form-control"
                                   value="<?= e((string) old('reserve_price')) ?>">
                            <div class="form-text">Hidden from bidders. Below it, you are not obliged to sell.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="price_basis">Price basis</label>
                            <select id="price_basis" name="price_basis" class="form-select">
                                <?php foreach (['per_kg' => 'Per kg', 'per_mt' => 'Per MT', 'per_unit' => 'Per unit', 'lot' => 'Whole lot'] as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $key === 'per_mt' ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="payment_terms_auction">Payment terms</label>
                            <select id="payment_terms_auction" name="payment_terms" class="form-select">
                                <?php foreach ($payment_terms as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $key === 'advance' ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="starts_at">Starts at</label>
                            <input id="starts_at" name="starts_at" type="datetime-local" class="form-control" required
                                   value="<?= e(to_local_input(now())) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="ends_at">Ends at</label>
                            <input id="ends_at" name="ends_at" type="datetime-local" class="form-control" required
                                   value="<?= e(to_local_input(gmdate('Y-m-d H:i:s', strtotime('+3 days')))) ?>">
                        </div>
                    </div>
                    <p class="small text-muted mt-2 mb-0">Times are in Asia/Kolkata and stored in UTC.</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><h6 class="mb-0">Anti-sniping &amp; eligibility</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="extension_window_seconds">Extend if a bid lands within (seconds)</label>
                            <input id="extension_window_seconds" name="extension_window_seconds" type="number" min="0"
                                   class="form-control" value="<?= (int) SettingsService::int('auction_extension_window_seconds', 120) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="extension_duration_seconds">Extend by (seconds)</label>
                            <input id="extension_duration_seconds" name="extension_duration_seconds" type="number" min="0"
                                   class="form-control" value="<?= (int) SettingsService::int('auction_extension_duration_seconds', 120) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="max_extensions">Maximum extensions</label>
                            <input id="max_extensions" name="max_extensions" type="number" min="0" class="form-control"
                                   value="<?= (int) SettingsService::int('auction_max_extensions', 5) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="max_bidders">Maximum bidders</label>
                            <input id="max_bidders" name="max_bidders" type="number" min="0" class="form-control" placeholder="Unlimited">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="deposit_amount">Deposit amount (₹)</label>
                            <input id="deposit_amount" name="deposit_amount" type="number" step="0.01" min="0" class="form-control" value="0">
                        </div>
                        <div class="col-md-4 d-flex flex-column justify-content-end gap-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="requires_kyc" name="requires_kyc" value="1" checked>
                                <label class="form-check-label small" for="requires_kyc">KYC-verified bidders only</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="requires_approval" name="requires_approval" value="1">
                                <label class="form-check-label small" for="requires_approval">I approve each bidder</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="deposit_required" name="deposit_required" value="1">
                                <label class="form-check-label small" for="deposit_required">Require a deposit</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="mask_bidders" name="mask_bidders" value="1" checked>
                                <label class="form-check-label small" for="mask_bidders">Mask bidder names publicly</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button class="btn btn-teal btn-lg mb-5" type="submit">Create auction</button>
        </form>
    <?php endif; ?>
<?php endif; ?>
<?php View::endSection(); ?>

<?php View::section('scripts'); ?>
<?php if (!$isEdit): ?>
    <script src="<?= e(asset('js/wizard.js')) ?>"></script>
<?php endif; ?>
<?php View::endSection(); ?>
