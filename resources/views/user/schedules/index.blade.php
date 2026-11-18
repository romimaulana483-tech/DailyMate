@extends('layouts.app')

@section('title', 'My Schedule')
@section('page-title', 'My Schedule')
@section('page-subtitle', 'Plan your activities by date and time.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>My Schedule</h2>
            <p>
                @if ($selectedDate)
                    Showing schedules for {{ $selectedDate->format('l, F j, Y') }}.
                @else
                    All of your planned activities in one place.
                @endif
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('user.calendar') }}" class="btn btn-light">
                <i class="bi bi-calendar3 me-1"></i> Calendar View
            </a>
            <a href="{{ route('user.schedules.create', $selectedDate ? ['date' => $selectedDate->toDateString()] : []) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Add Schedule
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('user.schedules.index') }}" class="row g-2 align-items-end">
                        <div class="col-sm-6">
                            <label for="date" class="form-label">Filter by date</label>
                            <input type="date" class="form-control" id="date" name="date" value="{{ $selectedDate?->toDateString() }}">
                        </div>
                        <div class="col-sm-6 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Show schedules</button>
                            <a href="{{ route('user.schedules.index') }}" class="btn btn-light">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>{{ $selectedDate ? $selectedDate->format('F j, Y') : 'Schedule List' }}</h5>
                    <span class="fs-12 text-muted">{{ $schedules->total() }} schedule{{ $schedules->total() === 1 ? '' : 's' }}</span>
                </div>

                @if ($schedules->isEmpty())
                    <div class="card-body">
                        <x-empty-state icon="bi-calendar-x" title="No schedules found" message="Plan your first activity, or clear the date filter to see everything.">
                            <a href="{{ route('user.schedules.create') }}" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-lg me-1"></i> Add Schedule
                            </a>
                        </x-empty-state>
                    </div>
                @else
                    <div class="card-body">
                        @foreach ($schedules as $schedule)
                            <div class="schedule-item">
                                <div class="schedule-time">
                                    {{ substr($schedule->start_time, 0, 5) }}
                                    <span>{{ substr($schedule->end_time, 0, 5) }}</span>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <h6 class="mb-0" style="font-size: 0.9rem;">{{ $schedule->title }}</h6>
                                        @if ($schedule->schedule_date->isToday())
                                            <span class="badge badge-soft-primary">Today</span>
                                        @elseif ($schedule->schedule_date->isTomorrow())
                                            <span class="badge badge-soft-warning">Tomorrow</span>
                                        @endif
                                    </div>
                                    <div class="fs-12 text-muted mt-1">
                                        <i class="bi bi-calendar3 me-1"></i>{{ $schedule->schedule_date->format('D, M j, Y') }}
                                    </div>
                                    @if ($schedule->description)
                                        <p class="fs-12 text-muted mb-0 mt-1">{{ $schedule->description }}</p>
                                    @endif
                                </div>
                                <div class="d-flex gap-1 flex-shrink-0">
                                    <a href="{{ route('user.schedules.edit', $schedule->id) }}" class="btn btn-icon" style="width: 32px; height: 32px;" data-bs-toggle="tooltip" title="Edit schedule">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('user.schedules.destroy', $schedule->id) }}" data-confirm="Delete this schedule?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-icon text-danger" style="width: 32px; height: 32px;" data-bs-toggle="tooltip" title="Delete schedule">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($schedules->hasPages())
                        <div class="card-body border-top pt-3">
                            {{ $schedules->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 id="miniCalendarMonthLabel">{{ $calendarMonth->format('F Y') }}</h5>
                    <div class="d-flex gap-1">
                        <a href="{{ route('user.schedules.index', ['month' => $calendarMonth->copy()->subMonth()->format('Y-m')]) }}" id="miniCalendarPrev" data-mini-calendar-nav class="btn btn-icon" style="width: 30px; height: 30px;" aria-label="Previous month">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                        <a href="{{ route('user.schedules.index', ['month' => $calendarMonth->copy()->addMonth()->format('Y-m')]) }}" id="miniCalendarNext" data-mini-calendar-nav class="btn btn-icon" style="width: 30px; height: 30px;" aria-label="Next month">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div class="mini-calendar">
                        <div class="calendar-grid" id="miniCalendarGrid">
                            @include('user.schedules.partials.mini-calendar-grid', [
                                'calendarDays' => $calendarDays,
                                'calendarMonth' => $calendarMonth,
                                'selectedDate' => $selectedDate,
                                'monthSchedules' => $monthSchedules,
                            ])
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 justify-content-center fs-12 text-muted pt-2">
                        <span class="mini-dot d-inline-block"></span> Has scheduled activity
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="stat-icon success" style="width: 38px; height: 38px; font-size: 1rem;"><i class="bi bi-lightbulb"></i></span>
                        <h6 class="mb-0">Scheduling tip</h6>
                    </div>
                    <p class="fs-13 text-muted mb-0">
                        Leave a 15-minute buffer between activities. It keeps your day realistic and gives you room
                        for traffic, overruns or a quick recharge.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const grid = document.getElementById('miniCalendarGrid');
            const monthLabel = document.getElementById('miniCalendarMonthLabel');
            const prevLink = document.getElementById('miniCalendarPrev');
            const nextLink = document.getElementById('miniCalendarNext');

            async function loadMonth(url) {
                grid.setAttribute('aria-busy', 'true');

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });

                    if (!response.ok) {
                        window.location.href = url;
                        return;
                    }

                    const data = await response.json();
                    grid.innerHTML = data.grid_html;
                    monthLabel.textContent = data.month_label;
                    prevLink.href = data.prev_url;
                    nextLink.href = data.next_url;
                    history.pushState({ miniCalendar: true }, '', url);
                } catch (error) {
                    window.location.href = url;
                } finally {
                    grid.removeAttribute('aria-busy');
                }
            }

            [prevLink, nextLink].forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    loadMonth(link.href);
                });
            });

            window.addEventListener('popstate', function () {
                loadMonth(window.location.href);
            });
        })();
    </script>
@endpush
