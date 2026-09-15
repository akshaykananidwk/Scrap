<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-1">Create a request for quotation</h1>
<p class="text-muted small mb-4">Add your line items, set a deadline, and invite suppliers — or leave it open to everyone.</p>

<form method="post" action="<?= e(url('dashboard/rfq')) ?>">
    <?= csrf_field() ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><h6 class="mb-0">RFQ details</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label required" for="title">Title</label>
                    <input id="title" name="title" class="form-control form-control-lg" required minlength="8" maxlength="190"
                           placeholder="e.g. Quarterly supply of mixed metal scrap — 3 grades" value="<?= e((string) old('title')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="visibility">Who can quote?</label>
                    <select id="visibility" name="visibility" class="form-select" required>
                        <option value="public" <?= old('visibility', 'public') === 'public' ? 'selected' : '' ?>>Public — any verified seller</option>
                        <option value="invited" <?= old('visibility') === 'invited' ? 'selected' : '' ?>>Invited suppliers only</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-select">
                        <option value="">Not category-specific</option>
                        <?php foreach ($categories as $category): ?>
                            <optgroup label="<?= e((string) $category['name']) ?>">
                                <option value="<?= (int) $category['id'] ?>"><?= e((string) $category['name']) ?> (general)</option>
                                <?php foreach ($category['children'] ?? [] as $child): ?>
                                    <option value="<?= (int) $child['id'] ?>"><?= e((string) $child['name']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="closes_at">Quote deadline</label>
                    <input id="closes_at" name="closes_at" type="datetime-local" class="form-control" required
                           value="<?= e(to_local_input(gmdate('Y-m-d H:i:s', strtotime('+7 days')))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="delivery_required_by">Delivery required by</label>
                    <input id="delivery_required_by" name="delivery_required_by" type="date" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Scope and conditions</label>
                    <textarea id="description" name="description" class="form-control" rows="3" maxlength="5000"><?= e((string) old('description')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Line items</h6>
            <button class="btn btn-sm btn-outline-teal" type="button" id="add-rfq-item">
                <i class="bi bi-plus-lg me-1"></i>Add item
            </button>
        </div>
        <div class="card-body">
            <div id="rfq-items">
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="row g-2 mb-2 rfq-item">
                        <div class="col-md-4">
                            <input name="items[<?= $i ?>][item_name]" class="form-control"
                                   placeholder="Item name<?= $i === 0 ? ' (required)' : '' ?>" <?= $i === 0 ? 'required' : '' ?>>
                        </div>
                        <div class="col-md-3">
                            <input name="items[<?= $i ?>][specification]" class="form-control" placeholder="Specification">
                        </div>
                        <div class="col-md-2">
                            <input name="items[<?= $i ?>][quantity]" type="number" step="0.001" min="0" class="form-control"
                                   placeholder="Qty" <?= $i === 0 ? 'required' : '' ?>>
                        </div>
                        <div class="col-md-2">
                            <select name="items[<?= $i ?>][unit_id]" class="form-select">
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?= (int) $unit['id'] ?>"><?= e((string) $unit['code']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <input name="items[<?= $i ?>][target_price]" type="number" step="0.01" min="0"
                                   class="form-control" placeholder="₹">
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
            <p class="form-text mb-0">Blank rows are ignored. The last column is your (private) target price.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><h6 class="mb-0">Delivery</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="delivery_address">Delivery address</label>
                    <input id="delivery_address" name="delivery_address" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="state_id">State</label>
                    <select id="state_id" name="state_id" class="form-select" data-load-cities="#city_id">
                        <option value="">Select</option>
                        <?php foreach ($states as $state): ?>
                            <option value="<?= (int) $state['id'] ?>"><?= e((string) $state['name']) ?></option>
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
                <div class="col-md-2">
                    <label class="form-label" for="payment_terms">Payment terms</label>
                    <select id="payment_terms" name="payment_terms" class="form-select">
                        <?php foreach ($payment_terms as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $key === 'on_delivery' ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <?php if ($suggested_sellers !== []): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Invite suppliers</h6></div>
            <div class="card-body" style="max-height:320px;overflow:auto">
                <div class="row g-2">
                    <?php foreach ($suggested_sellers as $seller): ?>
                        <div class="col-md-6">
                            <div class="form-check border rounded p-2 ps-4">
                                <input class="form-check-input" type="checkbox" name="invite_sellers[]"
                                       value="<?= (int) $seller['id'] ?>" id="seller-<?= (int) $seller['id'] ?>">
                                <label class="form-check-label small" for="seller-<?= (int) $seller['id'] ?>">
                                    <strong><?= e((string) ($seller['business_name'] ?: $seller['full_name'])) ?></strong>
                                    <?php if ((int) ($seller['kyc_verified'] ?? 0) === 1): ?>
                                        <i class="bi bi-patch-check-fill text-teal"></i>
                                    <?php endif; ?>
                                    <div class="text-muted"><?= e((string) ($seller['city_name'] ?? '—')) ?></div>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <button class="btn btn-teal btn-lg mb-5" type="submit">Publish RFQ</button>
</form>
<?php View::endSection(); ?>

<?php View::section('scripts'); ?>
<script>
// Add another line-item row. Without JS the three preset rows still work.
(function () {
    var container = document.getElementById('rfq-items');
    var button = document.getElementById('add-rfq-item');
    if (!container || !button) return;

    button.addEventListener('click', function () {
        var index = container.querySelectorAll('.rfq-item').length;
        var template = container.querySelector('.rfq-item');
        var row = template.cloneNode(true);

        row.querySelectorAll('input, select').forEach(function (field) {
            field.name = field.name.replace(/items\[\d+\]/, 'items[' + index + ']');
            field.removeAttribute('required');
            if (field.tagName === 'INPUT') field.value = '';
        });
        container.appendChild(row);
    });
})();
</script>
<?php View::endSection(); ?>
