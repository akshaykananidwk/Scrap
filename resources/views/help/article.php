<?php

use App\Core\View;

View::section('content');
?>

<div class="container py-4">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(url('help')) ?>">Help Centre</a></li>
            <li class="breadcrumb-item">
                <a href="<?= e(url('help/' . $category['slug'])) ?>"><?= e($category['title']) ?></a>
            </li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($article['title']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-3">
            <?= View::partial('help/_sidebar', [
                'categories' => $categories,
                'current_category' => $category['slug'],
                'current_article' => $article['slug'],
            ]) ?>
        </div>

        <div class="col-lg-9">
            <article class="guide-article">
                <?php if (!empty($falling_back)): ?>
                    <div class="alert alert-secondary small d-flex gap-2">
                        <i class="bi bi-translate flex-shrink-0 mt-1" aria-hidden="true"></i>
                        <div class="min-w-0">Not yet translated into your language, so shown in English.</div>
                    </div>
                <?php endif; ?>

                <header class="mb-4 pb-3 border-bottom">
                    <h1 class="h3 mb-2"><?= e($article['title']) ?></h1>
                    <?php if (!empty($article['summary'])): ?>
                        <p class="text-muted mb-2"><?= e((string) $article['summary']) ?></p>
                    <?php endif; ?>
                    <p class="small text-muted mb-0 d-flex flex-wrap gap-3">
                        <span><i class="bi bi-folder me-1" aria-hidden="true"></i><?= e($category['title']) ?></span>
                        <?php if (!empty($article['minutes'])): ?>
                            <span><i class="bi bi-clock me-1" aria-hidden="true"></i><?= (int) $article['minutes'] ?> min read</span>
                        <?php endif; ?>
                    </p>
                </header>

                <?= View::partial('help/_blocks', ['blocks' => $article['body'], 'flat' => false]) ?>
            </article>

            <nav class="row g-3 mt-5 pt-3 border-top" aria-label="More in this section">
                <div class="col-sm-6">
                    <?php if ($previous !== null): ?>
                        <a class="card border-0 bg-light text-decoration-none h-100"
                           href="<?= e(url('help/' . $previous['category_slug'] . '/' . $previous['slug'])) ?>" rel="prev">
                            <div class="card-body">
                                <span class="small text-muted d-block mb-1">
                                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Previous
                                </span>
                                <span class="fw-semibold link-dark small"><?= e($previous['title']) ?></span>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="col-sm-6">
                    <?php if ($next !== null): ?>
                        <a class="card border-0 bg-light text-decoration-none h-100 text-sm-end"
                           href="<?= e(url('help/' . $next['category_slug'] . '/' . $next['slug'])) ?>" rel="next">
                            <div class="card-body">
                                <span class="small text-muted d-block mb-1">
                                    Next<i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                                </span>
                                <span class="fw-semibold link-dark small"><?= e($next['title']) ?></span>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>
            </nav>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('help/' . $category['slug'])) ?>">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>All of <?= e($category['title']) ?>
                </a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('contact')) ?>">
                    <i class="bi bi-envelope me-1" aria-hidden="true"></i>This did not answer my question
                </a>
            </div>
        </div>
    </div>
</div>

<?php View::endSection(); ?>
