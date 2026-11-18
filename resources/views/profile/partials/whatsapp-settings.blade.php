<div class="card mb-4" id="whatsapp-settings">
    <div class="card-header d-flex justify-content-between align-items-center gap-2">
        <h5><i class="bi bi-whatsapp text-success me-1"></i> WhatsApp Notifications</h5>
        <span class="badge {{ $user->whatsapp_notifications_enabled ? 'badge-soft-success' : 'badge-soft-secondary' }}">
            {{ $user->whatsapp_notifications_enabled ? 'Active' : 'Disabled' }}
        </span>
    </div>
    <div class="card-body">
        <p class="fs-13 text-muted mb-3">
            Receive reminders for approaching task deadlines, high-priority work, and upcoming schedules.
        </p>

        <form method="POST" action="{{ route('user.whatsapp.update') }}" novalidate>
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="whatsapp_number" class="form-label">WhatsApp number</label>
                <input type="tel"
                       class="form-control @error('whatsapp_number') is-invalid @enderror"
                       id="whatsapp_number"
                       name="whatsapp_number"
                       value="{{ old('whatsapp_number', $user->whatsapp_number) }}"
                       placeholder="+6281234567890"
                       inputmode="tel"
                       maxlength="20"
                       autocomplete="tel">
                @error('whatsapp_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Use international format with a country code, for example +6281234567890.</div>
            </div>

            <input type="hidden" name="whatsapp_notifications_enabled" value="0">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="whatsapp_notifications_enabled"
                       name="whatsapp_notifications_enabled"
                       value="1"
                       @checked((bool) old('whatsapp_notifications_enabled', $user->whatsapp_notifications_enabled))>
                <label class="form-check-label" for="whatsapp_notifications_enabled">
                    Enable WhatsApp reminders
                </label>
            </div>

            <div class="mb-3">
                <label class="form-label">Deadline reminders</label>
                <div class="form-text mb-2">Choose when DailyMate should remind you before an incomplete task's deadline. Each reminder is sent only once.</div>
                @php
                    $selectedOffsets = collect(old('whatsapp_reminder_offsets', $user->reminderOffsets()))
                        ->map(fn ($offset) => (int) $offset)
                        ->all();
                @endphp
                <div class="row g-2">
                    @foreach ([1440 => '1 day before', 720 => '12 hours before', 180 => '3 hours before', 60 => '1 hour before'] as $value => $label)
                        <div class="col-sm-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       id="reminder_offset_{{ $value }}"
                                       name="whatsapp_reminder_offsets[]"
                                       value="{{ $value }}"
                                       @checked(in_array($value, $selectedOffsets, true))>
                                <label class="form-check-label fs-13" for="reminder_offset_{{ $value }}">{{ $label }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('whatsapp_reminder_offsets.*')<div class="text-danger fs-12 mt-1">{{ $message }}</div>@enderror
                <div class="form-text">If none are selected, DailyMate uses the 1-hour reminder by default.</div>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input @error('whatsapp_consent') is-invalid @enderror"
                       type="checkbox"
                       id="whatsapp_consent"
                       name="whatsapp_consent"
                       value="1"
                       @checked((bool) old('whatsapp_consent', $user->whatsapp_consent_at))>
                <label class="form-check-label fs-13" for="whatsapp_consent">
                    I agree to receive DailyMate reminders on WhatsApp. I can disable them at any time.
                </label>
                @error('whatsapp_consent')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i> Save WhatsApp Settings
            </button>
        </form>

        <div class="whatsapp-note mt-3">
            <i class="bi bi-shield-lock"></i>
            <span>Your number is only used for reminders. Administrators cannot read your tasks, schedules, or AI conversations.</span>
        </div>
    </div>
</div>
