<?php

use App\Core\View;

View::section('content');
?>

<div class="container py-4">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(url('help')) ?>">Help Centre</a></li>
            <li class="breadcrumb-item active" aria-current="page">Search</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-3">
            <?= View::partial('help/_sidebar', [
                'categories' => $categories,
                'current_category' => '',
                'current_article' => null,
            ]) ?>
        </div>

        <div class="col-lg-9">
            <form method="get" action="<?= e(url('help')) ?>" role="search" class="mb-4">
                <div class="input-group">
                    <input class="form-control" type="search" name="q" value="<?= e($query) ?>"
                           aria-label="Search the Help Centre" autocomplete="off">
                    <button class="btn btn-teal" type="submit">
                        <i class="bi bi-search me-1" aria-hidden="true"></i>Search
                    </button>
                </div>
            </form>

            <?php if ($results === []): ?>
                <div class="text-center py-5">
                    <i class="bi bi-search display-5 text-muted d-block mb-3" aria-hidden="true"></i>
                    <h1 class="h5">Nothing matched &ldquo;<?= e($query) ?>&rdquo;</h1>
                    <p class="text-muted small mb-4">
                        Try a single word rather than a phrase — <em>weighment</em>, <em>auction</em>,
                        <em>cron</em>, <em>GST</em>, <em>KYC</em> — or browse the sections.
                    </p>
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <a class="btn btn-sm btn-outline-teal" href="<?= e(url('help')) ?>">All sections</a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('contact')) ?>">Ask us</a>
                    </div>
                </div>
            <?php else: ?>
                <h1 class="h5 mb-1"><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?> for
                    &ldquo;<?= e($query) ?>&rdquo;</h1>
                <p class="text-muted small mb-4">Best matches first.</p>

                <ul class="list-unstyled">
                    <?php foreach ($results as $result): ?>
                        <li class="mb-3">
                            <a class="card border-0 shadow-sm text-decoration-none d-block guide-article-card"
                               href="<?= e(url('help/' . $result['category']['slug'] . '/' . $result['article']['slug'])) ?>">
                                <div class="card-body">
                                    <span class="small text-muted d-block mb-1">
                                        <i class="bi <?= e($result['category']['icon']) ?> me-1" aria-hidden="true"></i><?= e($result['category']['title']) ?>
                                    </span>
                                    <h2 class="h6 mb-1 link-dark fw-semibold"><?= e($result['article']['title']) ?></h2>
                                    <p class="small text-muted mb-0"><?= e($result['excerpt']) ?></p>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php View::endSection(); ?>
