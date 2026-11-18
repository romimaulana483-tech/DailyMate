@extends('layouts.app')

@section('title', 'Admin Reports')
@section('page-title', 'Admin Reports')
@section('page-subtitle', 'Send aggregate system reports manually or on a daily, weekly, or monthly schedule.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Admin Reports</h2>
            <p>Reports contain aggregate statistics only. Private user content is never included.</p>
        </div>
        <span class="badge {{ $setting->is_active ? 'badge-soft-success' : 'badge-soft-warning' }} align-self-start">
            {{ $setting->is_active ? 'Schedule active' : 'Schedule inactive' }}
        </span>
    </div>

    <div class="row g-3 row-cols-1 row-cols-sm-3 mb-4">
        <div class="col"><x-stat-card label="Reports sent" :value="$stats['sent']" icon="bi-send-check" tone="success" /></div>
        <div class="col"><x-stat-card label="Reports failed" :value="$stats['failed']" icon="bi-exclamation-triangle" tone="danger" /></div>
        <div class="col">
            <div class="stat-card">
                <span class="stat-icon primary"><i class="bi bi-clock-history"></i></span>
                <div>
                    <span class="stat-label">Last sent</span>
                    <span class="stat-value fs-5">
                        {{ $stats['last_sent_at'] ? \Illuminate\Support\Carbon::parse($stats['last_sent_at'])->format('M j, H:i') : 'Never' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Report settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.reports.update') }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="recipient_number" class="form-label">Recipient WhatsApp number</label>
                            <input type="tel"
                                   class="form-control @error('recipient_number') is-invalid @enderror"
                                   id="recipient_number"
                                   name="recipient_number"
                                   value="{{ old('recipient_number', $setting->recipient_number) }}"
                                   placeholder="+6281234567890"
                                   maxlength="20">
                            @error('recipient_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Use international format. Reports are sent to this number only.</div>
                        </div>

                        <div class="mb-3">
                            <label for="send_time" class="form-label">Send time</label>
                            <input type="time"
                                   class="form-control @error('send_time') is-invalid @enderror"
                                   id="send_time"
                                   name="send_time"
                                   value="{{ old('send_time', $setting->send_time ?: '08:00') }}"
                                   required>
                            @error('send_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Daily reports send every day. Weekly reports send on Monday. Monthly reports send on the 1st.</div>
                        </div>

                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                                   @checked((bool) old('is_active', $setting->is_active))>
                            <label class="form-check-label" for="is_active">Enable scheduled reports</label>
                        </div>

                        <div class="border rounded-3 p-3 mb-4">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="daily_enabled" name="daily_enabled" value="1"
                                       @checked((bool) old('daily_enabled', $setting->daily_enabled))>
                                <label class="form-check-label" for="daily_enabled">Daily report</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="weekly_enabled" name="weekly_enabled" value="1"
                                       @checked((bool) old('weekly_enabled', $setting->weekly_enabled))>
                                <label class="form-check-label" for="weekly_enabled">Weekly report (Monday)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="monthly_enabled" name="monthly_enabled" value="1"
                                       @checked((bool) old('monthly_enabled', $setting->monthly_enabled))>
                                <label class="form-check-label" for="monthly_enabled">Monthly report (1st)</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Save Settings
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Manual report</h5>
                </div>
                <div class="card-body">
                    <p class="fs-13 text-muted">
                        Send an aggregate report immediately using the saved recipient number and the current WhatsApp configuration.
                    </p>
                    <form method="POST" action="{{ route('admin.reports.send') }}">
                        @csrf
                        <button type="submit" class="btn btn-soft-primary" @disabled(blank($setting->recipient_number))>
                            <i class="bi bi-send me-1"></i> Send Report Now
                        </button>
                    </form>
                    @if (blank($setting->recipient_number))
                        <p class="fs-12 text-muted mb-0 mt-2">Save a recipient number first.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <h5>Report delivery history</h5>
                    <span class="badge badge-soft-primary">Aggregate content only</span>
                </div>
                <div class="card-body p-0">
                    @if ($logs->isEmpty())
                        <div class="empty-state py-5">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <p class="mb-0">No reports have been sent yet.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Frequency</th>
                                        <th>Recipient</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($logs as $log)
                                        <tr>
                                            <td><span class="badge badge-soft-primary">{{ $log->frequencyLabel() }}</span></td>
                                            <td class="fs-13">{{ $log->maskedRecipient() }}</td>
                                            <td>
                                                <span class="badge badge-soft-{{ $log->status === 'sent' ? 'success' : ($log->status === 'failed' ? 'danger' : 'warning') }}">
                                                    {{ $log->statusLabel() }}
                                                </span>
                                                @if ($log->status === 'failed' && $log->error_message)
                                                    <span class="d-block fs-12 text-muted mt-1">{{ \Illuminate\Support\Str::limit($log->error_message, 90) }}</span>
                                                @endif
                                            </td>
                                            <td class="fs-12 text-muted">{{ ($log->sent_at ?? $log->created_at)?->format('M j, Y H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="alert alert-light border mt-4 mb-0 fs-13">
                <i class="bi bi-shield-lock me-1"></i>
                This page and the generated report intentionally exclude task titles, schedule details, user phone numbers, and AI chat content.
            </div>
        </div>
    </div>
@endsection
