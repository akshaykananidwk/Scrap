<?php

use App\Core\View;

$steps = ['Welcome', 'Requirements', 'Database', 'Application', 'Install', 'Complete'];
$current = (int) ($step ?? 0);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Install') ?> | ScrapX Installer</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; }
        .install-shell { max-width: 880px; }
        .step-pill { width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center;
                     font-size: .8rem; font-weight: 600; background: #e2e8f0; color: #64748b; }
        .step-pill.active { background: #0f766e; color: #fff; }
        .step-pill.done { background: #14b8a6; color: #fff; }
        .step-line { flex: 1; height: 2px; background: #e2e8f0; }
        .step-line.done { background: #14b8a6; }
        .brand-mark { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center;
                      background: #0f766e; color: #fff; font-size: 1.2rem; }
    </style>
</head>
<body>
<div class="container install-shell py-4 py-lg-5">

    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center gap-2">
            <span class="brand-mark"><i class="bi bi-recycle"></i></span>
            <span class="h4 mb-0 fw-bold" style="color:#0f766e">ScrapX Installer</span>
        </div>
        <p class="text-muted small mt-2 mb-0">B2B Scrap Trading, Auction &amp; RFQ Marketplace</p>
    </div>

    <div class="d-flex align-items-center gap-2 mb-4 px-lg-4">
        <?php foreach ($steps as $index => $label): ?>
            <?php if ($index > 0): ?>
                <div class="step-line <?= $index <= $current ? 'done' : '' ?>"></div>
            <?php endif; ?>
            <div class="text-center" style="min-width:64px">
                <div class="step-pill mx-auto <?= $index === $current ? 'active' : ($index < $current ? 'done' : '') ?>">
                    <?= $index < $current ? '<i class="bi bi-check-lg"></i>' : $index + 1 ?>
                </div>
                <div class="small mt-1 <?= $index === $current ? 'fw-semibold' : 'text-muted' ?>" style="font-size:.7rem">
                    <?= e($label) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?= View::partial('partials/flash', ['flashes' => $flashes ?? []]) ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $messages): ?>
                    <?php foreach ((array) $messages as $message): ?>
                        <li><?= e((string) $message) ?></li>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?= View::yieldSection('content') ?>

    <p class="text-center text-muted small mt-4 mb-0">
        Need help? Read <code>docs/INSTALLATION.md</code> in your upload.
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?= View::yieldSection('scripts') ?>
</body>
</html>
