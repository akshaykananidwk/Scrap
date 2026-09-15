/**
 * ScrapX front-end.
 *
 * Vanilla JS only — no build step, no framework. Everything is progressive:
 * every feature here has a working non-JS fallback (a normal form post or link),
 * so the marketplace still functions if a script fails to load.
 */
(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // ---------------------------------------------------------------- helpers

    window.ScrapX = {
        csrfToken,

        async post(url, data = {}, options = {}) {
            const isFormData = data instanceof FormData;
            const headers = {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken,
                Accept: 'application/json',
            };
            if (!isFormData) {
                headers['Content-Type'] = 'application/json';
            }

            const response = await fetch(url, {
                method: 'POST',
                headers,
                credentials: 'same-origin',
                body: isFormData ? data : JSON.stringify({ ...data, _token: csrfToken }),
                ...options,
            });

            let payload = {};
            try {
                payload = await response.json();
            } catch (e) {
                payload = { success: false, error: 'Unexpected server response (HTTP ' + response.status + ').' };
            }
            return { ok: response.ok, status: response.status, ...payload };
        },

        async get(url) {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                credentials: 'same-origin',
            });
            try {
                return await response.json();
            } catch (e) {
                return { success: false, error: 'Unexpected server response.' };
            }
        },

        toast(message, type = 'info') {
            let holder = document.getElementById('sx-toasts');
            if (!holder) {
                holder = document.createElement('div');
                holder.id = 'sx-toasts';
                holder.className = 'toast-container position-fixed top-0 end-0 p-3';
                holder.style.zIndex = '1090';
                document.body.appendChild(holder);
            }

            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-bg-' + type + ' border-0 show';
            toast.setAttribute('role', 'alert');
            toast.innerHTML =
                '<div class="d-flex"><div class="toast-body">' +
                String(message).replace(/[<>&]/g, (c) => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c])) +
                '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
            holder.appendChild(toast);

            setTimeout(() => toast.remove(), 6000);
            toast.querySelector('.btn-close')?.addEventListener('click', () => toast.remove());
        },

        money(value) {
            const number = Number(value || 0);
            return '₹' + number.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        formatDuration(seconds) {
            if (seconds <= 0) return null;
            const d = Math.floor(seconds / 86400);
            const h = Math.floor((seconds % 86400) / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            return { d, h, m, s };
        },
    };

    // ------------------------------------------------------------- countdowns

    function renderCountdowns() {
        document.querySelectorAll('.js-countdown').forEach((el) => {
            let seconds = parseInt(el.dataset.seconds || '0', 10);
            if (Number.isNaN(seconds)) return;

            seconds = Math.max(0, seconds - 1);
            el.dataset.seconds = String(seconds);

            if (seconds <= 0) {
                el.textContent = 'Closed';
                el.classList.add('text-muted');
                return;
            }

            const parts = ScrapX.formatDuration(seconds);
            const pad = (n) => String(n).padStart(2, '0');
            el.textContent = parts.d > 0
                ? `${parts.d}d ${pad(parts.h)}:${pad(parts.m)}:${pad(parts.s)}`
                : `${pad(parts.h)}:${pad(parts.m)}:${pad(parts.s)}`;

            if (seconds < 300) el.classList.add('text-danger', 'fw-bold');
        });

        document.querySelectorAll('.js-countdown-box').forEach((box) => {
            let seconds = Math.max(0, parseInt(box.dataset.seconds || '0', 10) - 1);
            box.dataset.seconds = String(seconds);
            const parts = ScrapX.formatDuration(seconds) || { d: 0, h: 0, m: 0, s: 0 };
            const set = (key, value) => {
                const node = box.querySelector('[data-unit="' + key + '"]');
                if (node) node.textContent = String(value).padStart(2, '0');
            };
            set('d', parts.d);
            set('h', parts.h);
            set('m', parts.m);
            set('s', parts.s);
            box.classList.toggle('countdown-urgent', seconds > 0 && seconds < 300);
        });
    }
    setInterval(renderCountdowns, 1000);
    renderCountdowns();

    // --------------------------------------------------------------- favorites

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('.js-favorite');
        if (!button) return;

        event.preventDefault();
        button.disabled = true;

        const result = await ScrapX.post('/dashboard/favorites/toggle', {
            type: button.dataset.type || 'listing',
            id: button.dataset.id,
        });

        button.disabled = false;
        if (result.success) {
            const icon = button.querySelector('i');
            if (icon) {
                icon.className = result.saved ? 'bi bi-bookmark-fill text-teal' : 'bi bi-bookmark';
            }
            ScrapX.toast(result.message, 'success');
        } else {
            ScrapX.toast(result.error || 'Sign in to save listings.', 'danger');
        }
    });

    // ----------------------------------------------------- dependent dropdowns

    // Category → materials → grades, and state → cities.
    document.querySelectorAll('[data-load-materials]').forEach((select) => {
        select.addEventListener('change', async function () {
            const target = document.querySelector(this.dataset.loadMaterials);
            if (!target || !this.value) return;

            target.innerHTML = '<option value="">Loading…</option>';
            const result = await ScrapX.get('/api/v1/categories/' + encodeURIComponent(this.value) + '/materials');
            target.innerHTML = '<option value="">Select material</option>';

            (result.data || []).forEach((material) => {
                const option = document.createElement('option');
                option.value = material.id;
                option.textContent = material.name;
                option.dataset.unitId = material.default_unit_id || '';
                option.dataset.gstRate = material.gst_rate || '';
                target.appendChild(option);
            });
        });
    });

    document.querySelectorAll('[data-load-grades]').forEach((select) => {
        select.addEventListener('change', async function () {
            const target = document.querySelector(this.dataset.loadGrades);
            if (!target || !this.value) return;

            // Carry the material's default unit and GST rate into the form.
            const option = this.options[this.selectedIndex];
            const unitField = document.querySelector('[name="unit_id"]');
            if (unitField && option?.dataset.unitId) unitField.value = option.dataset.unitId;
            const gstField = document.querySelector('[name="gst_rate"]');
            if (gstField && option?.dataset.gstRate) gstField.value = option.dataset.gstRate;

            target.innerHTML = '<option value="">Loading…</option>';
            const result = await ScrapX.get('/api/v1/materials/' + encodeURIComponent(this.value) + '/grades');
            target.innerHTML = '<option value="">Select grade (optional)</option>';

            (result.data || []).forEach((grade) => {
                const opt = document.createElement('option');
                opt.value = grade.id;
                opt.textContent = grade.name;
                target.appendChild(opt);
            });
        });
    });

    document.querySelectorAll('[data-load-cities]').forEach((select) => {
        select.addEventListener('change', async function () {
            const target = document.querySelector(this.dataset.loadCities);
            if (!target || !this.value) return;

            target.innerHTML = '<option value="">Loading…</option>';
            const result = await ScrapX.get('/api/v1/states/' + encodeURIComponent(this.value) + '/cities');
            target.innerHTML = '<option value="">Select city</option>';

            (result.data || []).forEach((city) => {
                const option = document.createElement('option');
                option.value = city.id;
                option.textContent = city.name;
                target.appendChild(option);
            });
        });
    });

    // Pincode → city/state auto-fill.
    document.querySelectorAll('[data-pincode-lookup]').forEach((input) => {
        input.addEventListener('blur', async function () {
            const pincode = (this.value || '').replace(/\D/g, '');
            if (pincode.length !== 6) return;

            const result = await ScrapX.get('/api/v1/pincode/' + pincode);
            if (!result.success || !result.data) return;

            const stateField = document.querySelector('[name="state_id"]');
            const cityField = document.querySelector('[name="city_id"]');
            if (stateField && result.data.state_id) {
                stateField.value = result.data.state_id;
                stateField.dispatchEvent(new Event('change'));
            }
            if (cityField && result.data.city_id) {
                setTimeout(() => { cityField.value = result.data.city_id; }, 400);
            }
            ScrapX.toast('Location filled from pincode: ' + (result.data.city || '') + ', ' + (result.data.state || ''), 'info');
        });
    });

    // -------------------------------------------------------------- utilities

    // Auto-submit filter forms when a select changes.
    document.querySelectorAll('[data-auto-submit]').forEach((element) => {
        element.addEventListener('change', () => element.closest('form')?.submit());
    });

    // Confirm destructive actions.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        const message = form.dataset?.confirm;
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // Disable submit buttons on POST to prevent double submission (double bids,
    // duplicate orders). A short timeout re-enables in case of validation errors.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (form.method?.toLowerCase() !== 'post' || form.dataset.noLock === '1') return;

        const button = form.querySelector('button[type="submit"]:not([data-no-lock])');
        if (!button || button.disabled) return;

        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Working…';
        setTimeout(() => {
            button.disabled = false;
            button.innerHTML = original;
        }, 12000);
    });

    // Image preview for file inputs.
    document.querySelectorAll('[data-preview]').forEach((input) => {
        input.addEventListener('change', function () {
            const holder = document.querySelector(this.dataset.preview);
            if (!holder) return;
            holder.innerHTML = '';

            Array.from(this.files || []).slice(0, 12).forEach((file) => {
                if (!file.type.startsWith('image/')) return;
                const img = document.createElement('img');
                img.className = 'rounded border me-2 mb-2';
                img.style.cssText = 'width:84px;height:84px;object-fit:cover';
                img.src = URL.createObjectURL(file);
                img.onload = () => URL.revokeObjectURL(img.src);
                holder.appendChild(img);
            });
        });
    });

    // Copy-to-clipboard buttons (cron URL, API token, UPI id…).
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy]');
        if (!button) return;

        event.preventDefault();
        const text = button.dataset.copy || document.querySelector(button.dataset.copyTarget || '')?.value || '';
        try {
            await navigator.clipboard.writeText(text);
            ScrapX.toast('Copied to clipboard.', 'success');
        } catch (e) {
            ScrapX.toast('Copy failed — select the text and copy manually.', 'warning');
        }
    });

    // Mark notifications read from the dropdown.
    document.querySelectorAll('[data-mark-read]').forEach((button) => {
        button.addEventListener('click', async (event) => {
            event.preventDefault();
            const result = await ScrapX.post('/dashboard/notifications/read', { id: button.dataset.markRead || 0 });
            if (result.success) window.location.reload();
        });
    });

    // ------------------------------------------------------------------- PWA

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/service-worker.js').catch(() => {
                // A failed registration must never break the page.
            });
        });
    }

    let installPrompt = null;
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event;
        document.querySelectorAll('[data-pwa-install]').forEach((button) => {
            button.classList.remove('d-none');
            button.addEventListener('click', async () => {
                if (!installPrompt) return;
                installPrompt.prompt();
                await installPrompt.userChoice;
                installPrompt = null;
                button.classList.add('d-none');
            });
        });
    });
})();
