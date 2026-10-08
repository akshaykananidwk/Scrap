<?php

/**
 * Renders one article's body from its block list.
 *
 * Every article in the Help Centre is a list of typed blocks rather than a slab
 * of HTML, so that steps, callouts and settings tables look the same in all
 * forty-eight articles and the print view can reuse this file unchanged.
 *
 * Guide content is written by us and shipped with the code, so the small amount
 * of inline markup in it (<strong>, <em>, <code>) is intentional and passed
 * through. Everything that could ever come from elsewhere is escaped.
 *
 * @var array $blocks
 * @var bool  $flat    True in the print view, where headings must not be links
 *                     and callouts should stay readable in black and white.
 */

$blocks = $blocks ?? [];
$flat = $flat ?? false;

/** Callout styling by type: Bootstrap alert class, icon, and a label for print. */
$callouts = [
    'note' => ['alert-secondary', 'bi-info-circle', 'Note'],
    'tip' => ['alert-success', 'bi-lightbulb', 'Tip'],
    'warning' => ['alert-warning', 'bi-exclamation-triangle', 'Important'],
    'danger' => ['alert-danger', 'bi-exclamation-octagon', 'Warning'],
];

foreach ($blocks as $block):
    $type = (string) ($block['type'] ?? 'p');
    ?>

    <?php if ($type === 'p'): ?>
        <p class="guide-text"><?= $block['text'] ?? '' ?></p>

    <?php elseif ($type === 'h'): ?>
        <h2 class="h5 fw-semibold mt-4 mb-3 guide-heading"><?= e((string) ($block['text'] ?? '')) ?></h2>

    <?php elseif ($type === 'steps'): ?>
        <ol class="guide-steps">
            <?php foreach (($block['items'] ?? []) as $item): ?>
                <li>
                    <?php if (is_array($item)): ?>
                        <span class="guide-step-title"><?= e((string) ($item['title'] ?? '')) ?></span>
                        <?php if (!empty($item['text'])): ?>
                            <span class="guide-step-text"><?= $item['text'] ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="guide-step-text"><?= $item ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>

    <?php elseif ($type === 'list'): ?>
        <ul class="guide-list">
            <?php foreach (($block['items'] ?? []) as $item): ?>
                <li><?= is_array($item) ? e((string) ($item['text'] ?? '')) : $item ?></li>
            <?php endforeach; ?>
        </ul>

    <?php elseif (isset($callouts[$type])): ?>
        <?php [$alertClass, $icon, $label] = $callouts[$type]; ?>
        <div class="alert <?= $alertClass ?> guide-callout d-flex gap-2" role="note">
            <i class="bi <?= $icon ?> flex-shrink-0 mt-1" aria-hidden="true"></i>
            <div class="min-w-0">
                <?php if ($flat): ?><strong class="d-block"><?= $label ?></strong><?php endif; ?>
                <?= $block['text'] ?? '' ?>
            </div>
        </div>

    <?php elseif ($type === 'settings'): ?>
        <?php if (!empty($block['intro'])): ?>
            <p class="guide-text mb-2"><strong><?= $block['intro'] ?></strong></p>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-sm guide-table align-top">
                <thead class="table-light">
                    <tr><th scope="col" style="width:34%">Setting</th><th scope="col">What to put, and why</th></tr>
                </thead>
                <tbody>
                    <?php foreach (($block['rows'] ?? []) as $row): ?>
                        <tr>
                            <th scope="row" class="fw-semibold"><?= $row[0] ?? '' ?></th>
                            <td><?= $row[1] ?? '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($type === 'table'): ?>
        <div class="table-responsive">
            <table class="table table-sm guide-table align-top">
                <?php if (!empty($block['head'])): ?>
                    <thead class="table-light">
                        <tr>
                            <?php foreach ($block['head'] as $heading): ?>
                                <th scope="col"><?= $heading ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                <?php endif; ?>
                <tbody>
                    <?php foreach (($block['rows'] ?? []) as $row): ?>
                        <tr>
                            <?php foreach ((array) $row as $index => $cell): ?>
                                <?php if ($index === 0): ?>
                                    <th scope="row" class="fw-semibold"><?= $cell ?></th>
                                <?php else: ?>
                                    <td><?= $cell ?></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($type === 'faq'): ?>
        <dl class="guide-faq">
            <?php foreach (($block['items'] ?? []) as $item): ?>
                <dt><?= $item['q'] ?? '' ?></dt>
                <dd><?= $item['a'] ?? '' ?></dd>
            <?php endforeach; ?>
        </dl>

    <?php elseif ($type === 'code'): ?>
        <pre class="guide-code"><code><?= e((string) ($block['text'] ?? '')) ?></code></pre>

    <?php elseif ($type === 'link'): ?>
        <?php if ($flat): ?>
            <?php // In print, a button is useless — spell the address out instead. ?>
            <p class="guide-text">
                <i class="bi <?= e((string) ($block['icon'] ?? 'bi-arrow-right-circle')) ?> me-1" aria-hidden="true"></i>
                <?= e((string) ($block['text'] ?? '')) ?>:
                <code><?= e(base_url(ltrim((string) ($block['to'] ?? ''), '/'))) ?></code>
            </p>
        <?php else: ?>
            <p class="my-3">
                <a class="btn btn-sm btn-outline-teal" href="<?= e(url(ltrim((string) ($block['to'] ?? ''), '/'))) ?>">
                    <i class="bi <?= e((string) ($block['icon'] ?? 'bi-arrow-right-circle')) ?> me-1" aria-hidden="true"></i><?= e((string) ($block['text'] ?? '')) ?>
                </a>
            </p>
        <?php endif; ?>
    <?php endif; ?>

<?php endforeach; ?>
