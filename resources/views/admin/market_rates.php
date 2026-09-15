<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-1">Market rates</h1>
<p class="text-muted small mb-4">
    Published rates power the public rate board and the price-trend charts. Nothing is scraped or invented —
    every figure here is entered by your team or imported from a CSV.
</p>

<div class="row g-4">
    <div class="col-lg-8">
        <form method="get" class="row g-2 mb-3">
            <div class="col-md-4">
                <select name="material_id" class="form-select" data-auto-submit>
                    <option value="">All materials</option>
                    <?php foreach ($materials as $material): ?>
                        <option value="<?= (int) $material['id'] ?>" <?= (int) ($filters['material_id'] ?? 0) === (int) $material['id'] ? 'selected' : '' ?>>
                            <?= e((string) $material['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <select name="city_id" class="form-select" data-auto-submit>
                    <option value="">All cities</option>
                    <?php foreach ($cities as $city): ?>
                        <option value="<?= (int) $city['id'] ?>" <?= (int) ($filters['city_id'] ?? 0) === (int) $city['id'] ? 'selected' : '' ?>>
                            <?= e((string) $city['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input name="rate_date" type="date" class="form-control" value="<?= e((string) ($filters['rate_date'] ?? '')) ?>">
            </div>
            <div class="col-md-1 d-grid"><button class="btn btn-outline-teal" type="submit">Go</button></div>
        </form>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr><th>Date</th><th>Material</th><th>Grade</th><th>City</th>
                        <th class="text-end">Rate</th><th>Unit</th><th>Source</th><th>Live</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rates->items as $rate): ?>
                        <tr>
                            <td class="small text-nowrap"><?= e(fmt_date($rate['rate_date'])) ?></td>
                            <td class="small"><?= e((string) ($rate['material_name'] ?? '—')) ?></td>
                            <td class="small text-muted"><?= e((string) ($rate['grade_name'] ?? '—')) ?></td>
                            <td class="small"><?= e((string) ($rate['city_name'] ?? 'All India')) ?></td>
                            <td class="text-end fw-semibold"><?= money($rate['rate']) ?></td>
                            <td class="small"><?= e((string) ($rate['unit_code'] ?? '')) ?></td>
                            <td class="small text-muted"><?= e((string) ($rate['source'] ?? '—')) ?></td>
                            <td><?= (int) $rate['is_published'] === 1
                                    ? '<span class="badge badge-soft-success">Yes</span>'
                                    : '<span class="badge text-bg-secondary">No</span>' ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= e(url('admin/market-rates/' . $rate['id'] . '/delete')) ?>"
                                      data-confirm="Delete this rate?">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-link text-danger p-0" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3 d-flex justify-content-center"><?= $rates->links() ?></div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Publish a rate</h6></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/market-rates')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label small required" for="material_id">Material</label>
                        <select id="material_id" name="material_id" class="form-select" required>
                            <?php foreach ($materials as $material): ?>
                                <option value="<?= (int) $material['id'] ?>"><?= e((string) $material['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small required" for="rate">Rate (₹)</label>
                            <input id="rate" name="rate" type="number" step="0.0001" min="0.0001" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small required" for="unit_id">Unit</label>
                            <select id="unit_id" name="unit_id" class="form-select" required>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?= (int) $unit['id'] ?>"><?= e((string) $unit['code']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="city_id">City</label>
                            <select id="city_id" name="city_id" class="form-select">
                                <option value="">All India</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?= (int) $city['id'] ?>"><?= e((string) $city['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small required" for="rate_date">Date</label>
                            <input id="rate_date" name="rate_date" type="date" class="form-control" required
                                   value="<?= e(gmdate('Y-m-d')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small" for="source">Source</label>
                            <input id="source" name="source" class="form-control" placeholder="e.g. Trade survey, MCX">
                        </div>
                        <div class="col-12">
                            <label class="form-label small" for="notes">Notes</label>
                            <input id="notes" name="notes" class="form-control">
                        </div>
                    </div>
                    <div class="form-check my-3">
                        <input class="form-check-input" type="checkbox" name="is_published" value="1" id="is_published" checked>
                        <label class="form-check-label small" for="is_published">Publish immediately</label>
                    </div>
                    <button class="btn btn-teal w-100" type="submit">Save rate</button>
                </form>
                <p class="small text-muted mt-3 mb-0">
                    Bulk rates can be loaded from CSV under
                    <a href="<?= e(url('admin/export')) ?>">Import &amp; export</a>.
                </p>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
