<?php

use App\Core\View;

View::section('content');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1"><?= e((string) $dispute['subject']) ?></h1>
        <p class="text-muted small mb-0">
            <?= e((string) $dispute['reference']) ?>
            · <?= e($categories[$dispute['category']] ?? label((string) $dispute['category'])) ?>
            · <?= status_badge((string) $dispute['status']) ?>
        </p>
    </div>
    <?php if ($order !== null): ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/orders/' . $order['id'])) ?>">
            Open order <?= e((string) $order['reference']) ?>
        </a>
    <?php endif; ?>
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
            <div class="card-header bg-white">
                <h6 class="mb-0">Conversation <span class="small text-muted fw-normal">(internal notes included)</span></h6>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($messages as $message): ?>
                    <li class="list-group-item <?= (int) ($message['is_internal'] ?? 0) === 1 ? 'bg-warning-subtle' : '' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong class="small">
                                <?= e((string) ($message['business_name'] ?? $message['full_name'] ?? 'User')) ?>
                                <?php if ((int) ($message['is_internal'] ?? 0) === 1): ?>
                                    <span class="badge text-bg-warning ms-1">Internal</span>
                                <?php endif; ?>
                            </strong>
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
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/disputes/' . $dispute['id'] . '/message')) ?>">
                    <?= csrf_field() ?>
                    <label class="form-label small required" for="body">Reply</label>
                    <textarea id="body" name="body" class="form-control mb-2" rows="3" required maxlength="5000"></textarea>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_internal" value="1" id="is_internal">
                        <label class="form-check-label small" for="is_internal">
                            Internal note — visible to staff only, never to the parties
                        </label>
                    </div>
                    <button class="btn btn-teal" type="submit">Post</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Resolve</h6></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/disputes/' . $dispute['id'] . '/status')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label small required" for="status">Status</label>
                        <select id="status" name="status" class="form-select" required>
                            <?php foreach (['under_review' => 'Under review', 'awaiting_response' => 'Awaiting response',
                                            'resolved' => 'Resolved', 'rejected' => 'Rejected', 'closed' => 'Closed'] as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $dispute['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="priority">Priority</label>
                        <select id="priority" name="priority" class="form-select">
                            <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= ($dispute['priority'] ?? 'normal') === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="assigned_to">Assign to</label>
                        <select id="assigned_to" name="assigned_to" class="form-select">
                            <option value="">Unassigned</option>
                            <?php foreach ($staff as $member): ?>
                                <option value="<?= (int) $member['id'] ?>"
                                    <?= (int) ($dispute['assigned_to'] ?? 0) === (int) $member['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $member['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="resolution_amount">Settlement amount (₹)</label>
                        <input id="resolution_amount" name="resolution_amount" type="number" step="0.01" min="0"
                               class="form-control" value="<?= e((string) ($dispute['resolution_amount'] ?? '')) ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="resolution">Resolution (shown to both parties)</label>
                        <textarea id="resolution" name="resolution" class="form-control" rows="3"><?= e((string) ($dispute['resolution'] ?? '')) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small" for="internal_notes">Internal notes</label>
                        <textarea id="internal_notes" name="internal_notes" class="form-control" rows="2"><?= e((string) ($dispute['internal_notes'] ?? '')) ?></textarea>
                    </div>
                    <button class="btn btn-teal w-100" type="submit">Save decision</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Details</h6></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Raised by</span>
                    <a href="<?= e(url('admin/users/' . $dispute['raised_by'])) ?>"><?= e((string) ($dispute['raised_by_business'] ?? $dispute['raised_by_name'] ?? 'User')) ?></a></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Against</span>
                    <a href="<?= e(url('admin/users/' . $dispute['against_user_id'])) ?>"><?= e((string) ($dispute['against_business'] ?? $dispute['against_name'] ?? 'User')) ?></a></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Claimed</span>
                    <strong><?= $dispute['claimed_amount'] !== null ? money($dispute['claimed_amount']) : '—' ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Raised</span>
                    <strong><?= e(fmt_dt($dispute['created_at'])) ?></strong></li>
            </ul>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
