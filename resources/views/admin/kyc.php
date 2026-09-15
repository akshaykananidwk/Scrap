<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">KYC verification</h1>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['All', (int) ($counts['total'] ?? 0), ''],
        ['Pending', (int) ($counts['pending'] ?? 0), 'pending'],
        ['Under review', (int) ($counts['under_review'] ?? 0), 'under_review'],
        ['Verified', (int) ($counts['verified'] ?? 0), 'verified'],
        ['Rejected', (int) ($counts['rejected'] ?? 0), 'rejected'],
    ] as [$label, $value, $status]): ?>
        <div class="col-4 col-md-2">
            <a class="text-decoration-none" href="<?= e(url('admin/kyc' . ($status !== '' ? '?status=' . $status : ''))) ?>">
                <div class="stat-card text-center">
                    <div class="stat-value fs-6"><?= $value ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($submissions->isEmpty()): ?>
    <div class="empty-state bg-white rounded shadow-sm"><i class="bi bi-patch-check"></i><p class="mb-0">Nothing to review.</p></div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr><th>Business</th><th>Applicant</th><th>GSTIN</th><th>PAN</th><th>Submitted</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($submissions->items as $submission): ?>
                    <tr>
                        <td class="small"><?= e((string) ($submission['business_name'] ?? '—')) ?></td>
                        <td class="small">
                            <?= e((string) $submission['full_name']) ?>
                            <div class="text-muted"><?= e((string) ($submission['mobile'] ?? '')) ?></div>
                        </td>
                        <td class="small font-monospace"><?= e((string) ($submission['gstin'] ?? '—')) ?></td>
                        <td class="small font-monospace"><?= e((string) ($submission['pan'] ?? '—')) ?></td>
                        <td class="small text-nowrap"><?= e(fmt_dt($submission['submitted_at'] ?? $submission['created_at'])) ?></td>
                        <td><?= status_badge((string) $submission['status']) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-teal" href="<?= e(url('admin/kyc/' . $submission['id'])) ?>">Review</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center"><?= $submissions->links() ?></div>
<?php endif; ?>
<?php View::endSection(); ?>
