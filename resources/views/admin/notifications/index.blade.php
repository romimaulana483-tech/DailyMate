@extends('layouts.app')

@section('title', 'System Notifications')
@section('page-title', 'System Notifications')
@section('page-subtitle', 'Technical system status, delivery health, and administrative activity.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>System Notifications</h2>
            <p>Administrative and technical information only. Private user content is never displayed.</p>
        </div>
        <span class="badge badge-soft-primary align-self-start">
            <i class="bi bi-shield-lock me-1"></i> Aggregate data only
        </span>
    </div>

    <div class="row g-3 row-cols-2 row-cols-lg-4 mb-4">
        <div class="col"><x-stat-card label="Delivered" :value="$status['sent']" icon="bi-check2-circle" tone="success" /></div>
        <div class="col"><x-stat-card label="Pending" :value="$status['pending']" icon="bi-hourglass-split" tone="warning" /></div>
        <div class="col"><x-stat-card label="Failed" :value="$status['failed']" icon="bi-exclamation-triangle" tone="danger" /></div>
        <div class="col"><x-stat-card label="Skipped" :value="$status['skipped']" icon="bi-dash-circle" tone="violet" /></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>System alerts</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        @foreach ($alerts as $alert)
                            <div class="alert alert-{{ $alert['tone'] }} mb-0">
                                <div class="d-flex gap-3">
                                    <i class="bi {{ $alert['icon'] }} fs-5 mt-1"></i>
                                    <div class="flex-grow-1">
                                        <strong>{{ $alert['title'] }}</strong>
                                        <p class="fs-13 mb-2 mt-1">{{ $alert['message'] }}</p>
                                        <a href="{{ $alert['route'] }}" class="btn btn-sm btn-light">{{ $alert['action'] }}</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <h5>Recent WhatsApp delivery status</h5>
                    <span class="badge badge-soft-primary">No message content</span>
                </div>
                <div class="card-body p-0">
                    @if ($recentNotifications->isEmpty())
                        <div class="empty-state py-5">
                            <i class="bi bi-whatsapp"></i>
                            <p class="mb-0">No delivery attempts yet.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 delivery-table">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Reminder</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentNotifications as $notification)
                                        <tr>
                                            <td>
                                                <span class="fw-600 fs-13">{{ $notification->user?->name ?? 'Deleted user' }}</span>
                                                @if ($notification->status === 'failed')
                                                    <span class="d-block text-muted fs-12">Technical details in server log</span>
                                                @elseif ($notification->status === 'skipped')
                                                    <span class="d-block text-muted fs-12">User preference or number unavailable</span>
                                                @endif
                                            </td>
                                            <td class="fs-13">{{ $notification->eventLabel() }}</td>
                                            <td>
                                                <span class="badge badge-soft-{{ $notification->status === 'sent' ? 'success' : ($notification->status === 'failed' ? 'danger' : ($notification->status === 'skipped' ? 'warning' : 'primary')) }}">
                                                    {{ $notification->statusLabel() }}
                                                </span>
                                            </td>
                                            <td class="fs-12 text-muted">{{ ($notification->sent_at ?? $notification->created_at)?->format('M j, H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Integration status</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="fs-13 text-muted">Qwen / Friday</span>
                        <span class="badge {{ $ai?->isReady() ? 'badge-soft-success' : 'badge-soft-warning' }}">
                            {{ $ai?->isReady() ? 'Ready' : 'Not ready' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="fs-13 text-muted">WhatsApp provider</span>
                        <span class="badge {{ $whatsapp?->isReadyToSend() ? 'badge-soft-success' : 'badge-soft-warning' }}">
                            {{ $whatsapp?->providerLabel() ?? 'Not configured' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="fs-13 text-muted">User accounts</span>
                        <span class="fw-600 fs-13">{{ $userCounts['total'] }} total</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="fs-13 text-muted">Active / inactive</span>
                        <span class="fw-600 fs-13">{{ $userCounts['active'] }} / {{ $userCounts['inactive'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <span class="fs-13 text-muted">Scheduler</span>
                        <span class="badge badge-soft-primary">Every 5 minutes</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Recent administrative activity</h5>
                </div>
                <div class="card-body p-0">
                    @if ($recentActivity->isEmpty())
                        <div class="empty-state py-5">
                            <i class="bi bi-activity"></i>
                            <p class="mb-0">No administrative activity recorded yet.</p>
                        </div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach ($recentActivity as $activity)
                                <div class="list-group-item py-3">
                                    <div class="d-flex justify-content-between gap-2">
                                        <span class="fw-600 fs-13">{{ $activity->action }}</span>
                                        <span class="fs-12 text-muted text-nowrap">{{ $activity->created_at->format('M j, H:i') }}</span>
                                    </div>
                                    <span class="fs-12 text-muted">{{ $activity->admin?->name ?? 'System' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="alert alert-light border mt-4 mb-0 fs-13">
                <i class="bi bi-info-circle me-1"></i>
                This page intentionally excludes task titles, schedule details, phone numbers, and AI conversations.
                Those records remain private to each user.
            </div>
        </div>
    </div>
@endsection
