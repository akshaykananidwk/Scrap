<?php

use App\Core\View;

View::section('content');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1"><?= e((string) ($business['name'] ?? $user['full_name'])) ?></h1>
        <p class="text-muted small mb-0">
            KYC file #<?= (int) $verification['id'] ?>
            · submitted <?= e(fmt_dt($verification['submitted_at'] ?? $verification['created_at'])) ?>
            · <?= status_badge((string) $verification['status']) ?>
        </p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('admin/users/' . $user['id'])) ?>">Open user</a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Documents</h6></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($documents as $document): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                            <div class="min-w-0">
                                <strong class="small"><?= e($doc_types[$document['doc_type']] ?? label((string) $document['doc_type'])) ?></strong>
                                <?php if (in_array($document['doc_type'], $required_docs, true)): ?>
                                    <span class="badge text-bg-light border ms-1">Required</span>
                                <?php endif; ?>
                                <div class="small text-muted text-truncate">
                                    <?= e((string) $document['original_name']) ?>
                                    · <?= e(human_bytes((int) $document['file_size'])) ?>
                                    <?php if (!empty($document['doc_number'])): ?>
                                        · <?= e((string) $document['doc_number']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <?= status_badge((string) $document['status']) ?>
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="<?= e(url('admin/kyc/documents/' . $document['id'] . '/view')) ?>"
                                   target="_blank" rel="noopener">View</a>
                            </div>
                        </div>

                        <?php if ($document['status'] !== 'approved'): ?>
                            <form method="post" action="<?= e(url('admin/kyc/documents/' . $document['id'] . '/review')) ?>"
                                  class="row g-2 mt-1">
                                <?= csrf_field() ?>
                                <div class="col-md-3">
                                    <select name="status" class="form-select form-select-sm">
                                        <option value="approved">Approve</option>
                                        <option value="rejected">Reject</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <input name="rejection_reason" class="form-control form-control-sm" placeholder="Reason if rejecting">
                                </div>
                                <div class="col-md-3 d-grid">
                                    <button class="btn btn-sm btn-outline-teal" type="submit">Save</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Decision</h6></div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <form method="post" action="<?= e(url('admin/kyc/' . $verification['id'] . '/approve')) ?>"
                              data-confirm="Approve this business? It gets the verified badge immediately.">
                            <?= csrf_field() ?>
                            <input name="remarks" class="form-control form-control-sm mb-2" placeholder="Internal remarks (optional)">
                            <button class="btn btn-teal w-100" type="submit">
                                <i class="bi bi-patch-check me-1"></i>Approve KYC
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="post" action="<?= e(url('admin/kyc/' . $verification['id'] . '/reject')) ?>">
                            <?= csrf_field() ?>
                            <input name="remarks" class="form-control form-control-sm mb-2" required
                                   placeholder="Reason shown to the applicant">
                            <button class="btn btn-outline-danger w-100" type="submit">Reject</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Business details</h6></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Type</span>
                    <strong><?= e(label((string) ($business['business_type'] ?? ''))) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">GSTIN</span>
                    <strong class="font-monospace"><?= e((string) ($business['gstin'] ?? '—')) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">PAN</span>
                    <strong class="font-monospace"><?= e((string) ($business['pan'] ?? '—')) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Registration</span>
                    <strong><?= e((string) ($business['registration_number'] ?? '—')) ?></strong></li>
                <li class="list-group-item"><span class="text-muted d-block">Address</span>
                    <?= e(trim(implode(', ', array_filter([
                        $business['address_line1'] ?? null,
                        $business['city_name'] ?? null,
                        $business['state_name'] ?? null,
                        $business['pincode'] ?? null,
                    ])))) ?: '—' ?></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Contact</span>
                    <strong><?= e((string) ($business['contact_mobile'] ?? $user['mobile'])) ?></strong></li>
            </ul>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">GSTIN check</h6></div>
            <div class="card-body small">
                <?php if ($gst_check === null): ?>
                    <p class="text-muted mb-0">No GSTIN on file.</p>
                <?php elseif (!empty($gst_check['ok'])): ?>
                    <p class="mb-1"><i class="bi bi-check2-circle text-success me-1"></i>
                        <?= e((string) ($gst_check['message'] ?? 'Format and checksum are valid.')) ?></p>
                    <?php if (!empty($gst_check['state_name'])): ?>
                        <p class="text-muted mb-0">State from GSTIN: <?= e((string) $gst_check['state_name']) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="mb-0"><i class="bi bi-exclamation-triangle text-warning me-1"></i>
                        <?= e((string) ($gst_check['error'] ?? 'Could not verify.')) ?></p>
                <?php endif; ?>
                <hr>
                <p class="text-muted mb-0">
                    The checksum is validated offline against the GSTN algorithm. Live lookup against a
                    government API happens only when an operator configures a provider — the platform never
                    fabricates a verification result.
                </p>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
