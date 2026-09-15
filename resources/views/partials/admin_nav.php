<?php
/** @var array $nav */

use App\Core\Auth;
?>
<div class="py-3">
    <?php foreach ($nav as [$group, $links]): ?>
        <?php
        // Hide a whole group when the user has none of its permissions.
        $visible = array_values(array_filter(
            $links,
            static fn (array $link): bool => $link[4] === null || Auth::can((string) $link[4])
        ));
        if ($visible === []) {
            continue;
        }
        ?>
        <div class="admin-nav-group">
            <div class="admin-nav-heading"><?= e($group) ?></div>
            <ul class="nav flex-column">
                <?php foreach ($visible as [$href, $icon, $label, $badge, $permission]): ?>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 <?= active_nav($href) ?>"
                           href="<?= e(url(ltrim($href, '/'))) ?>">
                            <i class="bi <?= e($icon) ?>"></i>
                            <span class="flex-grow-1"><?= e($label) ?></span>
                            <?php if ($badge): ?>
                                <span class="badge rounded-pill text-bg-danger"><?= (int) $badge ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</div>
