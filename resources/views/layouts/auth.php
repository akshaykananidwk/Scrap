<?php

use App\Core\View;
use App\Services\SettingsService;

$siteName = (string) SettingsService::get('site_name', 'ScrapX');
$logo = SettingsService::get('site_logo');
?><!doctype html>
<html lang="<?= e(App\Core\Lang::locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Sign in') ?> | <?= e($siteName) ?></title>
    <meta name="description" content="<?= e($meta_description ?? '') ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="theme-color" content="#0f766e">
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="auth-body">

<div class="container py-4 py-lg-5">
    <div class="text-center mb-4">
        <a class="d-inline-flex align-items-center gap-2 text-decoration-none" href="<?= e(url('/')) ?>">
            <?php if ($logo): ?>
                <img src="<?= e(upload_url((string) $logo)) ?>" alt="<?= e($siteName) ?>" height="40">
            <?php else: ?>
                <span class="brand-mark bg-teal text-white"><i class="bi bi-recycle"></i></span>
            <?php endif; ?>
            <span class="h4 mb-0 fw-bold text-teal"><?= e($siteName) ?></span>
        </a>
        <p class="text-muted small mt-2 mb-0"><?= e((string) SettingsService::get('site_tagline', '')) ?></p>
    </div>

    <?= View::partial('partials/flash', ['flashes' => $flashes ?? []]) ?>

    <?php if (!empty($errors)): ?>
        <div class="container" style="max-width:640px">
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $messages): ?>
                        <?php foreach ((array) $messages as $message): ?>
                            <li><?= e((string) $message) ?></li>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <?= View::yieldSection('content') ?>

    <div class="text-center mt-4 small text-muted">
        <a class="link-secondary text-decoration-none me-3" href="<?= e(url('page/terms')) ?>">Terms</a>
        <a class="link-secondary text-decoration-none me-3" href="<?= e(url('page/privacy')) ?>">Privacy</a>
        <a class="link-secondary text-decoration-none" href="<?= e(url('contact')) ?>">Help</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?= View::yieldSection('scripts') ?>
</body>
</html>
