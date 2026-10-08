<?php

use App\Core\View;

View::section('content');
?>

<div class="container py-4">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(url('help')) ?>">Help Centre</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($category['title']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-3">
            <?= View::partial('help/_sidebar', [
                'categories' => $categories,
                'current_category' => $category['slug'],
                'current_article' => null,
            ]) ?>
        </div>

        <div class="col-lg-9">
            <?php if (!empty($falling_back)): ?>
                <div class="alert alert-secondary small d-flex gap-2">
                    <i class="bi bi-translate flex-shrink-0 mt-1" aria-hidden="true"></i>
                    <div class="min-w-0">Not yet translated into your language, so shown in English.</div>
                </div>
            <?php endif; ?>

            <div class="d-flex align-items-start gap-3 mb-4">
                <span class="guide-cat-icon guide-cat-icon-lg flex-shrink-0">
                    <i class="bi <?= e($category['icon']) ?>" aria-hidden="true"></i>
                </span>
                <div class="min-w-0">
                    <h1 class="h3 mb-2"><?= e($category['title']) ?></h1>
                    <p class="text-muted mb-0"><?= e($category['summary']) ?></p>
                </div>
            </div>

            <?php if ($category['audience'] === 'operator'): ?>
                <div class="alert alert-warning d-flex gap-2">
                    <i class="bi bi-sliders flex-shrink-0 mt-1" aria-hidden="true"></i>
                    <div class="min-w-0">
                        This section is for whoever installs and administers
                        <?= e(site_name()) ?>. If you are here to buy or sell scrap, you want
                        <a class="alert-link" href="<?= e(url('help/getting-started/what-this-platform-does')) ?>">Getting started</a> instead.
                    </div>
                </div>
            <?php endif; ?>

            <ol class="list-unstyled guide-article-list">
                <?php foreach ($category['articles'] as $index => $article): ?>
                    <li>
                        <a class="card border-0 shadow-sm mb-3 text-decoration-none d-block guide-article-card"
                           href="<?= e(url('help/' . $category['slug'] . '/' . $article['slug'])) ?>">
                            <div class="card-body d-flex gap-3">
                                <span class="guide-article-number flex-shrink-0"><?= $index + 1 ?></span>
                                <div class="min-w-0 flex-grow-1">
                                    <h2 class="h6 mb-1 link-dark fw-semibold"><?= e($article['title']) ?></h2>
                                    <p class="small text-muted mb-0"><?= e((string) ($article['summary'] ?? '')) ?></p>
                                </div>
                                <?php if (!empty($article['minutes'])): ?>
                                    <span class="small text-muted text-nowrap flex-shrink-0 d-none d-sm-block">
                                        <i class="bi bi-clock me-1" aria-hidden="true"></i><?= (int) $article['minutes'] ?> min
                                    </span>
                                <?php endif; ?>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>

            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('help')) ?>">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>All sections
            </a>
        </div>
    </div>
</div>

<?php View::endSection(); ?>
