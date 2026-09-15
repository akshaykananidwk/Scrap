<?php

use App\Core\View;

View::section('content');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h4 mb-0">My requirements</h1>
    <a class="btn btn-teal" href="<?= e(url('dashboard/requirements/create')) ?>"><i class="bi bi-plus-lg me-1"></i>Post a requirement</a>
</div>

<ul class="nav nav-pills flex-wrap gap-1 mb-3">
    <?php $current = (string) ($filters['status'] ?? ''); ?>
    <?php foreach (['' => 'All', 'open' => 'Open', 'closed' => 'Closed', 'fulfilled' => 'Fulfilled', 'cancelled' => 'Cancelled'] as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $current === $key ? 'active' : '' ?>"
               href="<?= e(url('dashboard/requirements' . ($key !== '' ? '?status=' . $key : ''))) ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($requirements->isEmpty()): ?>
    <div class="empty-state bg-white rounded shadow-sm">
        <i class="bi bi-card-checklist"></i>
        <h5>No requirements posted</h5>
        <p class="text-muted small">Tell sellers what you need — matching suppliers are notified automatically.</p>
        <a class="btn btn-teal" href="<?= e(url('dashboard/requirements/create')) ?>">Post a requirement</a>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr><th>Requirement</th><th class="text-end">Quantity</th><th class="text-end">Target</th>
                    <th>Frequency</th><th class="text-end">Offers</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($requirements->items as $requirement): ?>
                    <tr>
                        <td>
                            <a class="text-decoration-none d-block text-truncate" style="max-width:280px"
                               href="<?= e(url('dashboard/requirements/' . $requirement['id'])) ?>">
                                <?= e((string) $requirement['title']) ?>
                            </a>
                            <span class="small text-muted"><?= e((string) $requirement['reference']) ?></span>
                        </td>
                        <td class="text-end small text-nowrap"><?= e(qty($requirement['quantity'], (string) ($requirement['unit_code'] ?? ''))) ?></td>
                        <td class="text-end small text-nowrap">
                            <?= $requirement['target_price'] !== null ? money($requirement['target_price']) : '—' ?>
                        </td>
                        <td class="small"><?= e(label((string) $requirement['frequency'])) ?></td>
                        <td class="text-end"><span class="badge text-bg-light border"><?= (int) ($requirement['offer_count'] ?? 0) ?></span></td>
                        <td><?= status_badge((string) $requirement['status']) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-teal" href="<?= e(url('dashboard/requirements/' . $requirement['id'])) ?>">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center"><?= $requirements->links() ?></div>
<?php endif; ?>
<?php View::endSection(); ?>
