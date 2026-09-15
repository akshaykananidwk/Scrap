<?php

use App\Core\View;

View::section('content');
$r = $requirement ?? [];
$val = static fn (string $key, string $default = ''): string => (string) old($key, $r[$key] ?? $default);
?>
<h1 class="h4 mb-1">Post a buying requirement</h1>
<p class="text-muted small mb-4">Sellers who deal in this material are notified as soon as you publish.</p>

<form method="post" action="<?= e(url('dashboard/requirements')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><h6 class="mb-0">What do you need?</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label required" for="title">Requirement title</label>
                    <input id="title" name="title" class="form-control form-control-lg" required minlength="8" maxlength="190"
                           placeholder="e.g. Need 50 MT Aluminium Scrap monthly — Rajkot" value="<?= e($val('title')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="frequency">How often?</label>
                    <select id="frequency" name="frequency" class="form-select" required>
                        <?php foreach ($frequencies as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $val('frequency', 'one_time') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-select" required data-load-materials="#material_id">
                        <option value="">Select category</option>
                        <?php foreach ($categories as $category): ?>
                            <optgroup label="<?= e((string) $category['name']) ?>">
                                <option value="<?= (int) $category['id'] ?>" <?= (int) $val('category_id') === (int) $category['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $category['name']) ?> (general)
                                </option>
                                <?php foreach ($category['children'] ?? [] as $child): ?>
                                    <option value="<?= (int) $child['id'] ?>" <?= (int) $val('category_id') === (int) $child['id'] ? 'selected' : '' ?>>
                                        <?= e((string) $child['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="material_id">Material</label>
                    <select id="material_id" name="material_id" class="form-select" data-load-grades="#grade_id">
                        <option value="">Any material in this category</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="grade_text">Grade / specification</label>
                    <input id="grade_text" name="grade_text" class="form-control" value="<?= e($val('grade_text')) ?>">
                    <select id="grade_id" name="grade_id" class="d-none"><option value=""></option></select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Details</label>
                    <textarea id="description" name="description" class="form-control" rows="3" maxlength="5000"
                              placeholder="Acceptable contamination levels, packing, testing requirements…"><?= e($val('description')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><h6 class="mb-0">Quantity and price</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label required" for="quantity">Quantity needed</label>
                    <input id="quantity" name="quantity" type="number" step="0.001" min="0.001" class="form-control" required
                           value="<?= e($val('quantity')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="unit_id">Unit</label>
                    <select id="unit_id" name="unit_id" class="form-select" required>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= (int) $unit['id'] ?>" <?= (int) $val('unit_id') === (int) $unit['id'] ? 'selected' : '' ?>>
                                <?= e((string) $unit['name']) ?> (<?= e((string) $unit['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="min_quantity">Minimum acceptable</label>
                    <input id="min_quantity" name="min_quantity" type="number" step="0.001" min="0" class="form-control"
                           value="<?= e($val('min_quantity')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="max_quantity">Maximum</label>
                    <input id="max_quantity" name="max_quantity" type="number" step="0.001" min="0" class="form-control"
                           value="<?= e($val('max_quantity')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="target_price">Target price (₹)</label>
                    <input id="target_price" name="target_price" type="number" step="0.01" min="0" class="form-control"
                           value="<?= e($val('target_price')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="price_basis">Price basis</label>
                    <select id="price_basis" name="price_basis" class="form-select">
                        <?php foreach ($basis as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $val('price_basis', 'per_mt') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="payment_terms">Payment terms</label>
                    <select id="payment_terms" name="payment_terms" class="form-select">
                        <?php foreach ($payment_terms as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $val('payment_terms', 'on_delivery') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><h6 class="mb-0">Delivery</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="delivery_required" name="delivery_required" value="1"
                            <?= old('delivery_required', $r['delivery_required'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="delivery_required">I need the seller to deliver</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="delivery_address">Delivery address</label>
                    <input id="delivery_address" name="delivery_address" class="form-control" value="<?= e($val('delivery_address')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="pincode">Pincode</label>
                    <input id="pincode" name="pincode" class="form-control" maxlength="6" data-pincode-lookup value="<?= e($val('pincode')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label required" for="state_id">State</label>
                    <select id="state_id" name="state_id" class="form-select" required data-load-cities="#city_id">
                        <option value="">Select</option>
                        <?php foreach ($states as $state): ?>
                            <option value="<?= (int) $state['id'] ?>" <?= (int) $val('state_id') === (int) $state['id'] ? 'selected' : '' ?>>
                                <?= e((string) $state['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="city_id">City</label>
                    <select id="city_id" name="city_id" class="form-select">
                        <option value="">Select</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= (int) $city['id'] ?>"><?= e((string) $city['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="required_by">Required by</label>
                    <input id="required_by" name="required_by" type="date" class="form-control" value="<?= e($val('required_by')) ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="documents">Attachments</label>
                    <input id="documents" name="documents[]" type="file" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png">
                    <div class="form-text">Specification sheets, drawings, sample photos.</div>
                </div>
            </div>
        </div>
    </div>

    <button class="btn btn-teal btn-lg mb-5" type="submit">Post requirement</button>
</form>
<?php View::endSection(); ?>
