<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Risk &amp; fraud</h1>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Open flags', (int) ($counts['open'] ?? 0)],
        ['Critical', (int) ($counts['critical'] ?? 0)],
        ['High-risk users', (int) ($counts['high_risk_users'] ?? 0)],
    ] as [$label, $value]): ?>
        <div class="col-4">
            <div class="stat-card text-center">
                <div class="stat-value fs-6"><?= $value ?></div>
                <div class="stat-label"><?= e($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <form method="get" class="row g-2 mb-3">
            <div class="col-md-4">
                <select name="status" class="form-select" data-auto-submit>
                    <option value="">Any status</option>
                    <?php foreach (['open', 'reviewed', 'confirmed', 'dismissed'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>>
                            <?= e(label($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <select name="severity" class="form-select" data-auto-submit>
                    <option value="">Any severity</option>
                    <?php foreach (['low', 'medium', 'high', 'critical'] as $severity): ?>
                        <option value="<?= e($severity) ?>" <?= ($filters['severity'] ?? '') === $severity ? 'selected' : '' ?>>
                            <?= e(label($severity)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($flags->isEmpty()): ?>
            <div class="empty-state bg-white rounded shadow-sm">
                <i class="bi bi-shield-check"></i><p class="mb-0">No flags raised.</p>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>User</th><th>Rule</th><th>Detail</th><th>Severity</th><th>Raised</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($flags->items as $flag): ?>
                            <tr>
                                <td class="small">
                                    <a href="<?= e(url('admin/users/' . $flag['user_id'])) ?>">
                                        <?= e((string) ($flag['full_name'] ?? 'User #' . $flag['user_id'])) ?>
                                    </a>
                                </td>
                                <td class="small"><?= e($rules[$flag['rule']][0] ?? label((string) $flag['rule'])) ?></td>
                                <td class="small text-truncate" style="max-width:240px"><?= e((string) $flag['description']) ?></td>
                                <td>
                                    <span class="badge <?= in_array($flag['severity'], ['critical', 'high'], true) ? 'text-bg-danger' : 'text-bg-warning' ?>">
                                        <?= e(label((string) $flag['severity'])) ?>
                                    </span>
                                </td>
                                <td class="small text-nowrap"><?= e(fmt_date($flag['created_at'])) ?></td>
                                <td><?= status_badge((string) $flag['status']) ?></td>
                                <td class="text-end">
                                    <?php if ($flag['status'] === 'open'): ?>
                                        <form method="post" action="<?= e(url('admin/fraud/' . $flag['id'] . '/resolve')) ?>"
                                              class="d-flex gap-1 justify-content-end">
                                            <?= csrf_field() ?>
                                            <select name="status" class="form-select form-select-sm" style="width:auto">
                                                <option value="reviewed">Reviewed</option>
                                                <option value="confirmed">Confirmed</option>
                                                <option value="dismissed">Dismissed</option>
                                            </select>
                                            <button class="btn btn-sm btn-outline-teal" type="submit">Save</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4 d-flex justify-content-center"><?= $flags->links() ?></div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Highest risk accounts</h6></div>
            <?php if ($high_risk === []): ?>
                <div class="card-body small text-muted">Nothing above the threshold.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($high_risk as $user): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center small">
                            <div class="min-w-0">
                                <a href="<?= e(url('admin/users/' . $user['id'])) ?>"><?= e((string) $user['full_name']) ?></a>
                                <div class="text-muted text-truncate"><?= e((string) ($user['business_name'] ?? $user['mobile'])) ?></div>
                            </div>
                            <span class="badge text-bg-danger"><?= (int) $user['risk_score'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Detection rules</h6></div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($rules as $key => [$description, $severity, $score]): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <span><?= e($description) ?></span>
                        <span class="text-nowrap">
                            <span class="badge <?= in_array($severity, ['critical', 'high'], true) ? 'text-bg-danger' : 'text-bg-warning' ?>">
                                <?= e(label($severity)) ?>
                            </span>
                            <span class="badge text-bg-light border">+<?= (int) $score ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
