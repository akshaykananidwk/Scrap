<?php

use App\Core\View;

View::section('content');
?>
<div class="container" style="max-width:440px">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h1 class="h5 mb-1">Set a new password</h1>
            <p class="text-muted small mb-4">Choose a password you have not used before.</p>

            <form method="post" action="<?= e(url('reset-password')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="mb-3">
                    <label class="form-label required" for="password">New password</label>
                    <input id="password" name="password" type="password" class="form-control form-control-lg"
                           required minlength="8" autocomplete="new-password" autofocus>
                    <div class="form-text">At least 8 characters, with letters and numbers.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label required" for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password"
                           class="form-control form-control-lg" required autocomplete="new-password">
                </div>

                <button class="btn btn-teal btn-lg w-100" type="submit">Save new password</button>
            </form>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
