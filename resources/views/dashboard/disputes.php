<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-1">Disputes</h1>
<p class="text-muted small mb-4">Raise a dispute from the order page. Our team reviews the full deal history.</p>

<?php if ($disputes->isEmpty()): ?>
    <div class="empty-state bg-white rounded shadow-sm">
        <i class="bi bi-shield-check"></i>
        <h5>No disputes</h5>
        <p class="text-muted small mb-0">Good news — every deal has gone through cleanly.</p>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr><th>Reference</th><th>Order</th><th>Category</th><th class="text-end">Claimed</th>
                    <th>Raised</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($disputes->items as $dispute): ?>
                    <tr>
                        <td class="small"><?= e((string) $dispute['reference']) ?></td>
                        <td class="small">
                            <a href="<?= e(url('dashboard/orders/' . $dispute['order_id'])) ?>">
                                <?= e((string) ($dispute['order_reference'] ?? '—')) ?>
                            </a>
                        </td>
                        <td class="small"><?= e($categories[$dispute['category']] ?? label((string) $dispute['category'])) ?></td>
                        <td class="text-end small"><?= $dispute['claimed_amount'] !== null ? money($dispute['claimed_amount']) : '—' ?></td>
                        <td class="small text-nowrap"><?= e(fmt_date($dispute['created_at'])) ?></td>
                        <td><?= status_badge((string) $dispute['status']) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-teal" href="<?= e(url('dashboard/disputes/' . $dispute['id'])) ?>">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center"><?= $disputes->links() ?></div>
<?php endif; ?>
<?php View::endSection(); ?>
