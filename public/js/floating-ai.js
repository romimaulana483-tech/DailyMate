/* DailyMate – floating AI Assistant */
(function () {
    'use strict';

    const widget = document.getElementById('aiWidget');
    if (!widget) return;

    const panel = document.getElementById('aiWidgetPanel');
    const toggle = document.getElementById('aiWidgetToggle');
    const closeButton = document.getElementById('aiWidgetClose');
    const clearButton = document.getElementById('aiWidgetClear');
    const form = document.getElementById('aiWidgetForm');
    const input = document.getElementById('aiWidgetInput');
    const sendButton = document.getElementById('aiWidgetSend');
    const messages = document.getElementById('aiWidgetMessages');
    const welcome = document.getElementById('aiWidgetWelcome');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const historyUrl = widget.dataset.historyUrl;
    const sendUrl = widget.dataset.sendUrl;
    const clearUrl = widget.dataset.clearUrl;

    let historyLoaded = false;
    let historyLoading = false;
    let sending = false;
    let closeTimer = null;

    function isOpen() {
        return widget.classList.contains('open');
    }

    function scrollToBottom() {
        messages.scrollTop = messages.scrollHeight;
    }

    function timeNow() {
        return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
    }

    function formatAiText(text) {
        const escaped = String(text ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        return escaped
            .replace(/\*\*(.+?)\*\*/gs, '<strong>$1</strong>')
            .replace(/(?<!\*)\*([^*\n]+)\*(?!\*)/g, '<em>$1</em>')
            .replace(/\n/g, '<br>');
    }

    function removeEmptyState() {
        if (welcome) welcome.hidden = true;
    }

    function showEmptyState() {
        messages.querySelectorAll('.chat-row').forEach((row) => row.remove());
        if (welcome) welcome.hidden = false;
    }

    function appendMessage(role, text, time, isHistory) {
        removeEmptyState();

        const row = document.createElement('div');
        row.className = 'chat-row ' + role;
        if (isHistory) row.dataset.historyMessage = '1';

        const avatar = document.createElement('span');
        avatar.className = 'chat-avatar-sm';
        avatar.innerHTML = role === 'user' ? '<i class="bi bi-person"></i>' : '<i class="bi bi-stars"></i>';

        const wrap = document.createElement('div');
        wrap.className = 'chat-bubble-wrap';

        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble';

        if (role === 'ai') {
            bubble.innerHTML = formatAiText(text);
        } else {
            bubble.style.whiteSpace = 'pre-line';
            bubble.textContent = text;
        }

        const timeElement = document.createElement('span');
        timeElement.className = 'chat-time';
        timeElement.textContent = time || timeNow();

        wrap.appendChild(bubble);
        wrap.appendChild(timeElement);
        row.appendChild(avatar);
        row.appendChild(wrap);
        messages.appendChild(row);
        scrollToBottom();
    }

    function showTyping() {
        const row = document.createElement('div');
        row.className = 'chat-row ai';
        row.id = 'aiWidgetTypingRow';

        const avatar = document.createElement('span');
        avatar.className = 'chat-avatar-sm';
        avatar.innerHTML = '<i class="bi bi-stars"></i>';

        const wrap = document.createElement('div');
        wrap.className = 'chat-bubble-wrap';

        const typing = document.createElement('div');
        typing.className = 'typing-indicator';
        typing.innerHTML = '<span></span><span></span><span></span>';

        wrap.appendChild(typing);
        row.appendChild(avatar);
        row.appendChild(wrap);
        messages.appendChild(row);
        scrollToBottom();
    }

    function hideTyping() {
        document.getElementById('aiWidgetTypingRow')?.remove();
    }

    function setSending(value) {
        sending = value;
        sendButton.disabled = value;
        input.disabled = value;
    }

    function autoResize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 104) + 'px';
    }

    async function loadHistory() {
        if (historyLoaded || historyLoading) return;
        historyLoading = true;

        try {
            const response = await fetch(historyUrl, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });

            if (!response.ok) return;

            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) return;

            const data = await response.json();
            const histories = Array.isArray(data.histories) ? data.histories : [];

            if (histories.length === 0) {
                historyLoaded = true;
                return;
            }

            histories.forEach(function (chat) {
                appendMessage('user', chat.message || '', chat.time, true);
                appendMessage('ai', chat.response || '', chat.time, true);
            });

            historyLoaded = true;
            scrollToBottom();
        } catch (error) {
            // Keep the welcome state; the user can still start a new chat.
        } finally {
            historyLoading = false;
        }
    }

    function openPanel() {
        window.clearTimeout(closeTimer);
        panel.hidden = false;

        window.requestAnimationFrame(function () {
            widget.classList.add('open');
        });

        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Close DailyMate AI Assistant');
        loadHistory();
        scrollToBottom();

        window.setTimeout(function () {
            input.focus({ preventScroll: true });
        }, 180);
    }

    function closePanel() {
        if (!isOpen()) return;
        widget.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open DailyMate AI Assistant');
        closeTimer = window.setTimeout(function () {
            panel.hidden = true;
        }, 180);
        toggle.focus({ preventScroll: true });
    }

    async function sendMessage() {
        const message = input.value.trim();
        if (!message || sending) return;

        appendMessage('user', message);
        input.value = '';
        autoResize();
        setSending(true);
        showTyping();

        try {
            const response = await fetch(sendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message: message })
            });

            hideTyping();

            if (!response.ok) {
                if (response.status === 422) {
                    const data = await response.json();
                    appendMessage('ai', data.errors?.message?.[0] || 'Please check your message and try again.');
                } else if (response.status === 401 || response.status === 419) {
                    appendMessage('ai', 'Your session has expired. Please refresh the page and sign in again.');
                } else {
                    appendMessage('ai', 'Sorry, the assistant could not answer right now. Please try again.');
                }
                return;
            }

            const data = await response.json();
            appendMessage('ai', data.response || 'I could not generate a reply. Please try again.', data.time);
        } catch (error) {
            hideTyping();
            appendMessage('ai', 'Network error. Please check your connection and try again.');
        } finally {
            setSending(false);
            input.focus({ preventScroll: true });
            scrollToBottom();
        }
    }

    async function clearHistory() {
        if (!window.confirm('Clear your entire AI conversation history? This cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch(clearUrl, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                appendMessage('ai', 'Could not clear the history. Please try again.');
                return;
            }

            historyLoaded = true;
            showEmptyState();
            appendMessage('ai', 'Your conversation history has been cleared.');
        } catch (error) {
            appendMessage('ai', 'Network error. Could not clear the history.');
        }
    }

    toggle?.addEventListener('click', function () {
        isOpen() ? closePanel() : openPanel();
    });

    closeButton?.addEventListener('click', closePanel);
    clearButton?.addEventListener('click', clearHistory);

    form?.addEventListener('submit', function (event) {
        event.preventDefault();
        sendMessage();
    });

    input?.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });

    input?.addEventListener('input', autoResize);

    document.querySelectorAll('[data-floating-suggestion]').forEach(function (chip) {
        chip.addEventListener('click', function () {
            input.value = chip.getAttribute('data-floating-suggestion') || '';
            autoResize();
            input.focus();
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && isOpen()) closePanel();
    });
})();
