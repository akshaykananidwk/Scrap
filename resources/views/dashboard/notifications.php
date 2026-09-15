<?php

use App\Core\View;

View::section('content');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Notifications <?php if ($unread > 0): ?><span class="badge text-bg-danger"><?= (int) $unread ?></span><?php endif; ?></h1>
    <?php if ($unread > 0): ?>
        <form method="post" action="<?= e(url('dashboard/notifications/read')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-teal" type="submit">Mark all read</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($notifications->isEmpty()): ?>
    <div class="empty-state bg-white rounded shadow-sm"><i class="bi bi-bell"></i><p class="mb-0">Nothing here yet.</p></div>
<?php else: ?>
    <div class="list-group shadow-sm">
        <?php foreach ($notifications->items as $notification): ?>
            <div class="list-group-item d-flex gap-3 <?= $notification['read_at'] === null ? 'bg-light-subtle border-start border-3 border-teal' : '' ?>">
                <i class="bi <?= e((string) ($notification['icon'] ?: 'bi-bell')) ?> fs-5 text-teal"></i>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold small"><?= e((string) $notification['title']) ?></div>
                    <div class="small text-muted"><?= e((string) $notification['body']) ?></div>
                    <div class="small text-muted mt-1"><?= e(time_ago($notification['created_at'])) ?></div>
                </div>
                <div class="text-end d-flex flex-column gap-1">
                    <?php if (!empty($notification['action_url'])): ?>
                        <a class="btn btn-sm btn-outline-teal" href="<?= e(url(ltrim((string) $notification['action_url'], '/'))) ?>">Open</a>
                    <?php endif; ?>
                    <?php if ($notification['read_at'] === null): ?>
                        <form method="post" action="<?= e(url('dashboard/notifications/read')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                            <button class="btn btn-sm btn-link text-muted p-0" type="submit">Mark read</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-4 d-flex justify-content-center"><?= $notifications->links() ?></div>
<?php endif; ?>
<?php View::endSection(); ?>
