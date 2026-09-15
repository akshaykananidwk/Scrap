<?php

use App\Core\View;

View::section('content');
$isClosed = in_array($dispute['status'], ['resolved', 'closed', 'rejected'], true);
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1"><?= e((string) $dispute['subject']) ?></h1>
        <p class="text-muted small mb-0">
            <?= e((string) $dispute['reference']) ?>
            · <?= e($categories[$dispute['category']] ?? label((string) $dispute['category'])) ?>
            · you are the <?= e($my_role) ?>
            · <?= status_badge((string) $dispute['status']) ?>
        </p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('dashboard/orders/' . $dispute['order_id'])) ?>">View order</a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h6>Complaint</h6>
                <p class="small mb-0" style="white-space:pre-line"><?= e((string) $dispute['description']) ?></p>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Conversation</h6></div>
            <?php if ($messages === []): ?>
                <div class="card-body small text-muted">No messages yet.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($messages as $message): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="small"><?= e((string) ($message['business_name'] ?? $message['full_name'] ?? 'User')) ?></strong>
                                <span class="small text-muted"><?= e(fmt_dt($message['created_at'])) ?></span>
                            </div>
                            <p class="small mb-1 mt-1" style="white-space:pre-line"><?= e((string) $message['body']) ?></p>
                            <?php if (!empty($message['attachment_path'])): ?>
                                <a class="small" href="<?= e(upload_url((string) $message['attachment_path'])) ?>" target="_blank" rel="noopener">
                                    <i class="bi bi-paperclip me-1"></i>Attachment
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php if (!$isClosed): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="post" action="<?= e(url('dashboard/disputes/' . $dispute['id'] . '/message')) ?>"
                          enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label small required" for="body">Add a message</label>
                            <textarea id="body" name="body" class="form-control" rows="3" required maxlength="5000"></textarea>
                        </div>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label small" for="attachment">Attach evidence</label>
                                <input id="attachment" name="attachment" type="file" class="form-control form-control-sm"
                                       accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                            <div class="col-md-4 d-grid">
                                <button class="btn btn-teal" type="submit">Send</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-secondary small">
                This dispute is <?= e(label((string) $dispute['status'])) ?> and no longer accepts messages.
                <?php if (!empty($dispute['resolution'])): ?>
                    <div class="mt-2"><strong>Resolution:</strong> <?= e((string) $dispute['resolution']) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Dispute details</h6></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Raised</span><strong><?= e(fmt_dt($dispute['created_at'])) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Claimed amount</span>
                    <strong><?= $dispute['claimed_amount'] !== null ? money($dispute['claimed_amount']) : '—' ?></strong>
                </li>
                <?php if ($dispute['resolution_amount'] !== null): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Settled amount</span><strong><?= money($dispute['resolution_amount']) ?></strong>
                    </li>
                <?php endif; ?>
                <?php if (!empty($dispute['resolved_at'])): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Resolved</span><strong><?= e(fmt_dt($dispute['resolved_at'])) ?></strong>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
