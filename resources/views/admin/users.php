<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Users</h1>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Total', (int) ($counts['total'] ?? 0), ''],
        ['Active', (int) ($counts['active'] ?? 0), 'active'],
        ['Pending', (int) ($counts['pending'] ?? 0), 'pending'],
        ['Suspended', (int) ($counts['suspended'] ?? 0), 'suspended'],
        ['KYC verified', (int) ($counts['kyc_verified'] ?? 0), ''],
        ['New today', (int) ($counts['today'] ?? 0), ''],
    ] as [$label, $value, $status]): ?>
        <div class="col-4 col-md-2">
            <a class="text-decoration-none" href="<?= e(url('admin/users' . ($status !== '' ? '?status=' . $status : ''))) ?>">
                <div class="stat-card text-center">
                    <div class="stat-value fs-6"><?= $value ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="search" name="q" class="form-control" placeholder="Name, mobile, email, business"
               value="<?= e((string) ($filters['q'] ?? '')) ?>">
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select" data-auto-submit>
            <option value="">Any status</option>
            <?php foreach (['active', 'pending', 'suspended', 'blocked'] as $status): ?>
                <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(label($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="role" class="form-select" data-auto-submit>
            <option value="">Any role</option>
            <?php foreach ($roles as $role): ?>
                <option value="<?= e((string) $role['slug']) ?>" <?= ($filters['role'] ?? '') === $role['slug'] ? 'selected' : '' ?>>
                    <?= e((string) $role['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="kyc_status" class="form-select" data-auto-submit>
            <option value="">Any KYC</option>
            <?php foreach (['none', 'pending', 'under_review', 'verified', 'rejected'] as $status): ?>
                <option value="<?= e($status) ?>" <?= ($filters['kyc_status'] ?? '') === $status ? 'selected' : '' ?>><?= e(label($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="state_id" class="form-select" data-auto-submit>
            <option value="">Any state</option>
            <?php foreach ($states as $state): ?>
                <option value="<?= (int) $state['id'] ?>" <?= (int) ($filters['state_id'] ?? 0) === (int) $state['id'] ? 'selected' : '' ?>>
                    <?= e((string) $state['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-1 d-grid"><button class="btn btn-outline-teal" type="submit">Go</button></div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
            <tr><th>User</th><th>Business</th><th>Type</th><th>KYC</th>
                <th class="text-end">Risk</th><th>Joined</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($users->items as $user): ?>
                <tr>
                    <td>
                        <a class="text-decoration-none" href="<?= e(url('admin/users/' . $user['id'])) ?>">
                            <?= e((string) $user['full_name']) ?>
                        </a>
                        <div class="small text-muted"><?= e((string) $user['mobile']) ?></div>
                    </td>
                    <td class="small text-truncate" style="max-width:200px"><?= e((string) ($user['business_name'] ?? '—')) ?></td>
                    <td class="small"><?= e(label((string) $user['account_type'])) ?></td>
                    <td><?= status_badge((string) $user['kyc_status']) ?></td>
                    <td class="text-end">
                        <span class="badge <?= (int) $user['risk_score'] >= 40 ? 'text-bg-danger' : ((int) $user['risk_score'] >= 20 ? 'text-bg-warning' : 'text-bg-light') ?>">
                            <?= (int) $user['risk_score'] ?>
                        </span>
                    </td>
                    <td class="small text-nowrap"><?= e(fmt_date($user['created_at'])) ?></td>
                    <td><?= status_badge((string) $user['status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-teal" href="<?= e(url('admin/users/' . $user['id'])) ?>">Open</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4 d-flex justify-content-center"><?= $users->links() ?></div>
<?php View::endSection(); ?>
