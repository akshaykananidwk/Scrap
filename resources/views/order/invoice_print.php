<?php

use App\Core\View;

View::section('content');
?>
<div class="invoice-sheet">
    <?= View::partial('order/invoice_body', ['invoice' => $invoice]) ?>
</div>
<div class="no-print text-center mt-4">
    <button class="btn btn-teal" type="button" onclick="window.print()">Print this invoice</button>
</div>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });</script>
<?php View::endSection(); ?>
