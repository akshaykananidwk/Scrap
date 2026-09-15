<?php

use App\Core\View;

View::section('content');
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h1 class="h5 mb-1">Database connection</h1>
        <p class="text-muted small mb-4">
            Create an empty database in your hosting control panel first, then enter its details here.
        </p>

        <form method="post" action="<?= e(url('install/database')) ?>" id="db-form">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label required" for="db_host">Database host</label>
                    <input id="db_host" name="db_host" class="form-control" required
                           value="<?= e((string) ($saved['host'] ?? old('db_host', '127.0.0.1'))) ?>">
                    <div class="form-text">Usually <code>localhost</code> or <code>127.0.0.1</code>.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="db_port">Port</label>
                    <input id="db_port" name="db_port" class="form-control"
                           value="<?= e((string) ($saved['port'] ?? old('db_port', '3306'))) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="db_name">Database name</label>
                    <input id="db_name" name="db_name" class="form-control" required
                           value="<?= e((string) ($saved['name'] ?? old('db_name'))) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="db_user">Database username</label>
                    <input id="db_user" name="db_user" class="form-control" required
                           value="<?= e((string) ($saved['user'] ?? old('db_user'))) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="db_pass">Database password</label>
                    <input id="db_pass" name="db_pass" type="password" class="form-control"
                           value="<?= e((string) ($saved['pass'] ?? '')) ?>">
                </div>
            </div>

            <div id="db-result" class="mt-3"></div>

            <div class="d-flex justify-content-between mt-4">
                <a class="btn btn-outline-secondary" href="<?= e(url('install/requirements')) ?>">Back</a>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-teal" type="button" id="test-db" data-no-lock>
                        <i class="bi bi-plug me-1"></i>Test connection
                    </button>
                    <button class="btn btn-teal" type="submit">
                        Continue <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php View::endSection(); ?>

<?php View::section('scripts'); ?>
<script>
document.getElementById('test-db').addEventListener('click', async function () {
    const form = document.getElementById('db-form');
    const box = document.getElementById('db-result');
    const data = new FormData(form);

    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Testing…';
    box.innerHTML = '';

    try {
        const response = await fetch('<?= e(url('install/database/test')) ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            body: data,
        });
        const result = await response.json();
        box.innerHTML = '<div class="alert alert-' + (result.success ? 'success' : 'danger') + ' mb-0">' +
            '<i class="bi bi-' + (result.success ? 'check-circle' : 'x-octagon') + ' me-1"></i>' +
            (result.message || result.error || '') + '</div>';
    } catch (e) {
        box.innerHTML = '<div class="alert alert-danger mb-0">Could not reach the server. Check your connection.</div>';
    }

    this.disabled = false;
    this.innerHTML = '<i class="bi bi-plug me-1"></i>Test connection';
});
</script>
<?php View::endSection(); ?>
