<?php

use App\Core\View;

View::section('content');
$m = $edit ?? [];
$val = static fn (string $key, string $default = ''): string => (string) old($key, $m[$key] ?? $default);
?>
<h1 class="h4 mb-4">Materials &amp; grades</h1>

<div class="row g-4">
    <div class="col-lg-7">
        <form method="get" class="row g-2 mb-3">
            <div class="col-md-5">
                <input type="search" name="q" class="form-control" placeholder="Material name"
                       value="<?= e((string) ($filters['q'] ?? '')) ?>">
            </div>
            <div class="col-md-5">
                <select name="category_id" class="form-select" data-auto-submit>
                    <option value="">All categories</option>
                    <?php foreach ($categories as $id => $name): ?>
                        <option value="<?= (int) $id ?>" <?= (int) ($filters['category_id'] ?? 0) === (int) $id ? 'selected' : '' ?>>
                            <?= e((string) $name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-teal" type="submit">Filter</button></div>
        </form>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr><th>Material</th><th>Category</th><th>Unit</th><th class="text-end">GST</th>
                        <th class="text-end">Listings</th><th>Active</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($materials->items as $material): ?>
                        <tr>
                            <td class="small"><?= e((string) $material['name']) ?></td>
                            <td class="small text-muted"><?= e((string) ($material['category_name'] ?? '—')) ?></td>
                            <td class="small"><?= e((string) ($material['unit_code'] ?? '—')) ?></td>
                            <td class="text-end small"><?= e(dec($material['default_gst_rate'], 2)) ?>%</td>
                            <td class="text-end small"><?= (int) ($material['listing_count'] ?? 0) ?></td>
                            <td><?= (int) $material['is_active'] === 1
                                    ? '<span class="badge badge-soft-success">Yes</span>'
                                    : '<span class="badge text-bg-secondary">No</span>' ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-link p-0" href="<?= e(url('admin/catalog/materials?edit=' . $material['id'])) ?>">Edit</a>
                                <form method="post" action="<?= e(url('admin/catalog/materials/' . $material['id'] . '/delete')) ?>"
                                      class="d-inline" data-confirm="Delete this material?">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-link text-danger p-0" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3 d-flex justify-content-center"><?= $materials->links() ?></div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white">
                <h6 class="mb-0"><?= $edit !== null ? 'Edit material' : 'Add a material' ?></h6>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/catalog/materials')) ?>">
                    <?= csrf_field() ?>
                    <?php if ($edit !== null): ?>
                        <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                    <?php endif; ?>
                    <div class="mb-2">
                        <label class="form-label small required" for="name">Name</label>
                        <input id="name" name="name" class="form-control" required value="<?= e($val('name')) ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small required" for="category_id">Category</label>
                        <select id="category_id" name="category_id" class="form-select" required>
                            <?php foreach ($categories as $id => $name): ?>
                                <option value="<?= (int) $id ?>" <?= (int) $val('category_id') === (int) $id ? 'selected' : '' ?>>
                                    <?= e((string) $name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small" for="default_unit_id">Default unit</label>
                            <select id="default_unit_id" name="default_unit_id" class="form-select">
                                <option value="">None</option>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?= (int) $unit['id'] ?>" <?= (int) $val('default_unit_id') === (int) $unit['id'] ? 'selected' : '' ?>>
                                        <?= e((string) $unit['code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="hsn_id">HSN code</label>
                            <select id="hsn_id" name="hsn_id" class="form-select">
                                <option value="">None</option>
                                <?php foreach ($hsn_codes as $hsn): ?>
                                    <option value="<?= (int) $hsn['id'] ?>" <?= (int) $val('hsn_id') === (int) $hsn['id'] ? 'selected' : '' ?>>
                                        <?= e((string) $hsn['code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="default_gst_rate">Default GST %</label>
                            <input id="default_gst_rate" name="default_gst_rate" type="number" step="0.01" min="0" max="50"
                                   class="form-control" value="<?= e($val('default_gst_rate', '18')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="sort_order">Sort order</label>
                            <input id="sort_order" name="sort_order" type="number" min="0" class="form-control"
                                   value="<?= e($val('sort_order', '0')) ?>">
                        </div>
                    </div>
                    <div class="d-flex gap-3 my-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                <?= old('is_active', $m['is_active'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="is_active">Active</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="is_featured"
                                <?= old('is_featured', $m['is_featured'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="is_featured">Featured</label>
                        </div>
                    </div>
                    <button class="btn btn-teal w-100" type="submit"><?= $edit !== null ? 'Save material' : 'Add material' ?></button>
                    <?php if ($edit !== null): ?>
                        <a class="btn btn-link w-100 mt-1" href="<?= e(url('admin/catalog/materials')) ?>">Cancel editing</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($edit !== null): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Grades for <?= e((string) $edit['name']) ?></h6></div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($grades as $grade): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center small">
                            <span><?= e((string) $grade['name']) ?></span>
                            <form method="post" action="<?= e(url('admin/catalog/grades/' . $grade['id'] . '/delete')) ?>"
                                  data-confirm="Delete this grade?">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-link text-danger p-0" type="submit">Delete</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="card-body">
                    <form method="post" action="<?= e(url('admin/catalog/grades')) ?>" class="row g-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="material_id" value="<?= (int) $edit['id'] ?>">
                        <div class="col-8">
                            <input name="name" class="form-control form-control-sm" placeholder="Grade name" required>
                        </div>
                        <div class="col-4 d-grid">
                            <button class="btn btn-sm btn-outline-teal" type="submit">Add grade</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection(); ?>
