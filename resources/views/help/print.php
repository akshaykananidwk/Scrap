<?php

use App\Core\View;

View::section('content');
?>

<div class="guide-print">
    <div class="d-print-none mb-4 d-flex flex-wrap gap-2 align-items-center">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('help')) ?>">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to the Help Centre
        </a>
        <span class="small text-muted">
            Use your browser&rsquo;s print dialogue and &ldquo;Save as PDF&rdquo; to keep a copy.
        </span>
    </div>

    <header class="mb-5 pb-3 border-bottom">
        <h1 class="h2 mb-2"><?= e(site_name()) ?> — complete guide</h1>
        <p class="text-muted mb-0">
            All <?= (int) $article_count ?> guides, in reading order. Generated <?= e(fmt_date(now())) ?>.
        </p>
    </header>

    <nav class="mb-5" aria-label="Contents">
        <h2 class="h5 mb-3">Contents</h2>
        <?php $articleNumber = 0; ?>
        <?php foreach ($categories as $categoryIndex => $category): ?>
            <p class="fw-semibold mb-1"><?= $categoryIndex + 1 ?>. <?= e($category['title']) ?></p>
            <ul class="list-unstyled ms-4 mb-3 small">
                <?php foreach ($category['articles'] as $article): ?>
                    <?php $articleNumber++; ?>
                    <li>
                        <a class="text-decoration-none" href="#guide-<?= e($category['slug'] . '-' . $article['slug']) ?>">
                            <?= $categoryIndex + 1 ?>.<?= $articleNumber ?> <?= e($article['title']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php $articleNumber = 0; ?>
        <?php endforeach; ?>
    </nav>

    <?php foreach ($categories as $categoryIndex => $category): ?>
        <section class="guide-print-category">
            <h2 class="h3 mt-5 mb-2 pt-3 border-top">
                <?= $categoryIndex + 1 ?>. <?= e($category['title']) ?>
            </h2>
            <p class="text-muted"><?= e($category['summary']) ?></p>

            <?php foreach ($category['articles'] as $articleIndex => $article): ?>
                <article class="guide-article guide-print-article mt-4"
                         id="guide-<?= e($category['slug'] . '-' . $article['slug']) ?>">
                    <h3 class="h5 mb-2">
                        <?= $categoryIndex + 1 ?>.<?= $articleIndex + 1 ?> <?= e($article['title']) ?>
                    </h3>
                    <?php if (!empty($article['summary'])): ?>
                        <p class="text-muted fst-italic"><?= e((string) $article['summary']) ?></p>
                    <?php endif; ?>

                    <?= View::partial('help/_blocks', ['blocks' => $article['body'], 'flat' => true]) ?>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>

    <footer class="mt-5 pt-3 border-top small text-muted">
        <?= e(site_name()) ?> — complete guide. For the latest version see
        <code><?= e(base_url('help')) ?></code>.
    </footer>
</div>

<?php View::endSection(); ?>
