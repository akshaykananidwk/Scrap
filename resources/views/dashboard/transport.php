<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-1">Transport &amp; vehicles</h1>
<p class="text-muted small mb-4">
    Keep your regular transporters and trucks here so assigning them to an order takes one click.
</p>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h6 class="mb-0">Deliveries on my orders</h6></div>
            <?php if ($deliveries === []): ?>
                <div class="card-body small text-muted">No deliveries recorded yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>Order</th><th>Transporter</th><th>Vehicle</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($deliveries as $delivery): ?>
                            <tr>
                                <td class="small">
                                    <a href="<?= e(url('dashboard/orders/' . $delivery['order_id'])) ?>">
                                        <?= e((string) $delivery['order_reference']) ?>
                                    </a>
                                </td>
                                <td class="small"><?= e((string) ($delivery['transporter_name'] ?? '—')) ?></td>
                                <td class="small"><?= e((string) ($delivery['vehicle_number'] ?? '—')) ?></td>
                                <td><?= status_badge((string) $delivery['status']) ?></td>
                                <td class="text-end">
                                    <?php if (!in_array($delivery['status'], ['delivered', 'cancelled'], true)): ?>
                                        <form method="post" action="<?= e(url('dashboard/orders/delivery/' . $delivery['id'] . '/status')) ?>"
                                              class="d-flex gap-1 justify-content-end">
                                            <?= csrf_field() ?>
                                            <select name="status" class="form-select form-select-sm" style="width:auto">
                                                <?php foreach ($statuses as $key => $label): ?>
                                                    <option value="<?= e($key) ?>" <?= $delivery['status'] === $key ? 'selected' : '' ?>>
                                                        <?= e($label) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-sm btn-outline-teal" type="submit">Update</button>
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

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">My vehicles</h6></div>
            <?php if ($vehicles === []): ?>
                <div class="card-body small text-muted">No vehicles added.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>Number</th><th>Type</th><th class="text-end">Capacity</th><th>Driver</th><th>Active</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <tr>
                                <td class="small fw-semibold"><?= e((string) $vehicle['vehicle_number']) ?></td>
                                <td class="small"><?= e($vehicle_types[$vehicle['vehicle_type']] ?? label((string) $vehicle['vehicle_type'])) ?></td>
                                <td class="text-end small">
                                    <?= $vehicle['capacity_kg'] !== null ? e(qty($vehicle['capacity_kg'], 'KG')) : '—' ?>
                                </td>
                                <td class="small">
                                    <?= e((string) ($vehicle['driver_name'] ?? '—')) ?>
                                    <?php if (!empty($vehicle['driver_mobile'])): ?>
                                        <div class="text-muted"><?= e((string) $vehicle['driver_mobile']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $vehicle['is_active'] === 1
                                        ? '<span class="badge badge-soft-success">Yes</span>'
                                        : '<span class="badge text-bg-secondary">No</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h6 class="mb-0">Add a transporter</h6></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('dashboard/transport/transporters')) ?>">
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <div class="col-md-7">
                            <label class="form-label small required" for="t_name">Transporter name</label>
                            <input id="t_name" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small required" for="t_mobile">Mobile</label>
                            <input id="t_mobile" name="mobile" class="form-control" maxlength="10" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small" for="t_contact">Contact person</label>
                            <input id="t_contact" name="contact_person" class="form-control">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small" for="t_gstin">GSTIN</label>
                            <input id="t_gstin" name="gstin" class="form-control text-uppercase" maxlength="15">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="t_city">City</label>
                            <input id="t_city" name="city_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="t_state">State</label>
                            <input id="t_state" name="state_name" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label small" for="t_areas">Service areas</label>
                            <input id="t_areas" name="service_areas" class="form-control" placeholder="Gujarat, Maharashtra, Rajasthan">
                        </div>
                    </div>
                    <button class="btn btn-teal mt-3 w-100" type="submit">Add transporter</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h6 class="mb-0">Add a vehicle</h6></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('dashboard/transport/vehicles')) ?>">
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small required" for="v_number">Vehicle number</label>
                            <input id="v_number" name="vehicle_number" class="form-control text-uppercase" required
                                   placeholder="GJ01AB1234" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="v_type">Type</label>
                            <select id="v_type" name="vehicle_type" class="form-select">
                                <?php foreach ($vehicle_types as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $key === 'truck' ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="v_capacity">Capacity (kg)</label>
                            <input id="v_capacity" name="capacity_kg" type="number" step="0.01" min="0" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="v_transporter">Transporter</label>
                            <select id="v_transporter" name="transporter_id" class="form-select">
                                <option value="">Own vehicle</option>
                                <?php foreach ($all_transporters as $transporter): ?>
                                    <option value="<?= (int) $transporter['id'] ?>"><?= e((string) $transporter['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="v_driver">Driver name</label>
                            <input id="v_driver" name="driver_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="v_driver_mobile">Driver mobile</label>
                            <input id="v_driver_mobile" name="driver_mobile" class="form-control" maxlength="10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="v_rc">RC number</label>
                            <input id="v_rc" name="rc_number" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="v_licence">Driver licence</label>
                            <input id="v_licence" name="driver_licence" class="form-control">
                        </div>
                    </div>
                    <button class="btn btn-teal mt-3 w-100" type="submit">Add vehicle</button>
                </form>
            </div>
        </div>

        <?php if ($transporters !== []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">My transporters</h6></div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($transporters as $transporter): ?>
                        <li class="list-group-item small d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= e((string) $transporter['name']) ?></strong>
                                <div class="text-muted"><?= e((string) $transporter['mobile']) ?>
                                    · <?= (int) ($transporter['vehicle_count'] ?? 0) ?> vehicles</div>
                            </div>
                            <?= (int) $transporter['is_active'] === 1
                                ? '<span class="badge badge-soft-success">Active</span>'
                                : '<span class="badge text-bg-secondary">Inactive</span>' ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection(); ?>
