<?php

use App\Core\View;

View::section('content');
$kyc = (int) ($business['kyc_verified'] ?? 0) === 1;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1">Welcome back, <?= e((string) ($user['full_name'] ?? 'trader')) ?></h1>
        <p class="text-muted small mb-0">
            <?php if ($business !== null): ?>
                <?= e((string) $business['name']) ?> · <?= e(label((string) $business['business_type'])) ?>
            <?php else: ?>
                Complete your business profile to start trading.
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($is_seller): ?>
            <a class="btn btn-teal" href="<?= e(url('dashboard/listings/create')) ?>"><i class="bi bi-plus-lg me-1"></i>Sell scrap</a>
        <?php endif; ?>
        <?php if ($is_buyer): ?>
            <a class="btn btn-outline-teal" href="<?= e(url('dashboard/requirements/create')) ?>"><i class="bi bi-card-checklist me-1"></i>Post requirement</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($business === null): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-building me-1"></i>You have not set up a business profile yet.</span>
        <a class="btn btn-sm btn-warning" href="<?= e(url('dashboard/business')) ?>">Set it up</a>
    </div>
<?php elseif (!$kyc): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-patch-check me-1"></i>Verified businesses get more enquiries and can join restricted auctions.</span>
        <a class="btn btn-sm btn-teal" href="<?= e(url('dashboard/kyc')) ?>">Complete KYC</a>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php
    $tiles = [];
    if ($is_seller) {
        $tiles[] = ['Active listings', $stats['active_listings'], 'bi-box-seam', 'dashboard/listings'];
        $tiles[] = ['My auctions', $stats['auctions'], 'bi-hammer', 'dashboard/auctions'];
        $tiles[] = ['Orders (selling)', $stats['orders_selling'], 'bi-bag-check', 'dashboard/orders?role=seller'];
    }
    if ($is_buyer) {
        $tiles[] = ['Bids placed', $stats['bids'], 'bi-lightning-charge', 'dashboard/bids'];
        $tiles[] = ['Requirements', $stats['requirements'], 'bi-card-checklist', 'dashboard/requirements'];
        $tiles[] = ['Orders (buying)', $stats['orders_buying'], 'bi-bag', 'dashboard/orders?role=buyer'];
    }
    $tiles[] = ['Completed deals', $stats['completed'], 'bi-check2-circle', 'dashboard/orders?status=completed'];
    $tiles[] = ['Unread messages', $stats['unread_messages'], 'bi-chat-dots', 'dashboard/messages'];
    ?>
    <?php foreach (array_slice($tiles, 0, 8) as [$label, $value, $icon, $href]): ?>
        <div class="col-6 col-lg-3">
            <a class="text-decoration-none" href="<?= e(url($href)) ?>">
                <div class="stat-card h-100">
                    <i class="bi <?= e($icon) ?> stat-icon"></i>
                    <div class="stat-value"><?= (int) $value ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Recent orders</h6>
                <a class="small" href="<?= e(url('dashboard/orders')) ?>">View all</a>
            </div>
            <?php if ($recent_orders === []): ?>
                <div class="card-body text-center text-muted small py-4">No orders yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>Reference</th><th>Item</th><th class="text-end">Amount</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td><a href="<?= e(url('dashboard/orders/' . $order['id'])) ?>"><?= e((string) $order['reference']) ?></a></td>
                                <td class="small text-truncate" style="max-width:220px"><?= e((string) ($order['listing_title'] ?? $order['title'] ?? '—')) ?></td>
                                <td class="text-end"><?= money($order['final_amount']) ?></td>
                                <td><?= status_badge((string) $order['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($is_seller && $my_listings !== []): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">My latest listings</h6>
                    <a class="small" href="<?= e(url('dashboard/listings')) ?>">Manage</a>
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($my_listings as $listing): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <div class="min-w-0">
                                <a class="text-decoration-none d-block text-truncate"
                                   href="<?= e(url('dashboard/listings/' . $listing['id'] . '/edit')) ?>">
                                    <?= e((string) $listing['title']) ?>
                                </a>
                                <span class="small text-muted">
                                    <?= e(qty($listing['quantity'], (string) ($listing['unit_code'] ?? ''))) ?>
                                    · <?= (int) ($listing['view_count'] ?? 0) ?> views
                                </span>
                            </div>
                            <?= status_badge((string) $listing['status']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($is_buyer && $my_bids !== []): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">My recent bids</h6>
                    <a class="small" href="<?= e(url('dashboard/bids')) ?>">View all</a>
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($my_bids as $bid): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <div class="min-w-0">
                                <a class="text-decoration-none d-block text-truncate"
                                   href="<?= e(url('auctions/' . $bid['auction_id'])) ?>">
                                    <?= e((string) ($bid['auction_title'] ?? $bid['title'] ?? 'Auction')) ?>
                                </a>
                                <span class="small text-muted"><?= money($bid['amount']) ?> · <?= e(time_ago($bid['created_at'])) ?></span>
                            </div>
                            <?= status_badge((string) $bid['status']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <?php if ($pending_offers !== []): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0">Offers waiting on you</h6></div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($pending_offers as $offer): ?>
                        <li class="list-group-item">
                            <a class="d-block text-decoration-none text-truncate"
                               href="<?= e(url('dashboard/offers/' . $offer['id'])) ?>">
                                <?= e((string) ($offer['listing_title'] ?? 'Direct offer')) ?>
                            </a>
                            <div class="small text-muted">
                                <?= money($offer['amount']) ?> for <?= e(qty($offer['quantity'], (string) $offer['unit_code'])) ?>
                                · from <?= e((string) ($offer['direction'] === 'buyer_to_seller' ? $offer['buyer_name'] : $offer['seller_name'])) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($is_seller && $my_auctions !== []): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0">My auctions</h6></div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($my_auctions as $auction): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <div class="min-w-0">
                                <a class="d-block text-decoration-none text-truncate"
                                   href="<?= e(url('dashboard/auctions/' . $auction['id'])) ?>">
                                    <?= e((string) $auction['title']) ?>
                                </a>
                                <span class="small text-muted">
                                    <?= (int) ($auction['bid_count'] ?? 0) ?> bids ·
                                    <?= money($auction['current_price'] ?? $auction['start_price']) ?>
                                </span>
                            </div>
                            <?= status_badge((string) $auction['status']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($is_seller && $requirement_matches !== []): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Buyers looking for your material</h6>
                    <a class="small" href="<?= e(url('dashboard/requirement-matches')) ?>">All</a>
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($requirement_matches as $match): ?>
                        <li class="list-group-item">
                            <a class="d-block text-decoration-none text-truncate"
                               href="<?= e(url('wanted/' . $match['slug'])) ?>"><?= e((string) $match['title']) ?></a>
                            <div class="small text-muted"><?= e(qty($match['quantity'], (string) ($match['unit_code'] ?? ''))) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($is_buyer && $my_requirements !== []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">My requirements</h6></div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($my_requirements as $requirement): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <a class="text-decoration-none text-truncate"
                               href="<?= e(url('dashboard/requirements/' . $requirement['id'])) ?>">
                                <?= e((string) $requirement['title']) ?>
                            </a>
                            <span class="badge text-bg-light border"><?= (int) ($requirement['offer_count'] ?? 0) ?> offers</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection(); ?>
