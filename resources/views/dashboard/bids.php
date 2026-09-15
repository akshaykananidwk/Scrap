<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">My bids</h1>

<?php if ($watching !== []): ?>
    <h6 class="text-muted mb-2">Auctions you are bidding in right now</h6>
    <div class="row g-3 mb-4">
        <?php foreach ($watching as $auction): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <a class="text-decoration-none d-block text-truncate fw-semibold"
                           href="<?= e(url('auctions/' . $auction['id'])) ?>"><?= e((string) $auction['title']) ?></a>
                        <div class="d-flex justify-content-between align-items-end mt-2">
                            <div>
                                <div class="small text-muted">Current</div>
                                <div class="h6 mb-0"><?= money($auction['current_price']) ?></div>
                            </div>
                            <div class="countdown small" data-countdown="<?= (int) countdown_seconds($auction['ends_at']) ?>"
                                 data-ends-at="<?= e((string) $auction['ends_at']) ?>">
                                <span class="countdown-box"><span data-unit="hours">00</span><small>h</small></span>
                                <span class="countdown-box"><span data-unit="minutes">00</span><small>m</small></span>
                                <span class="countdown-box"><span data-unit="seconds">00</span><small>s</small></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<ul class="nav nav-pills flex-wrap gap-1 mb-3">
    <?php $current = (string) ($filters['status'] ?? ''); ?>
    <?php foreach (['' => 'All', 'winning' => 'Winning', 'outbid' => 'Outbid', 'won' => 'Won', 'lost' => 'Lost'] as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $current === $key ? 'active' : '' ?>"
               href="<?= e(url('dashboard/bids' . ($key !== '' ? '?status=' . $key : ''))) ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($bids->isEmpty()): ?>
    <div class="empty-state bg-white rounded shadow-sm">
        <i class="bi bi-lightning-charge"></i>
        <h5>No bids yet</h5>
        <a class="btn btn-teal" href="<?= e(url('auctions')) ?>">Browse live auctions</a>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr><th>Auction</th><th class="text-end">My bid</th><th class="text-end">Current</th>
                    <th>Placed</th><th>Ends</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($bids->items as $bid): ?>
                    <tr>
                        <td>
                            <a class="text-decoration-none d-block text-truncate" style="max-width:280px"
                               href="<?= e(url('auctions/' . $bid['auction_id'])) ?>">
                                <?= e((string) ($bid['auction_title'] ?? 'Auction')) ?>
                            </a>
                            <span class="small text-muted"><?= e((string) ($bid['auction_reference'] ?? '')) ?></span>
                        </td>
                        <td class="text-end fw-semibold"><?= money($bid['amount']) ?></td>
                        <td class="text-end"><?= money($bid['current_price'] ?? $bid['amount']) ?></td>
                        <td class="small text-nowrap"><?= e(fmt_dt($bid['placed_at'], 'd M, h:i A')) ?></td>
                        <td class="small text-nowrap"><?= e(fmt_dt($bid['ends_at'] ?? null)) ?></td>
                        <td><?= status_badge((string) $bid['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center"><?= $bids->links() ?></div>
<?php endif; ?>
<?php View::endSection(); ?>

<?php View::section('scripts'); ?>
<script src="<?= e(asset('js/auction.js')) ?>"></script>
<?php View::endSection(); ?>
