<?php

use App\Core\View;
use App\Models\Order;

View::section('content');

$isSeller = $my_role === 'seller';
$isBuyer = $my_role === 'buyer';
$isClosed = in_array($order['status'], ['completed', 'cancelled'], true);
$flow = ['pending', 'confirmed', 'processing', 'ready_for_pickup', 'picked_up', 'in_transit',
    'delivered', 'weighment', 'payment_pending', 'paid', 'completed'];
$currentIndex = array_search((string) $order['status'], $flow, true);
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1">Order <?= e((string) $order['reference']) ?></h1>
        <p class="text-muted small mb-0">
            Placed <?= e(fmt_dt($order['created_at'])) ?> · you are the <strong><?= e($my_role) ?></strong>
            · <?= status_badge((string) $order['status']) ?>
            · payment <?= status_badge((string) $order['payment_status']) ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($invoices !== []): ?>
            <a class="btn btn-outline-secondary" href="<?= e(url('dashboard/invoices/' . $invoices[0]['id'])) ?>">
                <i class="bi bi-receipt me-1"></i>Invoice
            </a>
        <?php elseif ($isSeller): ?>
            <form method="post" action="<?= e(url('dashboard/orders/' . $order['id'] . '/invoice')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-teal" type="submit"><i class="bi bi-receipt me-1"></i>Raise GST invoice</button>
            </form>
        <?php endif; ?>
        <form method="post" action="<?= e(url('dashboard/messages/start')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="seller_id" value="<?= (int) ($isSeller ? $order['buyer_id'] : $order['seller_id']) ?>">
            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
            <input type="hidden" name="body" value="About order <?= e((string) $order['reference']) ?>:">
            <button class="btn btn-outline-teal" type="submit"><i class="bi bi-chat-dots me-1"></i>Message</button>
        </form>
    </div>
</div>

