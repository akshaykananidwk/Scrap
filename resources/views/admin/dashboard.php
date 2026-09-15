<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Admin dashboard</h1>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Users', (int) ($overview['users']['total'] ?? 0), (int) ($overview['users']['today'] ?? 0) . ' today', 'bi-people', 'admin/users'],
        ['Listings', (int) ($overview['listings']['total'] ?? 0), (int) ($overview['listings']['pending'] ?? 0) . ' pending', 'bi-box-seam', 'admin/listings'],
        ['Live auctions', (int) ($overview['auctions']['live'] ?? 0), (int) $overview['active_bids'] . ' live bids', 'bi-hammer', 'admin/auctions'],
        ['Orders', (int) ($overview['orders']['total'] ?? 0), (int) ($overview['orders']['today'] ?? 0) . ' today', 'bi-bag-check', 'admin/orders'],
        ['GMV', money($overview['gmv']), 'all orders', 'bi-graph-up', 'admin/orders'],
        ['Platform revenue', money($overview['revenue']['total'] ?? 0), money($overview['revenue']['outstanding'] ?? 0) . ' outstanding', 'bi-cash-stack', 'admin/commissions'],
        ['KYC pending', (int) ($overview['kyc']['pending'] ?? 0), (int) ($overview['kyc']['verified'] ?? 0) . ' verified', 'bi-patch-check', 'admin/kyc'],
        ['Open disputes', (int) ($overview['disputes']['open_disputes'] ?? 0), (int) ($overview['disputes']['open_reports'] ?? 0) . ' reports', 'bi-shield-exclamation', 'admin/disputes'],
    ] as [$label, $value, $sub, $icon, $href]): ?>
        <div class="col-6 col-lg-3">
            <a class="text-decoration-none" href="<?= e(url($href)) ?>">
                <div class="stat-card h-100">
                    <i class="bi <?= e($icon) ?> stat-icon"></i>
                    <div class="stat-value fs-5"><?= e((string) $value) ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                    <div class="small text-muted"><?= e($sub) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<!-- Operational alerts: real state, not decoration -->
