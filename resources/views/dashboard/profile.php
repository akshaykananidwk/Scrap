<?php

use App\Core\View;

View::section('content');
$prefs = json_decode((string) ($user['notification_preferences'] ?? '{}'), true) ?: [];
?>
<h1 class="h4 mb-4">My profile</h1>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h6 class="mb-0">Personal details</h6></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('dashboard/profile')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required" for="full_name">Full name</label>
                            <input id="full_name" name="full_name" class="form-control" required
                                   value="<?= e((string) old('full_name', $user['full_name'])) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="designation">Designation</label>
                            <input id="designation" name="designation" class="form-control"
                                   value="<?= e((string) old('designation', $user['designation'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile (verified login)</label>
                            <input class="form-control" value="<?= e((string) $user['mobile']) ?>" disabled>
                            <div class="form-text">
                                <?= !empty($user['mobile_verified_at'])
                                    ? '<span class="text-success">Verified</span>'
                                    : '<a href="' . e(url('verify/mobile')) . '">Verify now</a>' ?>
                                — contact support to change your login number.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="alt_mobile">Alternate mobile</label>
                            <input id="alt_mobile" name="alt_mobile" class="form-control" maxlength="10"
                                   value="<?= e((string) old('alt_mobile', $user['alt_mobile'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input id="email" name="email" type="email" class="form-control"
                                   value="<?= e((string) old('email', $user['email'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="account_type">I want to</label>
                            <select id="account_type" name="account_type" class="form-select" required>
                                <?php foreach (['buyer' => 'Buy only', 'seller' => 'Sell only', 'both' => 'Buy and sell'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= old('account_type', $user['account_type']) === $value ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="preferred_language">Preferred language</label>
                            <select id="preferred_language" name="preferred_language" class="form-select">
                                <?php foreach (['en' => 'English', 'hi' => 'हिन्दी', 'gu' => 'ગુજરાતી'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= old('preferred_language', $user['preferred_language'] ?? 'en') === $value ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="avatar">Profile photo</label>
                            <input id="avatar" name="avatar" type="file" class="form-control" accept="image/*">
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="<?= e(upload_url((string) $user['avatar'])) ?>" class="rounded mt-2" width="56" height="56" style="object-fit:cover" alt="">
                            <?php endif; ?>
                        </div>
                    </div>
                    <button class="btn btn-teal mt-3" type="submit">Save profile</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">Change password</h6></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('dashboard/profile/password')) ?>">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label required" for="current_password">Current password</label>
                            <input id="current_password" name="current_password" type="password" class="form-control" required autocomplete="current-password">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="password">New password</label>
                            <input id="password" name="password" type="password" class="form-control" required minlength="8" autocomplete="new-password">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="password_confirmation">Confirm</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required autocomplete="new-password">
                        </div>
                    </div>
                    <button class="btn btn-outline-teal mt-3" type="submit">Update password</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h6 class="mb-0">Notification preferences</h6></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('dashboard/profile/notifications')) ?>">
                    <?= csrf_field() ?>
                    <?php foreach ([
                        'email' => 'Email',
                        'sms' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                        'push' => 'Browser push',
                    ] as $channel => $label): ?>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="pref-<?= e($channel) ?>" name="channels[]" value="<?= e($channel) ?>"
                                <?= !empty($prefs['channels'][$channel]) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="pref-<?= e($channel) ?>"><?= e($label) ?></label>
                        </div>
                    <?php endforeach; ?>

                    <hr>
                    <p class="small text-muted mb-2">Send me alerts about</p>
                    <?php foreach ([
                        'bids' => 'Bids on my auctions / outbid alerts',
                        'offers' => 'Offers and counter-offers',
                        'orders' => 'Order status changes',
                        'requirements' => 'Buyer requirements matching my materials',
                        'rates' => 'Daily market rate digest',
                        'marketing' => 'Product news and offers',
                    ] as $topic => $label): ?>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="topic-<?= e($topic) ?>"
                                   name="topics[]" value="<?= e($topic) ?>"
                                <?= !empty($prefs['topics'][$topic]) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="topic-<?= e($topic) ?>"><?= e($label) ?></label>
                        </div>
                    <?php endforeach; ?>

                    <button class="btn btn-outline-teal mt-3 w-100" type="submit">Save preferences</button>
                </form>
                <p class="small text-muted mt-3 mb-0">
                    In-app and email alerts are active. SMS, WhatsApp and push are delivered only once an
                    administrator configures a provider — until then those messages are recorded as
                    <em>skipped</em> rather than silently dropped.
                </p>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="mb-3">Business</h6>
                <?php if ($business === null): ?>
                    <p class="small text-muted">No business profile yet.</p>
                    <a class="btn btn-sm btn-teal" href="<?= e(url('dashboard/business')) ?>">Create business profile</a>
                <?php else: ?>
                    <p class="mb-1 fw-semibold"><?= e((string) $business['name']) ?></p>
                    <p class="small text-muted mb-3"><?= e(label((string) $business['business_type'])) ?></p>
                    <a class="btn btn-sm btn-outline-teal" href="<?= e(url('dashboard/business')) ?>">Edit business</a>
                    <a class="btn btn-sm btn-link" href="<?= e(url('business/' . $business['slug'])) ?>">View public page</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
