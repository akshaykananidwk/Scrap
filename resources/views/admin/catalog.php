<?php

use App\Core\View;

View::section('content');
$c = $edit ?? [];
$val = static fn (string $key, string $default = ''): string => (string) old($key, $c[$key] ?? $default);
?>
<h1 class="h4 mb-1">Scrap categories</h1>
<p class="text-muted small mb-4">
    Categories are fully dynamic — add, rename, nest and reorder them without touching code.
</p>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Category tree</h6></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($tree as $root): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <div>
                                <?php if (!empty($root['icon'])): ?>
                                    <i class="bi <?= e((string) $root['icon']) ?> me-1 text-teal"></i>
                                <?php endif; ?>
                                <strong><?= e((string) $root['name']) ?></strong>
                                <span class="small text-muted">
                                    · <?= (int) ($root['listing_count'] ?? 0) ?> listings
                                    · order <?= (int) $root['sort_order'] ?>
                                </span>
                                <?php if ((int) $root['is_featured'] === 1): ?>
                                    <span class="badge badge-soft-info ms-1">Featured</span>
                                <?php endif; ?>
                                <?php if ((int) $root['is_active'] !== 1): ?>
                                    <span class="badge text-bg-secondary ms-1">Hidden</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-1">
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/catalog?edit=' . $root['id'])) ?>">Edit</a>
                                <form method="post" action="<?= e(url('admin/catalog/categories/' . $root['id'] . '/delete')) ?>"
                                      data-confirm="Delete this category? It must have no listings or subcategories.">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>

                        <?php if (!empty($root['children'])): ?>
                            <ul class="list-unstyled ms-4 mt-2 mb-0">
                                <?php foreach ($root['children'] as $child): ?>
                                    <li class="d-flex justify-content-between align-items-center gap-2 py-1 border-top">
                                        <span class="small">
                                            <?= e((string) $child['name']) ?>
                                            <span class="text-muted">· <?= (int) ($child['listing_count'] ?? 0) ?> listings</span>
                                            <?php if ((int) $child['is_active'] !== 1): ?>
                                                <span class="badge text-bg-secondary ms-1">Hidden</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="d-flex gap-1">
                                            <a class="btn btn-sm btn-link p-0" href="<?= e(url('admin/catalog?edit=' . $child['id'])) ?>">Edit</a>
                                            <form method="post" action="<?= e(url('admin/catalog/categories/' . $child['id'] . '/delete')) ?>"
                                                  data-confirm="Delete this subcategory?">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-link text-danger p-0" type="submit">Delete</button>
                                            </form>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><?= $edit !== null ? 'Edit category' : 'Add a category' ?></h6>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/catalog/categories')) ?>">
                    <?= csrf_field() ?>
                    <?php if ($edit !== null): ?>
                        <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-2">
                        <label class="form-label small required" for="name">Name</label>
                        <input id="name" name="name" class="form-control" required value="<?= e($val('name')) ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="parent_id">Parent category</label>
                        <select id="parent_id" name="parent_id" class="form-select">
                            <option value="">None — this is a top-level category</option>
                            <?php foreach ($roots as $root): ?>
                                <?php if ($edit !== null && (int) $root['id'] === (int) $edit['id']) { continue; } ?>
                                <option value="<?= (int) $root['id'] ?>" <?= (int) $val('parent_id') === (int) $root['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $root['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small" for="icon">Icon class</label>
                            <input id="icon" name="icon" class="form-control" placeholder="bi-nut" value="<?= e($val('icon')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="sort_order">Sort order</label>
                            <input id="sort_order" name="sort_order" type="number" min="0" class="form-control"
                                   value="<?= e($val('sort_order', '0')) ?>">
                        </div>
                    </div>
                    <div class="mb-2 mt-2">
                        <label class="form-label small" for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="2"><?= e($val('description')) ?></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="meta_title">SEO title</label>
                        <input id="meta_title" name="meta_title" class="form-control" maxlength="190" value="<?= e($val('meta_title')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small" for="meta_description">SEO description</label>
                        <textarea id="meta_description" name="meta_description" class="form-control" rows="2"
                                  maxlength="300"><?= e($val('meta_description')) ?></textarea>
                    </div>
                    <div class="d-flex gap-3 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                <?= old('is_active', $c['is_active'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="is_active">Active</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="is_featured"
                                <?= old('is_featured', $c['is_featured'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="is_featured">Featured on the home page</label>
                        </div>
                    </div>
                    <button class="btn btn-teal w-100" type="submit"><?= $edit !== null ? 'Save changes' : 'Add category' ?></button>
                    <?php if ($edit !== null): ?>
                        <a class="btn btn-link w-100 mt-1" href="<?= e(url('admin/catalog')) ?>">Cancel editing</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
