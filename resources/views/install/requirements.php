<?php

use App\Core\View;

View::section('content');
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h1 class="h5 mb-3">System requirements</h1>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr><th>Requirement</th><th>Needed</th><th>Detected</th><th class="text-end">Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($checks as $check): ?>
                    <tr>
                        <td>
                            <?= e($check['name']) ?>
                            <?php if (!$check['required']): ?>
                                <span class="badge text-bg-light border ms-1">optional</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= e($check['expected']) ?></td>
                        <td class="small"><?= e($check['current']) ?></td>
                        <td class="text-end">
                            <?php if ($check['passed']): ?>
                                <span class="badge text-bg-success"><i class="bi bi-check-lg"></i></span>
                            <?php elseif ($check['required']): ?>
                                <span class="badge text-bg-danger"><i class="bi bi-x-lg"></i></span>
                            <?php else: ?>
                                <span class="badge text-bg-warning"><i class="bi bi-dash-lg"></i></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!$passed): ?>
            <div class="alert alert-danger">
                <strong>Some required checks failed.</strong>
                Fix them and reload this page. For "not writable" errors, set the folder permission to
                775 (or 755 if your host runs PHP as the file owner) in your hosting file manager.
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-1"></i>Your server meets every requirement.
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between">
            <a class="btn btn-outline-secondary" href="<?= e(url('install')) ?>">Back</a>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="<?= e(url('install/requirements')) ?>">
                    <i class="bi bi-arrow-clockwise me-1"></i>Re-check
                </a>
                <a class="btn btn-teal <?= $passed ? '' : 'disabled' ?>" href="<?= e(url('install/database')) ?>">
                    Continue <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
