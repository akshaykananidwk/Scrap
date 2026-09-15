<?php

use App\Core\View;
use App\Services\SettingsService;

View::section('content');
?>
<div class="container py-5" style="max-width:900px">
    <h1 class="h3 mb-4">Contact us</h1>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="post" action="<?= e(url('contact')) ?>">
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required" for="name">Your name</label>
                                <input id="name" name="name" class="form-control" required value="<?= e((string) old('name')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required" for="email">Email</label>
                                <input id="email" name="email" type="email" class="form-control" required value="<?= e((string) old('email')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="mobile">Mobile</label>
                                <input id="mobile" name="mobile" class="form-control" maxlength="10" value="<?= e((string) old('mobile')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="subject">Subject</label>
                                <input id="subject" name="subject" class="form-control" value="<?= e((string) old('subject')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label required" for="message">Message</label>
                                <textarea id="message" name="message" class="form-control" rows="5" required><?= e((string) old('message')) ?></textarea>
                            </div>
                        </div>
                        <button class="btn btn-teal mt-3" type="submit">Send message</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="mb-3">Reach us directly</h6>
                    <?php if ($phone = SettingsService::get('contact_phone')): ?>
                        <p class="small mb-2"><i class="bi bi-telephone text-teal me-2"></i><a href="tel:<?= e((string) $phone) ?>"><?= e((string) $phone) ?></a></p>
                    <?php endif; ?>
                    <?php if ($email = SettingsService::get('contact_email')): ?>
                        <p class="small mb-2"><i class="bi bi-envelope text-teal me-2"></i><a href="mailto:<?= e((string) $email) ?>"><?= e((string) $email) ?></a></p>
                    <?php endif; ?>
                    <?php if ($whatsapp = SettingsService::get('whatsapp_number')): ?>
                        <p class="small mb-2"><i class="bi bi-whatsapp text-success me-2"></i><?= e((string) $whatsapp) ?></p>
                    <?php endif; ?>
                    <?php if ($address = SettingsService::get('contact_address')): ?>
                        <p class="small mb-0"><i class="bi bi-geo-alt text-teal me-2"></i><?= nl2br(e((string) $address)) ?></p>
                    <?php endif; ?>

                    <hr>
                    <p class="small text-muted mb-0">
                        Have a problem with a specific order? Raise a dispute from the order page —
                        it reaches our support team with the full deal history attached.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
