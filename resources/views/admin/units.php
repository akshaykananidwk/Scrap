<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Units &amp; HSN codes</h1>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Units of measure</h6></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr><th>Code</th><th>Name</th><th class="text-end">KG factor</th><th>Weight?</th><th>Active</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($units as $unit): ?>
                        <tr>
                            <td class="small fw-semibold"><?= e((string) $unit['code']) ?></td>
                            <td class="small"><?= e((string) $unit['name']) ?></td>
                            <td class="text-end small"><?= $unit['kg_factor'] !== null ? e(dec($unit['kg_factor'], 6)) : '—' ?></td>
                            <td class="small"><?= (int) $unit['is_weight'] === 1 ? 'Yes' : 'No' ?></td>
                            <td><?= (int) $unit['is_active'] === 1
                                    ? '<span class="badge badge-soft-success">Yes</span>'
                                    : '<span class="badge text-bg-secondary">No</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top">
                <form method="post" action="<?= e(url('admin/catalog/units')) ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-md-2">
                        <input name="code" class="form-control form-control-sm text-uppercase" placeholder="Code" required maxlength="12">
                    </div>
                    <div class="col-md-4">
                        <input name="name" class="form-control form-control-sm" placeholder="Name" required maxlength="60">
                    </div>
                    <div class="col-md-2">
                        <input name="kg_factor" type="number" step="0.000001" min="0" class="form-control form-control-sm" placeholder="KG factor">
                    </div>
                    <div class="col-md-2 d-flex align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_weight" value="1" id="is_weight" checked>
                            <label class="form-check-label small" for="is_weight">Weight</label>
                        </div>
                    </div>
                    <div class="col-md-2 d-grid">
                        <input type="hidden" name="is_active" value="1">
                        <button class="btn btn-sm btn-outline-teal" type="submit">Add</button>
                    </div>
                </form>
                <p class="form-text mb-0">
                    The KG factor converts the unit to kilograms — it is what makes weighbridge settlement
                    work regardless of how the seller quoted the lot.
                </p>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">HSN codes</h6></div>
            <div class="table-responsive" style="max-height:420px;overflow:auto">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light sticky-top">
                    <tr><th>Code</th><th>Description</th><th class="text-end">GST</th><th>Active</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($hsn_codes as $hsn): ?>
                        <tr>
                            <td class="small fw-semibold font-monospace"><?= e((string) $hsn['code']) ?></td>
                            <td class="small"><?= e((string) $hsn['description']) ?></td>
                            <td class="text-end small"><?= e(dec($hsn['gst_rate'], 2)) ?>%</td>
                            <td><?= (int) $hsn['is_active'] === 1
                                    ? '<span class="badge badge-soft-success">Yes</span>'
                                    : '<span class="badge text-bg-secondary">No</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top">
                <form method="post" action="<?= e(url('admin/catalog/hsn')) ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-md-3">
                        <input name="code" class="form-control form-control-sm" placeholder="HSN" required maxlength="12">
                    </div>
                    <div class="col-md-5">
                        <input name="description" class="form-control form-control-sm" placeholder="Description" required>
                    </div>
                    <div class="col-md-2">
                        <input name="gst_rate" type="number" step="0.01" min="0" max="50" class="form-control form-control-sm" value="18">
                    </div>
                    <div class="col-md-2 d-grid">
                        <input type="hidden" name="is_active" value="1">
                        <button class="btn btn-sm btn-outline-teal" type="submit">Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
