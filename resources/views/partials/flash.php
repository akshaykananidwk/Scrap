<?php
/** @var array $flashes */
$flashes = $flashes ?? [];
$icons = ['success' => 'bi-check-circle', 'danger' => 'bi-exclamation-octagon', 'warning' => 'bi-exclamation-triangle', 'info' => 'bi-info-circle'];
?>
<?php if ($flashes !== []): ?>
    <div class="container mt-3">
        <?php foreach ($flashes as $type => $messages): ?>
            <?php foreach ((array) $messages as $message): ?>
                <div class="alert alert-<?= e($type) ?> alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
                    <i class="bi <?= e($icons[$type] ?? 'bi-info-circle') ?> mt-1"></i>
                    <div class="flex-grow-1"><?= e((string) $message) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
