<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Error logs</h1>

<div class="row g-4">
    <div class="col-lg-3">
        <div class="list-group shadow-sm">
            <?php if ($files === []): ?>
                <div class="list-group-item small text-muted">No log files — nothing has gone wrong.</div>
            <?php endif; ?>
            <?php foreach ($files as $file): ?>
                <?php $name = is_array($file) ? (string) $file['name'] : (string) $file; ?>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $current === $name ? 'active' : '' ?>"
                   href="<?= e(url('admin/logs?file=' . urlencode($name))) ?>">
                    <span class="small font-monospace text-truncate"><?= e($name) ?></span>
                    <?php if (is_array($file) && isset($file['size'])): ?>
                        <span class="small"><?= e(human_bytes((int) $file['size'])) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-lg-9">
        <?php if ($current === ''): ?>
            <div class="empty-state bg-white rounded shadow-sm">
                <i class="bi bi-journal-text"></i>
                <p class="mb-0">Pick a log file to read it.</p>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 font-monospace"><?= e($current) ?></h6>
                    <form method="post" action="<?= e(url('admin/logs/' . urlencode($current) . '/delete')) ?>"
                          data-confirm="Delete this log file?">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete file</button>
                    </form>
                </div>
                <div class="card-body p-0">
                    <pre class="mb-0 p-3 small" style="max-height:70vh;overflow:auto"><?= e($contents !== '' ? $contents : '(empty)') ?></pre>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection(); ?>