<div class="row g-3 mb-4">
    <?php if (!$cron['ok']): ?>
        <div class="col-md-6">
            <div class="alert alert-warning mb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong>Scheduler is not running.</strong>
                    <div class="small"><?= e((string) ($cron['message'] ?? 'No recent cron run recorded.')) ?></div>
                </div>
                <a class="btn btn-sm btn-warning" href="<?= e(url('admin/cron')) ?>">Set it up</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($queue_backlog > 50): ?>
        <div class="col-md-6">
            <div class="alert alert-warning mb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div><strong><?= (int) $queue_backlog ?> notifications queued.</strong>
                    <div class="small">The queue is drained by the scheduler.</div></div>
                <a class="btn btn-sm btn-warning" href="<?= e(url('admin/notifications')) ?>">Open queue</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ((int) ($fraud['open'] ?? 0) > 0): ?>
        <div class="col-md-6">
            <div class="alert alert-danger mb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div><strong><?= (int) $fraud['open'] ?> open risk flags.</strong>
                    <div class="small"><?= (int) ($fraud['critical'] ?? 0) ?> marked critical.</div></div>
                <a class="btn btn-sm btn-danger" href="<?= e(url('admin/fraud')) ?>">Review</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$update['configured']): ?>
        <div class="col-md-6">
            <div class="alert alert-secondary mb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div><strong>GitHub updates are not configured.</strong>
                    <div class="small">Running version <?= e((string) $update['current_version']) ?>.</div></div>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/updates')) ?>">Configure</a>
            </div>
        </div>
    <?php elseif (!empty($update['latest_version']) && $update['latest_version'] !== $update['current_version']): ?>
        <div class="col-md-6">
            <div class="alert alert-info mb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div><strong>Update available: <?= e((string) $update['latest_version']) ?></strong>
                    <div class="small">You are on <?= e((string) $update['current_version']) ?>.</div></div>
                <a class="btn btn-sm btn-teal" href="<?= e(url('admin/updates')) ?>">Review update</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['New registrations (30 days)', $series['registrations']],
        ['Orders (30 days)', $series['orders']],
        ['GMV (30 days)', $series['gmv']],
        ['Commission revenue (30 days)', $series['revenue']],
    ] as [$label, $data]): ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="mb-3"><?= e($label) ?></h6>
                    <?= View::partial('admin/_chart', ['series' => $data, 'label' => $label]) ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Listings awaiting approval</h6>
                <a class="small" href="<?= e(url('admin/listings?status=pending')) ?>">All pending</a>
            </div>
            <?php if ($pending_listings === []): ?>
                <div class="card-body small text-muted">Nothing waiting for review.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($pending_listings as $listing): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <div class="min-w-0">
                                <a class="small text-decoration-none d-block text-truncate"
                                   href="<?= e(url('listing/' . $listing['slug'])) ?>"><?= e((string) $listing['title']) ?></a>
                                <span class="small text-muted"><?= e((string) $listing['seller_name']) ?>
                                    · <?= e(time_ago($listing['created_at'])) ?></span>
                            </div>
                            <form method="post" action="<?= e(url('admin/listings/' . $listing['id'] . '/approve')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-teal" type="submit">Approve</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Recent registrations</h6></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($recent_users as $user): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div class="min-w-0">
                            <a class="small text-decoration-none d-block text-truncate"
                               href="<?= e(url('admin/users/' . $user['id'])) ?>"><?= e((string) $user['full_name']) ?></a>
                            <span class="small text-muted">
                                <?= e((string) ($user['business_name'] ?? $user['mobile'])) ?>
                                · <?= e(label((string) $user['account_type'])) ?>
                            </span>
                        </div>
                        <?= status_badge((string) $user['status']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Category performance</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light"><tr><th>Category</th><th class="text-end">Listings</th>
                        <th class="text-end">Orders</th><th class="text-end">Value</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td class="small"><?= e((string) $category['name']) ?></td>
                            <td class="text-end small"><?= (int) $category['listings'] ?></td>
                            <td class="text-end small"><?= (int) $category['orders'] ?></td>
                            <td class="text-end small"><?= money($category['value']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Live auctions</h6>
                <a class="small" href="<?= e(url('admin/auctions?status=live')) ?>">All live</a>
            </div>
            <?php if ($live_auctions === []): ?>
                <div class="card-body small text-muted">No auctions running.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($live_auctions as $auction): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <div class="min-w-0">
                                <a class="small text-decoration-none d-block text-truncate"
                                   href="<?= e(url('admin/auctions/' . $auction['id'])) ?>"><?= e((string) $auction['title']) ?></a>
                                <span class="small text-muted"><?= (int) $auction['bid_count'] ?> bids
                                    · ends <?= e(fmt_dt($auction['ends_at'])) ?></span>
                            </div>
                            <strong class="small"><?= money($auction['current_price']) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Recent orders</h6>
                <a class="small" href="<?= e(url('admin/orders')) ?>">All orders</a>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($recent_orders as $order): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div class="min-w-0">
                            <a class="small text-decoration-none d-block"
                               href="<?= e(url('admin/orders/' . $order['id'])) ?>"><?= e((string) $order['reference']) ?></a>
                            <span class="small text-muted text-truncate d-block">
                                <?= e((string) $order['buyer_name']) ?> ← <?= e((string) $order['seller_name']) ?>
                            </span>
                        </div>
                        <div class="text-end">
                            <div class="small fw-semibold"><?= money($order['final_amount']) ?></div>
                            <?= status_badge((string) $order['status']) ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Top sellers by value</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light"><tr><th>Business</th><th class="text-end">Orders</th><th class="text-end">Value</th></tr></thead>
                    <tbody>
                    <?php foreach ($top_sellers as $seller): ?>
                        <tr>
                            <td class="small">
                                <a href="<?= e(url('business/' . $seller['slug'])) ?>"><?= e((string) $seller['name']) ?></a>
                                <div class="text-muted"><?= e((string) ($seller['city_name'] ?? '—')) ?></div>
                            </td>
                            <td class="text-end small"><?= (int) $seller['orders'] ?></td>
                            <td class="text-end small"><?= money($seller['value']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
