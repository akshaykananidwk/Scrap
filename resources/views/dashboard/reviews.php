<?php

use App\Core\View;

View::section('content');
$overall = $summary['overall'] ?? [];
?>
<h1 class="h4 mb-4">Reviews</h1>

<?php if ($pending !== []): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><h6 class="mb-0">Waiting for your review</h6></div>
        <ul class="list-group list-group-flush">
            <?php foreach ($pending as $order): ?>
                <li class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <a class="text-decoration-none" href="<?= e(url('dashboard/orders/' . $order['id'])) ?>">
                                <?= e((string) $order['reference']) ?>
                            </a>
                            <span class="small text-muted">
                                · <?= money($order['final_amount']) ?>
                                · with <?= e((string) $order['counterparty']) ?>
                                · completed <?= e(fmt_date($order['completed_at'])) ?>
                            </span>
                        </div>
                        <button class="btn btn-sm btn-teal" data-bs-toggle="collapse"
                                data-bs-target="#review-<?= (int) $order['id'] ?>">Write a review</button>
                    </div>

                    <div class="collapse mt-3" id="review-<?= (int) $order['id'] ?>">
                        <form method="post" action="<?= e(url('dashboard/orders/' . $order['id'] . '/review')) ?>">
                            <?= csrf_field() ?>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small required" for="overall-<?= (int) $order['id'] ?>">Overall</label>
                                    <select id="overall-<?= (int) $order['id'] ?>" name="overall_rating" class="form-select" required>
                                        <option value="">Rate 1–5</option>
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <option value="<?= $i ?>"><?= str_repeat('★', $i) ?> (<?= $i ?>)</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <?php foreach ($criteria as $field => $label): ?>
                                    <div class="col-md-4">
                                        <label class="form-label small" for="<?= e($field) ?>-<?= (int) $order['id'] ?>"><?= e($label) ?></label>
                                        <select id="<?= e($field) ?>-<?= (int) $order['id'] ?>" name="<?= e($field) ?>" class="form-select">
                                            <option value="">—</option>
                                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                                <option value="<?= $i ?>"><?= $i ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                                <div class="col-12">
                                    <label class="form-label small" for="comment-<?= (int) $order['id'] ?>">Comment</label>
                                    <textarea id="comment-<?= (int) $order['id'] ?>" name="comment" class="form-control"
                                              rows="2" maxlength="2000"></textarea>
                                </div>
                            </div>
                            <button class="btn btn-teal mt-2" type="submit">Submit review</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="display-5 fw-bold"><?= e(number_format((float) ($overall['average'] ?? 0), 1)) ?></div>
                <div class="star-rating fs-5"><?= str_repeat('★', max(1, (int) round((float) ($overall['average'] ?? 0)))) ?></div>
                <p class="small text-muted"><?= (int) ($overall['total'] ?? 0) ?> published reviews</p>

                <?php $total = max(1, (int) ($overall['total'] ?? 0)); ?>
                <?php foreach (['five' => 5, 'four' => 4, 'three' => 3, 'two' => 2, 'one' => 1] as $key => $stars): ?>
                    <?php $count = (int) ($overall[$key] ?? 0); ?>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="small text-muted" style="width:1.5rem"><?= $stars ?>★</span>
                        <div class="progress flex-grow-1" style="height:6px">
                            <div class="progress-bar bg-teal" style="width:<?= (int) round($count / $total * 100) ?>%"></div>
                        </div>
                        <span class="small text-muted" style="width:2rem"><?= $count ?></span>
                    </div>
                <?php endforeach; ?>

                <hr>
                <?php foreach ($summary['criteria'] ?? [] as $label => $score): ?>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted"><?= e($label) ?></span>
                        <strong><?= e((string) $score) ?>/5</strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <ul class="nav nav-tabs mb-3">
            <?php foreach (['received' => 'Received', 'given' => 'Given'] as $key => $label): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $direction === $key ? 'active' : '' ?>"
                       href="<?= e(url('dashboard/reviews?direction=' . $key)) ?>"><?= e($label) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($reviews->isEmpty()): ?>
            <div class="empty-state bg-white rounded shadow-sm"><i class="bi bi-star"></i><p class="mb-0">No reviews here yet.</p></div>
        <?php else: ?>
            <?php foreach ($reviews->items as $review): ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="star-rating"><?= str_repeat('★', (int) $review['overall_rating']) ?></div>
                                <div class="small text-muted">
                                    <?= $direction === 'received'
                                        ? e((string) ($review['reviewer_business'] ?: $review['reviewer_name']))
                                        : e((string) ($review['reviewee_business'] ?: $review['reviewee_name'])) ?>
                                    · order <?= e((string) $review['order_reference']) ?>
                                    · <?= e(fmt_date($review['created_at'])) ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($review['comment'])): ?>
                            <p class="small mt-2 mb-2"><?= e((string) $review['comment']) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($review['seller_response'])): ?>
                            <div class="bg-light rounded p-2 small">
                                <strong>Response:</strong> <?= e((string) $review['seller_response']) ?>
                            </div>
                        <?php elseif ($direction === 'received'): ?>
                            <form method="post" action="<?= e(url('dashboard/reviews/' . $review['id'] . '/respond')) ?>" class="row g-2 mt-1">
                                <?= csrf_field() ?>
                                <div class="col-md-9">
                                    <input name="response" class="form-control form-control-sm"
                                           placeholder="Reply publicly (once)" maxlength="1000" required>
                                </div>
                                <div class="col-md-3 d-grid">
                                    <button class="btn btn-sm btn-outline-teal" type="submit">Reply</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="mt-4 d-flex justify-content-center"><?= $reviews->links() ?></div>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection(); ?>
