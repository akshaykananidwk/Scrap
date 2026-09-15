<?php

use App\Core\View;

View::section('content');
?>
<div class="container" style="max-width:440px">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 text-center">
            <i class="bi bi-phone text-teal" style="font-size:2.5rem"></i>
            <h1 class="h5 mt-3 mb-1">Verify your mobile number</h1>
            <p class="text-muted small mb-4">
                We sent a code to <strong><?= e($masked) ?></strong>.
            </p>

            <form method="post" action="<?= e(url('verify/confirm')) ?>" class="mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="channel" value="sms">
                <input name="code" class="form-control form-control-lg text-center mb-3" inputmode="numeric"
                       maxlength="8" placeholder="000000" required autofocus
                       style="letter-spacing:.5rem;font-size:1.5rem">
                <button class="btn btn-teal btn-lg w-100" type="submit">Verify</button>
            </form>

            <form method="post" action="<?= e(url('verify/send')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="channel" value="sms">
                <button class="btn btn-link btn-sm" type="submit">Send the code again</button>
            </form>

            <?php if (!empty($user['email'])): ?>
                <form method="post" action="<?= e(url('verify/send')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="channel" value="email">
                    <button class="btn btn-link btn-sm text-muted" type="submit">Send to my email instead</button>
                </form>
            <?php endif; ?>

            <hr class="my-3">
            <a class="small text-muted" href="<?= e(url('dashboard')) ?>">Skip for now — some features stay locked</a>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
