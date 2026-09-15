<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">My quotes</h1>

<?php if ($open_rfqs !== []): ?>
    <h6 class="text-muted mb-2">Open RFQs you are invited to</h6>
    <div class="row g-3 mb-4">
        <?php foreach ($open_rfqs as $rfq): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <a class="fw-semibold text-decoration-none d-block text-truncate"
                           href="<?= e(url('rfq/' . $rfq['id'])) ?>"><?= e((string) $rfq['title']) ?></a>
                        <div class="small text-muted mb-3">
                            <?= (int) ($rfq['item_count'] ?? 0) ?> items · closes <?= e(fmt_dt($rfq['closes_at'])) ?>
                        </div>
                        <a class="btn btn-sm btn-teal mt-auto" href="<?= e(url('rfq/' . $rfq['id'])) ?>">Submit a quote</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h5 class="mb-3">Quotes I have submitted</h5>
<?php if ($quotes->isEmpty()): ?>
    <div class="empty-state bg-white rounded shadow-sm">
        <i class="bi bi-file-earmark-text"></i>
        <p class="mb-0">You have not quoted on any RFQ yet.</p>
        <a class="btn btn-teal mt-2" href="<?= e(url('rfq')) ?>">Browse open RFQs</a>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr><th>RFQ</th><th class="text-end">My quote</th><th>Valid until</th>
                    <th>Submitted</th><th>My status</th><th>RFQ status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($quotes->items as $quote): ?>
                    <tr>
                        <td>
                            <a class="text-decoration-none d-block text-truncate" style="max-width:260px"
                               href="<?= e(url('rfq/' . $quote['rfq_id'])) ?>"><?= e((string) $quote['title']) ?></a>
                            <span class="small text-muted"><?= e((string) $quote['reference']) ?></span>
                        </td>
                        <td class="text-end fw-semibold"><?= money($quote['grand_total']) ?></td>
                        <td class="small"><?= e($quote['expires_at'] ? fmt_date($quote['expires_at']) : '—') ?></td>
                        <td class="small text-nowrap"><?= e(fmt_date($quote['created_at'])) ?></td>
                        <td>
                            <?php if ((int) ($quote['awarded_quote_id'] ?? 0) === (int) $quote['id']): ?>
                                <span class="badge badge-soft-success">Awarded</span>
                            <?php else: ?>
                                <?= status_badge((string) $quote['status']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= status_badge((string) $quote['rfq_status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center"><?= $quotes->links() ?></div>
<?php endif; ?>
<?php View::endSection(); ?>
