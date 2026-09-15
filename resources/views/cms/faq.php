<?php

use App\Core\View;

View::section('content');
?>
<div class="container py-5" style="max-width:860px">
    <h1 class="h3 mb-4">Frequently asked questions</h1>

    <?php foreach ($grouped as $category => $faqs): ?>
        <h5 class="mt-4 mb-3 text-teal"><?= e(label((string) $category)) ?></h5>
        <div class="accordion mb-3" id="faq-<?= e(slugify((string) $category)) ?>">
            <?php foreach ($faqs as $faq): ?>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#q<?= (int) $faq['id'] ?>">
                            <?= e((string) $faq['question']) ?>
                        </button>
                    </h3>
                    <div id="q<?= (int) $faq['id'] ?>" class="accordion-collapse collapse"
                         data-bs-parent="#faq-<?= e(slugify((string) $category)) ?>">
                        <div class="accordion-body small">
                            <?= strip_tags((string) $faq['answer'], '<p><br><ul><ol><li><strong><em><a><code>') ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="alert alert-light border mt-4">
        Still stuck? <a href="<?= e(url('contact')) ?>">Contact our team</a>.
    </div>
</div>
<?php View::endSection(); ?>
