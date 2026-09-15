<?php

use App\Core\View;

View::section('content');
$isLive = $auction['status'] === 'live';
$isEnded = in_array($auction['status'], ['ended', 'awarded'], true);
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1"><?= e((string) $auction['title']) ?></h1>
        <p class="text-muted small mb-0">
            <?= e((string) $auction['reference']) ?> · <?= e(label((string) $auction['auction_type'])) ?> auction
            · <?= status_badge((string) $auction['status']) ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('auctions/' . $auction['id'])) ?>">
            <i class="bi bi-eye me-1"></i>Public page
        </a>
        <?php if (in_array($auction['status'], ['draft', 'scheduled', 'live'], true)): ?>
            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelAuction">Cancel auction</button>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Current price', $state['current_price_display'] ?? money($auction['current_price'])],
        ['Starting price', money($auction['starting_price'])],
        ['Bid increment', money($auction['bid_increment'])],
        ['Bids', (string) (int) $auction['bid_count']],
        ['Bidders', (string) (int) $auction['bidder_count']],
        ['Extensions', (int) $auction['extension_count'] . ' / ' . (int) $auction['max_extensions']],
    ] as [$label, $value]): ?>
        <div class="col-6 col-md-2">
            <div class="stat-card text-center">
                <div class="stat-value fs-6"><?= e((string) $value) ?></div>
                <div class="stat-label"><?= e($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($auction['reserve_price'] !== null): ?>
    <div class="alert <?= (int) $auction['reserve_met'] === 1 ? 'alert-success' : 'alert-warning' ?> small">
        Reserve price <?= money($auction['reserve_price']) ?> —
        <?= (int) $auction['reserve_met'] === 1
            ? 'met. You can award this auction.'
            : 'not yet met. You are free to award anyway, or let it end unsold.' ?>
        Bidders never see the reserve amount, only whether it is met.
    </div>
<?php endif; ?>

<?php if ($isLive): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2"
         id="auction-console" data-auction-id="<?= (int) $auction['id'] ?>"
         data-state-url="<?= e(url('auctions/' . $auction['id'] . '/state')) ?>">
        <div>
            <strong>Live now</strong> — ends <span id="auction-ends"><?= e(fmt_dt($auction['ends_at'])) ?></span>
        </div>
        <div class="countdown" data-countdown="<?= (int) countdown_seconds($auction['ends_at']) ?>"
             data-ends-at="<?= e((string) $auction['ends_at']) ?>">
            <span class="countdown-box"><span data-unit="days">00</span><small>d</small></span>
            <span class="countdown-box"><span data-unit="hours">00</span><small>h</small></span>
            <span class="countdown-box"><span data-unit="minutes">00</span><small>m</small></span>
            <span class="countdown-box"><span data-unit="seconds">00</span><small>s</small></span>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Bid history</h6>
                <span class="small text-muted">You see real names; the public page respects your masking setting.</span>
            </div>
            <?php if ($bids === []): ?>
                <div class="card-body small text-muted text-center py-4">No bids yet.</div>
            <?php else: ?>
                <div class="table-responsive" style="max-height:480px;overflow:auto">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light sticky-top">
                        <tr><th>#</th><th>Bidder</th><th class="text-end">Amount</th><th>Placed</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody id="bid-history-body">
                        <?php foreach ($bids as $index => $bid): ?>
                            <tr class="<?= $bid['status'] === 'winning' ? 'table-success' : '' ?>">
                                <td class="small text-muted"><?= count($bids) - $index ?></td>
                                <td class="small">
                                    <?= e((string) ($bid['display_name'] ?? $bid['full_name'] ?? 'Bidder')) ?>
                                    <?php if (!empty($bid['business_name'])): ?>
                                        <div class="text-muted"><?= e((string) $bid['business_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-semibold"><?= money($bid['amount']) ?></td>
                                <td class="small text-nowrap"><?= e(fmt_dt($bid['placed_at'], 'd M, h:i:s A')) ?></td>
                                <td><?= status_badge((string) $bid['status']) ?></td>
                                <td class="text-end">
                                    <?php if ($isEnded && $auction['status'] !== 'awarded'): ?>
                                        <form method="post" action="<?= e(url('dashboard/auctions/' . $auction['id'] . '/award')) ?>"
                                              data-confirm="Award this auction to this bidder? An order is created immediately.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="bid_id" value="<?= (int) $bid['id'] ?>">
                                            <button class="btn btn-sm btn-outline-teal" type="submit">Award</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($isEnded && $auction['status'] !== 'awarded' && $bids !== []): ?>
            <form method="post" action="<?= e(url('dashboard/auctions/' . $auction['id'] . '/award')) ?>" class="mb-4"
                  data-confirm="Award to the highest valid bidder and create the order?">
                <?= csrf_field() ?>
                <button class="btn btn-teal btn-lg w-100" type="submit">
                    <i class="bi bi-trophy me-1"></i>Award to the winning bid and create the order
                </button>
            </form>
        <?php endif; ?>

        <?php if ($auction['status'] === 'awarded' && !empty($auction['order_id'])): ?>
            <div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>This auction has been awarded and an order was created.</span>
                <a class="btn btn-sm btn-teal" href="<?= e(url('dashboard/orders/' . $auction['order_id'])) ?>">Open order</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h6 class="mb-0">Registered bidders</h6></div>
            <?php if ($bidders === []): ?>
                <div class="card-body small text-muted">Nobody has registered yet.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($bidders as $bidder): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="min-w-0">
                                    <div class="small fw-semibold">
                                        #<?= (int) $bidder['bidder_number'] ?> · <?= e((string) $bidder['full_name']) ?>
                                    </div>
                                    <div class="small text-muted text-truncate">
                                        <?= e((string) ($bidder['business_name'] ?? '—')) ?>
                                        · KYC <?= e(label((string) $bidder['kyc_status'])) ?>
                                    </div>
                                </div>
                                <?= status_badge((string) $bidder['status']) ?>
                            </div>
                            <?php if ((int) $auction['requires_approval'] === 1 || $bidder['status'] !== 'approved'): ?>
                                <div class="d-flex gap-1 mt-2">
                                    <?php foreach (['approved' => 'Approve', 'rejected' => 'Reject', 'blocked' => 'Block'] as $status => $label): ?>
                                        <?php if ($bidder['status'] !== $status): ?>
                                            <form method="post"
                                                  action="<?= e(url('dashboard/auctions/' . $auction['id'] . '/bidders/' . $bidder['user_id'])) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="status" value="<?= e($status) ?>">
                                                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= e($label) ?></button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h6 class="mb-0">Auction rules in force</h6></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between">
                    <span>Anti-sniping window</span>
                    <strong><?= (int) $auction['extension_window_seconds'] ?>s</strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>Extends by</span><strong><?= (int) $auction['extension_duration_seconds'] ?>s</strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>Max extensions</span><strong><?= (int) $auction['max_extensions'] ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>KYC required</span><strong><?= (int) $auction['requires_kyc'] === 1 ? 'Yes' : 'No' ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>Seller approves bidders</span><strong><?= (int) $auction['requires_approval'] === 1 ? 'Yes' : 'No' ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>Bidder names masked</span><strong><?= (int) $auction['mask_bidders'] === 1 ? 'Yes' : 'No' ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>Deposit</span>
                    <strong><?= (int) $auction['deposit_required'] === 1 ? money($auction['deposit_amount']) : 'Not required' ?></strong>
                </li>
            </ul>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Event log</h6></div>
            <?php if ($events === []): ?>
                <div class="card-body small text-muted">No events recorded.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush" style="max-height:320px;overflow:auto">
                    <?php foreach ($events as $event): ?>
                        <li class="list-group-item small">
                            <span class="badge text-bg-light border me-1"><?= e(label((string) $event['event_type'])) ?></span>
                            <?= e((string) $event['details']) ?>
                            <div class="text-muted"><?= e(fmt_dt($event['created_at'], 'd M, h:i:s A')) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="cancelAuction" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= e(url('dashboard/auctions/' . $auction['id'] . '/cancel')) ?>">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title">Cancel auction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Every registered bidder is notified with your reason. Cancelling a live auction with bids
                    affects your seller rating.
                </p>
                <label class="form-label required" for="cancel_reason">Reason</label>
                <textarea id="cancel_reason" name="reason" class="form-control" rows="3" required minlength="5"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep it running</button>
                <button type="submit" class="btn btn-danger">Cancel auction</button>
            </div>
        </form>
    </div>
</div>
<?php View::endSection(); ?>

<?php View::section('scripts'); ?>
<script src="<?= e(asset('js/auction.js')) ?>"></script>
<?php View::endSection(); ?>
