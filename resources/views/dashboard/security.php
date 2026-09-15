<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Security &amp; API access</h1>

<?php if (!empty($new_token)): ?>
    <div class="alert alert-success">
        <h6 class="alert-heading">Copy your new API token now</h6>
        <p class="small mb-2">This is the only time it is shown. We store a hash, not the token.</p>
        <code class="user-select-all d-block bg-white border rounded p-2"><?= e((string) $new_token) ?></code>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h6 class="mb-0">Account security</h6></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="small">Mobile verified</span>
                    <span><?= !empty($user['mobile_verified_at'])
                            ? '<span class="badge badge-soft-success">Yes</span>'
                            : '<a class="small" href="' . e(url('verify/mobile')) . '">Verify</a>' ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="small">Email verified</span>
                    <span><?= !empty($user['email_verified_at'])
                            ? '<span class="badge badge-soft-success">Yes</span>'
                            : '<span class="badge text-bg-secondary">No</span>' ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="small">Account last updated</span>
                    <span class="small text-muted"><?= e(!empty($user['updated_at']) ? fmt_date($user['updated_at']) : '—') ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="small">Change password</span>
                    <a class="small" href="<?= e(url('dashboard/profile')) ?>">Go to profile</a>
                </li>
            </ul>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Recent sign-in activity</h6></div>
            <?php if ($logins === []): ?>
                <div class="card-body small text-muted">No recorded sign-ins.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light"><tr><th>When</th><th>IP</th><th>Result</th></tr></thead>
                        <tbody>
                        <?php foreach ($logins as $login): ?>
                            <tr>
                                <td class="small text-nowrap"><?= e(fmt_dt($login['created_at'])) ?></td>
                                <td class="small"><?= e((string) $login['ip']) ?></td>
                                <td>
                                    <?= (int) $login['successful'] === 1
                                        ? '<span class="badge badge-soft-success">Success</span>'
                                        : '<span class="badge badge-soft-danger">Failed</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h6 class="mb-0">Create an API token</h6></div>
            <div class="card-body">
                <p class="small text-muted">
                    Tokens authenticate REST API calls (<code>Authorization: Bearer &lt;token&gt;</code>).
                    See <a href="<?= e(url('page/api')) ?>">the API guide</a> for endpoints.
                </p>
                <form method="post" action="<?= e(url('dashboard/api-tokens')) ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-8">
                        <input name="name" class="form-control" placeholder="Token name (e.g. Mobile app)" required>
                    </div>
                    <div class="col-4 d-grid">
                        <button class="btn btn-teal" type="submit">Create</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Your tokens</h6></div>
            <?php if ($tokens === []): ?>
                <div class="card-body small text-muted">No API tokens yet.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($tokens as $token): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <div class="min-w-0">
                                <div class="small fw-semibold"><?= e((string) $token['name']) ?></div>
                                <div class="small text-muted">
                                    Created <?= e(fmt_date($token['created_at'])) ?>
                                    · Last used <?= e($token['last_used_at'] ? time_ago($token['last_used_at']) : 'never') ?>
                                </div>
                            </div>
                            <?php if ($token['revoked_at'] !== null): ?>
                                <span class="badge text-bg-secondary">Revoked</span>
                            <?php else: ?>
                                <form method="post" action="<?= e(url('dashboard/api-tokens/' . $token['id'] . '/revoke')) ?>"
                                      data-confirm="Revoke this token? Apps using it stop working immediately.">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Revoke</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
