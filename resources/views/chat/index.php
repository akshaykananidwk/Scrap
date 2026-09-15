<?php

use App\Core\View;

View::section('content');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h4 mb-0">Messages</h1>
    <a class="btn btn-sm btn-outline-teal" href="<?= e(url('dashboard/messages?unread=1')) ?>">Unread only</a>
</div>

<?php if ($conversations === []): ?>
    <div class="empty-state bg-white rounded shadow-sm">
        <i class="bi bi-chat-dots"></i>
        <h5>No conversations</h5>
        <p class="text-muted small mb-0">Message a seller from any listing to start a conversation.</p>
    </div>
<?php else: ?>
    <div class="list-group shadow-sm">
        <?php foreach ($conversations as $conversation): ?>
            <a class="list-group-item list-group-item-action d-flex gap-3 align-items-center <?= (int) $conversation['unread'] > 0 ? 'bg-light-subtle' : '' ?>"
               href="<?= e(url('dashboard/messages/' . $conversation['id'])) ?>">
                <span class="avatar-initial" style="width:44px;height:44px">
                    <?= e(mb_strtoupper(mb_substr((string) ($conversation['other_name'] ?? '?'), 0, 1))) ?>
                </span>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex justify-content-between gap-2">
                        <strong class="small text-truncate">
                            <?= e((string) ($conversation['other_name'] ?? 'Conversation')) ?>
                        </strong>
                        <span class="small text-muted text-nowrap"><?= e(time_ago($conversation['last_message_at'])) ?></span>
                    </div>
                    <div class="small text-muted text-truncate">
                        <?php if (!empty($conversation['listing_title'])): ?>
                            <i class="bi bi-box-seam me-1"></i><?= e((string) $conversation['listing_title']) ?>
                        <?php elseif (!empty($conversation['requirement_title'])): ?>
                            <i class="bi bi-card-checklist me-1"></i><?= e((string) $conversation['requirement_title']) ?>
                        <?php elseif (!empty($conversation['order_reference'])): ?>
                            <i class="bi bi-bag-check me-1"></i>Order <?= e((string) $conversation['order_reference']) ?>
                        <?php else: ?>
                            Direct message
                        <?php endif; ?>
                    </div>
                    <div class="small text-truncate"><?= e((string) ($conversation['last_message_preview'] ?? '')) ?></div>
                </div>
                <?php if ((int) $conversation['unread'] > 0): ?>
                    <span class="badge rounded-pill text-bg-danger"><?= (int) $conversation['unread'] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php View::endSection(); ?>
