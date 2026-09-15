<?php

use App\Core\View;

View::section('content');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1">
            Update <?= e((string) $update['from_version']) ?> → <?= e((string) $update['to_version']) ?>
        </h1>
        <p class="text-muted small mb-0">
            Started <?= e(fmt_dt($update['started_at'])) ?>
            · <?= (int) $update['duration_ms'] ?> ms
            · <?= status_badge((string) $update['status']) ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/updates')) ?>">Back to updates</a>
        <?php if ($update['status'] === 'success' && !empty($update['backup_id'])): ?>
            <form method="post" action="<?= e(url('admin/updates/' . $update['id'] . '/rollback')) ?>"
                  data-confirm="Roll back to the pre-update backup? Files and database are both restored.">
                <?= csrf_field() ?>
                <button class="btn btn-outline-warning" type="submit">Roll back this update</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Files added', (string) (int) $update['files_added']],
        ['Files updated', (string) (int) $update['files_updated']],
        ['Files skipped', (string) (int) $update['files_skipped']],
        ['Downloaded', human_bytes((int) $update['download_bytes'])],
        ['Migrations', label((string) $update['migration_status'])],
        ['Health check', label((string) $update['health_status'])],
    ] as [$label, $value]): ?>
        <div class="col-4 col-md-2">
            <div class="stat-card text-center">
                <div class="stat-value fs-6"><?= e($value) ?></div>
                <div class="stat-label"><?= e($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($update['error_message'])): ?>
    <div class="alert alert-danger">
        <strong>The update failed.</strong>
        <pre class="small mb-0 mt-2"><?= e((string) $update['error_message']) ?></pre>
        <?php if ($update['status'] === 'rolled_back'): ?>
            <div class="mt-2">The previous version was restored automatically from the pre-update backup.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Files</h6></div>
            <div class="card-body">
                <?php if ($files_by_action === []): ?>
                    <p class="small text-muted mb-0">No file records for this update.</p>
                <?php endif; ?>
                <?php foreach ($files_by_action as $action => $files): ?>
                    <details class="mb-2" <?= in_array($action, ['failed', 'skipped_protected'], true) ? 'open' : '' ?>>
                        <summary class="small">
                            <span class="badge <?= $action === 'failed' ? 'text-bg-danger' : 'text-bg-light border' ?>">
                                <?= e(label((string) $action)) ?>
                            </span>
                            <?= count($files) ?> file(s)
                        </summary>
                        <ul class="small font-monospace mt-2 mb-0" style="max-height:240px;overflow:auto">
                            <?php foreach ($files as $file): ?>
                                <li>
                                    <?= e((string) $file['path']) ?>
                                    <?php if (!empty($file['note'])): ?>
                                        <span class="text-muted">— <?= e((string) $file['note']) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($update['log'])): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Step-by-step log</h6></div>
                <div class="card-body p-0">
                    <pre class="mb-0 p-3 small" style="max-height:420px;overflow:auto"><?= e((string) $update['log']) ?></pre>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Source</h6></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between gap-2">
                    <span class="text-muted">Channel</span><strong><?= e(label((string) $update['channel'])) ?></strong>
                </li>
                <?php if (!empty($update['commit_sha'])): ?>
                    <li class="list-group-item d-flex justify-content-between gap-2">
                        <span class="text-muted">Commit</span>
                        <code><?= e(substr((string) $update['commit_sha'], 0, 10)) ?></code>
                    </li>
                <?php endif; ?>
                <?php if (!empty($update['commit_author'])): ?>
                    <li class="list-group-item d-flex justify-content-between gap-2">
                        <span class="text-muted">Author</span><strong><?= e((string) $update['commit_author']) ?></strong>
                    </li>
                <?php endif; ?>
                <?php if (!empty($update['commit_message'])): ?>
                    <li class="list-group-item">
                        <span class="text-muted d-block mb-1">Message</span>
                        <?= e((string) $update['commit_message']) ?>
                    </li>
                <?php endif; ?>
                <li class="list-group-item d-flex justify-content-between gap-2">
                    <span class="text-muted">Cache</span><strong><?= e(label((string) $update['cache_status'])) ?></strong>
                </li>
                <?php if (!empty($update['migrations_applied'])): ?>
                    <li class="list-group-item">
                        <span class="text-muted d-block mb-1">Migrations applied</span>
                        <code class="small"><?= e((string) $update['migrations_applied']) ?></code>
                    </li>
                <?php endif; ?>
                <?php if (!empty($update['backup_id'])): ?>
                    <li class="list-group-item d-flex justify-content-between gap-2">
                        <span class="text-muted">Pre-update backup</span>
                        <a href="<?= e(url('admin/backups')) ?>">#<?= (int) $update['backup_id'] ?></a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <?php if (!empty($update['health_report'])): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Post-update health report</h6></div>
                <div class="card-body p-0">
                    <pre class="mb-0 p-3 small" style="max-height:320px;overflow:auto"><?= e((string) $update['health_report']) ?></pre>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection(); ?>
