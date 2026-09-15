<?php
/**
 * Inline SVG series chart — no charting library, renders identically without JS.
 * Expects $series (rows of ['day' => 'Y-m-d', 'value' => n]) and $label.
 */
$rows = $series ?? [];
$values = array_map(static fn (array $r): float => (float) ($r['value'] ?? 0), $rows);
?>
<?php if (count($values) < 2): ?>
    <p class="small text-muted mb-0">Not enough data yet.</p>
<?php else: ?>
    <?php
    $max = max($values);
    $min = min($values);
    $range = ($max - $min) > 0 ? ($max - $min) : 1;
    $w = 640;
    $h = 140;
    $step = $w / (count($values) - 1);
    $points = [];
    foreach ($values as $i => $value) {
        $points[] = round($i * $step, 2) . ',' . round($h - (($value - $min) / $range) * ($h - 16) - 8, 2);
    }
    $line = implode(' ', $points);
    ?>
    <svg viewBox="0 0 <?= $w ?> <?= $h ?>" class="line-chart" preserveAspectRatio="none" role="img"
         aria-label="<?= e((string) ($label ?? 'Trend')) ?>">
        <polyline points="0,<?= $h ?> <?= $line ?> <?= $w ?>,<?= $h ?>" fill="rgba(15,118,110,.12)" stroke="none"/>
        <polyline points="<?= $line ?>" fill="none" stroke="#0f766e" stroke-width="2.5"
                  stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
    </svg>
    <div class="d-flex justify-content-between small text-muted mt-1">
        <span><?= e((string) ($rows[0]['day'] ?? '')) ?></span>
        <span>peak <?= e(number_format($max, 0)) ?></span>
        <span><?= e((string) ($rows[count($rows) - 1]['day'] ?? '')) ?></span>
    </div>
<?php endif; ?>
