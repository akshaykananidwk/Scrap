<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Offers</h1>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <ul class="nav nav-pills flex-wrap gap-1 mb-0">
        <?php $current = (string) ($filters['status'] ?? ''); ?>
        <?php foreach (['' => 'All'] + $statuses as $key => $label): ?>
            <li class="nav-item">
                <a class="nav-link <?= $current === $key ? 'active' : '' ?>"
                   href="<?= e(url('dashboard/offers' . ($key !== '' ? '?status=' . $key : ''))) ?>"><?= e($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <form method="get" class="d-flex gap-2">
        <input type="hidden" name="status" value="<?= e($current) ?>">
        <select name="role" class="form-select form-select-sm" data-auto-submit>
            <option value="">Buying and selling</option>
            <option value="buyer" <?= ($filters['role'] ?? '') === 'buyer' ? 'selected' : '' ?>>Offers I made</option>
            <option value="seller" <?= ($filters['role'] ?? '') === 'seller' ? 'selected' : '' ?>>Offers I received</option>
        </select>
    </form>
</div>

<?php if ($offers->isEmpty()): ?>
    <div class="empty-state bg-white rounded shadow-sm">
        <i class="bi bi-tags"></i>
        <h5>No offers</h5>
        <p class="text-muted small">Offers you make or receive on listings appear here.</p>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr><th>Offer</th><th>Counterparty</th><th class="text-end">Amount</th>
                    <th class="text-end">Quantity</th><th>Made</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($offers->items as $offer): ?>
                    <tr>
                        <td>
                            <a class="text-decoration-none d-block text-truncate" style="max-width:240px"
                               href="<?= e(url('dashboard/offers/' . $offer['id'])) ?>">
                                <?= e((string) ($offer['listing_title'] ?? 'Direct offer')) ?>
                            </a>
                            <span class="small text-muted"><?= e((string) $offer['reference']) ?></span>
                        </td>
                        <td class="small">
                            <?= e((string) ($offer['direction'] === 'buyer_to_seller'
                                ? ($offer['buyer_business'] ?: $offer['buyer_name'])
                                : ($offer['seller_business'] ?: $offer['seller_name']))) ?>
                            <div class="text-muted"><?= $offer['direction'] === 'buyer_to_seller' ? 'Buyer' : 'Seller' ?></div>
                        </td>
                        <td class="text-end fw-semibold"><?= money($offer['amount']) ?></td>
                        <td class="text-end small"><?= e(qty($offer['quantity'], (string) $offer['unit_code'])) ?></td>
                        <td class="small text-nowrap"><?= e(time_ago($offer['created_at'])) ?></td>
                        <td><?= status_badge((string) $offer['status']) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-teal" href="<?= e(url('dashboard/offers/' . $offer['id'])) ?>">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center"><?= $offers->links() ?></div>
<?php endif; ?>
<?php View::endSection(); ?>
