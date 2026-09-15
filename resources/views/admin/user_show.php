<?php

use App\Core\View;

View::section('content');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1"><?= e((string) $user['full_name']) ?></h1>
        <p class="text-muted small mb-0">
            <?= e((string) $user['mobile']) ?>
            <?php if (!empty($user['email'])): ?> · <?= e((string) $user['email']) ?><?php endif; ?>
            · joined <?= e(fmt_date($user['created_at'])) ?>
            · <?= status_badge((string) $user['status']) ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <form method="post" action="<?= e(url('admin/users/' . $user['id'] . '/risk')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline-secondary" type="submit">Re-evaluate risk</button>
        </form>
        <div class="dropdown">
            <button class="btn btn-outline-danger dropdown-toggle" data-bs-toggle="dropdown" type="button">Account status</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach (['active' => 'Activate', 'suspended' => 'Suspend', 'blocked' => 'Block'] as $status => $label): ?>
                    <?php if ($user['status'] !== $status): ?>
                        <li>
                            <form method="post" action="<?= e(url('admin/users/' . $user['id'] . '/status')) ?>"
                                  data-confirm="Change this account to <?= e($label) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="<?= e($status) ?>">
                                <input name="reason" type="hidden" value="Changed by administrator">
                                <button class="dropdown-item" type="submit"><?= e($label) ?></button>
                            </form>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Listings', (int) $stats['listings']],
        ['Auctions', (int) $stats['auctions']],
        ['Bids', (int) $stats['bids']],
        ['Orders (buy)', (int) $stats['orders_buying']],
        ['Orders (sell)', (int) $stats['orders_selling']],
        ['Completed', (int) $stats['completed']],
    ] as [$label, $value]): ?>
        <div class="col-4 col-md-2">
            <div class="stat-card text-center">
                <div class="stat-value fs-6"><?= $value ?></div>
                <div class="stat-label"><?= e($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Recent listings</h6></div>
            <?php if ($listings === []): ?>
                <div class="card-body small text-muted">No listings.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($listings as $listing): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <a class="small text-truncate text-decoration-none" href="<?= e(url('listing/' . $listing['slug'])) ?>">
                                <?= e((string) $listing['title']) ?>
                            </a>
                            <?= status_badge((string) $listing['status']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Recent orders</h6></div>
            <?php if ($orders === []): ?>
                <div class="card-body small text-muted">No orders.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($orders as $order): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <a class="small text-decoration-none" href="<?= e(url('admin/orders/' . $order['id'])) ?>">
                                <?= e((string) $order['reference']) ?>
                            </a>
                            <span><?= money($order['final_amount']) ?> <?= status_badge((string) $order['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Audit trail</h6></div>
            <?php if ($audit === []): ?>
                <div class="card-body small text-muted">Nothing recorded.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush" style="max-height:280px;overflow:auto">
                    <?php foreach ($audit as $entry): ?>
                        <li class="list-group-item small">
                            <span class="badge text-bg-light border"><?= e(label((string) $entry['action'])) ?></span>
                            <span class="text-muted ms-1"><?= e(fmt_dt($entry['created_at'])) ?>
                                · <?= e((string) ($entry['ip'] ?? '')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Sign-in history</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>When</th><th>IP</th><th>Result</th></tr></thead>
                    <tbody>
                    <?php foreach ($logins as $login): ?>
                        <tr>
                            <td class="small text-nowrap"><?= e(fmt_dt($login['created_at'])) ?></td>
                            <td class="small"><?= e((string) $login['ip']) ?></td>
                            <td><?= (int) $login['successful'] === 1
                                    ? '<span class="badge badge-soft-success">OK</span>'
                                    : '<span class="badge badge-soft-danger">Failed</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Roles</h6></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/users/' . $user['id'] . '/roles')) ?>">
                    <?= csrf_field() ?>
                    <?php foreach ($roles as $role): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="roles[]" value="<?= e((string) $role['slug']) ?>"
                                   id="role-<?= (int) $role['id'] ?>" <?= in_array($role['slug'], $user_roles, true) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="role-<?= (int) $role['id'] ?>">
                                <?= e((string) $role['name']) ?>
                                <?php if ((int) $role['is_staff'] === 1): ?>
                                    <span class="badge text-bg-warning ms-1">Staff</span>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    <button class="btn btn-sm btn-teal mt-3 w-100" type="submit">Save roles</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">KYC</h6></div>
            <div class="card-body small">
                <p class="mb-2">Status: <?= status_badge((string) $kyc['status']) ?></p>
                <?php if ($kyc['missing'] !== []): ?>
                    <p class="text-muted mb-2">Missing: <?= e(implode(', ', $kyc['missing'])) ?></p>
                <?php endif; ?>
                <?php if ($kyc['verification'] !== null): ?>
                    <a class="btn btn-sm btn-outline-teal" href="<?= e(url('admin/kyc/' . $kyc['verification']['id'])) ?>">Open KYC file</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($fraud_flags !== []): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><h6 class="mb-0">Risk flags</h6></div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($fraud_flags as $flag): ?>
                        <li class="list-group-item small">
                            <span class="badge <?= $flag['severity'] === 'critical' ? 'text-bg-danger' : 'text-bg-warning' ?>">
                                <?= e(label((string) $flag['severity'])) ?>
                            </span>
                            <?= e((string) $flag['description']) ?>
                            <div class="text-muted"><?= e(fmt_dt($flag['created_at'])) ?> · <?= status_badge((string) $flag['status']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($wallet !== null): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><h6 class="mb-0">Wallet</h6></div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><span class="text-muted">Balance</span><strong><?= money($wallet['balance']) ?></strong></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Held</span><strong><?= money($wallet['held_amount'] ?? 0) ?></strong></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Reset password</h6></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/users/' . $user['id'] . '/password')) ?>"
                      data-confirm="Reset this user's password? They must use the new one to sign in.">
                    <?= csrf_field() ?>
                    <div class="input-group">
                        <input name="password" type="text" class="form-control" placeholder="New password" required minlength="8">
                        <button class="btn btn-outline-danger" type="submit">Reset</button>
                    </div>
                    <div class="form-text">Share it with the user over a channel you trust. The action is written to the audit log.</div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
