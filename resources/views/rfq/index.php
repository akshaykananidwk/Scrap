<?php

use App\Core\View;

View::section('content');
$query = $filters ?? [];
?>
<div class="bg-white border-bottom py-3">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-1">Open RFQs</h1>
            <p class="text-muted small mb-0">
                Buyers request quotations; suppliers quote line by line. The buyer compares and awards.
            </p>
        </div>
        <a class="btn btn-teal" href="<?= e(url('dashboard/rfq/create')) ?>"><i class="bi bi-plus-circle me-1"></i>Create an RFQ</a>
    </div>
</div>

<div class="container py-4">
    <form method="get" class="row g-2 mb-4">
        <div class="col-md-5">
            <input type="search" name="q" class="form-control" placeholder="RFQ title or reference"
                   value="<?= e((string) ($query['q'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <select name="category_id" class="form-select" data-auto-submit>
                <option value="">All categories</option>
                <?php foreach ($categories as $root): ?>
                    <option value="<?= (int) $root['id'] ?>" <?= (int) ($query['category_id'] ?? 0) === (int) $root['id'] ? 'selected' : '' ?>><?= e($root['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-grid"><button class="btn btn-outline-teal" type="submit">Filter</button></div>
    </form>

    <?php if ($rfqs->isEmpty()): ?>
        <div class="empty-state bg-white rounded shadow-sm">
            <i class="bi bi-clipboard-check"></i>
            <h5>No open RFQs</h5>
            <p class="mb-3">Buyers can invite specific suppliers or publish an RFQ to the whole marketplace.</p>
            <a class="btn btn-teal btn-sm" href="<?= e(url('dashboard/rfq/create')) ?>">Create an RFQ</a>
        </div>
    <?php else: ?>
        <div class="table-responsive bg-white rounded shadow-sm">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>RFQ</th><th>Buyer</th><th class="text-center">Items</th>
                        <th class="text-center">Quotes</th><th>Closes</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rfqs->items as $rfq): ?>
                    <tr>
                        <td>
                            <a class="fw-semibold text-decoration-none" href="<?= e(url('rfq/' . $rfq['id'])) ?>">
                                <?= e(mb_strimwidth((string) $rfq['title'], 0, 52, '…')) ?>
                            </a>
                            <div class="small text-muted">
                                <?= e((string) $rfq['reference']) ?>
                                <?php if (!empty($rfq['category_name'])): ?> · <?= e((string) $rfq['category_name']) ?><?php endif; ?>
                                <?php if ($rfq['visibility'] === 'invited'): ?>
                                    <span class="badge text-bg-light border ms-1">Invited only</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="small">
                            <?= e((string) ($rfq['business_name'] ?? '—')) ?>
                            <?php if (!empty($rfq['kyc_verified'])): ?>
                                <i class="bi bi-patch-check-fill text-teal" title="Verified"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= (int) $rfq['item_count'] ?></td>
                        <td class="text-center"><?= (int) $rfq['quote_count'] ?></td>
                        <td class="small">
                            <?= e(fmt_dt($rfq['closes_at'], 'd M, h:i A')) ?>
                            <div><?= status_badge((string) $rfq['status']) ?></div>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-teal" href="<?= e(url('rfq/' . $rfq['id'])) ?>">Quote</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-4 d-flex justify-content-center"><?= $rfqs->links() ?></div>
    <?php endif; ?>
</div>
<?php View::endSection(); ?>
