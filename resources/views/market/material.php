<?php

use App\Core\View;

View::section('content');

// Build a simple inline SVG line chart — no charting library needed.
$points = $history ?? [];
$chart = '';
if (count($points) > 1) {
    $values = array_map(static fn (array $p): float => (float) $p['rate'], $points);
    $min = min($values);
    $max = max($values);
    $range = ($max - $min) > 0 ? ($max - $min) : 1;
    $width = 720;
    $height = 220;
    $padding = 28;
    $step = ($width - $padding * 2) / max(1, count($values) - 1);

    $coords = [];
    foreach ($values as $i => $value) {
        $x = $padding + ($i * $step);
        $y = $height - $padding - ((($value - $min) / $range) * ($height - $padding * 2));
        $coords[] = round($x, 1) . ',' . round($y, 1);
    }
    $line = implode(' ', $coords);
    $area = $padding . ',' . ($height - $padding) . ' ' . $line . ' ' . round($padding + (count($values) - 1) * $step, 1) . ',' . ($height - $padding);

    $chart = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="line-chart" preserveAspectRatio="none" role="img" aria-label="Price trend">'
        . '<polyline points="' . $area . '" fill="rgba(15,118,110,.12)" stroke="none"/>'
        . '<polyline points="' . $line . '" fill="none" stroke="#0f766e" stroke-width="2.5" stroke-linejoin="round"/>'
        . '</svg>';
}
?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= e(url('market-rates')) ?>">Scrap rates</a></li>
            <li class="breadcrumb-item active"><?= e((string) $material['name']) ?></li>
        </ol>
    </nav>

    <h1 class="h4 mb-1"><?= e((string) $material['name']) ?> rate today</h1>
    <p class="text-muted small mb-4">
        Indicative rates across Indian cities, with a 30-day trend and live listings.
    </p>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="mb-3">30-day price trend</h6>
                    <?php if ($chart !== ''): ?>
                        <?= $chart ?>
                        <div class="d-flex justify-content-between small text-muted">
                            <span><?= e(fmt_date($points[0]['rate_date'] ?? null)) ?></span>
                            <span><?= e(fmt_date(end($points)['rate_date'] ?? null)) ?></span>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0">Not enough price history to draw a chart yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">City rates</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>City</th><th class="text-end">Rate</th><th class="text-end">Change</th><th class="text-end">Updated</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rates as $rate): ?>
                            <?php $change = (float) $rate['change_percent']; ?>
                            <tr>
                                <td><?= e((string) ($rate['city_name'] ?: 'All India')) ?></td>
                                <td class="text-end fw-semibold"><?= e(money($rate['rate'])) ?>/<?= e((string) $rate['unit_code']) ?></td>
                                <td class="text-end <?= $change >= 0 ? 'rate-up' : 'rate-down' ?>">
                                    <?= $change >= 0 ? '+' : '' ?><?= e(number_format($change, 2)) ?>%
                                </td>
                                <td class="text-end small text-muted"><?= e(fmt_date($rate['rate_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rates === []): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No rates published for this material yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="mb-2">Trade this material</h6>
                    <div class="d-grid gap-2">
                        <a class="btn btn-teal" href="<?= e(url('material/' . $material['slug'])) ?>">
                            <i class="bi bi-search me-1"></i>Buy <?= e((string) $material['name']) ?>
                        </a>
                        <a class="btn btn-outline-teal" href="<?= e(url('dashboard/listings/create')) ?>">
                            <i class="bi bi-plus-circle me-1"></i>Sell <?= e((string) $material['name']) ?>
                        </a>
                        <a class="btn btn-outline-secondary" href="<?= e(url('dashboard/requirements/create')) ?>">
                            <i class="bi bi-megaphone me-1"></i>Post a requirement
                        </a>
                    </div>
                </div>
            </div>

            <?php if (!empty($listings)): ?>
                <h6 class="mb-2">Live listings</h6>
                <?php foreach ($listings as $listing): ?>
                    <div class="card border-0 shadow-sm mb-2">
                        <div class="card-body p-2">
                            <a class="small fw-semibold text-decoration-none text-dark d-block text-truncate"
                               href="<?= e(url('listing/' . $listing['slug'])) ?>">
                                <?= e(mb_strimwidth((string) $listing['title'], 0, 44, '…')) ?>
                            </a>
                            <div class="d-flex justify-content-between small text-muted">
                                <span><?= e(qty($listing['quantity'], (string) $listing['unit_code'])) ?></span>
                                <?php if (!empty($listing['price'])): ?>
                                    <span class="text-teal fw-semibold"><?= e(money($listing['price'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
