@extends('layouts.app')

@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('page-subtitle', 'Review reminder history and manage your WhatsApp deadline reminders.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Notifications</h2>
            <p>Deadline and schedule reminders are private to your account.</p>
        </div>
        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('user.notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-light">
                    <i class="bi bi-check2-all me-1"></i> Mark all as read
                </button>
            </form>
        @endif
    </div>

    <div class="row g-3 row-cols-1 row-cols-sm-3 mb-4">
        <div class="col"><x-stat-card label="Unread" :value="$unreadCount" icon="bi-bell-fill" tone="primary" /></div>
        <div class="col"><x-stat-card label="Total notifications" :value="$totalCount" icon="bi-archive" tone="violet" /></div>
        <div class="col"><x-stat-card label="Created today" :value="$todayCount" icon="bi-calendar-check" tone="success" /></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <h5>Reminder history</h5>
                    <span class="badge badge-soft-primary">{{ $notifications->count() }} shown</span>
                </div>
                <div class="card-body p-0">
                    @if ($notifications->isEmpty())
                        <div class="empty-state py-5">
                            <i class="bi bi-bell"></i>
                            <p class="mb-1">No notifications yet.</p>
                            <p class="fs-13 text-muted mb-0">When a task deadline or schedule reminder is triggered, it will appear here.</p>
                        </div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach ($notifications as $notification)
                                <div class="list-group-item py-3 {{ $notification->read_at ? '' : 'bg-light' }}">
                                    <div class="d-flex gap-3">
                                        <span class="stat-icon {{ $notification->read_at ? 'violet' : 'primary' }} flex-shrink-0" style="width:40px;height:40px;font-size:1rem;">
                                            <i class="bi {{ $notification->type === 'schedule_start' ? 'bi-clock-history' : ($notification->type === 'task_high_priority' ? 'bi-exclamation-triangle' : 'bi-alarm') }}"></i>
                                        </span>
                                        <div class="min-w-0 flex-grow-1">
                                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                                <div>
                                                    <span class="badge badge-soft-primary mb-1">{{ $notification->typeLabel() }}</span>
                                                    @unless ($notification->read_at)
                                                        <span class="badge badge-soft-danger mb-1">Unread</span>
                                                    @endunless
                                                </div>
                                                <span class="fs-12 text-muted">{{ $notification->created_at->format('M j, Y H:i') }}</span>
                                            </div>
                                            <h6 class="mb-1">{{ $notification->title }}</h6>
                                            <p class="fs-13 text-muted mb-2">{!! nl2br(e($notification->body)) !!}</p>
                                            @unless ($notification->read_at)
                                                <form method="POST" action="{{ route('user.notifications.read', $notification->id) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-soft-primary">
                                                        <i class="bi bi-check2 me-1"></i> Mark as read
                                                    </button>
                                                </form>
                                            @endunless
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Reminder preferences</h5>
                </div>
                <div class="card-body">
                    <p class="fs-13 text-muted">
                        Only incomplete tasks trigger deadline reminders. DailyMate records each reminder and never sends
                        the same offset twice for the same deadline.
                    </p>
                    <a href="{{ route('user.profile.edit') }}#whatsapp-settings" class="btn btn-soft-primary">
                        <i class="bi bi-whatsapp me-1"></i> Open WhatsApp settings
                    </a>
                </div>
            </div>

            @include('profile.partials.whatsapp-settings', ['user' => $user])
        </div>
    </div>
@endsection
