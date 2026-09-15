<?php

use App\Core\View;

View::section('content');
$p = $edit ?? [];
$val = static fn (string $key, string $default = ''): string => (string) old($key, $p[$key] ?? $default);
?>
<h1 class="h4 mb-4">Subscription plans</h1>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr><th>Plan</th><th>Audience</th><th class="text-end">Price</th><th>Period</th>
                        <th class="text-end">Listings</th><th>Active</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($plans as $plan): ?>
                        <tr>
                            <td class="small fw-semibold"><?= e((string) $plan['name']) ?></td>
                            <td class="small"><?= e(label((string) $plan['audience'])) ?></td>
                            <td class="text-end"><?= money($plan['price']) ?></td>
                            <td class="small"><?= e(label((string) $plan['billing_period'])) ?></td>
                            <td class="text-end small"><?= (int) $plan['listing_limit'] === 0 ? 'Unlimited' : (int) $plan['listing_limit'] ?></td>
                            <td><?= (int) $plan['is_active'] === 1
                                    ? '<span class="badge badge-soft-success">Yes</span>'
                                    : '<span class="badge text-bg-secondary">No</span>' ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-link p-0" href="<?= e(url('admin/plans?edit=' . $plan['id'])) ?>">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Recent subscriptions</h6></div>
            <?php if ($subscriptions === []): ?>
                <div class="card-body small text-muted">
                    Nobody is on a paid plan. Subscriptions are activated manually by staff until an online
                    payment gateway is configured.
                </div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($subscriptions as $subscription): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center small">
                            <div>
                                <a href="<?= e(url('admin/users/' . $subscription['user_id'])) ?>">
                                    <?= e((string) $subscription['full_name']) ?>
                                </a>
                                <span class="text-muted">· <?= e((string) $subscription['plan_name']) ?></span>
                            </div>
                            <span>
                                <?= status_badge((string) $subscription['status']) ?>
                                <span class="text-muted ms-1">till <?= e(fmt_date($subscription['ends_at'])) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0"><?= $edit !== null ? 'Edit plan' : 'Add a plan' ?></h6></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/plans')) ?>">
                    <?= csrf_field() ?>
                    <?php if ($edit !== null): ?>
                        <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                    <?php endif; ?>
                    <div class="mb-2">
                        <label class="form-label small required" for="name">Plan name</label>
                        <input id="name" name="name" class="form-control" required value="<?= e($val('name')) ?>">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small" for="audience">Audience</label>
                            <select id="audience" name="audience" class="form-select">
                                <?php foreach (['both' => 'Buyers and sellers', 'buyer' => 'Buyers', 'seller' => 'Sellers'] as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $val('audience', 'both') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="billing_period">Billing period</label>
                            <select id="billing_period" name="billing_period" class="form-select">
                                <?php foreach (['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly', 'lifetime' => 'Lifetime'] as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $val('billing_period', 'monthly') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="price">Price (₹)</label>
                            <input id="price" name="price" type="number" step="0.01" min="0" class="form-control"
                                   value="<?= e($val('price', '0')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="gst_rate">GST %</label>
                            <input id="gst_rate" name="gst_rate" type="number" step="0.01" min="0" max="50"
                                   class="form-control" value="<?= e($val('gst_rate', '18')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="listing_limit">Listing limit</label>
                            <input id="listing_limit" name="listing_limit" type="number" min="0" class="form-control"
                                   value="<?= e($val('listing_limit', '0')) ?>">
                            <div class="form-text">0 = unlimited</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="auction_limit">Auction limit</label>
                            <input id="auction_limit" name="auction_limit" type="number" min="0" class="form-control"
                                   value="<?= e($val('auction_limit', '0')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="rfq_limit">RFQ limit</label>
                            <input id="rfq_limit" name="rfq_limit" type="number" min="0" class="form-control"
                                   value="<?= e($val('rfq_limit', '0')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="featured_credits">Featured credits</label>
                            <input id="featured_credits" name="featured_credits" type="number" min="0" class="form-control"
                                   value="<?= e($val('featured_credits', '0')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="commission_discount">Commission discount %</label>
                            <input id="commission_discount" name="commission_discount" type="number" step="0.001" min="0" max="100"
                                   class="form-control" value="<?= e($val('commission_discount', '0')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="sort_order">Sort order</label>
                            <input id="sort_order" name="sort_order" type="number" min="0" class="form-control"
                                   value="<?= e($val('sort_order', '0')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small" for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="2"><?= e($val('description')) ?></textarea>
                        </div>
                    </div>
                    <div class="form-check my-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                            <?= old('is_active', $p['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="is_active">Active</label>
                    </div>
                    <button class="btn btn-teal w-100" type="submit">Save plan</button>
                    <?php if ($edit !== null): ?>
                        <a class="btn btn-link w-100 mt-1" href="<?= e(url('admin/plans')) ?>">New plan</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
