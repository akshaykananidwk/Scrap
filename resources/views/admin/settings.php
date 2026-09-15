<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Settings</h1>

<div class="row g-4">
    <div class="col-lg-3">
        <div class="list-group shadow-sm">
            <?php foreach ($groups as $key => $label): ?>
                <a class="list-group-item list-group-item-action <?= $group === $key ? 'active' : '' ?>"
                   href="<?= e(url('admin/settings?group=' . $key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-lg-9">
        <?php if ($group === 'mail'): ?>
            <div class="alert <?= $providers['email']->isConfigured() ? 'alert-success' : 'alert-secondary' ?> small
                        d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>
                    Email provider: <strong><?= e($providers['email']->name()) ?></strong> —
                    <?= $providers['email']->isConfigured() ? 'configured and sending.' : 'not configured; email is queued and marked skipped.' ?>
                </span>
                <form method="post" action="<?= e(url('admin/settings/test-mail')) ?>" class="d-flex gap-2">
                    <?= csrf_field() ?>
                    <input name="to" type="email" class="form-control form-control-sm" placeholder="Send a test to…" required>
                    <button class="btn btn-sm btn-outline-teal" type="submit">Send test</button>
                </form>
            </div>
        <?php elseif ($group === 'sms'): ?>
            <div class="alert <?= $providers['sms']->isConfigured() ? 'alert-success' : 'alert-secondary' ?> small">
                SMS provider: <strong><?= e($providers['sms']->name()) ?></strong> —
                <?= $providers['sms']->isConfigured()
                    ? 'configured.'
                    : 'not configured. SMS is architected and queued but intentionally disabled until an operator supplies gateway credentials.' ?>
            </div>
        <?php elseif ($group === 'whatsapp'): ?>
            <div class="alert <?= $providers['whatsapp']->isConfigured() ? 'alert-success' : 'alert-secondary' ?> small">
                WhatsApp provider: <strong><?= e($providers['whatsapp']->name()) ?></strong> —
                <?= $providers['whatsapp']->isConfigured()
                    ? 'configured.'
                    : 'not configured. Messages are recorded as skipped until Business API credentials are supplied.' ?>
            </div>
        <?php elseif ($group === 'payments'): ?>
            <div class="alert <?= $payment_gateway->isConfigured() ? 'alert-success' : 'alert-secondary' ?> small">
                Payment gateway: <strong><?= e($payment_gateway->name()) ?></strong> —
                <?= $payment_gateway->isConfigured()
                    ? 'configured for online collection.'
                    : 'not configured. Payments are recorded manually (UPI, NEFT, RTGS, cheque, cash) and confirmed by the seller.' ?>
            </div>
        <?php elseif ($group === 'cron'): ?>
            <div class="alert alert-light border small">
                <strong>Scheduler URL</strong> (keep this secret — anyone with it can trigger the scheduler):
                <div class="input-group input-group-sm mt-2">
                    <input class="form-control font-monospace" value="<?= e($cron_url) ?>" readonly id="cron-url">
                    <button class="btn btn-outline-secondary" type="button" data-copy="#cron-url">Copy</button>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('admin/settings')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="group" value="<?= e($group) ?>">

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0"><?= e($groups[$group] ?? 'Settings') ?></h6></div>
                <div class="card-body">
                    <?php if ($settings === []): ?>
                        <p class="small text-muted mb-0">No settings in this group.</p>
                    <?php endif; ?>

                    <?php foreach ($settings as $setting): ?>
                        <?php $key = (string) $setting['key_name']; ?>
                        <div class="mb-3">
                            <?php if ($setting['type'] === 'boolean'): ?>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="s-<?= e($key) ?>" name="settings[<?= e($key) ?>]" value="1"
                                        <?= in_array((string) $setting['value'], ['1', 'true', 'yes', 'on'], true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="s-<?= e($key) ?>">
                                        <?= e((string) ($setting['label'] ?: $key)) ?>
                                    </label>
                                </div>
                            <?php else: ?>
                                <label class="form-label" for="s-<?= e($key) ?>">
                                    <?= e((string) ($setting['label'] ?: $key)) ?>
                                </label>
                                <?php if ($setting['type'] === 'text' || $setting['type'] === 'json'): ?>
                                    <textarea id="s-<?= e($key) ?>" name="settings[<?= e($key) ?>]"
                                              class="form-control <?= $setting['type'] === 'json' ? 'font-monospace' : '' ?>"
                                              rows="4"><?= e((string) $setting['value']) ?></textarea>
                                <?php elseif ($setting['type'] === 'secret'): ?>
                                    <input id="s-<?= e($key) ?>" name="settings[<?= e($key) ?>]" type="password"
                                           class="form-control" autocomplete="new-password"
                                           value="<?= $setting['value'] === '__SET__' ? '__SET__' : '' ?>"
                                           placeholder="<?= $setting['value'] === '__SET__' ? 'Stored — leave unchanged to keep it' : 'Not set' ?>">
                                <?php elseif ($setting['type'] === 'integer'): ?>
                                    <input id="s-<?= e($key) ?>" name="settings[<?= e($key) ?>]" type="number" step="1"
                                           class="form-control" value="<?= e((string) $setting['value']) ?>">
                                <?php elseif ($setting['type'] === 'decimal'): ?>
                                    <input id="s-<?= e($key) ?>" name="settings[<?= e($key) ?>]" type="number" step="0.001"
                                           class="form-control" value="<?= e((string) $setting['value']) ?>">
                                <?php else: ?>
                                    <input id="s-<?= e($key) ?>" name="settings[<?= e($key) ?>]" class="form-control"
                                           value="<?= e((string) $setting['value']) ?>">
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (!empty($setting['description'])): ?>
                                <div class="form-text"><?= e((string) $setting['description']) ?></div>
                            <?php endif; ?>
                            <?php if ($setting['type'] === 'secret'): ?>
                                <div class="form-text">
                                    Stored encrypted with AES-256-GCM. The value is never echoed back to the browser,
                                    written to logs, or returned by the API.
                                </div>
                            <?php endif; ?>
                            <div class="form-text font-monospace text-muted"><?= e($key) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($settings !== []): ?>
                    <div class="card-footer bg-white">
                        <button class="btn btn-teal" type="submit">Save <?= e($groups[$group] ?? '') ?> settings</button>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?php View::endSection(); ?>
