<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Auctions</h1>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['All', (int) ($counts['total'] ?? 0), ''],
        ['Live', (int) ($counts['live'] ?? 0), 'live'],
        ['Scheduled', (int) ($counts['scheduled'] ?? 0), 'scheduled'],
        ['Ended', (int) ($counts['ended'] ?? 0), 'ended'],
        ['Awarded', (int) ($counts['awarded'] ?? 0), 'awarded'],
        ['Unsold', (int) ($counts['unsold'] ?? 0), 'unsold'],
    ] as [$label, $value, $status]): ?>
        <div class="col-4 col-md-2">
            <a class="text-decoration-none" href="<?= e(url('admin/auctions' . ($status !== '' ? '?status=' . $status : ''))) ?>">
                <div class="stat-card text-center">
                    <div class="stat-value fs-6"><?= $value ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
            <tr><th>Auction</th><th>Seller</th><th>Type</th><th class="text-end">Current</th>
                <th class="text-end">Bids</th><th>Ends</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($auctions->items as $auction): ?>
                <tr>
                    <td>
                        <a class="text-decoration-none d-block text-truncate" style="max-width:240px"
                           href="<?= e(url('admin/auctions/' . $auction['id'])) ?>"><?= e((string) $auction['title']) ?></a>
                        <span class="small text-muted"><?= e((string) $auction['reference']) ?></span>
                    </td>
                    <td class="small">
                        <a href="<?= e(url('admin/users/' . $auction['owner_id'])) ?>">
                            <?= e((string) ($auction['business_name'] ?? $auction['seller_name'] ?? '—')) ?>
                        </a>
                    </td>
                    <td class="small"><?= e(label((string) $auction['auction_type'])) ?></td>
                    <td class="text-end"><?= money($auction['current_price']) ?></td>
                    <td class="text-end small"><?= (int) $auction['bid_count'] ?></td>
                    <td class="small text-nowrap">
                        <?= e(fmt_dt($auction['ends_at'])) ?>
                        <?php if ((int) $auction['extension_count'] > 0): ?>
                            <div class="text-warning">+<?= (int) $auction['extension_count'] ?> ext</div>
                        <?php endif; ?>
                    </td>
                    <td><?= status_badge((string) $auction['status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-teal" href="<?= e(url('admin/auctions/' . $auction['id'])) ?>">Open</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4 d-flex justify-content-center"><?= $auctions->links() ?></div>
<?php View::endSection(); ?>
