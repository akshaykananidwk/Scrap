<?php

use App\Core\View;

View::section('content');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h4 mb-0">Invoice <?= e((string) $invoice['invoice_number']) ?></h1>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/orders/' . $invoice['order_id'])) ?>">Open order</a>
        <a class="btn btn-teal" href="<?= e(url('dashboard/invoices/' . $invoice['id'] . '/print')) ?>"
           target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print</a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <?= View::partial('order/invoice_body', ['invoice' => $invoice]) ?>
    </div>
</div>
<?php View::endSection(); ?>
