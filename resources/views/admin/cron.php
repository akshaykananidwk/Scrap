<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Scheduler</h1>

<div class="alert <?= $health['ok'] ? 'alert-success' : 'alert-warning' ?>">
    <?= e((string) $health['message']) ?>
    <?php if (!empty($last_run)): ?>
        <div class="small mt-1">Last run recorded at <?= e(fmt_dt($last_run)) ?>.</div>
    <?php endif; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><h6 class="mb-0">Option A — real cron (recommended)</h6></div>
            <div class="card-body">
                <p class="small text-muted">Add this line to your hosting control panel's cron manager:</p>
                <div class="input-group input-group-sm">
                    <input class="form-control font-monospace" id="cli-command" value="<?= e($cli_command) ?>" readonly>
                    <button class="btn btn-outline-secondary" type="button" data-copy="#cli-command">Copy</button>
                </div>
                <p class="small text-muted mt-2 mb-0">
                    Runs every minute; each job still respects its own interval, so this is cheap.
                </p>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">Option B — web fallback
                    <?php if ($web_enabled): ?>
                        <span class="badge badge-soft-success ms-1">Enabled</span>
                    <?php else: ?>
                        <span class="badge text-bg-secondary ms-1">Disabled</span>
                    <?php endif; ?>
                </h6>
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    For hosts without cron access. Point an external uptime monitor at this URL, or let
                    ordinary page traffic trigger it.
                </p>
                <div class="input-group input-group-sm">
                    <input class="form-control font-monospace" id="cron-url" value="<?= e($cron_url) ?>" readonly>
                    <button class="btn btn-outline-secondary" type="button" data-copy="#cron-url">Copy</button>
                </div>
                <p class="small text-muted mt-2 mb-0">
                    Treat the key as a secret — anyone holding it can trigger the scheduler. Rotate it under
                    <a href="<?= e(url('admin/settings?group=cron')) ?>">Settings → Scheduler</a>.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white"><h6 class="mb-0">Jobs</h6></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
            <tr><th>Job</th><th>Every</th><th>Last run</th><th class="text-end">Duration</th>
                <th class="text-end">Runs</th><th class="text-end">Failures</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($jobs as $job): ?>
                <tr>
                    <td class="small">
                        <strong><?= e((string) $job['name']) ?></strong>
                        <div class="text-muted"><?= e((string) ($job['description'] ?? '')) ?></div>
                        <?php if ((int) $job['is_enabled'] !== 1): ?>
                            <span class="badge text-bg-secondary">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= (int) $job['interval_minutes'] ?> min</td>
                    <td class="small text-nowrap"><?= e($job['last_run_at'] ? time_ago($job['last_run_at']) : 'never') ?></td>
                    <td class="text-end small"><?= (int) $job['last_duration_ms'] ?> ms</td>
                    <td class="text-end small"><?= (int) $job['run_count'] ?></td>
                    <td class="text-end small <?= (int) $job['fail_count'] > 0 ? 'text-danger' : '' ?>"><?= (int) $job['fail_count'] ?></td>
                    <td>
                        <?= status_badge((string) $job['last_status']) ?>
                        <?php if (!empty($job['last_message'])): ?>
                            <div class="small text-muted text-truncate" style="max-width:200px"><?= e((string) $job['last_message']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <form method="post" action="<?= e(url('admin/cron/' . $job['job_key'] . '/run')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-teal" type="submit">Run now</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white"><h6 class="mb-0">Recent runs</h6></div>
    <?php if ($runs === []): ?>
        <div class="card-body small text-muted">The scheduler has not run yet.</div>
    <?php else: ?>
        <div class="table-responsive" style="max-height:420px;overflow:auto">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light sticky-top">
                <tr><th>When</th><th>Job</th><th>Trigger</th><th class="text-end">Affected</th>
                    <th class="text-end">Duration</th><th>Status</th><th>Message</th></tr>
                </thead>
                <tbody>
                <?php foreach ($runs as $run): ?>
                    <tr>
                        <td class="small text-nowrap"><?= e(fmt_dt($run['created_at'])) ?></td>
                        <td class="small font-monospace"><?= e((string) $run['job_key']) ?></td>
                        <td class="small"><?= e(label((string) $run['trigger_source'])) ?></td>
                        <td class="text-end small"><?= (int) $run['affected'] ?></td>
                        <td class="text-end small"><?= (int) $run['duration_ms'] ?> ms</td>
                        <td><?= status_badge((string) $run['status']) ?></td>
                        <td class="small text-truncate" style="max-width:280px"><?= e((string) ($run['message'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php View::endSection(); ?>
