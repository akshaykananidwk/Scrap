<?php

use App\Core\View;

View::section('content');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1"><?= e((string) $auction['title']) ?></h1>
        <p class="text-muted small mb-0">
            <?= e((string) $auction['reference']) ?> · <?= e(label((string) $auction['auction_type'])) ?>
            · <?= status_badge((string) $auction['status']) ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('auctions/' . $auction['id'])) ?>" target="_blank" rel="noopener">Public page</a>
        <?php if (in_array($auction['status'], ['live', 'scheduled'], true)): ?>
            <form method="post" action="<?= e(url('admin/auctions/' . $auction['id'] . '/close')) ?>"
                  data-confirm="Close this auction now? No further bids are accepted.">
                <?= csrf_field() ?>
                <button class="btn btn-outline-warning" type="submit">Close now</button>
            </form>
            <form method="post" action="<?= e(url('admin/auctions/' . $auction['id'] . '/cancel')) ?>"
                  data-confirm="Cancel this auction? All bidders are notified.">
                <?= csrf_field() ?>
                <input type="hidden" name="reason" value="Cancelled by platform administrator">
                <button class="btn btn-outline-danger" type="submit">Cancel</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Starting', money($auction['starting_price'])],
        ['Current', money($auction['current_price'])],
        ['Reserve', $auction['reserve_price'] !== null ? money($auction['reserve_price']) : 'None'],
        ['Increment', money($auction['bid_increment'])],
        ['Bids', (string) (int) $auction['bid_count']],
        ['Bidders', (string) (int) $auction['bidder_count']],
    ] as [$label, $value]): ?>
        <div class="col-4 col-md-2">
            <div class="stat-card text-center">
                <div class="stat-value fs-6"><?= e($value) ?></div>
                <div class="stat-label"><?= e($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Full bid log</h6>
                <span class="small text-muted">Identity and IP shown for fraud review</span>
            </div>
            <div class="table-responsive" style="max-height:520px;overflow:auto">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light sticky-top">
                    <tr><th>#</th><th>Bidder</th><th class="text-end">Amount</th><th>Placed</th><th>IP</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($raw_bids as $bid): ?>
                        <tr class="<?= $bid['status'] === 'winning' ? 'table-success' : ($bid['status'] === 'retracted' ? 'table-danger' : '') ?>">
                            <td class="small text-muted"><?= (int) $bid['id'] ?></td>
                            <td class="small">
                                <a href="<?= e(url('admin/users/' . $bid['user_id'])) ?>"><?= e((string) $bid['full_name']) ?></a>
                                <div class="text-muted"><?= e((string) $bid['mobile']) ?></div>
                            </td>
                            <td class="text-end fw-semibold"><?= money($bid['amount']) ?></td>
                            <td class="small text-nowrap"><?= e(fmt_dt($bid['placed_at'], 'd M, h:i:s A')) ?></td>
                            <td class="small font-monospace"><?= e((string) ($bid['ip'] ?? '—')) ?></td>
                            <td><?= status_badge((string) $bid['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Registered bidders</h6></div>
            <?php if ($bidders === []): ?>
                <div class="card-body small text-muted">No registrations.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($bidders as $bidder): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2 small">
                            <div class="min-w-0">
                                #<?= (int) $bidder['bidder_number'] ?>
                                <a href="<?= e(url('admin/users/' . $bidder['user_id'])) ?>"><?= e((string) $bidder['full_name']) ?></a>
                                <div class="text-muted text-truncate">
                                    <?= e((string) ($bidder['business_name'] ?? '—')) ?> · KYC <?= e(label((string) $bidder['kyc_status'])) ?>
                                </div>
                            </div>
                            <?= status_badge((string) $bidder['status']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Event log</h6></div>
            <ul class="list-group list-group-flush" style="max-height:400px;overflow:auto">
                <?php foreach ($events as $event): ?>
                    <li class="list-group-item small">
                        <span class="badge text-bg-light border"><?= e(label((string) $event['event_type'])) ?></span>
                        <?= e((string) $event['details']) ?>
                        <div class="text-muted"><?= e(fmt_dt($event['created_at'], 'd M, h:i:s A')) ?>
                            <?php if (!empty($event['ip'])): ?>· <?= e((string) $event['ip']) ?><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
