<?php

use App\Core\View;

View::section('content');
$query = $filters ?? [];
?>
<div class="bg-white border-bottom py-3">
    <div class="container">
        <h1 class="h4 mb-1">Scrap rates today</h1>
        <p class="text-muted small mb-0">
            Indicative market rates by material and city.
            <?php if (!empty($updated_at)): ?>Last updated <?= e(fmt_date($updated_at)) ?>.<?php endif; ?>
            Always confirm the final rate with the seller.
        </p>
    </div>
</div>

<div class="container py-4">
    <?php if (!empty($movers)): ?>
        <div class="row g-2 mb-4">
            <?php foreach (array_slice($movers, 0, 6) as $rate): ?>
                <?php $change = (float) $rate['change_percent']; ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <a class="category-tile text-decoration-none" href="<?= e(url('market-rates/' . $rate['material_slug'])) ?>">
                        <div class="small text-muted text-truncate"><?= e((string) $rate['material_name']) ?></div>
                        <div class="fw-bold"><?= e(money($rate['rate'])) ?></div>
                        <div class="small <?= $change >= 0 ? 'rate-up' : 'rate-down' ?>">
                            <i class="bi bi-caret-<?= $change >= 0 ? 'up' : 'down' ?>-fill"></i>
                            <?= e(number_format(abs($change), 2)) ?>%
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="get" class="row g-2 mb-4">
        <div class="col-md-4">
            <input type="search" name="q" class="form-control" placeholder="Material or city"
                   value="<?= e((string) ($query['q'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <select name="category_id" class="form-select" data-auto-submit>
                <option value="">All categories</option>
                <?php foreach ($categories as $root): ?>
                    <option value="<?= (int) $root['id'] ?>" <?= (int) ($query['category_id'] ?? 0) === (int) $root['id'] ? 'selected' : '' ?>><?= e($root['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="city_id" class="form-select" data-auto-submit>
                <option value="">All cities</option>
                <?php foreach ($cities as $city): ?>
                    <option value="<?= (int) $city['id'] ?>" <?= (int) ($query['city_id'] ?? 0) === (int) $city['id'] ? 'selected' : '' ?>>
                        <?= e((string) $city['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-grid"><button class="btn btn-outline-teal" type="submit">Filter</button></div>
    </form>

    <?php if (empty($grouped)): ?>
        <div class="empty-state bg-white rounded shadow-sm">
            <i class="bi bi-graph-up"></i>
            <h5>No rates published yet</h5>
            <p class="mb-0">An administrator can add rates in Admin → Content → Market Rates, or import them from CSV.</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $materialName => $rows): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><?= e((string) $materialName) ?></h6>
                    <a class="small" href="<?= e(url('market-rates/' . ($rows[0]['material_slug'] ?? ''))) ?>">
                        Price trend <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>City</th><th>Grade</th><th class="text-end">Rate</th><th class="text-end">Change</th><th class="text-end">Date</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $rate): ?>
                            <?php $change = (float) $rate['change_percent']; ?>
                            <tr>
                                <td><?= e((string) ($rate['city_name'] ?: 'All India')) ?></td>
                                <td class="small text-muted"><?= e((string) ($rate['grade_name'] ?: '—')) ?></td>
                                <td class="text-end fw-semibold">
                                    <?= e(money($rate['rate'])) ?><span class="text-muted small">/<?= e((string) $rate['unit_code']) ?></span>
                                </td>
                                <td class="text-end <?= $change >= 0 ? 'rate-up' : 'rate-down' ?>">
                                    <?= $change >= 0 ? '+' : '' ?><?= e(money($rate['change_amount'], false)) ?>
                                    (<?= e(number_format($change, 2)) ?>%)
                                </td>
                                <td class="text-end small text-muted"><?= e(fmt_date($rate['rate_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <p class="small text-muted mt-3">
        <i class="bi bi-info-circle me-1"></i>
        Rates are indicative and published by the platform operator. They are not a quotation or an offer to trade.
    </p>
</div>
<?php View::endSection(); ?>
