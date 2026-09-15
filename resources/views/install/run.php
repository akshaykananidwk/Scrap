<?php

use App\Core\View;

View::section('content');
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h1 class="h5 mb-1">Installing <?= e($site_name ?? 'ScrapX') ?></h1>
        <p class="text-muted small mb-4">
            Creating tables, seeding data and setting up your administrator. This usually takes 10–40 seconds —
            please do not close this page.
        </p>

        <div id="install-progress">
            <?php if (!empty($steps)): ?>
                <?php foreach ($steps as $step): ?>
                    <div class="d-flex align-items-start gap-2 py-2 border-bottom">
                        <i class="bi bi-<?= $step['ok'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?>"></i>
                        <div>
                            <div class="fw-semibold small"><?= e($step['label']) ?></div>
                            <div class="text-muted small"><?= e($step['detail']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-4">
                    <div class="spinner-border text-teal mb-3"></div>
                    <div class="small text-muted" id="install-status">Starting installation…</div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger mt-3">
                <strong>Installation failed.</strong><br><?= e($error) ?>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="<?= e(url('install/database')) ?>">Change database details</a>
                <a class="btn btn-teal" href="<?= e(url('install/run')) ?>">Try again</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection(); ?>

<?php View::section('scripts'); ?>
<?php if (empty($steps) && empty($error)): ?>
<script>
(async function () {
    const box = document.getElementById('install-progress');
    const status = document.getElementById('install-status');

    try {
        const response = await fetch('<?= e(url('install/run')) ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        const result = await response.json();

        box.innerHTML = (result.steps || []).map((step) =>
            '<div class="d-flex align-items-start gap-2 py-2 border-bottom">' +
            '<i class="bi bi-' + (step.ok ? 'check-circle-fill text-success' : 'x-circle-fill text-danger') + '"></i>' +
            '<div><div class="fw-semibold small">' + step.label + '</div>' +
            '<div class="text-muted small">' + step.detail + '</div></div></div>'
        ).join('');

        if (result.success && result.redirect) {
            box.insertAdjacentHTML('beforeend',
                '<div class="alert alert-success mt-3 mb-0">Installation complete — redirecting…</div>');
            setTimeout(() => { window.location.href = result.redirect; }, 1200);
        } else {
            box.insertAdjacentHTML('beforeend',
                '<div class="alert alert-danger mt-3"><strong>Installation failed.</strong><br>' +
                (result.error || 'Unknown error') + '</div>' +
                '<a class="btn btn-outline-secondary" href="<?= e(url('install/database')) ?>">Change database details</a>');
        }
    } catch (e) {
        status.textContent = 'The installer stopped responding. Check storage/logs for details.';
    }
})();
</script>
<?php endif; ?>
<?php View::endSection(); ?>
