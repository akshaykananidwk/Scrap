<?php

use App\Core\View;

View::section('content');
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4 text-center">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i>
        <h1 class="h4 mt-3 mb-2">Installation complete</h1>
        <p class="text-muted">Your marketplace is live. The installer is now locked.</p>

        <?php if (!empty($steps)): ?>
            <div class="text-start small bg-light rounded p-3 my-4">
                <?php foreach ($steps as $step): ?>
                    <div class="d-flex gap-2 py-1">
                        <i class="bi bi-check text-success"></i>
                        <span><strong><?= e($step['label']) ?>:</strong> <?= e($step['detail']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="alert alert-warning text-start small">
            <strong>Three things to do now:</strong>
            <ol class="mb-0 mt-2 ps-3">
                <li>Sign in as <code><?= e($admin_email) ?></code> and change your password.</li>
                <li>Set up the scheduler so auctions close on time — Admin → System → Scheduler.
                    On a server with cron: <code><?= e($cron_command) ?></code></li>
                <li>Configure email (Admin → Settings → Email) so OTPs and notifications actually send.</li>
            </ol>
        </div>

        <?php if (!empty($demo_data)): ?>
            <div class="alert alert-info text-start small">
                <strong>Demo data installed.</strong> Demo accounts use password <code>Demo@1234</code>.
                Remove everything from Admin → Import / Export → Demo data when you go live.
            </div>
        <?php endif; ?>

        <div class="d-flex gap-2 justify-content-center flex-wrap mt-4">
            <a class="btn btn-teal btn-lg" href="<?= e(url('login')) ?>">Sign in to admin</a>
            <a class="btn btn-outline-secondary btn-lg" href="<?= e(url('/')) ?>">View your marketplace</a>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
