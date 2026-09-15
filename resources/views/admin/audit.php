<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-1">Audit log</h1>
<p class="text-muted small mb-4">Every privileged action is recorded with the actor, IP and before/after values.</p>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
        <select name="action" class="form-select" data-auto-submit>
            <option value="">Any action</option>
            <?php foreach ($actions as $action): ?>
                <option value="<?= e((string) $action) ?>" <?= ($filters['action'] ?? '') === $action ? 'selected' : '' ?>>
                    <?= e(label((string) $action)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <input name="user_id" type="number" min="1" class="form-control" placeholder="User ID"
               value="<?= e((string) ($filters['user_id'] ?? '')) ?>">
    </div>
    <div class="col-md-2">
        <input name="from" type="date" class="form-control" value="<?= e((string) ($filters['from'] ?? '')) ?>">
    </div>
    <div class="col-md-2">
        <input name="to" type="date" class="form-control" value="<?= e((string) ($filters['to'] ?? '')) ?>">
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-teal" type="submit">Filter</button></div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
            <tr><th>When</th><th>Actor</th><th>Action</th><th>Entity</th><th>IP</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($logs->items as $log): ?>
                <tr>
                    <td class="small text-nowrap"><?= e(fmt_dt($log['created_at'])) ?></td>
                    <td class="small">
                        <?php if (!empty($log['user_id'])): ?>
                            <a href="<?= e(url('admin/users/' . $log['user_id'])) ?>">
                                <?= e((string) ($log['user_name'] ?? 'User #' . $log['user_id'])) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">system</span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><span class="badge text-bg-light border"><?= e(label((string) $log['action'])) ?></span></td>
                    <td class="small">
                        <?= e((string) ($log['entity_type'] ?? '—')) ?>
                        <?php if (!empty($log['entity_id'])): ?>#<?= (int) $log['entity_id'] ?><?php endif; ?>
                    </td>
                    <td class="small font-monospace"><?= e((string) ($log['ip'] ?? '—')) ?></td>
                    <td class="text-end">
                        <?php if (!empty($log['new_values']) || !empty($log['old_values'])): ?>
                            <button class="btn btn-sm btn-link p-0" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#log-<?= (int) $log['id'] ?>">Details</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (!empty($log['new_values']) || !empty($log['old_values'])): ?>
                    <tr class="collapse" id="log-<?= (int) $log['id'] ?>">
                        <td colspan="6" class="bg-light">
                            <div class="row g-2 small">
                                <?php if (!empty($log['old_values'])): ?>
                                    <div class="col-md-6">
                                        <strong>Before</strong>
                                        <pre class="mb-0 small"><?= e((string) $log['old_values']) ?></pre>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($log['new_values'])): ?>
                                    <div class="col-md-6">
                                        <strong>After</strong>
                                        <pre class="mb-0 small"><?= e((string) $log['new_values']) ?></pre>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4 d-flex justify-content-center"><?= $logs->links() ?></div>
<?php View::endSection(); ?>
