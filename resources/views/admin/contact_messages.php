<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Contact messages</h1>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
        <select name="status" class="form-select" data-auto-submit>
            <option value="">Any status</option>
            <?php foreach (['new', 'read', 'replied', 'closed'] as $status): ?>
                <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(label($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($messages->isEmpty()): ?>
    <div class="empty-state bg-white rounded shadow-sm"><i class="bi bi-envelope"></i><p class="mb-0">No messages.</p></div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <ul class="list-group list-group-flush">
            <?php foreach ($messages->items as $message): ?>
                <li class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                        <div class="min-w-0">
                            <strong class="small"><?= e((string) $message['name']) ?></strong>
                            <span class="small text-muted">
                                · <a href="mailto:<?= e((string) $message['email']) ?>"><?= e((string) $message['email']) ?></a>
                                <?php if (!empty($message['mobile'])): ?>· <?= e((string) $message['mobile']) ?><?php endif; ?>
                            </span>
                            <?php if (!empty($message['subject'])): ?>
                                <div class="small fw-semibold"><?= e((string) $message['subject']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="text-end">
                            <?= status_badge((string) $message['status']) ?>
                            <div class="small text-muted"><?= e(fmt_dt($message['created_at'])) ?></div>
                        </div>
                    </div>
                    <p class="small mt-2 mb-0" style="white-space:pre-line"><?= e((string) $message['message']) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="mt-4 d-flex justify-content-center"><?= $messages->links() ?></div>
<?php endif; ?>
<?php View::endSection(); ?>
