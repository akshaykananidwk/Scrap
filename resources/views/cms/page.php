<?php

use App\Core\View;

View::section('content');
?>
<div class="container py-5" style="max-width:820px">
    <h1 class="h3 mb-4"><?= e((string) $page['title']) ?></h1>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 cms-content">
            <?= strip_tags((string) $page['content'], '<p><br><b><strong><i><em><u><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><a><img><table><thead><tbody><tr><th><td><hr><span><div><small><code><pre>') ?>
        </div>
    </div>
    <p class="text-muted small mt-3">Last updated <?= e(fmt_date($page['updated_at'] ?? $page['created_at'])) ?></p>
</div>
<?php View::endSection(); ?>
