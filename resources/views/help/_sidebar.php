<?php

/**
 * The Help Centre contents list, shared by the category and article pages.
 *
 * On a phone it collapses into a single "All guides" button, because a
 * forty-eight-item contents list above the article would push the article
 * itself off the screen.
 *
 * @var list<array>  $categories
 * @var string       $current_category
 * @var string|null  $current_article
 */

$categories = $categories ?? [];
$currentCategory = $current_category ?? '';
$currentArticle = $current_article ?? null;
?>
<nav class="guide-sidebar" aria-label="Help Centre contents">
    <button class="btn btn-outline-secondary btn-sm w-100 d-lg-none mb-3" type="button"
            data-bs-toggle="collapse" data-bs-target="#guideContents"
            aria-expanded="false" aria-controls="guideContents">
        <i class="bi bi-list-ul me-1" aria-hidden="true"></i>All guides
    </button>

    <div class="collapse d-lg-block" id="guideContents">
        <form method="get" action="<?= e(url('help')) ?>" role="search" class="mb-3">
            <div class="input-group input-group-sm">
                <input class="form-control" type="search" name="q" placeholder="Search guides"
                       aria-label="Search the Help Centre">
                <button class="btn btn-outline-secondary" type="submit">
                    <i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Search</span>
                </button>
            </div>
        </form>

        <?php foreach ($categories as $category): ?>
            <?php $isOpen = $category['slug'] === $currentCategory; ?>
            <div class="mb-3">
                <a class="d-flex align-items-center gap-2 text-decoration-none small fw-semibold <?= $isOpen ? 'text-teal' : 'link-dark' ?>"
                   href="<?= e(url('help/' . $category['slug'])) ?>">
                    <i class="bi <?= e($category['icon']) ?> flex-shrink-0" aria-hidden="true"></i>
                    <span class="min-w-0"><?= e($category['title']) ?></span>
                </a>

                <?php if ($isOpen): ?>
                    <ul class="list-unstyled small mt-2 mb-0 guide-sidebar-articles">
                        <?php foreach ($category['articles'] as $article): ?>
                            <?php $isCurrent = $article['slug'] === $currentArticle; ?>
                            <li>
                                <a class="d-block text-decoration-none py-1 <?= $isCurrent ? 'fw-semibold text-teal' : 'link-secondary' ?>"
                                   href="<?= e(url('help/' . $category['slug'] . '/' . $article['slug'])) ?>"
                                    <?= $isCurrent ? 'aria-current="page"' : '' ?>><?= e($article['title']) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <hr>
        <a class="small text-decoration-none" href="<?= e(url('help/print')) ?>">
            <i class="bi bi-printer me-1" aria-hidden="true"></i>Whole guide on one page
        </a>
    </div>
</nav>
