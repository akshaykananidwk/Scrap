<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Wallets</h1>

<?php if (!$enabled): ?>
    <div class="alert alert-secondary">
        <strong>The wallet subsystem is disabled.</strong>
        The ledger, holds and reconciliation logic are all implemented; enable
        <em>Settings → Payments → Enable wallet</em> to start using them. No balances are tracked while it is off.
    </div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card text-center">
                <div class="stat-value fs-6"><?= money($totals['balance'] ?? 0) ?></div>
                <div class="stat-label">Total balance held</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card text-center">
                <div class="stat-value fs-6"><?= money($totals['locked'] ?? 0) ?></div>
                <div class="stat-label">Locked balance</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card text-center">
                <div class="stat-value fs-6"><?= (int) $reconciliation['checked'] ?></div>
                <div class="stat-label">Wallets reconciled</div>
            </div>
        </div>
    </div>

    <?php if ($reconciliation['mismatches'] === []): ?>
        <div class="alert alert-success small">
            <i class="bi bi-check2-circle me-1"></i>
            Every wallet balance matches the sum of its ledger entries.
        </div>
    <?php else: ?>
        <div class="alert alert-danger">
            <strong><?= count($reconciliation['mismatches']) ?> wallet(s) do not reconcile.</strong>
            <ul class="small mb-0 mt-2">
                <?php foreach ($reconciliation['mismatches'] as $mismatch): ?>
                    <li>
                        User #<?= (int) $mismatch['user_id'] ?> — stored
                        <?= money($mismatch['stored_balance'] ?? 0) ?> vs ledger
                        <?= money($mismatch['ledger_balance'] ?? 0) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>User</th><th>Business</th><th class="text-end">Balance</th>
                            <th class="text-end">Locked</th><th class="text-end">Credited</th><th class="text-end">Debited</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($wallets->items as $wallet): ?>
                            <tr>
                                <td class="small">
                                    <a href="<?= e(url('admin/users/' . $wallet['user_id'])) ?>">
                                        <?= e((string) $wallet['full_name']) ?>
                                    </a>
                                    <div class="text-muted"><?= e((string) $wallet['mobile']) ?></div>
                                </td>
                                <td class="small text-truncate" style="max-width:180px"><?= e((string) ($wallet['business_name'] ?? '—')) ?></td>
                                <td class="text-end fw-semibold"><?= money($wallet['balance']) ?></td>
                                <td class="text-end small"><?= money($wallet['locked_balance'] ?? 0) ?></td>
                                <td class="text-end small"><?= money($wallet['total_credited'] ?? 0) ?></td>
                                <td class="text-end small"><?= money($wallet['total_debited'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4 d-flex justify-content-center"><?= $wallets->links() ?></div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Manual adjustment</h6></div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('admin/wallets/adjust')) ?>"
                          data-confirm="Post this adjustment? It is written to the immutable ledger and cannot be deleted.">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label small required" for="user_id">User ID</label>
                            <input id="user_id" name="user_id" type="number" min="1" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small required" for="direction">Direction</label>
                            <select id="direction" name="direction" class="form-select" required>
                                <option value="credit">Credit (add funds)</option>
                                <option value="debit">Debit (remove funds)</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small required" for="amount">Amount (₹)</label>
                            <input id="amount" name="amount" type="number" step="0.01" min="0.01" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small required" for="description">Reason</label>
                            <input id="description" name="description" class="form-control" required
                                   placeholder="Goodwill credit, dispute settlement…">
                        </div>
                        <button class="btn btn-teal w-100" type="submit">Post adjustment</button>
                    </form>
                    <p class="small text-muted mt-3 mb-0">
                        Adjustments are append-only entries with <code>balance_before</code> and
                        <code>balance_after</code> recorded, so the ledger always reconciles.
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php View::endSection(); ?>
