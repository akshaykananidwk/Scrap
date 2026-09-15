<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-1">Roles &amp; permissions</h1>
<p class="text-muted small mb-4">
    Every admin action checks a permission, not a role name — so a custom role gets exactly the access you tick here.
</p>

<div class="accordion shadow-sm" id="rolesAccordion">
    <?php foreach ($roles as $index => $role): ?>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button"
                        data-bs-toggle="collapse" data-bs-target="#role-<?= (int) $role['id'] ?>">
                    <span class="me-2"><?= e((string) $role['name']) ?></span>
                    <?php if ((int) $role['is_staff'] === 1): ?>
                        <span class="badge text-bg-warning me-2">Staff</span>
                    <?php endif; ?>
                    <span class="small text-muted">
                        <?= count($role['permissions'] ?? []) ?> permissions
                        · <?= (int) ($role['user_count'] ?? 0) ?> users
                    </span>
                </button>
            </h2>
            <div id="role-<?= (int) $role['id'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>"
                 data-bs-parent="#rolesAccordion">
                <div class="accordion-body">
                    <?php if (!empty($role['description'])): ?>
                        <p class="small text-muted"><?= e((string) $role['description']) ?></p>
                    <?php endif; ?>

                    <form method="post" action="<?= e(url('admin/roles/' . $role['id'] . '/permissions')) ?>">
                        <?= csrf_field() ?>
                        <?php foreach ($permission_groups as $groupName => $permissions): ?>
                            <h6 class="small text-teal text-uppercase mt-3"><?= e(label((string) $groupName)) ?></h6>
                            <div class="row g-2">
                                <?php foreach ($permissions as $permission): ?>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                   name="permissions[]" value="<?= e((string) $permission['slug']) ?>"
                                                   id="p-<?= (int) $role['id'] ?>-<?= (int) $permission['id'] ?>"
                                                <?= in_array($permission['slug'], $role['permissions'] ?? [], true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small"
                                                   for="p-<?= (int) $role['id'] ?>-<?= (int) $permission['id'] ?>">
                                                <?= e((string) $permission['name']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>

                        <button class="btn btn-teal mt-3" type="submit">Save permissions for <?= e((string) $role['name']) ?></button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php View::endSection(); ?>
