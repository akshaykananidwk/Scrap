<?php

use App\Core\Auth;
use App\Core\View;
use App\Services\SettingsService;

View::section('content');

$query = $filters ?? [];
$guestLimited = !Auth::check() && SettingsService::bool('guest_can_view_listings', true);
?>

<div class="bg-white border-bottom py-3">
    <div class="container">
        <?php if (!empty($breadcrumbs)): ?>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small mb-2">
                    <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?= e(url('buy')) ?>">Buy Scrap</a></li>
                    <?php foreach ($breadcrumbs as $crumb): ?>
                        <li class="breadcrumb-item"><a href="<?= e(url('scrap/' . $crumb['slug'])) ?>"><?= e($crumb['name']) ?></a></li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        <?php endif; ?>

        <h1 class="h4 mb-1"><?= e($heading ?? 'Buy Scrap') ?></h1>
        <p class="text-muted small mb-0"><?= e(trim((string) ($subheading ?? ''))) ?></p>

        <?php if (!empty($subcategories)): ?>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <?php foreach ($subcategories as $subcategory): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('scrap/' . $subcategory['slug'])) ?>">
                        <?= e($subcategory['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($material_rates)): ?>
            <div class="alert alert-light border mt-3 mb-0 py-2 small d-flex flex-wrap gap-3 align-items-center">
                <strong class="text-teal"><i class="bi bi-graph-up-arrow me-1"></i>Today's rate</strong>
                <?php foreach (array_slice($material_rates, 0, 5) as $rate): ?>
                    <span>
                        <?= e((string) ($rate['city_name'] ?? 'India')) ?>:
                        <strong><?= e(money($rate['rate'])) ?></strong>/<?= e((string) $rate['unit_code']) ?>
                        <?php $change = (float) $rate['change_percent']; ?>
                        <span class="<?= $change >= 0 ? 'rate-up' : 'rate-down' ?>">
                            <?= $change >= 0 ? '+' : '' ?><?= e(number_format($change, 2)) ?>%
                        </span>
                    </span>
                <?php endforeach; ?>
                <a href="<?= e(url('market-rates/' . ($material['slug'] ?? ''))) ?>">Price history →</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="container py-4">
    <div class="row g-4">
        <!-- Filters -->
        <div class="col-lg-3">
            <div class="d-lg-none mb-3">
                <button class="btn btn-outline-secondary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel">
                    <i class="bi bi-funnel me-1"></i>Filters
                    <?php if (count($query) > 1): ?>
                        <span class="badge text-bg-teal ms-1"><?= count($query) - 1 ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <div class="collapse d-lg-block" id="filterPanel">
                <form method="get" class="card border-0 shadow-sm filter-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Filters</h6>
                            <a class="small" href="<?= e(url(ltrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/buy', PHP_URL_PATH), '/'))) ?>">Clear</a>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="f-q">Keyword</label>
                            <input id="f-q" type="search" name="q" class="form-control form-control-sm"
                                   value="<?= e((string) ($query['q'] ?? '')) ?>" placeholder="Material, grade, seller">
                        </div>

                        <?php if (empty($category)): ?>
                            <div class="mb-3">
                                <label class="form-label" for="f-category">Category</label>
                                <select id="f-category" name="category_id" class="form-select form-select-sm">
                                    <option value="">All categories</option>
                                    <?php foreach ($categories as $root): ?>
                                        <optgroup label="<?= e($root['name']) ?>">
                                            <option value="<?= (int) $root['id'] ?>" <?= (int) ($query['category_id'] ?? 0) === (int) $root['id'] ? 'selected' : '' ?>>
                                                All <?= e($root['name']) ?>
                                            </option>
                                            <?php foreach ($root['children'] as $child): ?>
                                                <option value="<?= (int) $child['id'] ?>" <?= (int) ($query['category_id'] ?? 0) === (int) $child['id'] ? 'selected' : '' ?>>
                                                    <?= e($child['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($materials)): ?>
                            <div class="mb-3">
                                <label class="form-label" for="f-material">Material</label>
                                <select id="f-material" name="material_id" class="form-select form-select-sm">
                                    <option value="">All materials</option>
                                    <?php foreach ($materials as $material_option): ?>
                                        <option value="<?= (int) $material_option['id'] ?>"
                                            <?= (int) ($query['material_id'] ?? 0) === (int) $material_option['id'] ? 'selected' : '' ?>>
                                            <?= e($material_option['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($grades)): ?>
                            <div class="mb-3">
                                <label class="form-label" for="f-grade">Grade</label>
                                <select id="f-grade" name="grade_id" class="form-select form-select-sm">
                                    <option value="">Any grade</option>
                                    <?php foreach ($grades as $grade): ?>
                                        <option value="<?= (int) $grade['id'] ?>" <?= (int) ($query['grade_id'] ?? 0) === (int) $grade['id'] ? 'selected' : '' ?>>
                                            <?= e($grade['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label" for="f-state">State</label>
                            <select id="f-state" name="state_id" class="form-select form-select-sm"
                                    data-load-cities="#f-city">
                                <option value="">All India</option>
                                <?php foreach ($states as $state): ?>
                                    <option value="<?= (int) $state['id'] ?>" <?= (int) ($query['state_id'] ?? 0) === (int) $state['id'] ? 'selected' : '' ?>>
                                        <?= e($state['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="f-city">City</label>
                            <select id="f-city" name="city_id" class="form-select form-select-sm">
                                <option value="">All cities</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?= (int) $city['id'] ?>" <?= (int) ($query['city_id'] ?? 0) === (int) $city['id'] ? 'selected' : '' ?>>
                                        <?= e($city['name']) ?><?= isset($city['state_name']) ? ', ' . e((string) $city['state_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="f-type">Sale method</label>
                            <select id="f-type" name="listing_type" class="form-select form-select-sm">
                                <option value="">Any</option>
                                <?php foreach ($listing_types as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= ($query['listing_type'] ?? '') === $key ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Price per MT (₹)</label>
                            <div class="row g-1">
                                <div class="col-6">
                                    <input type="number" name="min_price" class="form-control form-control-sm"
                                           placeholder="Min" step="0.01" min="0" value="<?= e((string) ($query['min_price'] ?? '')) ?>">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="max_price" class="form-control form-control-sm"
                                           placeholder="Max" step="0.01" min="0" value="<?= e((string) ($query['max_price'] ?? '')) ?>">
                                </div>
                            </div>
                            <div class="form-text">Compared on the per-kilogram price.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <div class="row g-1">
                                <div class="col-6">
                                    <input type="number" name="min_quantity" class="form-control form-control-sm"
                                           placeholder="Min" step="0.001" min="0" value="<?= e((string) ($query['min_quantity'] ?? '')) ?>">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="max_quantity" class="form-control form-control-sm"
                                           placeholder="Max" step="0.001" min="0" value="<?= e((string) ($query['max_quantity'] ?? '')) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="f-condition">Condition</label>
                            <select id="f-condition" name="condition" class="form-select form-select-sm">
                                <option value="">Any condition</option>
                                <?php foreach ($conditions as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= ($query['condition'] ?? '') === $key ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="verified_only" value="1"
                                   id="f-verified" <?= !empty($query['verified_only']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="f-verified">KYC-verified sellers only</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="delivery_available" value="1"
                                   id="f-delivery" <?= !empty($query['delivery_available']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="f-delivery">Delivery available</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="ending_soon" value="1"
                                   id="f-ending" <?= !empty($query['ending_soon']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="f-ending">Auctions ending in 24h</label>
                        </div>

                        <button class="btn btn-teal w-100" type="submit">Apply filters</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <span class="text-muted small">
                    <?php if ($listings->total > 0): ?>
                        Showing <?= number_format($listings->from()) ?>–<?= number_format($listings->to()) ?>
                        of <?= number_format($listings->total) ?> listings
                    <?php else: ?>
                        No listings matched
                    <?php endif; ?>
                </span>

                <form method="get" class="d-flex align-items-center gap-2">
                    <?php foreach ($query as $key => $value): ?>
                        <?php if ($key !== 'sort' && !is_array($value)): ?>
                            <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <label class="form-label mb-0 small text-muted" for="sort">Sort</label>
                    <select id="sort" name="sort" class="form-select form-select-sm" data-auto-submit style="width:auto">
                        <?php foreach ($sorts as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= ($query['sort'] ?? 'newest') === $key ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($listings->isEmpty()): ?>
                <div class="empty-state bg-white rounded shadow-sm">
                    <i class="bi bi-search"></i>
                    <h5>No listings match those filters</h5>
                    <p class="mb-3">Try widening the location or clearing some filters.</p>
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <a class="btn btn-outline-teal btn-sm" href="<?= e(url('buy')) ?>">Clear filters</a>
                        <a class="btn btn-teal btn-sm" href="<?= e(url('dashboard/requirements/create')) ?>">
                            Post a requirement instead
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($listings->items as $listing): ?>
                        <div class="col-6 col-xl-4">
                            <?= View::partial('partials/listing_card', ['listing' => $listing]) ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($guestLimited && $listings->total > count($listings->items)): ?>
                    <div class="alert alert-info mt-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>
                            <i class="bi bi-lock me-1"></i>
                            You are viewing a limited preview. Create a free account to see all
                            <?= number_format($listings->total) ?> listings and contact sellers.
                        </span>
                        <a class="btn btn-sm btn-teal" href="<?= e(url('register')) ?>">Create account</a>
                    </div>
                <?php endif; ?>

                <div class="mt-4 d-flex justify-content-center">
                    <?= $listings->links() ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php View::endSection(); ?>
