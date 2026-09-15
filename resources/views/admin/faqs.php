<?php

use App\Core\View;

View::section('content');
$f = $edit ?? [];
$val = static fn (string $key, string $default = ''): string => (string) old($key, $f[$key] ?? $default);
?>
<h1 class="h4 mb-4">FAQs</h1>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <ul class="list-group list-group-flush">
                <?php foreach ($faqs as $faq): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="min-w-0">
                                <span class="badge text-bg-light border"><?= e(label((string) $faq['category'])) ?></span>
                                <a class="small text-decoration-none ms-1"
                                   href="<?= e(url('admin/faqs?edit=' . $faq['id'])) ?>"><?= e((string) $faq['question']) ?></a>
                            </div>
                            <div class="d-flex gap-1 align-items-center">
                                <?= (int) $faq['is_published'] === 1
                                    ? '<span class="badge badge-soft-success">Live</span>'
                                    : '<span class="badge text-bg-secondary">Draft</span>' ?>
                                <form method="post" action="<?= e(url('admin/faqs/' . $faq['id'] . '/delete')) ?>"
                                      data-confirm="Delete this FAQ?">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-link text-danger p-0" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0"><?= $edit !== null ? 'Edit FAQ' : 'Add an FAQ' ?></h6></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/faqs')) ?>">
                    <?= csrf_field() ?>
                    <?php if ($edit !== null): ?>
                        <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                    <?php endif; ?>
                    <div class="mb-2">
                        <label class="form-label small required" for="question">Question</label>
                        <input id="question" name="question" class="form-control" required maxlength="255" value="<?= e($val('question')) ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small required" for="answer">Answer</label>
                        <textarea id="answer" name="answer" class="form-control" rows="6" required><?= e($val('answer')) ?></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label small" for="category">Category</label>
                            <select id="category" name="category" class="form-select">
                                <?php foreach (['general' => 'General', 'buying' => 'Buying', 'selling' => 'Selling',
                                                'auctions' => 'Auctions', 'payments' => 'Payments', 'kyc' => 'KYC',
                                                'shipping' => 'Transport', 'account' => 'Account'] as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $val('category', 'general') === $key ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label small" for="sort_order">Order</label>
                            <input id="sort_order" name="sort_order" type="number" min="0" class="form-control"
                                   value="<?= e($val('sort_order', '0')) ?>">
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_published" value="1" id="is_published"
                            <?= old('is_published', $f['is_published'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="is_published">Published</label>
                    </div>
                    <button class="btn btn-teal w-100" type="submit">Save FAQ</button>
                    <?php if ($edit !== null): ?>
                        <a class="btn btn-link w-100 mt-1" href="<?= e(url('admin/faqs')) ?>">New FAQ</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
