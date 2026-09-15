<?php

use App\Core\View;

View::section('content');
?>
<div class="container" style="max-width:440px">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h1 class="h5 mb-1">Sign in</h1>
            <p class="text-muted small mb-4">Use your registered mobile number or email.</p>

            <form method="post" action="<?= e(url('login')) ?>">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label required" for="identifier">Mobile number or email</label>
                    <input id="identifier" name="identifier" type="text" class="form-control form-control-lg"
                           value="<?= e((string) old('identifier')) ?>" required autofocus autocomplete="username">
                </div>

                <div class="mb-3">
                    <label class="form-label required" for="password">Password</label>
                    <div class="input-group">
                        <input id="password" name="password" type="password" class="form-control form-control-lg"
                               required autocomplete="current-password">
                        <button class="btn btn-outline-secondary" type="button"
                                onclick="const f=document.getElementById('password');f.type=f.type==='password'?'text':'password';">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                        <label class="form-check-label small" for="remember">Keep me signed in</label>
                    </div>
                    <a class="small" href="<?= e(url('forgot-password')) ?>">Forgot password?</a>
                </div>

                <button class="btn btn-teal btn-lg w-100" type="submit">Sign in</button>
            </form>

            <hr class="my-4">
            <p class="text-center small mb-0">
                New to ScrapX? <a href="<?= e(url('register')) ?>" class="fw-semibold">Create a free business account</a>
            </p>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
