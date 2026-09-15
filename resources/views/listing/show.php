<?php

use App\Core\Auth;
use App\Core\View;
use App\Models\Listing;

View::section('content');

$primaryImage = $images[0]['file_path'] ?? null;
$canSeePrice = (int) $listing['show_price'] === 1 && $listing['price'] !== null;
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= e(url('buy')) ?>">Buy Scrap</a></li>
            <?php if (!empty($listing['category_slug'])): ?>
                <li class="breadcrumb-item"><a href="<?= e(url('scrap/' . $listing['category_slug'])) ?>"><?= e((string) $listing['category_name']) ?></a></li>
            <?php endif; ?>
            <?php if (!empty($listing['material_slug'])): ?>
                <li class="breadcrumb-item"><a href="<?= e(url('material/' . $listing['material_slug'])) ?>"><?= e((string) $listing['material_name']) ?></a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active"><?= e(mb_strimwidth((string) $listing['title'], 0, 40, '…')) ?></li>
        </ol>
    </nav>

    <?php if ($listing['status'] !== 'active'): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            This listing is <strong><?= e(label((string) $listing['status'])) ?></strong>
            <?php if (!empty($listing['rejection_reason'])): ?>
                — <?= e((string) $listing['rejection_reason']) ?>
            <?php endif; ?>
            <?php if ($is_owner): ?>
                <a href="<?= e(url('dashboard/listings/' . $listing['id'] . '/edit')) ?>" class="alert-link ms-2">Edit listing</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Media + detail -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <?php if ($images !== []): ?>
                    <div id="listingGallery" class="carousel slide" data-bs-ride="false">
                        <div class="carousel-inner rounded-top">
                            <?php foreach ($images as $index => $image): ?>
                                <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                    <img src="<?= e(upload_url((string) $image['file_path'])) ?>"
                                         class="d-block w-100" style="max-height:480px;object-fit:contain;background:#f1f5f9"
                                         alt="<?= e((string) ($image['caption'] ?: $listing['title'])) ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($images) > 1): ?>
                            <button class="carousel-control-prev" type="button" data-bs-target="#listingGallery" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon"></span><span class="visually-hidden">Previous</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#listingGallery" data-bs-slide="next">
                                <span class="carousel-control-next-icon"></span><span class="visually-hidden">Next</span>
                            </button>
                            <div class="d-flex gap-2 p-2 overflow-auto bg-light">
                                <?php foreach ($images as $index => $image): ?>
                                    <button type="button" data-bs-target="#listingGallery" data-bs-slide-to="<?= $index ?>"
                                            class="btn p-0 border rounded flex-shrink-0" style="width:64px;height:64px">
                                        <img src="<?= e(upload_url((string) $image['file_path'])) ?>"
                                             style="width:100%;height:100%;object-fit:cover" alt="" class="rounded">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="listing-thumb rounded-top" style="aspect-ratio:16/7">
                        <span class="listing-thumb-placeholder"><i class="bi bi-image"></i></span>
                    </div>
                <?php endif; ?>

                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <h1 class="h4 mb-0"><?= e((string) $listing['title']) ?></h1>
                        <?php if (Auth::check()): ?>
                            <button class="btn btn-sm btn-outline-secondary js-favorite flex-shrink-0"
                                    data-type="listing" data-id="<?= (int) $listing['id'] ?>">
                                <i class="bi bi-bookmark<?= $is_favorite ? '-fill text-teal' : '' ?>"></i>
                                <span class="d-none d-sm-inline ms-1">Save</span>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge text-bg-light border"><?= e(Listing::TYPES[$listing['listing_type']] ?? '') ?></span>
                        <?php if ((int) $listing['is_negotiable'] === 1): ?>
                            <span class="badge badge-soft-info">Negotiable</span>
                        <?php endif; ?>
                        <?php if ((int) $listing['inspection_available'] === 1): ?>
                            <span class="badge badge-soft-success"><i class="bi bi-eye me-1"></i>Inspection allowed</span>
                        <?php endif; ?>
                        <?php if ((int) $listing['delivery_available'] === 1): ?>
                            <span class="badge badge-soft-info"><i class="bi bi-truck me-1"></i>Delivery available</span>
                        <?php endif; ?>
                        <span class="badge text-bg-light border"><i class="bi bi-eye me-1"></i><?= (int) $listing['view_count'] ?> views</span>
                        <span class="badge text-bg-light border">Ref <?= e((string) $listing['reference']) ?></span>
                    </div>

                    <div class="row g-3 mb-3">
                        <?php
                        $specs = [
                            ['Category', ($listing['category_name'] ?? '') . (!empty($listing['subcategory_name']) ? ' › ' . $listing['subcategory_name'] : '')],
                            ['Material', $listing['material_name'] ?? '—'],
                            ['Grade', $listing['grade_name'] ?: ($listing['grade_text'] ?: '—')],
                            ['Quantity available', qty($listing['quantity'], (string) $listing['unit_code'])],
                            ['Minimum order', $listing['min_order_quantity'] ? qty($listing['min_order_quantity'], (string) $listing['unit_code']) : 'No minimum'],
                            ['Estimated weight', $listing['estimated_weight_kg'] ? qty($listing['estimated_weight_kg'], 'KG') : '—'],
                            ['Condition', label((string) $listing['material_condition'])],
                            ['Material source', label((string) $listing['material_source'])],
                            ['Loading by', label((string) $listing['loading_by'])],
                            ['Transport by', label((string) $listing['transport_by'])],
                            ['Payment terms', Listing::PAYMENT_TERMS[$listing['payment_terms']] ?? '—'],
                            ['GST', (int) $listing['gst_applicable'] === 1 ? number_format((float) $listing['gst_rate'], 2) . '%' . (!empty($listing['hsn_code']) ? ' (HSN ' . $listing['hsn_code'] . ')' : '') : 'Not applicable'],
                        ];
                        ?>
                        <?php foreach ($specs as [$label, $value]): ?>
                            <div class="col-6 col-md-4">
                                <div class="small text-muted"><?= e($label) ?></div>
                                <div class="fw-semibold small"><?= e((string) $value) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($listing['description'])): ?>
                        <h6 class="mt-4">Description</h6>
                        <p class="small" style="white-space:pre-line"><?= e((string) $listing['description']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($listing['inspection_notes'])): ?>
                        <div class="alert alert-light border small">
                            <strong>Inspection:</strong> <?= e((string) $listing['inspection_notes']) ?>
                        </div>
                    <?php endif; ?>

                    <h6 class="mt-4">Location</h6>
                    <p class="small mb-0">
                        <i class="bi bi-geo-alt text-teal me-1"></i>
                        <?= e(trim(implode(', ', array_filter([
                            $listing['pickup_address'] ?? null,
                            $listing['city_name'] ?? null,
                            $listing['state_name'] ?? null,
                            $listing['pincode'] ?? null,
                        ])))) ?: 'Location not specified' ?>
                    </p>

                    <?php if ($videos !== [] || $documents !== []): ?>
                        <h6 class="mt-4">Attachments</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($videos as $video): ?>
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="<?= e($video['external_url'] ?: upload_url((string) $video['file_path'])) ?>"
                                   target="_blank" rel="noopener">
                                    <i class="bi bi-play-circle me-1"></i>Video
                                </a>
                            <?php endforeach; ?>
                            <?php foreach ($documents as $document): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(upload_url((string) $document['file_path'])) ?>"
                                   target="_blank" rel="noopener">
                                    <i class="bi bi-file-earmark-text me-1"></i>
                                    <?= e(mb_strimwidth((string) ($document['title'] ?: 'Document'), 0, 24, '…')) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($reviews)): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h6 class="mb-3">Recent reviews for this seller</h6>
                        <?php foreach ($reviews as $review): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="star-rating small"><?= str_repeat('★', (int) $review['overall_rating']) ?></div>
                                <?php if (!empty($review['comment'])): ?>
                                    <p class="small mb-1"><?= e(mb_strimwidth((string) $review['comment'], 0, 240, '…')) ?></p>
                                <?php endif; ?>
                                <span class="small text-muted">
                                    <?= e((string) ($review['reviewer_business'] ?: $review['reviewer_name'])) ?> ·
                                    <?= e(fmt_date($review['created_at'])) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action panel -->
        <div class="col-lg-4">
            <div class="bid-panel">
                <?php if ($auction !== null && in_array($auction['status'], ['live', 'scheduled'], true)): ?>
                    <div class="card border-0 shadow-sm mb-3 border-start border-4 border-danger">
                        <div class="card-body">
                            <span class="badge text-bg-danger mb-2"><i class="bi bi-hammer me-1"></i>
                                <?= $auction['status'] === 'live' ? 'Live auction' : 'Auction scheduled' ?>
                            </span>
                            <div class="small text-muted">Current bid</div>
                            <div class="price-display text-danger mb-1">
                                <?= e(money($auction['current_price'] ?? $auction['starting_price'])) ?>
                            </div>
                            <div class="small text-muted mb-2">
                                <?= (int) $auction['bid_count'] ?> bids ·
                                <?= $auction['status'] === 'live' ? 'Closes' : 'Starts' ?>
                                <span class="js-countdown fw-semibold"
                                      data-seconds="<?= countdown_seconds($auction['status'] === 'live' ? $auction['ends_at'] : $auction['starts_at']) ?>">—</span>
                            </div>
                            <a class="btn btn-danger w-100" href="<?= e(url('auctions/' . $auction['id'])) ?>">
                                <i class="bi bi-hammer me-1"></i>Go to auction
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <?php if ($canSeePrice): ?>
                            <div class="small text-muted">Asking price</div>
                            <div class="price-display text-teal"><?= e(money($listing['price'])) ?></div>
                            <div class="small text-muted mb-3">
                                <?= e(money($listing['price_per_mt'])) ?>/MT ·
                                <?= e(money($listing['price_per_kg'])) ?>/KG
                                <?php if ((int) $listing['is_negotiable'] === 1): ?>
                                    · <span class="text-info">Negotiable</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($listing['total_price'])): ?>
                                <div class="alert alert-light border small py-2">
                                    Full lot (<?= e(qty($listing['quantity'], (string) $listing['unit_code'])) ?>):
                                    <strong><?= e(money($listing['total_price'])) ?></strong>
                                    <?php if ((int) $listing['gst_applicable'] === 1): ?>
                                        <span class="text-muted">+ <?= e(number_format((float) $listing['gst_rate'], 0)) ?>% GST</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="h5 text-muted mb-3">Price on request</div>
                        <?php endif; ?>

                        <?php if ($is_owner): ?>
                            <a class="btn btn-outline-teal w-100 mb-2" href="<?= e(url('dashboard/listings/' . $listing['id'] . '/edit')) ?>">
                                <i class="bi bi-pencil me-1"></i>Edit your listing
                            </a>
                            <div class="row g-2 text-center small">
                                <div class="col-4"><div class="fw-bold"><?= (int) $listing['view_count'] ?></div><div class="text-muted">Views</div></div>
                                <div class="col-4"><div class="fw-bold"><?= (int) $listing['enquiry_count'] ?></div><div class="text-muted">Enquiries</div></div>
                                <div class="col-4"><div class="fw-bold"><?= (int) $listing['offer_count'] ?></div><div class="text-muted">Offers</div></div>
                            </div>
                        <?php elseif (!$can_contact): ?>
                            <a class="btn btn-teal w-100 mb-2" href="<?= e(url('login')) ?>">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Sign in to contact seller
                            </a>
                            <p class="small text-muted text-center mb-0">
                                Free account. Contact details, bidding and offers require sign-in.
                            </p>
                        <?php elseif ($listing['status'] === 'active'): ?>
                            <button class="btn btn-teal w-100 mb-2" id="reveal-contact"
                                    data-url="<?= e(url('listings/' . $listing['id'] . '/contact')) ?>">
                                <i class="bi bi-telephone me-1"></i>Show contact details
                            </button>
                            <div id="contact-box" class="d-none alert alert-light border small mb-2"></div>

                            <?php if (in_array($listing['listing_type'], ['negotiable', 'make_offer', 'fixed'], true)): ?>
                                <button class="btn btn-outline-teal w-100 mb-2" data-bs-toggle="modal" data-bs-target="#offerModal">
                                    <i class="bi bi-tag me-1"></i>Make an offer
                                </button>
                            <?php endif; ?>

                            <form method="post" action="<?= e(url('dashboard/messages/start')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
                                <input type="hidden" name="body" value="Hi, I am interested in this listing. Is it still available?">
                                <button class="btn btn-outline-secondary w-100" type="submit">
                                    <i class="bi bi-chat-dots me-1"></i>Message seller
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Seller card -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="mb-3">Seller</h6>
                        <div class="d-flex gap-2 mb-2">
                            <?php if (!empty($listing['business_logo'])): ?>
                                <img src="<?= e(upload_url((string) $listing['business_logo'])) ?>" class="rounded" width="44" height="44" style="object-fit:cover" alt="">
                            <?php else: ?>
                                <span class="avatar-initial" style="width:44px;height:44px;font-size:1.1rem">
                                    <?= e(mb_strtoupper(mb_substr((string) ($listing['business_name'] ?: $listing['seller_name']), 0, 1))) ?>
                                </span>
                            <?php endif; ?>
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate">
                                    <?php if (!empty($listing['business_slug'])): ?>
                                        <a class="text-decoration-none" href="<?= e(url('business/' . $listing['business_slug'])) ?>">
                                            <?= e((string) ($listing['business_name'] ?: $listing['seller_name'])) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= e((string) $listing['seller_name']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted"><?= e(label((string) ($listing['business_type'] ?? ''))) ?></div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <?php if ((int) ($listing['kyc_verified'] ?? 0) === 1): ?>
                                <span class="badge badge-soft-success kyc-badge"><i class="bi bi-patch-check-fill me-1"></i>KYC verified</span>
                            <?php endif; ?>
                            <?php if ((int) ($listing['gst_verified'] ?? 0) === 1): ?>
                                <span class="badge badge-soft-info kyc-badge">GST verified</span>
                            <?php endif; ?>
                        </div>

                        <div class="row g-2 text-center small border-top pt-2">
                            <div class="col-4">
                                <div class="fw-bold">
                                    <?php if ((int) ($listing['rating_count'] ?? 0) > 0): ?>
                                        <span class="star-rating">★</span> <?= e(number_format((float) $listing['rating_avg'], 1)) ?>
                                    <?php else: ?>—<?php endif; ?>
                                </div>
                                <div class="text-muted"><?= (int) ($listing['rating_count'] ?? 0) ?> reviews</div>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold"><?= e(number_format((float) ($listing['response_rate'] ?? 0), 0)) ?>%</div>
                                <div class="text-muted">Response</div>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold"><?= e(fmt_dt($listing['seller_since'], 'M Y')) ?></div>
                                <div class="text-muted">Member since</div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (Auth::check() && !$is_owner): ?>
                    <button class="btn btn-sm btn-link text-muted w-100" data-bs-toggle="modal" data-bs-target="#reportModal">
                        <i class="bi bi-flag me-1"></i>Report this listing
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- More from this seller -->
    <?php if (!empty($seller_listings) && count($seller_listings) > 1): ?>
        <h5 class="mt-5 mb-3">More from this seller</h5>
        <div class="row g-3">
            <?php foreach ($seller_listings as $other): ?>
                <?php if ((int) $other['id'] === (int) $listing['id']) { continue; } ?>
                <div class="col-6 col-lg-3">
                    <?= View::partial('partials/listing_card', ['listing' => $other]) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Related -->
    <?php if (!empty($related)): ?>
        <h5 class="mt-5 mb-3">Similar material</h5>
        <div class="row g-3">
            <?php foreach ($related as $other): ?>
                <div class="col-6 col-lg-3">
                    <?= View::partial('partials/listing_card', ['listing' => $other]) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Offer modal -->
<?php if ($can_contact && !$is_owner && $listing['status'] === 'active'): ?>
    <div class="modal fade" id="offerModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?= e(url('dashboard/offers')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Make an offer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Your offer goes straight to the seller. If they accept it, an order is created automatically.
                    </p>
                    <div class="mb-3">
                        <label class="form-label required" for="offer-amount">Your price</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input id="offer-amount" type="number" name="amount" class="form-control" step="0.01" min="0.01" required
                                   value="<?= e((string) ($listing['price'] ?? '')) ?>">
                            <select name="price_basis" class="form-select" style="max-width:130px">
                                <option value="per_mt">per MT</option>
                                <option value="per_kg">per KG</option>
                                <option value="per_unit">per unit</option>
                                <option value="lot">for the lot</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="offer-quantity">Quantity you want</label>
                        <div class="input-group">
                            <input id="offer-quantity" type="number" name="quantity" class="form-control" step="0.001" min="0.001" required
                                   value="<?= e((string) $listing['quantity']) ?>">
                            <span class="input-group-text"><?= e((string) $listing['unit_code']) ?></span>
                        </div>
                        <?php if (!empty($listing['min_order_quantity'])): ?>
                            <div class="form-text">Minimum order: <?= e(qty($listing['min_order_quantity'], (string) $listing['unit_code'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="gst_included" value="1" id="offer-gst">
                                <label class="form-check-label small" for="offer-gst">Price includes GST</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="transport_included" value="1" id="offer-transport">
                                <label class="form-check-label small" for="offer-transport">Includes transport</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="offer-message">Message (optional)</label>
                        <textarea id="offer-message" name="message" class="form-control" rows="3"
                                  placeholder="Lifting schedule, payment terms, inspection request…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-teal">Send offer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report modal -->
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?= e(url('report')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="type" value="listing">
                <input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Report this listing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required" for="report-reason">What is wrong?</label>
                        <select id="report-reason" name="reason" class="form-select" required>
                            <?php foreach (App\Services\DisputeService::REPORT_REASONS as $key => $label): ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="report-details">Details</label>
                        <textarea id="report-details" name="details" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="report-evidence">Evidence (optional)</label>
                        <input id="report-evidence" type="file" name="evidence" class="form-control" accept="image/*,application/pdf">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Submit report</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php View::endSection(); ?>

<?php View::section('scripts'); ?>
<script>
document.getElementById('reveal-contact')?.addEventListener('click', async function () {
    const box = document.getElementById('contact-box');
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading…';

    const result = await window.ScrapX.get(this.dataset.url);
    this.classList.add('d-none');

    if (result.success && result.seller) {
        const s = result.seller;
        box.classList.remove('d-none');
        box.innerHTML =
            '<div class="fw-semibold mb-1">' + (s.name || '') + '</div>' +
            (s.contact_person ? '<div>' + s.contact_person + '</div>' : '') +
            (s.mobile ? '<div><i class="bi bi-telephone me-1"></i><a href="tel:' + s.mobile + '">' + s.mobile + '</a></div>' : '') +
            (s.email ? '<div><i class="bi bi-envelope me-1"></i><a href="mailto:' + s.email + '">' + s.email + '</a></div>' : '') +
            (result.whatsapp ? '<a class="btn btn-sm btn-success mt-2 w-100" target="_blank" rel="noopener" href="' + result.whatsapp + '"><i class="bi bi-whatsapp me-1"></i>WhatsApp seller</a>' : '');
    } else {
        this.classList.remove('d-none');
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-telephone me-1"></i>Show contact details';
        window.ScrapX.toast(result.error || 'Could not load contact details.', 'danger');
    }
});
</script>
<?php View::endSection(); ?>
