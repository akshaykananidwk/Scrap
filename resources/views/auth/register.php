<?php

use App\Core\View;

View::section('content');
?>
<div class="container" style="max-width:820px">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h1 class="h5 mb-1">Create your business account</h1>
            <p class="text-muted small mb-4">
                Free to register. One account can buy and sell — you can change this later.
            </p>

            <form method="post" action="<?= e(url('register')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <h6 class="text-teal mb-3">1. Your details</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label required" for="full_name">Full name</label>
                        <input id="full_name" name="full_name" class="form-control" required
                               value="<?= e((string) old('full_name')) ?>" autocomplete="name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="designation">Designation</label>
                        <input id="designation" name="designation" class="form-control"
                               value="<?= e((string) old('designation')) ?>" placeholder="Proprietor, Manager…">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="mobile">Mobile number</label>
                        <div class="input-group">
                            <span class="input-group-text">+91</span>
                            <input id="mobile" name="mobile" class="form-control" required inputmode="numeric"
                                   maxlength="10" pattern="[6-9][0-9]{9}" value="<?= e((string) old('mobile')) ?>"
                                   autocomplete="tel-national">
                        </div>
                        <div class="form-text">We send a verification code to this number.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input id="email" name="email" type="email" class="form-control"
                               value="<?= e((string) old('email')) ?>" autocomplete="email">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="password">Password</label>
                        <input id="password" name="password" type="password" class="form-control" required
                               autocomplete="new-password" minlength="8">
                        <div class="form-text">At least 8 characters, with letters and numbers.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="password_confirmation">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password"
                               class="form-control" required autocomplete="new-password">
                    </div>
                </div>

                <h6 class="text-teal mb-3">2. Your business</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label required" for="business_name">Business name</label>
                        <input id="business_name" name="business_name" class="form-control" required
                               value="<?= e((string) old('business_name')) ?>" autocomplete="organization">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="business_type">Business type</label>
                        <select id="business_type" name="business_type" class="form-select" required>
                            <?php foreach ($business_types as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= old('business_type') === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">I want to</label>
                        <div class="btn-group w-100" role="group">
                            <?php foreach (['buyer' => 'Buy', 'seller' => 'Sell', 'both' => 'Both'] as $value => $label): ?>
                                <input type="radio" class="btn-check" name="account_type" id="at-<?= e($value) ?>"
                                       value="<?= e($value) ?>" <?= (old('account_type', 'both') === $value) ? 'checked' : '' ?> required>
                                <label class="btn btn-outline-teal" for="at-<?= e($value) ?>"><?= e($label) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="gstin">GSTIN</label>
                        <input id="gstin" name="gstin" class="form-control text-uppercase" maxlength="15"
                               value="<?= e((string) old('gstin')) ?>" placeholder="24AAACC1206D1ZM">
                        <div class="form-text">Optional now, required for KYC verification.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="pan">PAN</label>
                        <input id="pan" name="pan" class="form-control text-uppercase" maxlength="10"
                               value="<?= e((string) old('pan')) ?>" placeholder="ABCDE1234F">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="registration_number">Business registration / Udyam number</label>
                        <input id="registration_number" name="registration_number" class="form-control"
                               value="<?= e((string) old('registration_number')) ?>">
                    </div>
                </div>

                <h6 class="text-teal mb-3">3. Location</h6>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <label class="form-label" for="address_line1">Address</label>
                        <input id="address_line1" name="address_line1" class="form-control"
                               value="<?= e((string) old('address_line1')) ?>" autocomplete="street-address">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="pincode">Pincode</label>
                        <input id="pincode" name="pincode" class="form-control" maxlength="6" inputmode="numeric"
                               data-pincode-lookup value="<?= e((string) old('pincode')) ?>">
                        <div class="form-text">We fill city and state automatically.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="state_id">State</label>
                        <select id="state_id" name="state_id" class="form-select" required data-load-cities="#city_id">
                            <option value="">Select state</option>
                            <?php foreach ($states as $state): ?>
                                <option value="<?= (int) $state['id'] ?>" <?= (int) old('state_id') === (int) $state['id'] ? 'selected' : '' ?>>
                                    <?= e($state['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="city_id">City</label>
                        <select id="city_id" name="city_id" class="form-select">
                            <option value="">Select state first</option>
                        </select>
                    </div>
                </div>

                <h6 class="text-teal mb-3">4. Branding <span class="text-muted fw-normal small">(optional)</span></h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label" for="avatar">Your photo</label>
                        <input id="avatar" name="avatar" type="file" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="logo">Business logo</label>
                        <input id="logo" name="logo" type="file" class="form-control" accept="image/*">
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="terms" value="1" id="terms" required>
                    <label class="form-check-label small" for="terms">
                        I agree to the <a href="<?= e(url('page/terms')) ?>" target="_blank" rel="noopener">Terms</a>,
                        <a href="<?= e(url('page/privacy')) ?>" target="_blank" rel="noopener">Privacy Policy</a> and
                        <a href="<?= e(url('page/auction-rules')) ?>" target="_blank" rel="noopener">Auction Rules</a>.
                    </label>
                </div>

                <button class="btn btn-teal btn-lg w-100" type="submit">Create account</button>
            </form>

            <hr class="my-4">
            <p class="text-center small mb-0">
                Already registered? <a href="<?= e(url('login')) ?>" class="fw-semibold">Sign in</a>
            </p>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
