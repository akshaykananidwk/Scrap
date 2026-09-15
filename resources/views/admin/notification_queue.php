<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Notification queue</h1>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Total', (int) ($summary['total'] ?? 0), ''],
        ['Queued', (int) ($summary['queued'] ?? 0), 'queued'],
        ['Sent', (int) ($summary['sent'] ?? 0), 'sent'],
        ['Failed', (int) ($summary['failed'] ?? 0), 'failed'],
        ['Skipped', (int) ($summary['skipped'] ?? 0), 'skipped'],
    ] as [$label, $value, $status]): ?>
        <div class="col-4 col-md-2">
            <a class="text-decoration-none" href="<?= e(url('admin/notifications' . ($status !== '' ? '?status=' . $status : ''))) ?>">
                <div class="stat-card text-center">
                    <div class="stat-value fs-6"><?= $value ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white"><h6 class="mb-0">Channel providers</h6></div>
    <ul class="list-group list-group-flush">
        <?php foreach ($providers as $key => $provider): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center small">
                <span><?= e(label((string) $key)) ?> — <span class="text-muted"><?= e($provider->name()) ?></span></span>
                <?php if ($provider->isConfigured()): ?>
                    <span class="badge badge-soft-success">Configured</span>
                <?php else: ?>
                    <span class="badge text-bg-secondary">Not configured — messages are marked skipped</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<?php if ($queue->isEmpty()): ?>
    <div class="empty-state bg-white rounded shadow-sm"><i class="bi bi-inbox"></i><p class="mb-0">The queue is empty.</p></div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                <tr><th>Queued</th><th>Event</th><th>Channel</th><th>Recipient</th><th>Subject</th>
                    <th class="text-end">Attempts</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($queue->items as $item): ?>
                    <tr>
                        <td class="small text-nowrap"><?= e(fmt_dt($item['created_at'])) ?></td>
                        <td class="small font-monospace"><?= e((string) $item['event']) ?></td>
                        <td class="small"><?= e(label((string) $item['channel'])) ?></td>
                        <td class="small text-truncate" style="max-width:170px"><?= e((string) ($item['recipient'] ?? '—')) ?></td>
                        <td class="small text-truncate" style="max-width:220px"><?= e((string) ($item['subject'] ?? '—')) ?></td>
                        <td class="text-end small"><?= (int) $item['attempts'] ?></td>
                        <td>
                            <?= status_badge((string) $item['status']) ?>
                            <?php if (!empty($item['last_error'])): ?>
                                <div class="small text-danger text-truncate" style="max-width:180px"><?= e((string) $item['last_error']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if (in_array($item['status'], ['failed', 'skipped'], true)): ?>
                                <form method="post" action="<?= e(url('admin/notifications/retry/' . $item['id'])) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-teal" type="submit">Retry</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center"><?= $queue->links() ?></div>
<?php endif; ?>
<?php View::endSection(); ?>
