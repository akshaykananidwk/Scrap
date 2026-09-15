<?php

use App\Core\View;

View::section('content');

/** Tiny inline sparkline — no charting library, works without JS. */
$spark = static function (array $series, string $valueKey = 'value'): string {
    $values = array_map(static fn (array $row): float => (float) ($row[$valueKey] ?? 0), $series);
    if (count($values) < 2) {
        return '<p class="small text-muted mb-0">Not enough data yet.</p>';
    }
    $max = max($values);
    $min = min($values);
    $range = ($max - $min) > 0 ? ($max - $min) : 1;
    $w = 600;
    $h = 120;
    $step = $w / (count($values) - 1);
    $points = [];
    foreach ($values as $i => $value) {
        $x = round($i * $step, 2);
        $y = round($h - (($value - $min) / $range) * ($h - 12) - 6, 2);
        $points[] = $x . ',' . $y;
    }
    $line = implode(' ', $points);
    $area = '0,' . $h . ' ' . $line . ' ' . $w . ',' . $h;

    return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" class="line-chart" preserveAspectRatio="none" role="img" aria-label="Trend">'
        . '<polyline points="' . $area . '" fill="rgba(15,118,110,.12)" stroke="none"/>'
        . '<polyline points="' . $line . '" fill="none" stroke="#0f766e" stroke-width="2.5" '
        . 'stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>'
        . '</svg>';
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h4 mb-0">Business analytics</h1>
    <form method="get" class="d-flex gap-2">
        <select name="days" class="form-select form-select-sm" data-auto-submit>
            <?php foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last year'] as $value => $label): ?>
                <option value="<?= (int) $value ?>" <?= (int) $days === (int) $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-sm btn-outline-teal" type="submit">Apply</button></noscript>
    </form>
</div>

<?php if ($is_seller && $seller !== null): ?>
    <h6 class="text-teal mb-3">Selling</h6>
    <div class="row g-3 mb-4">
        <?php foreach ([
            ['Listings', (int) ($seller['listings']['total'] ?? 0)],
            ['Active', (int) ($seller['listings']['active'] ?? 0)],
            ['Sold', (int) ($seller['listings']['sold'] ?? 0)],
            ['Listing views', (int) ($seller['listings']['views'] ?? 0)],
            ['Enquiries', (int) ($seller['listings']['enquiries'] ?? 0)],
            ['Offers received', (int) ($seller['listings']['offers'] ?? 0)],
            ['Bids received', (int) $seller['bids_received']],
            ['View → order', $seller['conversion_rate'] . '%'],
        ] as [$label, $value]): ?>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-value fs-5"><?= e((string) $value) ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">Revenue (all orders)</div>
                    <div class="h4 mb-0"><?= money($seller['sales']['revenue'] ?? 0) ?></div>
                    <div class="small text-muted mt-2">
                        Completed: <strong><?= money($seller['sales']['completed_revenue'] ?? 0) ?></strong>
                        (<?= (int) ($seller['sales']['completed'] ?? 0) ?> orders)
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="mb-3">Sales trend</h6>
                    <?= $spark($seller['revenue_series'] ?? $seller['sales_series'] ?? []) ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($seller['top_listings'])): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h6 class="mb-0">Best performing listings</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light"><tr><th>Listing</th><th class="text-end">Views</th><th class="text-end">Enquiries</th><th class="text-end">Offers</th></tr></thead>
                    <tbody>
                    <?php foreach ($seller['top_listings'] as $row): ?>
                        <tr>
                            <td class="small text-truncate" style="max-width:340px"><?= e((string) $row['title']) ?></td>
                            <td class="text-end"><?= (int) ($row['view_count'] ?? 0) ?></td>
                            <td class="text-end"><?= (int) ($row['enquiry_count'] ?? 0) ?></td>
                            <td class="text-end"><?= (int) ($row['offer_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($is_buyer && $buyer !== null): ?>
    <h6 class="text-teal mb-3">Buying</h6>
    <div class="row g-3 mb-4">
        <?php foreach ([
            ['Orders placed', (int) ($buyer['purchases']['orders'] ?? 0)],
            ['Completed', (int) ($buyer['purchases']['completed'] ?? 0)],
            ['Total spend', money($buyer['purchases']['spend'] ?? 0)],
            ['Bids placed', (int) ($buyer['bids']['total'] ?? 0)],
            ['Auctions won', (int) ($buyer['bids']['won'] ?? 0)],
            ['Live bids', (int) ($buyer['bids']['active'] ?? 0)],
            ['Requirements', (int) $buyer['requirements']],
            ['Saved items', (int) $buyer['saved']],
        ] as [$label, $value]): ?>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-value fs-5"><?= e((string) $value) ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="mb-3">Spend trend</h6>
                    <?= $spark($buyer['spend_series'] ?? []) ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="mb-3">Top materials bought</h6>
                    <?php if (empty($buyer['top_materials'])): ?>
                        <p class="small text-muted mb-0">No completed purchases yet.</p>
                    <?php else: ?>
                        <?php foreach ($buyer['top_materials'] as $row): ?>
                            <div class="d-flex justify-content-between small mb-2">
                                <span class="text-truncate"><?= e((string) $row['name']) ?></span>
                                <span class="fw-semibold"><?= money($row['value']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php View::endSection(); ?>
