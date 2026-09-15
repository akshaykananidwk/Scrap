<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-1">Requirements you can supply</h1>
<p class="text-muted small mb-4">Matched against the categories and materials you already list.</p>

<?php if ($matches === []): ?>
    <div class="empty-state bg-white rounded shadow-sm">
        <i class="bi bi-bullseye"></i>
        <h5>No matches right now</h5>
        <p class="text-muted small">List more materials and buyers looking for them will show up here.</p>
        <a class="btn btn-teal" href="<?= e(url('wanted')) ?>">Browse all requirements</a>
    </div>
<?php else: ?>
    <div class="row g-3 mb-5">
        <?php foreach ($matches as $match): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <a class="text-decoration-none fw-semibold d-block mb-1"
                           href="<?= e(url('wanted/' . $match['slug'])) ?>"><?= e((string) $match['title']) ?></a>
                        <div class="small text-muted mb-2">
                            <?= e(qty($match['quantity'], (string) ($match['unit_code'] ?? ''))) ?>
                            <?php if ($match['target_price'] !== null): ?>
                                · target <?= money($match['target_price']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted mb-3">
                            <i class="bi bi-geo-alt"></i>
                            <?= e(trim(((string) ($match['city_name'] ?? '')) . ', ' . ((string) ($match['state_name'] ?? '')), ', ') ?: 'India') ?>
                        </div>
                        <a class="btn btn-sm btn-teal mt-auto" href="<?= e(url('wanted/' . $match['slug'])) ?>">Send an offer</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h5 class="mb-3">Offers I have sent</h5>
<?php if ($my_offers === []): ?>
    <p class="small text-muted">You have not offered on any requirement yet.</p>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                <tr><th>Requirement</th><th class="text-end">My price</th><th class="text-end">Quantity</th>
                    <th>Sent</th><th>My offer</th><th>Requirement</th></tr>
                </thead>
                <tbody>
                <?php foreach ($my_offers as $offer): ?>
                    <tr>
                        <td class="small">
                            <a class="text-decoration-none" href="<?= e(url('wanted/' . $offer['slug'])) ?>"><?= e((string) $offer['title']) ?></a>
                        </td>
                        <td class="text-end"><?= money($offer['offered_price']) ?></td>
                        <td class="text-end small"><?= e(qty($offer['available_quantity'], (string) $offer['unit_code'])) ?></td>
                        <td class="small text-nowrap"><?= e(fmt_date($offer['created_at'])) ?></td>
                        <td><?= status_badge((string) $offer['status']) ?></td>
                        <td><?= status_badge((string) $offer['requirement_status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php View::endSection(); ?>
