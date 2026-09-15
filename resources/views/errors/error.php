<?php

use App\Core\View;

View::section('content');

$icons = [403 => 'bi-shield-lock', 404 => 'bi-compass', 419 => 'bi-hourglass-split',
          429 => 'bi-speedometer', 503 => 'bi-cone-striped', 500 => 'bi-bug'];
?>
<div class="container py-5">
    <div class="text-center" style="max-width:560px;margin:0 auto">
        <i class="bi <?= e($icons[$status] ?? 'bi-exclamation-triangle') ?> text-teal" style="font-size:4rem"></i>
        <h1 class="display-5 fw-bold mt-3 mb-2"><?= (int) $status ?></h1>
        <p class="lead text-muted mb-4"><?= e($message) ?></p>

        <?php if (!empty($detail)): ?>
            <!-- Staff only: the real cause, so an administrator has something to act on. -->
            <div class="alert alert-danger text-start small">
                <div class="fw-semibold mb-1">
                    <i class="bi bi-wrench-adjustable me-1"></i>Visible to administrators only
                </div>
                <code class="d-block" style="white-space:pre-wrap;word-break:break-word"><?= e((string) $detail) ?></code>
                <a class="d-inline-block mt-2" href="<?= e(url('admin/logs')) ?>">Open the full error log</a>
            </div>
        <?php endif; ?>

        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a class="btn btn-teal" href="<?= e(url('/')) ?>"><i class="bi bi-house me-1"></i>Go home</a>
            <a class="btn btn-outline-secondary" href="<?= e(url('buy')) ?>">Browse listings</a>
            <?php if ((int) $status === 403 && !auth_id()): ?>
                <a class="btn btn-outline-teal" href="<?= e(url('login')) ?>">Sign in</a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= e(url('contact')) ?>">Contact support</a>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
