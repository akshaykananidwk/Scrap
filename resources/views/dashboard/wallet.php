<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Wallet</h1>

<?php if (!$enabled): ?>
    <div class="alert alert-secondary">
        <h6 class="alert-heading">Wallet is turned off</h6>
        <p class="small mb-0">
            The wallet subsystem is fully built (immutable ledger, holds, payouts) but is
            <strong>disabled in settings</strong> for this installation. An administrator can enable it under
            <em>Admin → Settings → Payments → Enable wallet</em>. No balances are tracked while it is off.
        </p>
    </div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-value fs-5"><?= money($wallet['balance'] ?? 0) ?></div>
                <div class="stat-label">Available balance</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-value fs-5"><?= money($wallet['held_amount'] ?? 0) ?></div>
                <div class="stat-label">On hold</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-value fs-5"><?= money($wallet['total_credited'] ?? 0) ?></div>
                <div class="stat-label">Total credited</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-value fs-5"><?= money($wallet['total_debited'] ?? 0) ?></div>
                <div class="stat-label">Total debited</div>
            </div>
        </div>
    </div>

    <?php if (is_array($reconciliation)): ?>
        <div class="alert <?= !empty($reconciliation['matches']) ? 'alert-success' : 'alert-danger' ?> small">
            <?php if (!empty($reconciliation['matches'])): ?>
                <i class="bi bi-check2-circle me-1"></i>
                Ledger reconciled — the stored balance matches the sum of
                <?= (int) ($reconciliation['entries'] ?? 0) ?> ledger entries
                (<?= money($reconciliation['ledger_balance'] ?? 0) ?>).
            <?php else: ?>
                <i class="bi bi-exclamation-triangle me-1"></i>
                Reconciliation mismatch: stored <?= money($reconciliation['stored_balance'] ?? 0) ?>
                vs ledger <?= money($reconciliation['ledger_balance'] ?? 0) ?>.
                Contact support — no funds move until this is resolved.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><h6 class="mb-0">Ledger</h6></div>
        <?php if ($transactions === null || $transactions->isEmpty()): ?>
            <div class="card-body text-center text-muted small py-4">No wallet transactions.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Date</th><th>Reference</th><th>Description</th>
                        <th class="text-end">Credit</th><th class="text-end">Debit</th><th class="text-end">Balance</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($transactions->items as $transaction): ?>
                        <tr>
                            <td class="small text-nowrap"><?= e(fmt_dt($transaction['created_at'])) ?></td>
                            <td class="small"><?= e((string) $transaction['reference']) ?></td>
                            <td class="small"><?= e((string) $transaction['description']) ?></td>
                            <td class="text-end text-success">
                                <?= $transaction['type'] === 'credit' ? money($transaction['amount']) : '—' ?>
                            </td>
                            <td class="text-end text-danger">
                                <?= $transaction['type'] === 'debit' ? money($transaction['amount']) : '—' ?>
                            </td>
                            <td class="text-end fw-semibold"><?= money($transaction['balance_after']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body"><?= $transactions->links() ?></div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php View::endSection(); ?>
