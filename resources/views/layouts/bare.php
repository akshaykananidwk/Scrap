<?php

use App\Core\View;
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? site_name()) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?php // Pages on this layout still use the application's own classes — the
          // single-page guide is built from them — so the stylesheet belongs here
          // too, not only on the full layout. ?>
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <?= View::yieldSection('content') ?>
</div>
</body>
</html>
