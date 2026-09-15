/**
 * Live auction page.
 *
 * AJAX polling keeps this working on shared hosting with no WebSocket server.
 * The poll interval is configurable in Admin → Settings → Auctions, and the page
 * backs off automatically when the tab is hidden so idle tabs cost nothing.
 *
 * Swapping to WebSockets later means replacing `poll()` with a socket handler —
 * `applyState()` already takes exactly the payload a push would deliver.
 */
(function () {
    'use strict';

    const root = document.getElementById('auction-live');
    if (!root) return;

    const auctionId = root.dataset.auctionId;
    const baseInterval = Math.max(2000, parseInt(root.dataset.pollInterval || '4000', 10));
    const stateUrl = '/auctions/' + auctionId + '/state';
    const bidUrl = '/auctions/' + auctionId + '/bid';

    const el = {
        currentPrice: document.getElementById('current-price'),
        bidCount: document.getElementById('bid-count'),
        bidderCount: document.getElementById('bidder-count'),
        nextValid: document.getElementById('next-valid'),
        history: document.getElementById('bid-history'),
        position: document.getElementById('my-position'),
        countdown: document.getElementById('auction-countdown'),
        status: document.getElementById('auction-status'),
        form: document.getElementById('bid-form'),
        amount: document.getElementById('bid-amount'),
        feedback: document.getElementById('bid-feedback'),
        submit: document.getElementById('bid-submit'),
        extension: document.getElementById('extension-notice'),
        reserve: document.getElementById('reserve-status'),
    };

    let lastTopBidId = null;
    let pollTimer = null;
    let failures = 0;

    function applyState(state) {
        if (!state || !state.ok) return;

        if (el.currentPrice) el.currentPrice.textContent = state.current_price_display;
        if (el.bidCount) el.bidCount.textContent = state.bid_count;
        if (el.bidderCount) el.bidderCount.textContent = state.bidder_count;
        if (el.nextValid) el.nextValid.textContent = state.next_valid_display;

        // Keep the input aligned with the minimum next bid unless the user is typing.
        if (el.amount && document.activeElement !== el.amount) {
            el.amount.value = state.next_valid_amount;
            el.amount.min = state.auction_type === 'reverse' ? '0' : state.next_valid_amount;
        }

        if (el.countdown) {
            el.countdown.dataset.seconds = String(state.seconds_remaining);
        }

        if (el.reserve && state.has_reserve) {
            el.reserve.innerHTML = state.reserve_met
                ? '<span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Reserve met</span>'
                : '<span class="badge text-bg-warning"><i class="bi bi-exclamation-circle me-1"></i>Reserve not met</span>';
        }

        if (el.extension && state.extension_count > 0) {
            el.extension.classList.remove('d-none');
            el.extension.textContent =
                'Auto-extended ' + state.extension_count + ' of ' + state.max_extensions + ' times — late bids push the close time out.';
        }

        if (el.position && state.position) {
            el.position.innerHTML = state.position.has_bid
                ? (state.position.is_winning
                    ? '<div class="alert alert-success py-2 mb-0"><i class="bi bi-trophy me-1"></i>You hold the leading bid at ' + state.position.amount_display + '</div>'
                    : '<div class="alert alert-warning py-2 mb-0"><i class="bi bi-arrow-down-circle me-1"></i>You have been outbid — you are #' + state.position.rank + '</div>')
                : '';
            if (state.position.has_bid && !state.position.amount_display) {
                el.position.querySelector('.alert')?.appendChild(document.createTextNode(''));
            }
        }

        if (el.history && Array.isArray(state.bids)) {
            renderHistory(state.bids);
        }

        // The auction closed while we were watching.
        if (state.status !== 'live' && el.status) {
            el.status.innerHTML =
                '<div class="alert alert-secondary mb-0"><i class="bi bi-flag me-1"></i>This auction is ' +
                state.status + '. <a href="" onclick="location.reload();return false;">Refresh</a> for the result.</div>';
            el.form?.classList.add('d-none');
            stopPolling();
        }
    }

    function renderHistory(bids) {
        const topId = bids.length ? bids[0].id : null;
        const isNew = topId !== null && lastTopBidId !== null && topId !== lastTopBidId;

        el.history.innerHTML = bids.length === 0
            ? '<div class="text-muted small py-2">No bids yet — be the first.</div>'
            : bids.map((bid, index) => `
                <div class="bid-row d-flex justify-content-between align-items-center ${bid.is_you ? 'is-you' : ''}">
                    <div>
                        <span class="fw-semibold">${escapeHtml(bid.bidder)}</span>
                        ${bid.is_you ? '<span class="badge text-bg-info ms-1">You</span>' : ''}
                        ${index === 0 ? '<span class="badge text-bg-success ms-1">Leading</span>' : ''}
                        <div class="text-muted" style="font-size:.72rem">${escapeHtml(bid.ago)}</div>
                    </div>
                    <div class="text-end fw-bold ${index === 0 ? 'text-teal' : ''}">${escapeHtml(bid.amount)}</div>
                </div>`).join('');

        if (isNew) {
            el.history.firstElementChild?.classList.add('bid-flash');
            if (el.currentPrice) {
                el.currentPrice.classList.add('bid-flash');
                setTimeout(() => el.currentPrice.classList.remove('bid-flash'), 1200);
            }
        }
        lastTopBidId = topId;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
    }

    async function poll() {
        try {
            const state = await window.ScrapX.get(stateUrl);
            applyState(state);
            failures = 0;
        } catch (e) {
            // Back off on repeated failures instead of hammering a struggling server.
            failures += 1;
        }
        schedule();
    }

    function schedule() {
        clearTimeout(pollTimer);
        const hidden = document.hidden;
        const interval = hidden ? baseInterval * 5 : baseInterval * Math.min(8, 2 ** failures);
        pollTimer = setTimeout(poll, interval);
    }

    function stopPolling() {
        clearTimeout(pollTimer);
        pollTimer = null;
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && pollTimer !== null) {
            poll();
        }
    });

    // ------------------------------------------------------------ bid submit

    el.form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const amount = el.amount?.value;
        if (!amount || Number(amount) <= 0) {
            showFeedback('Enter a valid bid amount.', 'danger');
            return;
        }

        // Explicit confirmation: a bid is binding.
        const confirmMessage = 'Place a binding bid of ' + window.ScrapX.money(amount) + '?';
        if (!window.confirm(confirmMessage)) return;

        el.submit.disabled = true;
        el.submit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Placing bid…';

        const result = await window.ScrapX.post(bidUrl, { amount });

        el.submit.disabled = false;
        el.submit.innerHTML = '<i class="bi bi-hammer me-1"></i>Place bid';

        if (result.ok && result.success !== false) {
            showFeedback(result.message || 'Bid placed.', 'success');
            window.ScrapX.toast(result.message || 'Bid placed.', 'success');
            if (result.state) applyState(result.state);
            if (result.extended) {
                window.ScrapX.toast('The auction was extended because your bid landed near the close.', 'info');
            }
        } else {
            showFeedback(result.message || result.error || 'Your bid could not be placed.', 'danger');
            if (result.state) applyState(result.state);
        }
    });

    function showFeedback(message, type) {
        if (!el.feedback) return;
        el.feedback.className = 'alert alert-' + type + ' py-2 small mt-2';
        el.feedback.textContent = message;
        el.feedback.classList.remove('d-none');
    }

    // Quick bid-increment buttons.
    document.querySelectorAll('[data-bid-step]').forEach((button) => {
        button.addEventListener('click', () => {
            const step = parseFloat(button.dataset.bidStep || '0');
            const current = parseFloat(el.amount?.value || '0');
            if (el.amount) el.amount.value = (current + step).toFixed(2);
        });
    });

    schedule();
})();
