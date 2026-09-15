<?php

use App\Core\View;

View::section('content');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h4 mb-0">My RFQs</h1>
    <a class="btn btn-teal" href="<?= e(url('dashboard/rfq/create')) ?>"><i class="bi bi-plus-lg me-1"></i>Create an RFQ</a>
</div>

<?php if ($rfqs->isEmpty()): ?>
    <div class="empty-state bg-white rounded shadow-sm">
        <i class="bi bi-clipboard-check"></i>
        <h5>No RFQs yet</h5>
        <p class="text-muted small">
            An RFQ lets you collect comparable quotes for a multi-item purchase, then award the best one.
        </p>
        <a class="btn btn-teal" href="<?= e(url('dashboard/rfq/create')) ?>">Create an RFQ</a>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr><th>RFQ</th><th class="text-end">Items</th><th class="text-end">Quotes</th>
                    <th>Closes</th><th>Visibility</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($rfqs->items as $rfq): ?>
                    <tr>
                        <td>
                            <a class="text-decoration-none d-block text-truncate" style="max-width:280px"
                               href="<?= e(url('dashboard/rfq/' . $rfq['id'])) ?>"><?= e((string) $rfq['title']) ?></a>
                            <span class="small text-muted"><?= e((string) $rfq['reference']) ?></span>
                        </td>
                        <td class="text-end"><?= (int) ($rfq['item_count'] ?? 0) ?></td>
                        <td class="text-end"><span class="badge text-bg-light border"><?= (int) ($rfq['quote_count'] ?? 0) ?></span></td>
                        <td class="small text-nowrap"><?= e(fmt_dt($rfq['closes_at'])) ?></td>
                        <td class="small"><?= e(label((string) $rfq['visibility'])) ?></td>
                        <td><?= status_badge((string) $rfq['status']) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-teal" href="<?= e(url('dashboard/rfq/' . $rfq['id'])) ?>">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center"><?= $rfqs->links() ?></div>
<?php endif; ?>
<?php View::endSection(); ?>
