@php
    $floatingFirstName = explode(' ', trim(auth()->user()->name))[0];
    $floatingIsAdmin = auth()->user()->isAdmin();
@endphp

<div class="ai-widget"
     id="aiWidget"
     data-history-url="{{ route('friday.history') }}"
     data-send-url="{{ route('friday.send') }}"
     data-clear-url="{{ route('friday.clear') }}"
     data-full-url="{{ route('friday.index') }}">
    <section class="ai-widget-panel" id="aiWidgetPanel" hidden aria-label="{{ __('Friday AI Assistant') }}">
        <header class="ai-widget-header">
            <span class="chat-avatar"><i class="bi bi-stars"></i></span>
            <div class="flex-grow-1 min-w-0">
                <h5>Friday</h5>
                <p><span class="online-dot"></span> {{ $floatingIsAdmin ? __('Admin assistant · aggregate data only') : __('Ask about tasks, deadlines or planning') }}</p>
            </div>
            <div class="d-flex align-items-center gap-1">
                <button type="button" class="ai-widget-icon-btn" id="aiWidgetClear" aria-label="{{ __('Clear conversation history') }}" title="{{ __('Clear history') }}">
                    <i class="bi bi-trash3"></i>
                </button>
                <a href="{{ route('friday.index') }}" class="ai-widget-icon-btn" aria-label="{{ __('Open full Friday page') }}" title="{{ __('Open full page') }}">
                    <i class="bi bi-arrows-angle-expand"></i>
                </a>
                <button type="button" class="ai-widget-icon-btn" id="aiWidgetClose" aria-label="{{ __('Close Friday') }}">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </header>

        <div class="ai-widget-messages" id="aiWidgetMessages" aria-live="polite">
            <div class="chat-welcome" id="aiWidgetWelcome">
                <div class="welcome-icon"><i class="bi bi-stars"></i></div>
                <h5>{{ __('Hi :name, need a hand?', ['name' => $floatingFirstName]) }}</h5>
                <p>
                    {{ $floatingIsAdmin
                        ? __('I can help with DailyMate administration, aggregate statistics, AI/WhatsApp settings and system reports.')
                        : __('I can help you prioritise tasks, check deadlines, plan your day, or configure reminders.') }}
                </p>
                <div class="suggestion-chips">
                    @if ($floatingIsAdmin)
                        <button type="button" class="suggestion-chip" data-floating-suggestion="Give me an aggregate system overview.">{{ __('System overview') }}</button>
                        <button type="button" class="suggestion-chip" data-floating-suggestion="How do I configure Qwen for Friday?">{{ __('Configure Qwen') }}</button>
                        <button type="button" class="suggestion-chip" data-floating-suggestion="How do I test WhatsApp delivery?">{{ __('Test WhatsApp') }}</button>
                    @else
                        <button type="button" class="suggestion-chip" data-floating-suggestion="What should I work on first today?">{{ __('What first?') }}</button>
                        <button type="button" class="suggestion-chip" data-floating-suggestion="Help me plan tomorrow.">{{ __('Plan tomorrow') }}</button>
                        <button type="button" class="suggestion-chip" data-floating-suggestion="What are my upcoming deadlines?">{{ __('My deadlines') }}</button>
                    @endif
                </div>
            </div>
        </div>

        <form class="ai-widget-form" id="aiWidgetForm" method="POST" action="{{ route('friday.send') }}">
            @csrf
            <textarea class="form-control" id="aiWidgetInput" name="message" rows="1" maxlength="2000"
                      placeholder="{{ $floatingIsAdmin ? __('Ask about admin features or settings...') : __('Ask about your tasks, deadlines or schedule...') }}" required></textarea>
            <button type="submit" class="chat-send-btn" id="aiWidgetSend" aria-label="{{ __('Send message') }}">
                <i class="bi bi-send"></i>
            </button>
        </form>
        <div class="ai-widget-foot">
            <i class="bi bi-shield-lock"></i> {{ $floatingIsAdmin ? __('Admin history is private to this account') : __('Private to your account') }}
        </div>
    </section>

    <button type="button" class="ai-fab" id="aiWidgetToggle" aria-controls="aiWidgetPanel" aria-expanded="false" aria-label="{{ __('Open Friday AI Assistant') }}">
        <span class="ai-fab-pulse"></span>
        <span class="ai-fab-icon"><i class="bi bi-stars"></i></span>
        <span class="ai-fab-close"><i class="bi bi-x-lg"></i></span>
        <span class="ai-fab-label">Friday</span>
    </button>
</div>
