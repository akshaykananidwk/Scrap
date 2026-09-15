<?php

use App\Core\View;
use App\Services\SettingsService;

$siteName = (string) SettingsService::get('site_name', 'ScrapX');
$pageTitle = $title ?? $siteName;
$metaDescription = $meta_description ?? SettingsService::get('seo_meta_description', '');
$indexable = SettingsService::bool('seo_indexing_enabled', true);
?><!doctype html>
<html lang="<?= e(App\Core\Lang::locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?><?= str_contains($pageTitle, $siteName) ? '' : ' | ' . e($siteName) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <?php if (!$indexable): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <?php if (!empty($canonical)): ?>
        <link rel="canonical" href="<?= e($canonical) ?>">
    <?php endif; ?>

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:url" content="<?= e($canonical ?? base_url(ltrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/'))) ?>">
    <?php $ogImage = !empty($og_image) ? upload_url($og_image) : (SettingsService::get('seo_og_image') ? upload_url((string) SettingsService::get('seo_og_image')) : asset('img/og-default.svg')); ?>
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($metaDescription) ?>">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">

    <meta name="theme-color" content="#0f766e">
    <link rel="manifest" href="<?= e(base_url('manifest.webmanifest')) ?>">
    <?php $favicon = SettingsService::get('site_favicon'); ?>
    <link rel="icon" href="<?= e($favicon ? upload_url((string) $favicon) : asset('img/favicon.svg')) ?>">
    <link rel="apple-touch-icon" href="<?= e($favicon ? upload_url((string) $favicon) : asset('img/favicon.svg')) ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">

    <?php if (!empty($structured_data)): ?>
        <script type="application/ld+json"><?= json_encode(array_filter($structured_data, static fn ($v) => $v !== null), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <?= View::yieldSection('head') ?>
</head>
<body class="d-flex flex-column min-vh-100">

<?= View::partial('partials/header') ?>

<main class="flex-grow-1">
    <?= View::partial('partials/flash', ['flashes' => $flashes ?? []]) ?>
    <?= View::yieldSection('content') ?>
</main>

<?= View::partial('partials/footer') ?>
<?= View::partial('partials/mobile_nav') ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?= View::yieldSection('scripts') ?>
</body>
</html>
