/**
 * Chat thread — AJAX polling, same architecture as the auction page so both can
 * move to WebSockets later without changing the data flow.
 */
(function () {
    'use strict';

    const thread = document.getElementById('chat-thread');
    if (!thread) return;

    const conversationId = thread.dataset.conversationId;
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    let lastId = parseInt(thread.dataset.lastId || '0', 10);
    let timer = null;
    let failures = 0;

    function scrollToBottom() {
        thread.scrollTop = thread.scrollHeight;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
    }

    function renderMessage(message) {
        if (message.type === 'system') {
            return '<div class="chat-bubble system">' + escapeHtml(message.body) +
                '<div class="chat-meta">' + escapeHtml(message.time) + '</div></div>';
        }

        const attachments = (message.attachments || []).map((file) =>
            file.is_image
                ? '<a href="' + escapeHtml(file.url) + '" target="_blank" rel="noopener"><img src="' +
                  escapeHtml(file.url) + '" class="rounded mt-1" style="max-width:180px"></a>'
                : '<a href="' + escapeHtml(file.url) + '" target="_blank" rel="noopener" class="d-block small mt-1">' +
                  '<i class="bi bi-paperclip"></i> ' + escapeHtml(file.name) + ' (' + escapeHtml(file.size) + ')</a>'
        ).join('');

        return '<div class="chat-bubble ' + (message.is_mine ? 'me' : 'them') + '">' +
            (message.body ? escapeHtml(message.body).replace(/\n/g, '<br>') : '') +
            attachments +
            '<div class="chat-meta">' + escapeHtml(message.time) + '</div></div>';
    }

    async function poll() {
        try {
            const result = await window.ScrapX.get(
                '/dashboard/messages/' + conversationId + '/poll?after=' + lastId
            );
            if (result.success && Array.isArray(result.messages) && result.messages.length > 0) {
                const wasNearBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 120;
                result.messages.forEach((message) => {
                    thread.insertAdjacentHTML('beforeend', renderMessage(message));
                });
                lastId = result.last_id || lastId;
                if (wasNearBottom) scrollToBottom();
            }
            failures = 0;
        } catch (e) {
            failures += 1;
        }
        schedule();
    }

    function schedule() {
        clearTimeout(timer);
        const interval = (document.hidden ? 20000 : 5000) * Math.min(6, 2 ** failures);
        timer = setTimeout(poll, interval);
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) poll();
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(form);
        const body = (input?.value || '').trim();
        const hasFiles = (form.querySelector('input[type="file"]')?.files || []).length > 0;
        if (body === '' && !hasFiles) return;

        formData.set('after', String(lastId));

        const button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;

        const result = await window.ScrapX.post(
            '/dashboard/messages/' + conversationId,
            formData
        );

        if (button) button.disabled = false;

        if (result.success) {
            if (input) input.value = '';
            form.querySelectorAll('input[type="file"]').forEach((f) => { f.value = ''; });
            document.getElementById('chat-attachment-preview')?.replaceChildren();

            (result.messages || []).forEach((message) => {
                thread.insertAdjacentHTML('beforeend', renderMessage(message));
            });
            lastId = result.last_id || lastId;
            scrollToBottom();
        } else {
            window.ScrapX.toast(result.error || 'Message could not be sent.', 'danger');
        }
    });

    // Enter sends, Shift+Enter makes a new line.
    input?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form?.requestSubmit();
        }
    });

    scrollToBottom();
    schedule();
})();
