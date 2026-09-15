<?php

use App\Core\View;

View::section('content');
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h1 class="h4 mb-3">Welcome to the ScrapX installer</h1>
        <p>
            This wizard sets up your B2B scrap trading marketplace. It creates the database schema,
            seeds categories, materials, units, states and settings, and creates your administrator
            account. <strong>No manual file editing and no SQL import is required.</strong>
        </p>

        <div class="row g-3 my-4">
            <?php foreach ([
                ['bi-shield-check', 'Checks your server', 'PHP version, extensions and writable folders.'],
                ['bi-database', 'Builds the database', '13 migrations, 84 tables, all foreign keys and indexes.'],
                ['bi-boxes', 'Seeds the catalog', 'Scrap categories, materials, grades, units, HSN codes.'],
                ['bi-geo-alt', 'Seeds Indian locations', 'All states/UTs with GST codes, plus major trading cities.'],
                ['bi-person-badge', 'Creates your admin', 'Super administrator with every permission.'],
                ['bi-lock', 'Locks itself', 'After install, /install returns 403 automatically.'],
            ] as [$icon, $title, $text]): ?>
                <div class="col-md-6">
                    <div class="d-flex gap-2">
                        <i class="bi <?= e($icon) ?> text-teal fs-5"></i>
                        <div>
                            <div class="fw-semibold small"><?= e($title) ?></div>
                            <div class="text-muted small"><?= e($text) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="alert alert-light border small">
            <strong>Before you start, have ready:</strong> your database name, username and password
            (create the database in your hosting panel first), and the email address and mobile number
            for the administrator account.
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted small">Running PHP <?= e($php_version) ?></span>
            <a class="btn btn-teal btn-lg" href="<?= e(url('install/requirements')) ?>">
                Start installation <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
