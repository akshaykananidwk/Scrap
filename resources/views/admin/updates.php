<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-1">System updates</h1>
<p class="text-muted small mb-4">
    Pull new code straight from your GitHub repository — no SSH, no FTP, no command line.
</p>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Running version', (string) $current_version],
        ['Current commit', $current_commit !== '' ? substr((string) $current_commit, 0, 7) : '—'],
        ['Latest seen', $latest_version !== '' ? (string) $latest_version : '—'],
        ['Last checked', $last_check !== '' ? time_ago((string) $last_check) : 'never'],
    ] as [$label, $value]): ?>
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-value fs-6 font-monospace"><?= e($value) ?></div>
                <div class="stat-label"><?= e($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!$zip_available || !$curl_available || !$root_writable): ?>
    <div class="alert alert-warning">
        <strong>This server cannot apply updates yet:</strong>
        <ul class="small mb-0 mt-1">
            <?php if (!$curl_available): ?><li>The <code>curl</code> extension is not loaded — the archive cannot be downloaded.</li><?php endif; ?>
            <?php if (!$zip_available): ?><li>The <code>zip</code> extension is not loaded — the archive cannot be extracted.</li><?php endif; ?>
            <?php if (!$root_writable): ?><li>The application directory is not writable by PHP — files cannot be replaced.</li><?php endif; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($check !== null && !empty($check['ok'])): ?>
    <div class="card border-0 shadow-sm mb-4 <?= !empty($check['update_available']) ? 'border-start border-4 border-teal' : '' ?>">
        <div class="card-header bg-white">
            <h6 class="mb-0">
                <?= !empty($check['update_available'])
                    ? 'Update available: ' . e((string) $check['latest_version'])
                    : 'You are up to date' ?>
            </h6>
        </div>
        <div class="card-body">
            <div class="row g-3 small">
                <div class="col-md-6">
                    <div><span class="text-muted">Repository:</span> <?= e((string) $check['repository']) ?></div>
                    <div><span class="text-muted">Channel:</span> <?= e(label((string) $check['channel'])) ?>
                        (<?= e((string) $check['branch']) ?>)</div>
                    <div><span class="text-muted">Version:</span>
                        <?= e((string) $check['current_version']) ?> → <?= e((string) $check['latest_version']) ?></div>
                    <?php if (!empty($check['commit_short'])): ?>
                        <div><span class="text-muted">Commit:</span>
                            <code><?= e((string) $check['commit_short']) ?></code>
                            by <?= e((string) $check['commit_author']) ?>
                            <?= e(fmt_dt($check['commit_date'])) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($check['commits_behind'])): ?>
                        <div><span class="text-muted">Behind by:</span> <?= (int) $check['commits_behind'] ?> commit(s)</div>
                    <?php endif; ?>
                    <?php if (!empty($check['changed_file_count'])): ?>
                        <div><span class="text-muted">Files changed:</span> <?= (int) $check['changed_file_count'] ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <?php if (isset($check['compatible']) && !$check['compatible']): ?>
                        <div class="alert alert-danger mb-2 py-2">
                            <strong>Not compatible with this server.</strong>
                            <div><?= e((string) $check['compatibility_message']) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($check['commit_message'])): ?>
                        <div class="text-muted">Latest commit message</div>
                        <div class="bg-light rounded p-2 small"><?= e((string) $check['commit_message']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($check['release_notes'])): ?>
                        <div class="text-muted mt-2">Release notes</div>
                        <div class="bg-light rounded p-2 small" style="white-space:pre-line;max-height:160px;overflow:auto">
                            <?= e((string) $check['release_notes']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($check['changed_files'])): ?>
                <details class="mt-3">
                    <summary class="small">Show the <?= count($check['changed_files']) ?> changed file(s)</summary>
                    <ul class="small font-monospace mt-2 mb-0" style="max-height:220px;overflow:auto">
                        <?php foreach ($check['changed_files'] as $file): ?>
                            <li><?= e(is_array($file) ? (string) ($file['filename'] ?? '') : (string) $file) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>

            <?php if (!empty($check['update_available']) && ($check['compatible'] ?? true)): ?>
                <hr>
                <form method="post" action="<?= e(url('admin/updates/apply')) ?>" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-md-5">
                        <label class="form-label small required" for="confirm">
                            Type <strong>UPDATE</strong> to confirm
                        </label>
                        <input id="confirm" name="confirm" class="form-control" required placeholder="UPDATE" autocomplete="off">
                    </div>
                    <div class="col-md-7 d-grid">
                        <button class="btn btn-teal btn-lg" type="submit"
                            <?= ($zip_available && $curl_available && $root_writable) ? '' : 'disabled' ?>>
                            <i class="bi bi-download me-1"></i>Apply the update now
                        </button>
                    </div>
                    <div class="col-12">
                        <p class="small text-muted mb-0">
                            A full backup is taken first (when enabled), protected paths are never touched,
                            migrations run, the cache is cleared and a health check follows.
                            If any step fails the whole update is rolled back automatically.
                        </p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">GitHub repository</h6></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/updates/settings')) ?>">
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small required" for="github_owner">Owner</label>
                            <input id="github_owner" name="github_owner" class="form-control" required
                                   value="<?= e((string) $settings['owner']) ?>" placeholder="your-github-username">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small required" for="github_repo">Repository</label>
                            <input id="github_repo" name="github_repo" class="form-control" required
                                   value="<?= e((string) $settings['repo']) ?>" placeholder="scrapx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="github_branch">Branch</label>
                            <input id="github_branch" name="github_branch" class="form-control"
                                   value="<?= e((string) $settings['branch']) ?>" placeholder="main">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="github_token">Personal access token</label>
                            <input id="github_token" name="github_token" type="password" class="form-control"
                                   autocomplete="new-password"
                                   placeholder="<?= $token_saved ? 'Stored — leave blank to keep it' : 'Needed for private repositories' ?>">
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="github_use_releases" value="1" id="use_releases"
                                <?= $settings['use_releases'] ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="use_releases">
                                Track tagged releases instead of the branch head
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="update_auto_check" value="1" id="auto_check"
                                <?= $settings['auto_check'] ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="auto_check">
                                Check for updates automatically (daily, via the scheduler)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="update_backup_before" value="1" id="backup_before"
                                <?= $settings['backup_before'] ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="backup_before">
                                Take a full backup before every update (strongly recommended)
                            </label>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label small" for="update_protected_paths">Extra protected paths</label>
                        <textarea id="update_protected_paths" name="update_protected_paths" class="form-control font-monospace"
                                  rows="3" placeholder="One path per line"><?= e(implode("\n", (array) $custom_protected)) ?></textarea>
                        <div class="form-text">Files and folders here are never overwritten by an update.</div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-teal" type="submit">Save settings</button>
                    </div>
                </form>

                <form method="post" action="<?= e(url('admin/updates/test')) ?>" class="mt-2">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-secondary w-100" type="submit">Test the connection</button>
                </form>

                <?php if ($configured): ?>
                    <form method="post" action="<?= e(url('admin/updates/check')) ?>" class="mt-2">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-teal w-100" type="submit">
                            <i class="bi bi-arrow-repeat me-1"></i>Check for updates
                        </button>
                    </form>
                <?php else: ?>
                    <p class="small text-muted mt-3 mb-0">
                        Save the owner, repository and branch to enable update checks.
                    </p>
                <?php endif; ?>

                <p class="small text-muted mt-3 mb-0">
                    <strong>Your token is safe.</strong> It is encrypted with AES-256-GCM before it touches the
                    database and is never rendered into HTML, JavaScript, logs, API responses or error messages.
                </p>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Protected paths (never overwritten)</h6></div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($protected_paths as $path): ?>
                        <code class="badge text-bg-light border"><?= e((string) $path) ?></code>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <?php if ($manifest !== []): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><h6 class="mb-0">Local version manifest</h6></div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($manifest as $key => $value): ?>
                        <?php if (is_scalar($value)): ?>
                            <li class="list-group-item d-flex justify-content-between gap-2">
                                <span class="text-muted font-monospace"><?= e((string) $key) ?></span>
                                <strong class="text-end"><?= e((string) $value) ?></strong>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Update history</h6></div>
            <?php if ($history === []): ?>
                <div class="card-body small text-muted">No updates have been applied through this screen.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>When</th><th>Version</th><th class="text-end">Files</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($history as $update): ?>
                            <tr>
                                <td class="small text-nowrap"><?= e(fmt_dt($update['started_at'])) ?></td>
                                <td class="small font-monospace">
                                    <?= e((string) $update['from_version']) ?> → <?= e((string) $update['to_version']) ?>
                                </td>
                                <td class="text-end small">
                                    +<?= (int) $update['files_added'] ?> / ~<?= (int) $update['files_updated'] ?>
                                    / skip <?= (int) $update['files_skipped'] ?>
                                </td>
                                <td><?= status_badge((string) $update['status']) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-link p-0" href="<?= e(url('admin/updates/' . $update['id'])) ?>">Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6>Maintenance</h6>
                <form method="post" action="<?= e(url('admin/updates/clear-cache')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-secondary w-100" type="submit">
                        <i class="bi bi-trash3 me-1"></i>Clear the application cache
                    </button>
                </form>
                <p class="small text-muted mt-2 mb-0">
                    Safe to run any time — it only clears compiled route, settings and view caches.
                </p>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
