@extends('layouts.app')

@section('title', 'Friday AI')
@section('page-title', 'Friday AI')
@section('page-subtitle', 'Your personal DailyMate productivity assistant.')

@section('content')
    @php
        $firstName = explode(' ', trim(auth()->user()->name))[0];
    @endphp

    <div class="page-heading">
        <div>
            <h2>Friday</h2>
            <p>Ask for help prioritising, planning, managing time or checking your DailyMate data.</p>
        </div>
        @if ($histories->isNotEmpty())
            <form method="POST" action="{{ route('user.ai.clear') }}" data-confirm="Clear your entire conversation history? This cannot be undone.">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-soft-danger">
                    <i class="bi bi-trash me-1"></i> Clear History
                </button>
            </form>
        @endif
    </div>

    @if (! $hasApiKey)
        <div class="alert alert-info mb-3">
            <i class="bi bi-info-circle-fill mt-1"></i>
            <div>
                <strong>Built-in Friday mode.</strong>
                Qwen is not configured, so DailyMate is using its built-in productivity assistant.
                Ask an administrator to configure the API key in Friday AI Settings to enable full AI responses.
            </div>
        </div>
    @endif

    <div class="chat-shell">
        <div class="chat-header">
            <span class="chat-avatar"><i class="bi bi-stars"></i></span>
            <div class="flex-grow-1">
                <h5>Friday</h5>
                <p>
                    <span class="online-dot"></span>
                    {{ $hasApiKey ? 'Online · powered by Qwen' : 'Built-in mode · local assistant' }}
                </p>
            </div>
            <span class="badge badge-soft-primary d-none d-sm-inline">
                <i class="bi bi-shield-lock me-1"></i> Private conversation
            </span>
        </div>

        <div class="chat-messages" id="chatMessages">
            @if ($histories->isEmpty())
                <div class="chat-welcome" id="chatWelcome">
                    <div class="welcome-icon"><i class="bi bi-stars"></i></div>
                    <h5>Hi {{ $firstName }}, how can I help today?</h5>
                    <p>I can help you prioritise tasks, check deadlines, build a realistic daily plan, and configure reminders.</p>
                    <div class="suggestion-chips">
                        <button type="button" class="suggestion-chip" data-suggestion="What should I work on first today?">What should I work on first?</button>
                        <button type="button" class="suggestion-chip" data-suggestion="Help me plan tomorrow.">Help me plan tomorrow</button>
                        <button type="button" class="suggestion-chip" data-suggestion="Give me a productivity tip.">Productivity tip</button>
                        <button type="button" class="suggestion-chip" data-suggestion="I need some motivation.">I need motivation</button>
                    </div>
                </div>
            @else
                @foreach ($histories as $chat)
                    <div class="chat-row user">
                        <span class="chat-avatar-sm"><i class="bi bi-person"></i></span>
                        <div class="chat-bubble-wrap">
                            <div class="chat-bubble">{!! nl2br(e($chat->message)) !!}</div>
                            <span class="chat-time">{{ $chat->created_at->format('H:i') }}</span>
                        </div>
                    </div>

                    <div class="chat-row ai">
                        <span class="chat-avatar-sm"><i class="bi bi-stars"></i></span>
                        <div class="chat-bubble-wrap">
                            <div class="chat-bubble">{!! \App\Support\AiText::format($chat->response) !!}</div>
                            <span class="chat-time">{{ $chat->created_at->format('H:i') }}</span>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        <div class="chat-input-area">
            <form class="chat-input-form" id="chatForm" method="POST" action="{{ route('user.ai.send') }}">
                @csrf
                <textarea class="form-control" id="chatInput" name="message" rows="1" maxlength="2000"
                          placeholder="Ask about your tasks, schedule or productivity..." required></textarea>
                <button type="submit" class="chat-send-btn" id="chatSend" aria-label="Send message">
                    <i class="bi bi-send"></i>
                </button>
            </form>
            <div class="chat-hint">Press Enter to send · Shift + Enter for a new line · Your history is visible only to you.</div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const form = document.getElementById('chatForm');
            const input = document.getElementById('chatInput');
            const sendButton = document.getElementById('chatSend');
            const messages = document.getElementById('chatMessages');
            const welcome = document.getElementById('chatWelcome');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            function scrollToBottom() {
                messages.scrollTop = messages.scrollHeight;
            }

            function timeNow() {
                return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            function formatAiText(text) {
                const escaped = text
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');

                return escaped
                    .replace(/\*\*(.+?)\*\*/gs, '<strong>$1</strong>')
                    .replace(/(?<!\*)\*([^*\n]+)\*(?!\*)/g, '<em>$1</em>')
                    .replace(/\n/g, '<br>');
            }

            function appendMessage(role, text) {
                const row = document.createElement('div');
                row.className = 'chat-row ' + role;

                const avatar = document.createElement('span');
                avatar.className = 'chat-avatar-sm';
                avatar.innerHTML = role === 'user' ? '<i class="bi bi-person"></i>' : '<i class="bi bi-stars"></i>';

                const wrap = document.createElement('div');
                wrap.className = 'chat-bubble-wrap';

                const bubble = document.createElement('div');
                bubble.className = 'chat-bubble';
                bubble.style.whiteSpace = role === 'ai' ? 'normal' : 'pre-line';

                if (role === 'ai') {
                    bubble.innerHTML = formatAiText(text);
                } else {
                    bubble.textContent = text;
                }

                const time = document.createElement('span');
                time.className = 'chat-time';
                time.textContent = timeNow();

                wrap.appendChild(bubble);
                wrap.appendChild(time);
                row.appendChild(avatar);
                row.appendChild(wrap);
                messages.appendChild(row);
                scrollToBottom();

                return bubble;
            }

            function showTyping() {
                const row = document.createElement('div');
                row.className = 'chat-row ai';
                row.id = 'typingRow';

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
                document.getElementById('typingRow')?.remove();
            }

            function autoResize() {
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 140) + 'px';
            }

            async function sendMessage() {
                const message = input.value.trim();
                if (!message) return;

                welcome?.remove();
                appendMessage('user', message);
                input.value = '';
                autoResize();
                sendButton.disabled = true;
                showTyping();

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ message: message })
                    });

                    hideTyping();

                    if (!response.ok) {
                        if (response.status === 422) {
                            const data = await response.json();
                            const firstError = data.errors?.message?.[0] || 'Please check your message and try again.';
                            appendMessage('ai', firstError);
                        } else {
                            appendMessage('ai', 'Sorry, something went wrong while contacting the assistant. Please try again.');
                        }
                        return;
                    }

                    const data = await response.json();
                    appendMessage('ai', data.response || 'I could not generate a reply. Please try again.');
                } catch (error) {
                    hideTyping();
                    appendMessage('ai', 'Network error. Please check your connection and try again.');
                } finally {
                    sendButton.disabled = false;
                    input.focus({ preventScroll: true });
                    scrollToBottom();
                }
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                sendMessage();
            });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    sendMessage();
                }
            });

            input.addEventListener('input', autoResize);

            document.querySelectorAll('[data-suggestion]').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    input.value = chip.getAttribute('data-suggestion');
                    autoResize();
                    input.focus();
                });
            });

            scrollToBottom();
        })();
    </script>
@endpush
