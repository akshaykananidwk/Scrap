<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Reviews</h1>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
        <select name="status" class="form-select" data-auto-submit>
            <option value="">Any status</option>
            <?php foreach (['published', 'hidden', 'flagged', 'removed'] as $status): ?>
                <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(label($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($reviews->isEmpty()): ?>
    <div class="empty-state bg-white rounded shadow-sm"><i class="bi bi-star"></i><p class="mb-0">No reviews.</p></div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr><th>Rating</th><th>Comment</th><th>Reviewer</th><th>About</th><th>Order</th><th>Date</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($reviews->items as $review): ?>
                    <tr>
                        <td class="small text-nowrap">
                            <span class="star-rating"><?= str_repeat('★', (int) $review['overall_rating']) ?></span>
                        </td>
                        <td class="small text-truncate" style="max-width:300px"><?= e((string) ($review['comment'] ?? '—')) ?></td>
                        <td class="small">
                            <a href="<?= e(url('admin/users/' . $review['reviewer_id'])) ?>">
                                <?= e((string) ($review['reviewer_name'] ?? '—')) ?>
                            </a>
                        </td>
                        <td class="small">
                            <a href="<?= e(url('admin/users/' . $review['reviewee_id'])) ?>">
                                <?= e((string) ($review['reviewee_name'] ?? '—')) ?>
                            </a>
                        </td>
                        <td class="small"><?= e((string) ($review['order_reference'] ?? '—')) ?></td>
                        <td class="small text-nowrap"><?= e(fmt_date($review['created_at'])) ?></td>
                        <td><?= status_badge((string) $review['status']) ?></td>
                        <td class="text-end">
                            <form method="post" action="<?= e(url('admin/reviews/' . $review['id'] . '/moderate')) ?>"
                                  class="d-flex gap-1 justify-content-end">
                                <?= csrf_field() ?>
                                <select name="status" class="form-select form-select-sm" style="width:auto">
                                    <?php foreach (['published' => 'Publish', 'hidden' => 'Hide', 'removed' => 'Remove'] as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= $review['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-outline-teal" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center"><?= $reviews->links() ?></div>
<?php endif; ?>
<?php View::endSection(); ?>
