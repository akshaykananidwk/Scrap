<?php

use App\Core\View;

View::section('content');
?>
<div class="container" style="max-width:440px">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h1 class="h5 mb-1">Reset your password</h1>
            <p class="text-muted small mb-4">
                Enter the mobile number or email on your account and we will send reset instructions.
            </p>

            <form method="post" action="<?= e(url('forgot-password')) ?>">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label required" for="identifier">Mobile number or email</label>
                    <input id="identifier" name="identifier" class="form-control form-control-lg" required autofocus>
                </div>
                <button class="btn btn-teal btn-lg w-100" type="submit">Send reset instructions</button>
            </form>

            <hr class="my-4">
            <p class="text-center small mb-0"><a href="<?= e(url('login')) ?>">Back to sign in</a></p>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
