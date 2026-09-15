/**
 * Sell-scrap wizard: 9 steps, client-side navigation with per-step validation.
 *
 * The form is a single ordinary <form> — if JavaScript fails, every field is
 * still present and submitting posts the whole thing, so a seller is never
 * locked out by a script error.
 */
(function () {
    'use strict';

    const wizard = document.getElementById('sell-wizard');
    if (!wizard) return;

    const steps = Array.from(wizard.querySelectorAll('.wizard-step'));
    const navItems = Array.from(document.querySelectorAll('.wizard-nav .step'));
    const backButton = document.getElementById('wizard-back');
    const nextButton = document.getElementById('wizard-next');
    const submitButton = document.getElementById('wizard-submit');
    const preview = document.getElementById('wizard-preview');
    let current = 0;

    function show(index) {
        current = Math.max(0, Math.min(steps.length - 1, index));

        steps.forEach((step, i) => step.classList.toggle('active', i === current));
        navItems.forEach((item, i) => {
            item.classList.toggle('active', i === current);
            item.classList.toggle('done', i < current);
        });

        backButton.classList.toggle('invisible', current === 0);
        nextButton.classList.toggle('d-none', current === steps.length - 1);
        submitButton.classList.toggle('d-none', current !== steps.length - 1);

        if (current === steps.length - 1) buildPreview();

        window.scrollTo({ top: wizard.offsetTop - 90, behavior: 'smooth' });
    }

    function validateStep() {
        const step = steps[current];
        const fields = Array.from(step.querySelectorAll('input, select, textarea'));
        let valid = true;

        fields.forEach((field) => {
            if (field.disabled || field.type === 'hidden') return;
            // Only enforce required-ness for the step being left.
            if (field.required && !field.value.trim()) {
                field.classList.add('is-invalid');
                valid = false;
            } else if (!field.checkValidity()) {
                field.classList.add('is-invalid');
                valid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });

        if (!valid) {
            window.ScrapX.toast('Please complete the highlighted fields before continuing.', 'warning');
            step.querySelector('.is-invalid')?.focus();
        }
        return valid;
    }

    function value(name) {
        const field = wizard.querySelector('[name="' + name + '"]');
        if (!field) return '';
        if (field.tagName === 'SELECT') {
            return field.options[field.selectedIndex]?.textContent?.trim() || '';
        }
        return field.value || '';
    }

    function buildPreview() {
        if (!preview) return;

        const rows = [
            ['Title', value('title')],
            ['Category', value('category_id')],
            ['Material', value('material_id')],
            ['Grade', value('grade_id') || value('grade_text')],
            ['Quantity', value('quantity') + ' ' + value('unit_id')],
            ['Minimum order', value('min_order_quantity')],
            ['Sale method', value('listing_type')],
            ['Price', value('price') ? window.ScrapX.money(value('price')) + ' (' + value('price_basis') + ')' : 'On request'],
            ['GST', value('gst_rate') ? value('gst_rate') + '%' : '—'],
            ['Condition', value('material_condition')],
            ['Location', [value('city_id'), value('state_id'), value('pincode')].filter(Boolean).join(', ')],
            ['Pickup address', value('pickup_address')],
            ['Loading by', value('loading_by')],
            ['Transport by', value('transport_by')],
            ['Payment terms', value('payment_terms')],
        ];

        const images = wizard.querySelector('[name="images[]"]')?.files?.length || 0;

        preview.innerHTML =
            '<div class="table-responsive"><table class="table table-sm mb-0">' +
            rows.filter(([, v]) => v && String(v).trim() !== '')
                .map(([label, v]) =>
                    '<tr><th class="text-muted fw-normal" style="width:38%">' + label + '</th><td>' +
                    String(v).replace(/[<>&]/g, (c) => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c])) +
                    '</td></tr>')
                .join('') +
            '<tr><th class="text-muted fw-normal">Photos</th><td>' + images + ' selected</td></tr>' +
            '</table></div>';
    }

    nextButton?.addEventListener('click', () => {
        if (validateStep()) show(current + 1);
    });
    backButton?.addEventListener('click', () => show(current - 1));

    navItems.forEach((item, index) => {
        item.style.cursor = 'pointer';
        item.addEventListener('click', () => {
            // Jumping backwards is always allowed; forwards requires validation.
            if (index <= current || validateStep()) show(index);
        });
    });

    // Sale method drives which price fields matter.
    const typeField = wizard.querySelector('[name="listing_type"]');
    function syncSaleMethod() {
        const type = typeField?.value;
        const priceBlock = document.getElementById('price-block');
        const auctionNote = document.getElementById('auction-note');
        const priceField = wizard.querySelector('[name="price"]');

        if (type === 'auction') {
            auctionNote?.classList.remove('d-none');
            if (priceField) priceField.required = false;
        } else {
            auctionNote?.classList.add('d-none');
            if (priceField) priceField.required = (type === 'fixed');
        }
        priceBlock?.classList.toggle('opacity-50', type === 'rfq' || type === 'wanted');
    }
    typeField?.addEventListener('change', syncSaleMethod);
    syncSaleMethod();

    show(0);
})();
