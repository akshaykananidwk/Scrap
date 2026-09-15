<?php

use App\Core\Auth;
use App\Core\View;

View::section('content');
?>
<div class="bg-white border-bottom">
    <?php if (!empty($business['cover_image'])): ?>
        <div style="height:170px;background:url('<?= e(upload_url((string) $business['cover_image'])) ?>') center/cover"></div>
    <?php endif; ?>
    <div class="container py-4">
        <div class="d-flex flex-wrap gap-3 align-items-start">
            <?php if (!empty($business['logo'])): ?>
                <img src="<?= e(upload_url((string) $business['logo'])) ?>" class="rounded shadow-sm" width="84" height="84" style="object-fit:cover" alt="">
            <?php else: ?>
                <span class="avatar-initial shadow-sm" style="width:84px;height:84px;font-size:2rem">
                    <?= e(mb_strtoupper(mb_substr((string) $business['name'], 0, 1))) ?>
                </span>
            <?php endif; ?>

            <div class="flex-grow-1 min-w-0">
                <h1 class="h4 mb-1"><?= e((string) $business['name']) ?></h1>
                <div class="small text-muted mb-2">
                    <?= e(label((string) $business['business_type'])) ?>
                    · <i class="bi bi-geo-alt"></i>
                    <?= e(trim(((string) ($business['city_name'] ?? '')) . ', ' . ((string) ($business['state_name'] ?? '')), ', ') ?: 'India') ?>
                    · Member since <?= e(fmt_dt($business['member_since'], 'M Y')) ?>
                </div>
                <div class="d-flex gap-1 flex-wrap">
                    <?php if ((int) $business['kyc_verified'] === 1): ?>
                        <span class="badge badge-soft-success"><i class="bi bi-patch-check-fill me-1"></i>KYC verified</span>
                    <?php endif; ?>
                    <?php if ((int) $business['gst_verified'] === 1): ?>
                        <span class="badge badge-soft-info">GST verified</span>
                    <?php endif; ?>
                    <?php if ((int) $business['pan_verified'] === 1): ?>
                        <span class="badge badge-soft-info">PAN verified</span>
                    <?php endif; ?>
                    <?php if ((int) $business['rating_count'] > 0): ?>
                        <span class="badge text-bg-light border">
                            <span class="star-rating">★</span> <?= e(number_format((float) $business['rating_avg'], 1)) ?>
                            (<?= (int) $business['rating_count'] ?>)
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <?php if ($is_self): ?>
                    <a class="btn btn-outline-teal" href="<?= e(url('dashboard/business')) ?>">
                        <i class="bi bi-pencil me-1"></i>Edit profile
                    </a>
                <?php elseif ($can_contact): ?>
                    <form method="post" action="<?= e(url('business/' . $business['id'] . '/follow')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-teal" type="submit">
                            <i class="bi bi-<?= $is_following ? 'person-dash' : 'person-plus' ?> me-1"></i>
                            <?= $is_following ? 'Unfollow' : 'Follow' ?>
                        </button>
                    </form>
                    <form method="post" action="<?= e(url('dashboard/messages/start')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="seller_id" value="<?= (int) $business['user_id'] ?>">
                        <input type="hidden" name="body" value="Hi, I would like to discuss a possible deal.">
                        <button class="btn btn-teal" type="submit"><i class="bi bi-chat-dots me-1"></i>Message</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-teal" href="<?= e(url('login')) ?>">Sign in to contact</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-2 mt-3">
            <?php foreach ([
                ['Listings', (int) $business['total_listings'], 'bi-box-seam'],
                ['Completed deals', (int) $business['completed_orders'], 'bi-bag-check'],
                ['Response rate', number_format((float) $business['response_rate'], 0) . '%', 'bi-reply'],
                ['Avg response', (int) $business['avg_response_minutes'] > 0 ? (int) $business['avg_response_minutes'] . ' min' : '—', 'bi-clock'],
                ['Followers', (int) $business['follower_count'], 'bi-people'],
                ['Profile views', (int) $business['profile_views'], 'bi-eye'],
            ] as [$label, $value, $icon]): ?>
                <div class="col-6 col-md-2">
                    <div class="stat-card text-center py-2">
                        <div class="stat-value fs-6"><?= e((string) $value) ?></div>
                        <div class="stat-label"><?= e($label) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-8">
            <?php if (!empty($business['about'])): ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6>About</h6>
                        <p class="small mb-0" style="white-space:pre-line"><?= e((string) $business['about']) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <h5 class="mb-3">Listings</h5>
            <?php if ($listings->isEmpty()): ?>
                <div class="empty-state bg-white rounded shadow-sm"><i class="bi bi-box"></i><p class="mb-0">No active listings.</p></div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($listings->items as $listing): ?>
                        <div class="col-6 col-lg-4">
                            <?= View::partial('partials/listing_card', ['listing' => $listing]) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 d-flex justify-content-center"><?= $listings->links() ?></div>
            <?php endif; ?>

            <?php if (!empty($requirements)): ?>
                <h5 class="mt-4 mb-3">Open requirements</h5>
                <div class="row g-2">
                    <?php foreach ($requirements as $requirement): ?>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body p-3">
                                    <a class="small fw-semibold text-decoration-none text-dark"
                                       href="<?= e(url('wanted/' . $requirement['slug'])) ?>">
                                        <?= e(mb_strimwidth((string) $requirement['title'], 0, 56, '…')) ?>
                                    </a>
                                    <div class="small text-muted"><?= e(qty($requirement['quantity'], (string) $requirement['unit_code'])) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="mb-3">Reviews</h6>
                    <?php $overall = $review_summary['overall'] ?? []; ?>
                    <?php if ((int) ($overall['total'] ?? 0) === 0): ?>
                        <p class="small text-muted mb-0">No reviews yet.</p>
                    <?php else: ?>
                        <div class="text-center mb-3">
                            <div class="display-6 fw-bold"><?= e(number_format((float) $overall['average'], 1)) ?></div>
                            <div class="star-rating"><?= str_repeat('★', (int) round((float) $overall['average'])) ?></div>
                            <div class="small text-muted"><?= (int) $overall['total'] ?> reviews</div>
                        </div>
                        <?php foreach ($review_summary['criteria'] as $label => $score): ?>
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted"><?= e($label) ?></span>
                                <span class="fw-semibold"><?= e((string) $score) ?>/5</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($reviews)): ?>
                <?php foreach (array_slice($reviews, 0, 5) as $review): ?>
                    <div class="card border-0 shadow-sm mb-2">
                        <div class="card-body p-3">
                            <div class="star-rating small"><?= str_repeat('★', (int) $review['overall_rating']) ?></div>
                            <?php if (!empty($review['comment'])): ?>
                                <p class="small mb-1"><?= e(mb_strimwidth((string) $review['comment'], 0, 180, '…')) ?></p>
                            <?php endif; ?>
                            <div class="small text-muted">
                                <?= e((string) ($review['reviewer_business'] ?: $review['reviewer_name'])) ?> ·
                                <?= e(fmt_date($review['created_at'])) ?>
                            </div>
                            <?php if (!empty($review['seller_response'])): ?>
                                <div class="bg-light rounded p-2 mt-2 small">
                                    <strong>Reply:</strong> <?= e(mb_strimwidth((string) $review['seller_response'], 0, 160, '…')) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (Auth::check() && !$is_self): ?>
                <button class="btn btn-sm btn-link text-muted w-100" data-bs-toggle="modal" data-bs-target="#reportBusiness">
                    <i class="bi bi-flag me-1"></i>Report this business
                </button>

                <div class="modal fade" id="reportBusiness" tabindex="-1">
                    <div class="modal-dialog">
                        <form class="modal-content" method="post" action="<?= e(url('report')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="type" value="business">
                            <input type="hidden" name="id" value="<?= (int) $business['id'] ?>">
                            <div class="modal-header"><h5 class="modal-title">Report business</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <select name="reason" class="form-select mb-3" required>
                                    <?php foreach ($report_reasons as $key => $label): ?>
                                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <textarea name="details" class="form-control" rows="3" placeholder="What happened?"></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Submit</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