<!-- Lifecycle timeline -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="order-flow d-flex flex-wrap gap-1">
            <?php foreach ($flow as $index => $step): ?>
                <?php
                $state = 'pending';
                if ($currentIndex !== false && $index < $currentIndex) {
                    $state = 'done';
                } elseif ((string) $order['status'] === $step) {
                    $state = 'current';
                }
                ?>
                <div class="flow-step <?= $state ?>" title="<?= e(Order::FLOW[$step][0]) ?>">
                    <span class="flow-dot"><?= $state === 'done' ? '✓' : $index + 1 ?></span>
                    <span class="flow-label d-none d-lg-inline"><?= e(Order::FLOW[$step][0]) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($order['status'] === 'cancelled'): ?>
            <div class="alert alert-danger small mt-3 mb-0">This order was cancelled.</div>
        <?php elseif ($order['status'] === 'disputed'): ?>
            <div class="alert alert-warning small mt-3 mb-0">This order is under dispute. Payments are on hold until it is resolved.</div>
        <?php endif; ?>

        <?php if ($next_statuses !== [] && !$isClosed): ?>
            <hr>
            <form method="post" action="<?= e(url('dashboard/orders/' . $order['id'] . '/status')) ?>" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-4">
                    <label class="form-label small" for="status">Move this order to</label>
                    <select id="status" name="status" class="form-select" required>
                        <?php foreach ($next_statuses as $status): ?>
                            <option value="<?= e($status) ?>"><?= e(Order::FLOW[$status][0] ?? label($status)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small" for="status_note">Note (shared with the other party)</label>
                    <input id="status_note" name="note" class="form-control">
                </div>
                <div class="col-md-3 d-grid">
                    <button class="btn btn-teal" type="submit">Update status</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Items and amounts -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h6 class="mb-0">Items</h6></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr><th>Description</th><th class="text-end">Quantity</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($items === []): ?>
                        <tr>
                            <td class="small"><?= e((string) ($order['listing_title'] ?? $order['title'] ?? 'Scrap material')) ?></td>
                            <td class="text-end small"><?= e(qty($order['quantity'], (string) $order['unit_code'])) ?></td>
                            <td class="text-end small"><?= money($order['rate']) ?></td>
                            <td class="text-end small"><?= money($order['subtotal']) ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="small">
                                    <?= e((string) $item['description']) ?>
                                    <?php if (!empty($item['hsn_code'])): ?>
                                        <div class="text-muted">HSN <?= e((string) $item['hsn_code']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end small"><?= e(qty($item['quantity'], (string) ($item['unit_code'] ?? ''))) ?></td>
                                <td class="text-end small"><?= money($item['rate']) ?></td>
                                <td class="text-end small"><?= money($item['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    <tfoot>
                    <tr><th colspan="3" class="text-end fw-normal text-muted">Subtotal</th><th class="text-end"><?= money($order['subtotal']) ?></th></tr>
                    <?php if ((float) $order['discount'] > 0): ?>
                        <tr><th colspan="3" class="text-end fw-normal text-muted">Discount</th><th class="text-end">− <?= money($order['discount']) ?></th></tr>
                    <?php endif; ?>
                    <?php if ((float) $order['transport_charges'] > 0): ?>
                        <tr><th colspan="3" class="text-end fw-normal text-muted">Transport</th><th class="text-end"><?= money($order['transport_charges']) ?></th></tr>
                    <?php endif; ?>
                    <?php if ((float) $order['loading_charges'] > 0): ?>
                        <tr><th colspan="3" class="text-end fw-normal text-muted">Loading</th><th class="text-end"><?= money($order['loading_charges']) ?></th></tr>
                    <?php endif; ?>
                    <?php if ((float) $order['other_charges'] > 0): ?>
                        <tr><th colspan="3" class="text-end fw-normal text-muted">Other charges</th><th class="text-end"><?= money($order['other_charges']) ?></th></tr>
                    <?php endif; ?>
                    <?php if ((float) $order['gst_amount'] > 0): ?>
                        <tr>
                            <th colspan="3" class="text-end fw-normal text-muted">
                                GST @ <?= e(dec($order['gst_rate'], 2)) ?>%
                                <span class="d-block small">split into CGST/SGST or IGST on the invoice</span>
                            </th>
                            <th class="text-end"><?= money($order['gst_amount']) ?></th>
                        </tr>
                    <?php endif; ?>
                    <tr class="table-light">
                        <th colspan="3" class="text-end">Order total</th>
                        <th class="text-end h6 mb-0"><?= money($order['final_amount']) ?></th>
                    </tr>
                    <tr>
                        <th colspan="3" class="text-end fw-normal text-muted">Paid so far</th>
                        <th class="text-end text-success"><?= money($total_paid) ?></th>
                    </tr>
                    <tr>
                        <th colspan="3" class="text-end fw-normal text-muted">Balance due</th>
                        <th class="text-end <?= (float) $amount_due > 0 ? 'text-danger' : 'text-success' ?>"><?= money($amount_due) ?></th>
                    </tr>
                    </tfoot>
                </table>
            </div>

            <?php if ($isSeller && !$isClosed): ?>
                <div class="card-body border-top">
                    <h6 class="small text-muted mb-2">Adjust charges</h6>
                    <form method="post" action="<?= e(url('dashboard/orders/' . $order['id'] . '/charges')) ?>" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <?php foreach ([
                            'transport_charges' => 'Transport',
                            'loading_charges' => 'Loading',
                            'other_charges' => 'Other',
                            'discount' => 'Discount',
                        ] as $field => $label): ?>
                            <div class="col-6 col-md-2">
                                <label class="form-label small" for="<?= e($field) ?>"><?= e($label) ?></label>
                                <input id="<?= e($field) ?>" name="<?= e($field) ?>" type="number" step="0.01" min="0"
                                       class="form-control form-control-sm" value="<?= e(dec($order[$field], 2)) ?>">
                            </div>
                        <?php endforeach; ?>
                        <div class="col-md-4 d-grid">
                            <button class="btn btn-sm btn-outline-teal" type="submit">Recalculate total</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Weighment -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Weighment</h6>
                <span class="small text-muted">Settlement uses the actual weighbridge weight</span>
            </div>
            <div class="card-body">
                <?php if ($weighments === []): ?>
                    <p class="small text-muted">No weighment recorded yet.</p>
                <?php else: ?>
                    <?php foreach ($weighments as $weighment): ?>
                        <div class="border rounded p-3 mb-2">
                            <div class="row g-2 small">
                                <div class="col-6 col-md-3">
                                    <div class="text-muted">Expected</div>
                                    <strong><?= e(qty($weighment['expected_weight_kg'], 'KG')) ?></strong>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-muted">Actual</div>
                                    <strong><?= e(qty($weighment['actual_weight_kg'], 'KG')) ?></strong>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-muted">Difference</div>
                                    <strong class="<?= (float) $weighment['difference_kg'] < 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= e(dec($weighment['difference_kg'], 3)) ?> KG
                                        (<?= e(dec($weighment['difference_percent'], 2)) ?>%)
                                    </strong>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-muted">Settled</div>
                                    <strong><?= money($weighment['settled_amount']) ?></strong>
                                </div>
                                <?php if (!empty($weighment['weighbridge_name'])): ?>
                                    <div class="col-12 text-muted">
                                        <?= e((string) $weighment['weighbridge_name']) ?>
                                        <?php if (!empty($weighment['slip_number'])): ?>
                                            · slip <?= e((string) $weighment['slip_number']) ?>
                                        <?php endif; ?>
                                        · <?= e(fmt_dt($weighment['weighed_at'])) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ((float) $weighment['deduction_amount'] > 0): ?>
                                    <div class="col-12 text-danger">
                                        Deduction <?= money($weighment['deduction_amount']) ?>
                                        <?= !empty($weighment['deduction_reason']) ? '— ' . e((string) $weighment['deduction_reason']) : '' ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                                <div class="d-flex gap-2">
                                    <?php if (!empty($weighment['slip_path'])): ?>
                                        <a class="small" href="<?= e(upload_url((string) $weighment['slip_path'])) ?>" target="_blank" rel="noopener">
                                            <i class="bi bi-file-earmark me-1"></i>Slip
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($weighment['photo_path'])): ?>
                                        <a class="small" href="<?= e(upload_url((string) $weighment['photo_path'])) ?>" target="_blank" rel="noopener">
                                            <i class="bi bi-image me-1"></i>Photo
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <?= status_badge((string) $weighment['status']) ?>
                                    <?php if ($weighment['status'] === 'recorded' && (int) $weighment['recorded_by'] !== (int) auth_id()): ?>
                                        <form method="post" action="<?= e(url('dashboard/orders/weighment/' . $weighment['id'] . '/accept')) ?>"
                                              data-confirm="Accept this weighment? The order total is recalculated on the actual weight.">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-teal" type="submit">Accept weighment</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!$isClosed): ?>
                    <button class="btn btn-sm btn-outline-teal" data-bs-toggle="collapse" data-bs-target="#weighmentForm">
                        <i class="bi bi-plus-lg me-1"></i>Record a weighment
                    </button>

                    <div class="collapse mt-3" id="weighmentForm">
                        <form method="post" action="<?= e(url('dashboard/orders/' . $order['id'] . '/weighment')) ?>"
                              enctype="multipart/form-data" class="row g-2">
                            <?= csrf_field() ?>
                            <div class="col-md-3">
                                <label class="form-label small required" for="actual_weight_kg">Actual net weight (kg)</label>
                                <input id="actual_weight_kg" name="actual_weight_kg" type="number" step="0.001" min="0.001"
                                       class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="gross_weight_kg">Gross (kg)</label>
                                <input id="gross_weight_kg" name="gross_weight_kg" type="number" step="0.001" min="0" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="tare_weight_kg">Tare (kg)</label>
                                <input id="tare_weight_kg" name="tare_weight_kg" type="number" step="0.001" min="0" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="weighed_at">Weighed at</label>
                                <input id="weighed_at" name="weighed_at" type="datetime-local" class="form-control"
                                       value="<?= e(to_local_input(now())) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small" for="weighbridge_name">Weighbridge</label>
                                <input id="weighbridge_name" name="weighbridge_name" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small" for="slip_number">Slip number</label>
                                <input id="slip_number" name="slip_number" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="w_vehicle_number">Vehicle</label>
                                <input id="w_vehicle_number" name="vehicle_number" class="form-control text-uppercase" placeholder="GJ01AB1234">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="operator_name">Operator</label>
                                <input id="operator_name" name="operator_name" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="deduction_amount">Deduction (₹)</label>
                                <input id="deduction_amount" name="deduction_amount" type="number" step="0.01" min="0"
                                       class="form-control" value="0">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small" for="deduction_reason">Deduction reason</label>
                                <input id="deduction_reason" name="deduction_reason" class="form-control"
                                       placeholder="Moisture, contamination, shortage…">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small" for="slip">Slip file</label>
                                <input id="slip" name="slip" type="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small" for="photo">Photo</label>
                                <input id="photo" name="photo" type="file" class="form-control form-control-sm" accept="image/*">
                            </div>
                            <div class="col-12">
                                <p class="small text-muted mb-2">
                                    Rate used for settlement: <strong><?= money($rate_per_kg) ?>/kg</strong>.
                                    The other party must accept the weighment before the order total changes.
                                </p>
                                <button class="btn btn-teal" type="submit">Record weighment</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Deliveries -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h6 class="mb-0">Transport &amp; delivery</h6></div>
            <div class="card-body">
                <?php if ($deliveries === []): ?>
                    <p class="small text-muted">No vehicle assigned yet.</p>
                <?php else: ?>
                    <?php foreach ($deliveries as $delivery): ?>
                        <div class="border rounded p-3 mb-2">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div class="small">
                                    <strong><?= e((string) $delivery['vehicle_number']) ?></strong>
                                    <?php if (!empty($delivery['transporter_name'])): ?>
                                        · <?= e((string) $delivery['transporter_name']) ?>
                                    <?php endif; ?>
                                    <div class="text-muted">
                                        <?= e((string) ($delivery['driver_name'] ?? 'Driver not named')) ?>
                                        <?php if (!empty($delivery['driver_mobile'])): ?>
                                            · <?= e((string) $delivery['driver_mobile']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($delivery['lr_number']) || !empty($delivery['eway_bill_number'])): ?>
                                        <div class="text-muted">
                                            <?php if (!empty($delivery['lr_number'])): ?>LR <?= e((string) $delivery['lr_number']) ?><?php endif; ?>
                                            <?php if (!empty($delivery['eway_bill_number'])): ?>
                                                · E-way bill <?= e((string) $delivery['eway_bill_number']) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?= status_badge((string) $delivery['status']) ?>
                            </div>

                            <?php if (!in_array($delivery['status'], ['delivered', 'cancelled'], true)): ?>
                                <form method="post" action="<?= e(url('dashboard/orders/delivery/' . $delivery['id'] . '/status')) ?>"
                                      enctype="multipart/form-data" class="row g-2 mt-1">
                                    <?= csrf_field() ?>
                                    <div class="col-md-4">
                                        <select name="status" class="form-select form-select-sm">
                                            <?php foreach ($statuses ?? \App\Services\LogisticsService::DELIVERY_STATUSES as $key => $label): ?>
                                                <option value="<?= e($key) ?>" <?= $delivery['status'] === $key ? 'selected' : '' ?>>
                                                    <?= e($label) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <input name="pod" type="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
                                    </div>
                                    <div class="col-md-3 d-grid">
                                        <button class="btn btn-sm btn-outline-teal" type="submit">Update</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!$isClosed): ?>
                    <button class="btn btn-sm btn-outline-teal" data-bs-toggle="collapse" data-bs-target="#deliveryForm">
                        <i class="bi bi-truck me-1"></i>Assign a vehicle
                    </button>

                    <div class="collapse mt-3" id="deliveryForm">
                        <form method="post" action="<?= e(url('dashboard/orders/' . $order['id'] . '/delivery')) ?>" class="row g-2">
                            <?= csrf_field() ?>
                            <div class="col-md-3">
                                <label class="form-label small required" for="d_vehicle_number">Vehicle number</label>
                                <input id="d_vehicle_number" name="vehicle_number" class="form-control text-uppercase" required
                                       placeholder="GJ01AB1234">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="d_transporter">Transporter</label>
                                <select id="d_transporter" name="transporter_id" class="form-select">
                                    <option value="">Own arrangement</option>
                                    <?php foreach ($transporters as $transporter): ?>
                                        <option value="<?= (int) $transporter['id'] ?>"><?= e((string) $transporter['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="d_driver">Driver name</label>
                                <input id="d_driver" name="driver_name" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="d_driver_mobile">Driver mobile</label>
                                <input id="d_driver_mobile" name="driver_mobile" class="form-control" maxlength="10">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="d_lr">LR number</label>
                                <input id="d_lr" name="lr_number" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="d_eway">E-way bill</label>
                                <input id="d_eway" name="eway_bill_number" class="form-control" maxlength="20">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="d_freight">Freight (₹)</label>
                                <input id="d_freight" name="freight_amount" type="number" step="0.01" min="0" class="form-control" value="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="d_freight_by">Freight paid by</label>
                                <select id="d_freight_by" name="freight_paid_by" class="form-select">
                                    <option value="buyer">Buyer</option>
                                    <option value="seller">Seller</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="d_pickup_date">Pickup date</label>
                                <input id="d_pickup_date" name="pickup_date" type="date" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="d_delivery_date">Delivery date</label>
                                <input id="d_delivery_date" name="delivery_date" type="date" class="form-control">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-teal" type="submit">Assign vehicle</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payments -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h6 class="mb-0">Payments</h6></div>
            <div class="card-body">
                <?php if ($payments === []): ?>
                    <p class="small text-muted">No payment recorded yet.</p>
                <?php else: ?>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                            <tr><th>Reference</th><th>Method</th><th>UTR</th><th class="text-end">Amount</th><th>Status</th><th></th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td class="small"><?= e((string) $payment['reference']) ?></td>
                                    <td class="small"><?= e($payment_methods[$payment['method']] ?? label((string) $payment['method'])) ?></td>
                                    <td class="small"><?= e((string) ($payment['utr_number'] ?? '—')) ?></td>
                                    <td class="text-end"><?= money($payment['amount']) ?></td>
                                    <td><?= status_badge((string) $payment['status']) ?></td>
                                    <td class="text-end">
                                        <?php if ($payment['status'] === 'pending' && $isSeller): ?>
                                            <form method="post" action="<?= e(url('dashboard/orders/payment/' . $payment['id'] . '/confirm')) ?>"
                                                  data-confirm="Confirm you have received this payment?">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-teal" type="submit">Confirm receipt</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (!empty($payment['proof_path'])): ?>
                                            <a class="small" href="<?= e(upload_url((string) $payment['proof_path'])) ?>"
                                               target="_blank" rel="noopener">Proof</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (!$isClosed && (float) $amount_due > 0): ?>
                    <button class="btn btn-sm btn-outline-teal" data-bs-toggle="collapse" data-bs-target="#paymentForm">
                        <i class="bi bi-cash me-1"></i>Record a payment
                    </button>

                    <div class="collapse mt-3" id="paymentForm">
                        <form method="post" action="<?= e(url('dashboard/orders/' . $order['id'] . '/payment')) ?>"
                              enctype="multipart/form-data" class="row g-2">
                            <?= csrf_field() ?>
                            <div class="col-md-3">
                                <label class="form-label small required" for="p_amount">Amount (₹)</label>
                                <input id="p_amount" name="amount" type="number" step="0.01" min="0.01" class="form-control"
                                       required value="<?= e(dec($amount_due, 2)) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small required" for="p_method">Method</label>
                                <select id="p_method" name="method" class="form-select" required>
                                    <?php foreach ($payment_methods as $key => $label): ?>
                                        <?php if ($key === 'razorpay' && !$online_payment) { continue; } ?>
                                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="p_utr">UTR / transaction ref</label>
                                <input id="p_utr" name="utr_number" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="p_proof">Proof</label>
                                <input id="p_proof" name="proof" type="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                            <div class="col-12">
                                <input name="notes" class="form-control form-control-sm" placeholder="Notes (optional)">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-teal" type="submit">Record payment</button>
                                <?php if (!$online_payment): ?>
                                    <span class="small text-muted ms-2">
                                        Online payment is not configured on this installation — record bank/UPI/cash payments here instead.
                                    </span>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Review -->
        <?php if ($can_review['ok'] ?? false): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0">Rate this deal</h6></div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('dashboard/orders/' . $order['id'] . '/review')) ?>" class="row g-2">
                        <?= csrf_field() ?>
                        <div class="col-md-3">
                            <label class="form-label small required" for="overall_rating">Overall</label>
                            <select id="overall_rating" name="overall_rating" class="form-select" required>
                                <option value="">Rate 1–5</option>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <option value="<?= $i ?>"><?= str_repeat('★', $i) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label small" for="review_comment">Comment</label>
                            <input id="review_comment" name="comment" class="form-control" maxlength="2000">
                        </div>
                        <div class="col-12"><button class="btn btn-teal" type="submit">Submit review</button></div>
                    </form>
                </div>
            </div>
        <?php elseif ($my_review !== null): ?>
            <div class="alert alert-light border small">
                You rated this deal <span class="star-rating"><?= str_repeat('★', (int) $my_review['overall_rating']) ?></span>.
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Parties</h6></div>
            <div class="card-body small">
                <div class="mb-3">
                    <div class="text-muted">Seller</div>
                    <strong><?= e((string) ($order['seller_business'] ?? $order['seller_name'])) ?></strong>
                    <?php if ($isBuyer): ?>
                        <div><?= e((string) ($order['seller_mobile'] ?? '')) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="text-muted">Buyer</div>
                    <strong><?= e((string) ($order['buyer_business'] ?? $order['buyer_name'])) ?></strong>
                    <?php if ($isSeller): ?>
                        <div><?= e((string) ($order['buyer_mobile'] ?? '')) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Terms</h6></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Payment terms</span><strong><?= e(label((string) $order['payment_terms'])) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Loading by</span><strong><?= e(label((string) $order['loading_by'])) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Transport by</span><strong><?= e(label((string) $order['transport_by'])) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">GST rate</span><strong><?= e(dec($order['gst_rate'], 2)) ?>%</strong>
                </li>
                <li class="list-group-item">
                    <div class="text-muted">Pickup</div><?= e((string) ($order['pickup_address'] ?? '—')) ?>
                </li>
                <li class="list-group-item">
                    <div class="text-muted">Delivery</div><?= e((string) ($order['delivery_address'] ?? '—')) ?>
                </li>
            </ul>
        </div>

        <?php if ($commissions !== []): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><h6 class="mb-0">Platform commission</h6></div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($commissions as $commission): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">
                                <?= e(label((string) $commission['fee_type'])) ?>
                                (<?= e((string) $commission['party']) ?>)
                            </span>
                            <strong><?= money($commission['total_amount']) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">History</h6></div>
            <?php if ($history === []): ?>
                <div class="card-body small text-muted">No status changes yet.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush" style="max-height:320px;overflow:auto">
                    <?php foreach ($history as $entry): ?>
                        <li class="list-group-item small">
                            <?= status_badge((string) $entry['to_status']) ?>
                            <span class="text-muted ms-1"><?= e(fmt_dt($entry['created_at'])) ?></span>
                            <?php if (!empty($entry['note'])): ?>
                                <div class="mt-1"><?= e((string) $entry['note']) ?></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php if (!$isClosed): ?>
            <button class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#disputeModal">
                <i class="bi bi-shield-exclamation me-1"></i>Raise a dispute
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="disputeModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= e(url('dashboard/orders/' . $order['id'] . '/dispute')) ?>">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title">Raise a dispute</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label required" for="dispute_category">Category</label>
                    <select id="dispute_category" name="category" class="form-select" required>
                        <?php foreach ($dispute_categories as $key => $label): ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label required" for="dispute_subject">Subject</label>
                    <input id="dispute_subject" name="subject" class="form-control" required minlength="5" maxlength="190">
                </div>
                <div class="mb-3">
                    <label class="form-label required" for="dispute_description">What went wrong?</label>
                    <textarea id="dispute_description" name="description" class="form-control" rows="4"
                              required minlength="20" maxlength="5000"></textarea>
                </div>
                <div>
                    <label class="form-label" for="claimed_amount">Amount claimed (₹)</label>
                    <input id="claimed_amount" name="claimed_amount" type="number" step="0.01" min="0" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Raise dispute</button>
            </div>
        </form>
    </div>
</div>
<?php View::endSection(); ?>
