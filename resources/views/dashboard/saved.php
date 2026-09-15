<?php

use App\Core\View;

View::section('content');
?>
<h1 class="h4 mb-4">Saved items</h1>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-listings" type="button">Listings (<?= count($listings) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-auctions" type="button">Auctions (<?= count($auctions) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-follows" type="button">Following (<?= count($follows) ?>)</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="tab-listings">
        <?php if ($listings === []): ?>
            <div class="empty-state bg-white rounded shadow-sm"><i class="bi bi-bookmark"></i><p class="mb-0">Nothing saved yet.</p></div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($listings as $listing): ?>
                    <div class="col-6 col-lg-3">
                        <?= View::partial('partials/listing_card', ['listing' => $listing]) ?>
                        <form method="post" action="<?= e(url('dashboard/favorites/toggle')) ?>" class="mt-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="type" value="listing">
                            <input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">
                            <button class="btn btn-sm btn-link text-danger p-0" type="submit">Remove</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="tab-pane fade" id="tab-auctions">
        <?php if ($auctions === []): ?>
            <div class="empty-state bg-white rounded shadow-sm"><i class="bi bi-hammer"></i><p class="mb-0">No watched auctions.</p></div>
        <?php else: ?>
            <div class="list-group shadow-sm">
                <?php foreach ($auctions as $auction): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div class="min-w-0">
                            <a class="text-decoration-none d-block text-truncate"
                               href="<?= e(url('auctions/' . $auction['id'])) ?>">
                                <?= e((string) $auction['title']) ?>
                            </a>
                            <span class="small text-muted">
                                <?= money($auction['current_price'] ?? $auction['start_price']) ?> ·
                                <?= (int) ($auction['bid_count'] ?? 0) ?> bids ·
                                ends <?= e(fmt_dt($auction['ends_at'])) ?>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <?= status_badge((string) $auction['status']) ?>
                            <form method="post" action="<?= e(url('dashboard/favorites/toggle')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="auction">
                                <input type="hidden" name="id" value="<?= (int) $auction['id'] ?>">
                                <button class="btn btn-sm btn-link text-danger" type="submit"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="tab-pane fade" id="tab-follows">
        <?php if ($follows === []): ?>
            <div class="empty-state bg-white rounded shadow-sm"><i class="bi bi-people"></i><p class="mb-0">Not following anyone.</p></div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($follows as $follow): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body d-flex justify-content-between align-items-center gap-2">
                                <div class="min-w-0">
                                    <a class="text-decoration-none d-block text-truncate"
                                       href="<?= e(url('business/' . $follow['slug'])) ?>"><?= e((string) $follow['name']) ?></a>
                                    <span class="small text-muted"><?= e(label((string) $follow['business_type'])) ?></span>
                                </div>
                                <form method="post" action="<?= e(url('business/' . $follow['id'] . '/follow')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Unfollow</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection(); ?>
