<?php

use App\Core\View;

View::section('content');
$uploaded = [];
foreach ($documents as $document) {
    $uploaded[(string) $document['doc_type']][] = $document;
}
?>
<h1 class="h4 mb-1">KYC verification</h1>
<p class="text-muted small mb-4">
    Verified businesses rank higher, can bid in restricted auctions, and buyers trust them with larger deals.
</p>

<?php if ($business === null): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Create your business profile first — documents are attached to the business.</span>
        <a class="btn btn-sm btn-warning" href="<?= e(url('dashboard/business')) ?>">Create profile</a>
    </div>
<?php else: ?>
    <div class="alert <?= match ($status) {
        'verified' => 'alert-success',
        'rejected' => 'alert-danger',
        'pending', 'under_review' => 'alert-info',
        default => 'alert-secondary',
    } ?>">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <strong>Status: <?= e(label($status === 'none' ? 'Not submitted' : $status)) ?></strong>
                <?php if ($verification !== null && !empty($verification['remarks'])): ?>
                    <div class="small mt-1"><?= e((string) $verification['remarks']) ?></div>
                <?php endif; ?>
                <?php if ($verification !== null && !empty($verification['reviewed_at'])): ?>
                    <div class="small text-muted mt-1">Reviewed <?= e(fmt_dt($verification['reviewed_at'])) ?></div>
                <?php endif; ?>
            </div>
            <?php if ($can_submit): ?>
                <form method="post" action="<?= e(url('dashboard/kyc/submit')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-teal" type="submit">Submit for review</button>
                </form>
            <?php elseif ($missing !== []): ?>
                <span class="small">Missing: <?= e(implode(', ', array_map(static fn (string $d): string => $doc_types[$d] ?? $d, $missing))) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Documents</h6></div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($doc_types as $type => $typeLabel): ?>
                        <?php $isRequired = in_array($type, $required_docs, true); ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div>
                                    <span class="fw-semibold small"><?= e($typeLabel) ?></span>
                                    <?php if ($isRequired): ?><span class="badge text-bg-light border ms-1">Required</span><?php endif; ?>
                                </div>
                                <?php if (empty($uploaded[$type])): ?>
                                    <span class="badge text-bg-secondary">Not uploaded</span>
                                <?php endif; ?>
                            </div>
                            <?php foreach ($uploaded[$type] ?? [] as $document): ?>
                                <div class="d-flex justify-content-between align-items-center gap-2 mt-2 bg-light rounded p-2">
                                    <div class="small text-truncate">
                                        <i class="bi bi-file-earmark-check me-1"></i>
                                        <?= e((string) $document['original_name']) ?>
                                        <span class="text-muted">· <?= e(human_bytes((int) $document['file_size'])) ?></span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <?= status_badge((string) $document['status']) ?>
                                        <?php if ($document['status'] !== 'approved'): ?>
                                            <form method="post" action="<?= e(url('dashboard/kyc/documents/' . $document['id'] . '/delete')) ?>"
                                                  data-confirm="Delete this document?">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-link text-danger p-0" type="submit"><i class="bi bi-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!empty($document['rejection_reason'])): ?>
                                    <div class="small text-danger mt-1"><?= e((string) $document['rejection_reason']) ?></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Upload a document</h6></div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('dashboard/kyc/upload')) ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label required" for="doc_type">Document type</label>
                            <select id="doc_type" name="doc_type" class="form-select" required>
                                <?php foreach ($doc_types as $type => $typeLabel): ?>
                                    <option value="<?= e($type) ?>"><?= e($typeLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="doc_number">Document number</label>
                            <input id="doc_number" name="doc_number" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label required" for="document">File</label>
                            <input id="document" name="document" type="file" class="form-control" required
                                   accept=".pdf,.jpg,.jpeg,.png,.webp">
                            <div class="form-text">PDF or image, up to 5 MB. Stored outside the web root.</div>
                        </div>
                        <button class="btn btn-teal w-100" type="submit">Upload</button>
                    </form>
                </div>
            </div>

            <div class="alert alert-light border small mt-3 mb-0">
                <strong>Privacy:</strong> KYC files are never publicly linked. Only you and authorised
                administrators can open them, and every access is written to the audit log.
            </div>
        </div>
    </div>
<?php endif; ?>
<?php View::endSection(); ?>
