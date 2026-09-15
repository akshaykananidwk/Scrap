<?php

use App\Core\View;

View::section('content');
$t = $edit ?? [];
?>
<h1 class="h4 mb-1">Notification templates</h1>
<p class="text-muted small mb-4">
    One template per event and channel. Placeholders like <code>{{name}}</code> are replaced at send time.
</p>

<div class="row g-3 mb-4">
    <?php foreach ($channels as $key => $channel): ?>
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-value fs-6">
                    <?= $channel['configured']
                        ? '<span class="text-success">Configured</span>'
                        : '<span class="text-muted">Not configured</span>' ?>
                </div>
                <div class="stat-label"><?= e($channel['label']) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="alert alert-light border small">
    A template on an unconfigured channel is still stored and still rendered — the message is simply marked
    <strong>skipped</strong> in the queue instead of being silently dropped, so you can see exactly what would
    have been sent once a provider is configured.
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Event</th><th>Channel</th><th>Name</th><th>Active</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($templates as $template): ?>
                        <tr>
                            <td class="small font-monospace"><?= e((string) $template['event']) ?></td>
                            <td class="small">
                                <?= e(label((string) $template['channel'])) ?>
                                <?php if (empty($channels[$template['channel']]['configured'])): ?>
                                    <span class="badge text-bg-secondary ms-1">No provider</span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= e((string) $template['name']) ?></td>
                            <td><?= (int) $template['is_active'] === 1
                                    ? '<span class="badge badge-soft-success">Yes</span>'
                                    : '<span class="badge text-bg-secondary">No</span>' ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-link p-0" href="<?= e(url('admin/templates?edit=' . $template['id'])) ?>">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <?php if ($edit === null): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6>Events that can send</h6>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($events as $event): ?>
                            <span class="badge text-bg-light border font-monospace"><?= e((string) $event) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <p class="small text-muted mt-3 mb-0">Pick a template on the left to edit it.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Edit: <?= e((string) $t['event']) ?> / <?= e(label((string) $t['channel'])) ?></h6>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('admin/templates/' . $t['id'])) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label small required" for="name">Template name</label>
                            <input id="name" name="name" class="form-control" required value="<?= e((string) $t['name']) ?>">
                        </div>
                        <?php if ($t['channel'] === 'email'): ?>
                            <div class="mb-2">
                                <label class="form-label small" for="subject">Subject</label>
                                <input id="subject" name="subject" class="form-control" value="<?= e((string) ($t['subject'] ?? '')) ?>">
                            </div>
                        <?php endif; ?>
                        <div class="mb-2">
                            <label class="form-label small required" for="body">Body</label>
                            <textarea id="body" name="body" class="form-control font-monospace" rows="10" required><?= e((string) $t['body']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small" for="variables">Available variables</label>
                            <input id="variables" name="variables" class="form-control font-monospace"
                                   value="<?= e((string) ($t['variables'] ?? '')) ?>">
                            <div class="form-text">Comma-separated list, documented for whoever edits this next.</div>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                <?= (int) $t['is_active'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="is_active">Active</label>
                        </div>
                        <button class="btn btn-teal w-100" type="submit">Save template</button>
                        <a class="btn btn-link w-100 mt-1" href="<?= e(url('admin/templates')) ?>">Done</a>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection(); ?>
