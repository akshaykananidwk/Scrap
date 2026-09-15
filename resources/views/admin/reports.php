<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Content reports</h1>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
        <select name="status" class="form-select" data-auto-submit>
            <option value="">Any status</option>
            <?php foreach (['open', 'under_review', 'actioned', 'dismissed'] as $status): ?>
                <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(label($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="reason" class="form-select" data-auto-submit>
            <option value="">Any reason</option>
            <?php foreach ($reasons as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= ($filters['reason'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($reports->isEmpty()): ?>
    <div class="empty-state bg-white rounded shadow-sm"><i class="bi bi-flag"></i><p class="mb-0">No reports.</p></div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr><th>Reported</th><th>Type</th><th>Reason</th><th>Details</th><th>Reporter</th><th>When</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($reports->items as $report): ?>
                    <tr>
                        <td class="small">#<?= (int) $report['reportable_id'] ?></td>
                        <td class="small"><?= e(label((string) $report['reportable_type'])) ?></td>
                        <td class="small"><?= e($reasons[$report['reason']] ?? label((string) $report['reason'])) ?></td>
                        <td class="small text-truncate" style="max-width:260px"><?= e((string) ($report['details'] ?? '—')) ?></td>
                        <td class="small">
                            <a href="<?= e(url('admin/users/' . $report['reporter_id'])) ?>">
                                <?= e((string) ($report['reporter_name'] ?? 'User')) ?>
                            </a>
                        </td>
                        <td class="small text-nowrap"><?= e(fmt_date($report['created_at'])) ?></td>
                        <td><?= status_badge((string) $report['status']) ?></td>
                        <td class="text-end">
                            <?php if ($report['status'] === 'open' || $report['status'] === 'under_review'): ?>
                                <form method="post" action="<?= e(url('admin/reports/' . $report['id'] . '/handle')) ?>"
                                      class="d-flex gap-1 justify-content-end">
                                    <?= csrf_field() ?>
                                    <select name="status" class="form-select form-select-sm" style="width:auto">
                                        <option value="under_review">Under review</option>
                                        <option value="actioned">Actioned</option>
                                        <option value="dismissed">Dismissed</option>
                                    </select>
                                    <input name="notes" class="form-control form-control-sm" placeholder="Notes" style="max-width:150px">
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
    <div class="mt-4 d-flex justify-content-center"><?= $reports->links() ?></div>
<?php endif; ?>
<?php View::endSection(); ?>
