<?php

use App\Core\View;

View::section('content');
?>
<div class="container py-5" style="max-width:520px">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h1 class="h5 mb-3">Seller contact details</h1>
            <p class="small text-muted"><?= e((string) $listing['title']) ?></p>

            <div class="border rounded p-3 mb-3">
                <div class="fw-semibold"><?= e((string) $contact['name']) ?></div>
                <?php if (!empty($contact['contact_person'])): ?>
                    <div class="small text-muted"><?= e((string) $contact['contact_person']) ?></div>
                <?php endif; ?>
                <?php if (!empty($contact['mobile'])): ?>
                    <div class="mt-2"><i class="bi bi-telephone me-1"></i><a href="tel:<?= e((string) $contact['mobile']) ?>"><?= e((string) $contact['mobile']) ?></a></div>
                <?php endif; ?>
                <?php if (!empty($contact['email'])): ?>
                    <div><i class="bi bi-envelope me-1"></i><a href="mailto:<?= e((string) $contact['email']) ?>"><?= e((string) $contact['email']) ?></a></div>
                <?php endif; ?>
            </div>

            <?php if (!empty($whatsapp)): ?>
                <a class="btn btn-success w-100 mb-2" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-whatsapp me-1"></i>WhatsApp the seller
                </a>
            <?php endif; ?>

            <a class="btn btn-outline-secondary w-100" href="<?= e(url('listing/' . $listing['slug'])) ?>">Back to listing</a>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
