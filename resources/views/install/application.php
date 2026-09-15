<?php

use App\Core\View;

View::section('content');
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h1 class="h5 mb-1">Application settings</h1>
        <p class="text-muted small mb-4">These become your site name, URL and administrator login.</p>

        <form method="post" action="<?= e(url('install/application')) ?>">
            <h6 class="text-teal mb-3">Site</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label required" for="site_name">Site name</label>
                    <input id="site_name" name="site_name" class="form-control" required
                           value="<?= e((string) ($saved['site_name'] ?? old('site_name', 'ScrapX'))) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="site_url">Site URL</label>
                    <input id="site_url" name="site_url" type="url" class="form-control" required
                           value="<?= e((string) ($saved['site_url'] ?? old('site_url', $suggested_url))) ?>">
                    <div class="form-text">No trailing slash. Use https:// if you have an SSL certificate.</div>
                </div>
            </div>

            <h6 class="text-teal mb-3">Administrator account</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label required" for="admin_name">Full name</label>
                    <input id="admin_name" name="admin_name" class="form-control" required
                           value="<?= e((string) ($saved['admin_name'] ?? old('admin_name'))) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="admin_email">Email</label>
                    <input id="admin_email" name="admin_email" type="email" class="form-control" required
                           value="<?= e((string) ($saved['admin_email'] ?? old('admin_email'))) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="admin_mobile">Mobile number</label>
                    <div class="input-group">
                        <span class="input-group-text">+91</span>
                        <input id="admin_mobile" name="admin_mobile" class="form-control" required
                               maxlength="10" inputmode="numeric" pattern="[6-9][0-9]{9}"
                               value="<?= e((string) ($saved['admin_mobile'] ?? old('admin_mobile'))) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="admin_password">Password</label>
                    <input id="admin_password" name="admin_password" type="password" class="form-control"
                           required minlength="8">
                    <div class="form-text">At least 8 characters with letters and numbers.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="admin_password_confirmation">Confirm password</label>
                    <input id="admin_password_confirmation" name="admin_password_confirmation" type="password"
                           class="form-control" required>
                </div>
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="demo_data" value="1" id="demo_data">
                <label class="form-check-label" for="demo_data">
                    <strong>Install demo data</strong>
                    <div class="form-text mb-0">
                        6 demo businesses, 12 listings, 3 live auctions with bids, 4 buyer requirements and
                        30 days of market rates — all tagged so you can delete them in one click later.
                    </div>
                </label>
            </div>

            <div class="d-flex justify-content-between">
                <a class="btn btn-outline-secondary" href="<?= e(url('install/database')) ?>">Back</a>
                <button class="btn btn-teal btn-lg" type="submit">
                    Install now <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </div>
        </form>
    </div>
</div>
<?php View::endSection(); ?>
