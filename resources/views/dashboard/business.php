<?php

use App\Core\View;

View::section('content');
$b = $business ?? [];
$val = static fn (string $key, string $default = ''): string => (string) old($key, $b[$key] ?? $default);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <h1 class="h4 mb-0">Business profile</h1>
    <?php if ($business !== null): ?>
        <a class="btn btn-sm btn-outline-teal" href="<?= e(url('business/' . $business['slug'])) ?>">
            <i class="bi bi-box-arrow-up-right me-1"></i>View public page
        </a>
    <?php endif; ?>
</div>

<form method="post" action="<?= e(url('dashboard/business')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><h6 class="mb-0">Identity</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required" for="name">Business name</label>
                    <input id="name" name="name" class="form-control" required value="<?= e($val('name')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="business_type">Business type</label>
                    <select id="business_type" name="business_type" class="form-select" required>
                        <?php foreach ($types as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $val('business_type') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="established_year">Established year</label>
                    <input id="established_year" name="established_year" type="number" class="form-control"
                           min="1900" max="<?= (int) gmdate('Y') ?>" value="<?= e($val('established_year')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="employee_count">Employees</label>
                    <input id="employee_count" name="employee_count" class="form-control" value="<?= e($val('employee_count')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="annual_turnover">Annual turnover</label>
                    <input id="annual_turnover" name="annual_turnover" class="form-control"
                           placeholder="e.g. 5-10 Cr" value="<?= e($val('annual_turnover')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="about">About your business</label>
                    <textarea id="about" name="about" class="form-control" rows="4" maxlength="5000"><?= e($val('about')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><h6 class="mb-0">Statutory details</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="gstin">GSTIN</label>
                    <input id="gstin" name="gstin" class="form-control text-uppercase" maxlength="15" value="<?= e($val('gstin')) ?>">
                    <div class="form-text">
                        <?php if (!empty($b['gst_verified'])): ?>
                            <span class="text-success">Verified</span> — changing it resets verification.
                        <?php else: ?>
                            Checksum is validated on save.
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="pan">PAN</label>
                    <input id="pan" name="pan" class="form-control text-uppercase" maxlength="10" value="<?= e($val('pan')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="registration_number">Registration / Udyam</label>
                    <input id="registration_number" name="registration_number" class="form-control" value="<?= e($val('registration_number')) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><h6 class="mb-0">Contact &amp; address</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="contact_person">Contact person</label>
                    <input id="contact_person" name="contact_person" class="form-control" value="<?= e($val('contact_person')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="contact_mobile">Contact mobile</label>
                    <input id="contact_mobile" name="contact_mobile" class="form-control" maxlength="10" value="<?= e($val('contact_mobile')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="contact_email">Contact email</label>
                    <input id="contact_email" name="contact_email" type="email" class="form-control" value="<?= e($val('contact_email')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="website">Website</label>
                    <input id="website" name="website" type="url" class="form-control" placeholder="https://" value="<?= e($val('website')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="address_line1">Address line 1</label>
                    <input id="address_line1" name="address_line1" class="form-control" value="<?= e($val('address_line1')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="address_line2">Address line 2</label>
                    <input id="address_line2" name="address_line2" class="form-control" value="<?= e($val('address_line2')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="pincode">Pincode</label>
                    <input id="pincode" name="pincode" class="form-control" maxlength="6" data-pincode-lookup value="<?= e($val('pincode')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label required" for="state_id">State</label>
                    <select id="state_id" name="state_id" class="form-select" required data-load-cities="#city_id">
                        <option value="">Select</option>
                        <?php foreach ($states as $state): ?>
                            <option value="<?= (int) $state['id'] ?>" <?= (int) $val('state_id') === (int) $state['id'] ? 'selected' : '' ?>>
                                <?= e($state['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="city_id">City</label>
                    <select id="city_id" name="city_id" class="form-select">
                        <option value="">Select</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= (int) $city['id'] ?>" <?= (int) $val('city_id') === (int) $city['id'] ? 'selected' : '' ?>>
                                <?= e($city['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><h6 class="mb-0">Branding</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="logo">Logo</label>
                    <input id="logo" name="logo" type="file" class="form-control" accept="image/*">
                    <?php if (!empty($b['logo'])): ?>
                        <img src="<?= e(upload_url((string) $b['logo'])) ?>" class="rounded mt-2" width="72" height="72" style="object-fit:cover" alt="">
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="cover_image">Cover image</label>
                    <input id="cover_image" name="cover_image" type="file" class="form-control" accept="image/*">
                    <?php if (!empty($b['cover_image'])): ?>
                        <img src="<?= e(upload_url((string) $b['cover_image'])) ?>" class="rounded mt-2" style="max-height:72px" alt="">
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <button class="btn btn-teal btn-lg" type="submit">Save business profile</button>
</form>
<?php View::endSection(); ?>
