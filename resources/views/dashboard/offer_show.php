<?php

use App\Core\View;

View::section('content');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1">Offer <?= e((string) $offer['reference']) ?></h1>
        <p class="text-muted small mb-0">
            You are the <strong><?= e($my_role) ?></strong> ·
            <?= status_badge((string) $offer['status']) ?>
        </p>
    </div>
    <?php if (!empty($offer['listing_slug'])): ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('listing/' . $offer['listing_slug'])) ?>">View listing</a>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Negotiation history</h6></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($thread as $entry): ?>
                    <li class="list-group-item <?= (int) $entry['id'] === (int) $offer['id'] ? 'bg-light-subtle' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                            <div>
                                <span class="badge text-bg-light border">
                                    <?= $entry['direction'] === 'buyer_to_seller' ? 'Buyer → Seller' : 'Seller → Buyer' ?>
                                </span>
                                <span class="small text-muted ms-1"><?= e(fmt_dt($entry['created_at'])) ?></span>
                            </div>
                            <div class="text-end">
                                <div class="h6 mb-0"><?= money($entry['amount']) ?></div>
                                <div class="small text-muted"><?= e(qty($entry['quantity'])) ?></div>
                            </div>
                        </div>
                        <?php if (!empty($entry['message'])): ?>
                            <p class="small mt-2 mb-1"><?= e((string) $entry['message']) ?></p>
                        <?php endif; ?>
                        <?= status_badge((string) $entry['status']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if ($can_respond && $offer['status'] === 'pending'): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Respond</h6></div>
                <div class="card-body">
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <form method="post" action="<?= e(url('dashboard/offers/' . $offer['id'] . '/accept')) ?>"
                              data-confirm="Accept this offer? An order is created immediately at this price.">
                            <?= csrf_field() ?>
                            <button class="btn btn-teal" type="submit"><i class="bi bi-check2 me-1"></i>Accept &amp; create order</button>
                        </form>
                        <button class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#counterForm">
                            <i class="bi bi-arrow-left-right me-1"></i>Counter
                        </button>
                        <button class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#rejectForm">
                            <i class="bi bi-x me-1"></i>Reject
                        </button>
                    </div>

                    <div class="collapse" id="counterForm">
                        <form method="post" action="<?= e(url('dashboard/offers/' . $offer['id'] . '/counter')) ?>" class="row g-2">
                            <?= csrf_field() ?>
                            <div class="col-md-4">
                                <label class="form-label small required" for="counter_amount">Counter amount (₹)</label>
                                <input id="counter_amount" name="amount" type="number" step="0.01" min="0.01"
                                       class="form-control" required value="<?= e(dec($offer['amount'], 2)) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small required" for="counter_quantity">Quantity</label>
                                <input id="counter_quantity" name="quantity" type="number" step="0.001" min="0.001"
                                       class="form-control" required value="<?= e(dec($offer['quantity'], 3)) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small" for="counter_message">Message</label>
                                <input id="counter_message" name="message" class="form-control" maxlength="2000">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-teal" type="submit">Send counter-offer</button>
                            </div>
                        </form>
                    </div>

                    <div class="collapse" id="rejectForm">
                        <form method="post" action="<?= e(url('dashboard/offers/' . $offer['id'] . '/reject')) ?>" class="row g-2">
                            <?= csrf_field() ?>
                            <div class="col-md-9">
                                <input name="reason" class="form-control" placeholder="Reason (optional, shared with the other party)">
                            </div>
                            <div class="col-md-3 d-grid">
                                <button class="btn btn-outline-danger" type="submit">Reject offer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php elseif ($offer['status'] === 'pending'): ?>
            <div class="alert alert-info small d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Waiting for the other party to respond.</span>
                <form method="post" action="<?= e(url('dashboard/offers/' . $offer['id'] . '/cancel')) ?>"
                      data-confirm="Withdraw this offer?">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-secondary" type="submit">Withdraw offer</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($offer['status'] === 'accepted' && !empty($offer['order_id'])): ?>
            <div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Offer accepted — an order was created.</span>
                <a class="btn btn-sm btn-teal" href="<?= e(url('dashboard/orders/' . $offer['order_id'])) ?>">Open order</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Offer terms</h6></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Amount</span><strong><?= money($offer['amount']) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Quantity</span><strong><?= e(qty($offer['quantity'], (string) ($offer['unit_code'] ?? ''))) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Price basis</span><strong><?= e(label((string) $offer['price_basis'])) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Payment terms</span><strong><?= e(label((string) $offer['payment_terms'])) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">GST included</span><strong><?= (int) $offer['gst_included'] === 1 ? 'Yes' : 'No' ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Transport included</span><strong><?= (int) $offer['transport_included'] === 1 ? 'Yes' : 'No' ?></strong>
                </li>
                <?php if (!empty($offer['expires_at'])): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Valid until</span><strong><?= e(fmt_dt($offer['expires_at'])) ?></strong>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
