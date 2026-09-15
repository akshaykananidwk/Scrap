<?php

use App\Core\View;

View::section('content');
$failed = array_values(array_filter($report['checks'], static fn (array $c): bool => !$c['passed']));
?>
<h1 class="h4 mb-4">System health</h1>

<div class="alert <?= $report['passed'] ? 'alert-success' : 'alert-warning' ?>">
    <?php if ($report['passed']): ?>
        <i class="bi bi-check2-circle me-1"></i>
        All <?= count($report['checks']) ?> checks pass.
    <?php else: ?>
        <i class="bi bi-exclamation-triangle me-1"></i>
        <?= count($failed) ?> of <?= count($report['checks']) ?> checks need attention.
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Checks</h6></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($report['checks'] as $check): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div class="min-w-0">
                            <span class="small fw-semibold"><?= e((string) $check['name']) ?></span>
                            <?php if (!$check['required']): ?>
                                <span class="badge text-bg-light border ms-1">Optional</span>
                            <?php endif; ?>
                            <div class="small text-muted text-truncate"><?= e((string) $check['detail']) ?></div>
                        </div>
                        <?php if ($check['passed']): ?>
                            <span class="badge badge-soft-success"><i class="bi bi-check2"></i></span>
                        <?php elseif ($check['required']): ?>
                            <span class="badge text-bg-danger">Failed</span>
                        <?php else: ?>
                            <span class="badge text-bg-warning">Warning</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Environment</h6></div>
            <ul class="list-group list-group-flush small">
                <?php foreach ([
                    'App version' => $environment['app_version'],
                    'Schema version' => $environment['schema_version'],
                    'PHP' => $environment['php_version'] . ' (' . $environment['php_sapi'] . ')',
                    'Server' => $environment['server_software'],
                    'Database' => $environment['database'] . ' — ' . $table_count . ' tables',
                    'Memory limit' => $environment['memory_limit'],
                    'Upload max' => $environment['upload_max_filesize'],
                    'POST max' => $environment['post_max_size'],
                    'Max execution' => $environment['max_execution_time'] . 's',
                    'Display timezone' => $environment['timezone_display'],
                    'Server time (UTC)' => $environment['server_time_utc'],
                    'Server time (local)' => $environment['server_time_local'],
                    'Disk free' => $environment['disk_free'],
                ] as $label => $value): ?>
                    <li class="list-group-item d-flex justify-content-between gap-2">
                        <span class="text-muted"><?= e($label) ?></span>
                        <strong class="text-end"><?= e((string) $value) ?></strong>
                    </li>
                <?php endforeach; ?>
                <li class="list-group-item">
                    <span class="text-muted d-block mb-1">Extensions loaded</span>
                    <?php foreach ($environment['extensions'] as $extension): ?>
                        <span class="badge text-bg-light border me-1"><?= e((string) $extension) ?></span>
                    <?php endforeach; ?>
                </li>
            </ul>
        </div>

        <?php if ($environment['pending_migrations'] !== []): ?>
            <div class="alert alert-warning small">
                <strong><?= count($environment['pending_migrations']) ?> migration(s) pending.</strong>
                <ul class="mb-0 mt-1">
                    <?php foreach ($environment['pending_migrations'] as $migration): ?>
                        <li class="font-monospace"><?= e((string) $migration) ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="mt-2">Run them from the update screen or with <code>php cli.php migrate</code>.</div>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Scheduler</h6></div>
            <div class="card-body small">
                <p class="mb-2">
                    <?php if ($cron['ok']): ?>
                        <span class="badge badge-soft-success">Healthy</span>
                    <?php else: ?>
                        <span class="badge text-bg-warning">Attention</span>
                    <?php endif; ?>
                    <?= e((string) $cron['message']) ?>
                </p>
                <a class="btn btn-sm btn-outline-teal" href="<?= e(url('admin/cron')) ?>">Open the scheduler</a>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Backups</h6></div>
            <div class="card-body small">
                <p class="mb-2">
                    <?= (int) $backups['count'] ?> archive(s) using <?= e((string) $backups['human']) ?>.
                </p>
                <a class="btn btn-sm btn-outline-teal" href="<?= e(url('admin/backups')) ?>">Manage backups</a>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
