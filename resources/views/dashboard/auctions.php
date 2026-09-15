<?php

use App\Core\View;

View::section('content');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h4 mb-0">My auctions</h1>
    <a class="btn btn-teal" href="<?= e(url('dashboard/listings/create')) ?>"><i class="bi bi-hammer me-1"></i>New auction listing</a>
</div>

<ul class="nav nav-pills flex-wrap gap-1 mb-3">
    <?php $current = (string) ($filters['status'] ?? ''); ?>
    <?php foreach (['' => 'All', 'draft' => 'Draft', 'scheduled' => 'Scheduled', 'live' => 'Live',
                    'ended' => 'Ended', 'awarded' => 'Awarded', 'cancelled' => 'Cancelled'] as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $current === $key ? 'active' : '' ?>"
               href="<?= e(url('dashboard/auctions' . ($key !== '' ? '?status=' . $key : ''))) ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($auctions->isEmpty()): ?>
    <div class="empty-state bg-white rounded shadow-sm">
        <i class="bi bi-hammer"></i>
        <h5>No auctions yet</h5>
        <p class="text-muted small">Create a listing with the "Auction" sale method, then set the timings.</p>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Auction</th><th>Type</th><th class="text-end">Current</th>
                    <th class="text-end">Bids</th><th>Ends</th><th>Status</th><th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($auctions->items as $auction): ?>
                    <tr>
                        <td>
                            <a class="text-decoration-none d-block text-truncate" style="max-width:280px"
                               href="<?= e(url('dashboard/auctions/' . $auction['id'])) ?>"><?= e((string) $auction['title']) ?></a>
                            <span class="small text-muted"><?= e((string) $auction['reference']) ?></span>
                        </td>
                        <td class="small"><?= e(label((string) $auction['auction_type'])) ?></td>
                        <td class="text-end text-nowrap">
                            <strong><?= money($auction['current_price'] ?? $auction['starting_price']) ?></strong>
                            <?php if ($auction['reserve_price'] !== null): ?>
                                <div class="small <?= (int) $auction['reserve_met'] === 1 ? 'text-success' : 'text-muted' ?>">
                                    <?= (int) $auction['reserve_met'] === 1 ? 'Reserve met' : 'Below reserve' ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end small"><?= (int) $auction['bid_count'] ?> / <?= (int) $auction['bidder_count'] ?> bidders</td>
                        <td class="small text-nowrap">
                            <?= e(fmt_dt($auction['ends_at'])) ?>
                            <?php if ((int) $auction['extension_count'] > 0): ?>
                                <div class="small text-warning">Extended ×<?= (int) $auction['extension_count'] ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= status_badge((string) $auction['status']) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-teal" href="<?= e(url('dashboard/auctions/' . $auction['id'])) ?>">Manage</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center"><?= $auctions->links() ?></div>
<?php endif; ?>
<?php View::endSection(); ?>
