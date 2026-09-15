<?php

use App\Core\Auth;
use App\Core\View;

View::section('content');

$isLive = $auction['status'] === 'live';
$isReverse = $auction['auction_type'] === 'reverse';
?>

<div class="container py-4" id="auction-live"
     data-auction-id="<?= (int) $auction['id'] ?>"
     data-poll-interval="<?= (int) $poll_interval ?>">

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= e(url('auctions')) ?>">Auctions</a></li>
            <li class="breadcrumb-item active"><?= e((string) $auction['reference']) ?></li>
        </ol>
    </nav>

    <div id="auction-status">
        <?php if (!$isLive): ?>
            <div class="alert alert-secondary">
                <i class="bi bi-flag me-1"></i>
                This auction is <strong><?= e(label((string) $auction['status'])) ?></strong>.
                <?php if ($auction['status'] === 'scheduled'): ?>
                    Bidding opens <?= e(fmt_dt($auction['starts_at'])) ?>.
                <?php elseif (!empty($auction['cancel_reason'])): ?>
                    Reason: <?= e((string) $auction['cancel_reason']) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-3">
                <?php if ($images !== []): ?>
                    <img src="<?= e(upload_url((string) $images[0]['file_path'])) ?>" class="card-img-top"
                         style="max-height:380px;object-fit:contain;background:#f1f5f9" alt="">
                <?php endif; ?>
                <div class="card-body">
                    <div class="d-flex gap-2 mb-2 flex-wrap">
                        <span class="badge text-bg-<?= $isReverse ? 'primary' : 'danger' ?>">
                            <i class="bi bi-hammer me-1"></i><?= $isReverse ? 'Reverse auction' : 'Forward auction' ?>
                        </span>
                        <span class="badge text-bg-light border">Ref <?= e((string) $auction['reference']) ?></span>
                        <?php if ((int) $auction['requires_kyc'] === 1): ?>
                            <span class="badge badge-soft-warning">KYC required</span>
                        <?php endif; ?>
                        <?php if ((int) $auction['mask_bidders'] === 1): ?>
                            <span class="badge badge-soft-info">Bidders masked</span>
                        <?php endif; ?>
                        <span id="reserve-status"></span>
                    </div>

                    <h1 class="h4 mb-3"><?= e((string) $auction['title']) ?></h1>

                    <p class="small text-muted">
                        <?= $isReverse
                            ? 'Sellers compete by lowering their price. The lowest qualified offer at close wins.'
                            : 'Buyers compete by raising the price. The highest valid bid at close wins.' ?>
                    </p>

                    <div class="row g-3">
                        <?php
                        $specs = [
                            ['Quantity', qty($auction['quantity'], (string) $auction['unit_code'])],
                            ['Price basis', label((string) $auction['price_basis'])],
                            ['Starting price', money($auction['starting_price'])],
                            ['Bid increment', money($auction['bid_increment'])],
                            ['Opens', fmt_dt($auction['starts_at'])],
                            ['Closes', fmt_dt($auction['ends_at'])],
                            ['Payment terms', label((string) $auction['payment_terms'])],
                            ['Material', $auction['material_name'] ?? '—'],
                            ['Location', trim(((string) ($auction['city_name'] ?? '')) . ' ' . ((string) ($auction['state_name'] ?? ''))) ?: '—'],
                        ];
                        ?>
                        <?php foreach ($specs as [$label, $value]): ?>
                            <div class="col-6 col-md-4">
                                <div class="small text-muted"><?= e($label) ?></div>
                                <div class="fw-semibold small"><?= e((string) $value) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($auction['description'])): ?>
                        <h6 class="mt-4">Details</h6>
                        <p class="small" style="white-space:pre-line"><?= e((string) $auction['description']) ?></p>
                    <?php endif; ?>

                    <?php if ($listing !== null): ?>
                        <a class="btn btn-sm btn-outline-secondary mt-2" href="<?= e(url('listing/' . $listing['slug'])) ?>">
                            <i class="bi bi-box-seam me-1"></i>View the full listing
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Auction rules -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="mb-2"><i class="bi bi-info-circle me-1 text-teal"></i>Auction rules</h6>
                    <ul class="small mb-0 ps-3">
                        <li>Every bid is <strong>binding</strong> and cannot be retracted.</li>
                        <li>Bids must move by at least <?= e(money($auction['bid_increment'])) ?>
                            <?= $isReverse ? 'below' : 'above' ?> the current best.</li>
                        <?php if ((int) $auction['extension_window_seconds'] > 0): ?>
                            <li>
                                A bid in the last <?= (int) ((int) $auction['extension_window_seconds'] / 60) ?: 1 ?> minute(s)
                                extends the auction by <?= (int) ((int) $auction['extension_duration_seconds'] / 60) ?: 1 ?> minute(s),
                                up to <?= (int) $auction['max_extensions'] ?> times — so sniping does not work.
                            </li>
                        <?php endif; ?>
                        <?php if ($auction['reserve_price'] !== null): ?>
                            <li>A reserve price applies. Below it, the seller is not obliged to sell.</li>
                        <?php endif; ?>
                        <li>Every bid is validated on the server against the locked auction record.</li>
                    </ul>
                    <div id="extension-notice" class="alert alert-warning small mt-2 mb-0 d-none"></div>
                </div>
            </div>

            <!-- Activity -->
            <?php if ($events !== []): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="mb-3">Auction activity</h6>
                        <div class="timeline">
                            <?php foreach (array_slice($events, 0, 12) as $event): ?>
                                <div class="timeline-item done">
                                    <div class="small fw-semibold"><?= e(label((string) $event['event_type'])) ?></div>
                                    <div class="small text-muted"><?= e((string) $event['details']) ?></div>
                                    <div class="text-muted" style="font-size:.72rem"><?= e(fmt_dt(substr((string) $event['created_at'], 0, 19))) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bidding panel -->
        <div class="col-lg-5">
            <div class="bid-panel">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body text-center">
                        <div class="small text-muted"><?= $isReverse ? 'Lowest offer' : 'Current highest bid' ?></div>
                        <div class="price-display text-teal" id="current-price">
                            <?= e($state['current_price_display']) ?>
                        </div>

                        <div class="d-flex justify-content-center gap-4 small text-muted mb-3">
                            <span><strong id="bid-count"><?= (int) $state['bid_count'] ?></strong> bids</span>
                            <span><strong id="bidder-count"><?= (int) $state['bidder_count'] ?></strong> bidders</span>
                            <span><strong><?= (int) $auction['view_count'] ?></strong> views</span>
                        </div>

                        <div class="mb-2 small text-muted"><?= $isLive ? 'Closes in' : 'Auction closed' ?></div>
                        <div class="countdown-box js-countdown-box mb-3" id="auction-countdown"
                             data-seconds="<?= (int) $state['seconds_remaining'] ?>">
                            <div><span class="value" data-unit="d">00</span><span class="unit">days</span></div>
                            <div><span class="value" data-unit="h">00</span><span class="unit">hrs</span></div>
                            <div><span class="value" data-unit="m">00</span><span class="unit">min</span></div>
                            <div><span class="value" data-unit="s">00</span><span class="unit">sec</span></div>
                        </div>

                        <div class="small text-muted">
                            Closes <?= e(fmt_dt($auction['ends_at'])) ?>
                        </div>
                    </div>
                </div>

                <div id="my-position" class="mb-3"></div>

                <!-- Bid form -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <?php if ($is_owner): ?>
                            <div class="alert alert-info mb-2 small">
                                <i class="bi bi-person-badge me-1"></i>This is your auction.
                            </div>
                            <a class="btn btn-teal w-100" href="<?= e(url('dashboard/auctions/' . $auction['id'])) ?>">
                                <i class="bi bi-sliders me-1"></i>Manage auction
                            </a>
                        <?php elseif (!$eligibility['can_bid']): ?>
                            <div class="alert alert-warning small mb-2">
                                <i class="bi bi-exclamation-triangle me-1"></i><?= e($eligibility['message']) ?>
                            </div>
                            <?php if ($eligibility['reason'] === 'sign_in'): ?>
                                <a class="btn btn-teal w-100" href="<?= e(url('login')) ?>">Sign in to bid</a>
                            <?php elseif ($eligibility['reason'] === 'kyc'): ?>
                                <a class="btn btn-teal w-100" href="<?= e(url('dashboard/kyc')) ?>">Complete KYC</a>
                            <?php elseif ($eligibility['reason'] === 'verify'): ?>
                                <a class="btn btn-teal w-100" href="<?= e(url('verify/mobile')) ?>">Verify mobile</a>
                            <?php elseif ($eligibility['reason'] === 'approval' && $bidder === null): ?>
                                <form method="post" action="<?= e(url('auctions/' . $auction['id'] . '/register')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-teal w-100" type="submit">
                                        <i class="bi bi-person-plus me-1"></i>Register to bid
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <form id="bid-form">
                                <label class="form-label required" for="bid-amount">
                                    Your bid
                                    <span class="text-muted fw-normal">
                                        (<?= $isReverse ? 'at most' : 'at least' ?>
                                        <span id="next-valid"><?= e($state['next_valid_display']) ?></span>)
                                    </span>
                                </label>
                                <div class="input-group input-group-lg mb-2">
                                    <span class="input-group-text">₹</span>
                                    <input id="bid-amount" type="number" class="form-control" step="0.01" min="0"
                                           value="<?= e($state['next_valid_amount']) ?>" required
                                           inputmode="decimal" autocomplete="off">
                                </div>

                                <div class="d-flex gap-1 mb-3 flex-wrap">
                                    <?php foreach ([1, 2, 5, 10] as $multiplier): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1"
                                                data-bid-step="<?= (float) $auction['bid_increment'] * $multiplier * ($isReverse ? -1 : 1) ?>">
                                            <?= $isReverse ? '−' : '+' ?><?= e(money((float) $auction['bid_increment'] * $multiplier, false)) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>

                                <button class="btn btn-danger btn-lg w-100" type="submit" id="bid-submit" data-no-lock>
                                    <i class="bi bi-hammer me-1"></i>Place bid
                                </button>
                                <div id="bid-feedback" class="d-none"></div>
                                <p class="small text-muted mt-2 mb-0">
                                    By bidding you agree to the
                                    <a href="<?= e(url('page/auction-rules')) ?>" target="_blank" rel="noopener">auction rules</a>.
                                    Bids are binding.
                                </p>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bid history -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Bid history</h6>
                            <span class="small text-muted">
                                <i class="bi bi-arrow-repeat"></i> live
                            </span>
                        </div>
                        <div id="bid-history">
                            <?php if (empty($state['bids'])): ?>
                                <div class="text-muted small py-2">No bids yet — be the first.</div>
                            <?php else: ?>
                                <?php foreach ($state['bids'] as $index => $bid): ?>
                                    <div class="bid-row d-flex justify-content-between align-items-center <?= !empty($bid['is_you']) ? 'is-you' : '' ?>">
                                        <div>
                                            <span class="fw-semibold"><?= e((string) $bid['bidder']) ?></span>
                                            <?php if (!empty($bid['is_you'])): ?>
                                                <span class="badge text-bg-info ms-1">You</span>
                                            <?php endif; ?>
                                            <?php if ($index === 0): ?>
                                                <span class="badge text-bg-success ms-1">Leading</span>
                                            <?php endif; ?>
                                            <div class="text-muted" style="font-size:.72rem"><?= e((string) $bid['ago']) ?></div>
                                        </div>
                                        <div class="text-end fw-bold <?= $index === 0 ? 'text-teal' : '' ?>">
                                            <?= e((string) $bid['amount']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (Auth::check() && !$is_owner): ?>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-sm btn-outline-secondary flex-grow-1 js-favorite"
                                data-type="auction" data-id="<?= (int) $auction['id'] ?>">
                            <i class="bi bi-bookmark"></i> Watch
                        </button>
                        <a class="btn btn-sm btn-outline-secondary flex-grow-1"
                           href="<?= e(url('business/' . ($auction['business_slug'] ?? ''))) ?>">
                            <i class="bi bi-shop"></i> Seller
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($related)): ?>
        <h5 class="mt-5 mb-3">Other live auctions</h5>
        <div class="row g-3">
            <?php foreach ($related as $other): ?>
                <?php if ((int) $other['id'] === (int) $auction['id']) { continue; } ?>
                <div class="col-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <h6 class="small">
                                <a class="text-decoration-none text-dark" href="<?= e(url('auctions/' . $other['id'])) ?>">
                                    <?= e(mb_strimwidth((string) $other['title'], 0, 50, '…')) ?>
                                </a>
                            </h6>
                            <div class="fw-bold text-danger"><?= e(money($other['current_price'] ?? $other['starting_price'])) ?></div>
                            <div class="small text-muted">
                                <?= (int) $other['bid_count'] ?> bids ·
                                <span class="js-countdown" data-seconds="<?= countdown_seconds($other['ends_at']) ?>">—</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php View::endSection(); ?>

<?php View::section('scripts'); ?>
<script src="<?= e(asset('js/auction.js')) ?>"></script>
<?php View::endSection(); ?>
