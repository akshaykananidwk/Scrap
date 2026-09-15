<?php

use App\Core\View;

View::section('content');
$p = $edit ?? [];
$val = static fn (string $key, string $default = ''): string => (string) old($key, $p[$key] ?? $default);
?>
<h1 class="h4 mb-4">CMS pages</h1>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Pages</h6></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($pages as $page): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div class="min-w-0">
                            <a class="small text-decoration-none d-block text-truncate"
                               href="<?= e(url('admin/pages?edit=' . $page['id'])) ?>"><?= e((string) $page['title']) ?></a>
                            <span class="small text-muted">/page/<?= e((string) $page['slug']) ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <?= (int) $page['is_published'] === 1
                                ? '<span class="badge badge-soft-success">Live</span>'
                                : '<span class="badge text-bg-secondary">Draft</span>' ?>
                            <a class="btn btn-sm btn-link p-0" href="<?= e(url('page/' . $page['slug'])) ?>"
                               target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i></a>
                            <form method="post" action="<?= e(url('admin/pages/' . $page['id'] . '/delete')) ?>"
                                  data-confirm="Delete this page?">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-link text-danger p-0" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0"><?= $edit !== null ? 'Edit page' : 'New page' ?></h6></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/pages')) ?>">
                    <?= csrf_field() ?>
                    <?php if ($edit !== null): ?>
                        <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                    <?php endif; ?>

                    <div class="row g-2 mb-2">
                        <div class="col-md-8">
                            <label class="form-label small required" for="title">Title</label>
                            <input id="title" name="title" class="form-control" required value="<?= e($val('title')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small required" for="slug">URL slug</label>
                            <input id="slug" name="slug" class="form-control" required value="<?= e($val('slug')) ?>">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="content">Content (HTML)</label>
                        <textarea id="content" name="content" class="form-control font-monospace" rows="14"><?= e($val('content')) ?></textarea>
                        <div class="form-text">
                            Scripts, iframes and event handlers are stripped on save — only formatting tags survive.
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small" for="meta_title">SEO title</label>
                            <input id="meta_title" name="meta_title" class="form-control" maxlength="190" value="<?= e($val('meta_title')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="meta_description">SEO description</label>
                            <input id="meta_description" name="meta_description" class="form-control" maxlength="300"
                                   value="<?= e($val('meta_description')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small" for="sort_order">Sort order</label>
                            <input id="sort_order" name="sort_order" type="number" min="0" class="form-control"
                                   value="<?= e($val('sort_order', '0')) ?>">
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_published" value="1" id="is_published"
                                <?= old('is_published', $p['is_published'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="is_published">Published</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="show_in_footer" value="1" id="show_in_footer"
                                <?= old('show_in_footer', $p['show_in_footer'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="show_in_footer">Show in footer</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="show_in_header" value="1" id="show_in_header"
                                <?= old('show_in_header', $p['show_in_header'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="show_in_header">Show in header</label>
                        </div>
                    </div>
                    <button class="btn btn-teal" type="submit">Save page</button>
                    <?php if ($edit !== null): ?>
                        <a class="btn btn-link" href="<?= e(url('admin/pages')) ?>">New page</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
