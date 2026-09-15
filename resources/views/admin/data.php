<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Import &amp; export</h1>

<?php if (!empty($result)): ?>
    <div class="alert <?= !empty($result['ok']) ? 'alert-success' : 'alert-warning' ?>">
        <strong>Import finished.</strong>
        <?= (int) ($result['imported'] ?? 0) ?> row(s) imported,
        <?= (int) ($result['skipped'] ?? 0) ?> skipped.
        <?php if (!empty($result['errors'])): ?>
            <ul class="small mb-0 mt-2">
                <?php foreach (array_slice((array) $result['errors'], 0, 20) as $error): ?>
                    <li><?= e((string) $error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Export to CSV</h6></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($datasets as $key => $label): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <span class="small"><?= e($label) ?></span>
                            <span class="small text-muted">· <?= (int) ($counts[$key] ?? 0) ?> rows</span>
                        </div>
                        <a class="btn btn-sm btn-outline-teal" href="<?= e(url('admin/export/' . $key)) ?>">
                            <i class="bi bi-download me-1"></i>Download
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="card-body">
                <p class="small text-muted mb-0">
                    Exports stream directly to the browser, so a large table does not exhaust PHP memory
                    on shared hosting.
                </p>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Import from CSV</h6></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/import')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label small required" for="type">What are you importing?</label>
                        <select id="type" name="type" class="form-select" required>
                            <?php foreach ($import_types as $key => $type): ?>
                                <option value="<?= e($key) ?>"><?= e((string) $type['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small required" for="file">CSV file</label>
                        <input id="file" name="file" type="file" class="form-control" accept=".csv,text/csv" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="dry_run" value="1" id="dry_run" checked>
                        <label class="form-check-label small" for="dry_run">
                            Dry run — validate and report without writing anything
                        </label>
                    </div>
                    <button class="btn btn-teal w-100" type="submit">Run import</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Column templates</h6></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($import_types as $key => $type): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <span class="small"><?= e((string) $type['label']) ?></span>
                            <a class="btn btn-sm btn-link p-0" href="<?= e(url('admin/import/template/' . $key)) ?>">
                                Download template
                            </a>
                        </div>
                        <div class="small text-muted font-monospace"><?= e(implode(', ', (array) $type['columns'])) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Demo data</h6></div>
            <div class="card-body">
                <p class="small text-muted">
                    Demo records are tagged <code>is_demo = 1</code>, so they can be removed cleanly without
                    touching anything real.
                </p>
                <ul class="small">
                    <?php foreach ($demo_counts as $label => $count): ?>
                        <li><?= e(label((string) $label)) ?>: <strong><?= (int) $count ?></strong></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (array_sum(array_map('intval', $demo_counts)) > 0): ?>
                    <form method="post" action="<?= e(url('admin/demo-data/purge')) ?>"
                          data-confirm="Permanently delete every demo record? This cannot be undone.">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-danger w-100" type="submit">Purge all demo data</button>
                    </form>
                <?php else: ?>
                    <p class="small text-muted mb-0">No demo data present.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
