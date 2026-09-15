<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Backups</h1>

<?php if (!$zip_available): ?>
    <div class="alert alert-warning small">
        The PHP <code>zip</code> extension is not loaded on this server, so archives cannot be created.
        Ask your host to enable it, or take database dumps through your control panel instead.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card text-center">
            <div class="stat-value fs-6"><?= (int) $usage['count'] ?></div>
            <div class="stat-label">Archives on disk</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card text-center">
            <div class="stat-value fs-6"><?= e((string) $usage['human']) ?></div>
            <div class="stat-label">Disk used</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card text-center">
            <div class="stat-value fs-6"><?= (int) $retention ?></div>
            <div class="stat-label">Retained before pruning</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="post" action="<?= e(url('admin/backups')) ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label small" for="backup_type">What to back up</label>
                <select id="backup_type" name="backup_type" class="form-select">
                    <option value="full">Everything (database + files)</option>
                    <option value="database">Database only</option>
                    <option value="files">Files only</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="include_uploads" value="1" id="include_uploads"
                        <?= $include_uploads ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="include_uploads">Include uploaded files</label>
                </div>
            </div>
            <div class="col-md-4 d-grid">
                <button class="btn btn-teal" type="submit" <?= $zip_available ? '' : 'disabled' ?>>
                    <i class="bi bi-hdd me-1"></i>Create a backup now
                </button>
            </div>
        </form>
        <p class="small text-muted mb-0 mt-2">
            Automatic scheduled backups are <?= $auto_enabled ? '<strong>enabled</strong>' : '<strong>disabled</strong>' ?>
            (change under <a href="<?= e(url('admin/settings?group=backup')) ?>">Settings → Backups</a>).
            A backup is also taken automatically before every system update.
        </p>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white"><h6 class="mb-0">Archives</h6></div>
    <?php if ($backups === []): ?>
        <div class="card-body small text-muted">No backups yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                <tr><th>Created</th><th>File</th><th>Type</th><th>Trigger</th><th>Version</th>
                    <th class="text-end">Size</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($backups as $backup): ?>
                    <tr>
                        <td class="small text-nowrap"><?= e(fmt_dt($backup['created_at'])) ?></td>
                        <td class="small font-monospace text-truncate" style="max-width:220px">
                            <?= e((string) $backup['filename']) ?>
                            <?php if (!$backup['exists']): ?>
                                <div class="text-danger">File missing on disk</div>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= e(label((string) $backup['backup_type'])) ?></td>
                        <td class="small"><?= e(label((string) $backup['trigger_type'])) ?></td>
                        <td class="small"><?= e((string) ($backup['app_version'] ?? '—')) ?></td>
                        <td class="text-end small"><?= e(human_bytes((int) $backup['size_bytes'])) ?></td>
                        <td>
                            <?= status_badge((string) $backup['status']) ?>
                            <?php if (!empty($backup['error_message'])): ?>
                                <div class="small text-danger text-truncate" style="max-width:180px">
                                    <?= e((string) $backup['error_message']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($backup['exists'] && $backup['status'] === 'completed'): ?>
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="<?= e(url('admin/backups/' . $backup['id'] . '/download')) ?>">Download</a>
                                <button class="btn btn-sm btn-outline-warning" type="button"
                                        data-bs-toggle="modal" data-bs-target="#restore-<?= (int) $backup['id'] ?>">
                                    Restore
                                </button>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('admin/backups/' . $backup['id'] . '/delete')) ?>"
                                  class="d-inline" data-confirm="Delete this backup archive?">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php foreach ($backups as $backup): ?>
    <?php if (!$backup['exists'] || $backup['status'] !== 'completed') { continue; } ?>
    <div class="modal fade" id="restore-<?= (int) $backup['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post"
                  action="<?= e(url('admin/backups/' . $backup['id'] . '/restore')) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Restore <?= e((string) $backup['filename']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger small mb-3">
                        This overwrites the current database with the contents of this archive.
                        Everything recorded since <?= e(fmt_dt($backup['created_at'])) ?> is lost.
                        The site is put into maintenance mode while the restore runs.
                    </div>
                    <label class="form-label required" for="confirm-<?= (int) $backup['id'] ?>">
                        Type <strong>RESTORE</strong> to confirm
                    </label>
                    <input id="confirm-<?= (int) $backup['id'] ?>" name="confirm" class="form-control"
                           required placeholder="RESTORE" autocomplete="off">
                    <?php if ($backup['backup_type'] === 'full'): ?>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="restore_files" value="1"
                                   id="files-<?= (int) $backup['id'] ?>">
                            <label class="form-check-label small" for="files-<?= (int) $backup['id'] ?>">
                                Also restore application files from this archive
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Restore this backup</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>
<?php View::endSection(); ?>
