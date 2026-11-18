@extends('layouts.app')

@section('title', 'Calendar')
@section('page-title', 'Calendar')
@section('page-subtitle', 'Your month at a glance.')

@section('content')
    <div class="page-heading">
        <div>
            <h2 id="calendarMonthLabel">{{ $month->format('F Y') }}</h2>
            <p>Select any date to see the scheduled activities for that day.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('user.calendar', ['month' => $prevMonth]) }}" id="calendarPrev" data-calendar-nav class="btn btn-light" aria-label="Previous month">
                <i class="bi bi-chevron-left"></i>
            </a>
            <a href="{{ route('user.calendar') }}" class="btn btn-light">Today</a>
            <a href="{{ route('user.calendar', ['month' => $nextMonth]) }}" id="calendarNext" data-calendar-nav class="btn btn-light" aria-label="Next month">
                <i class="bi bi-chevron-right"></i>
            </a>
            <a href="{{ route('user.schedules.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Add Schedule
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-9">
            <div class="card calendar-card">
                <div class="card-body p-0">
                    <div class="calendar-grid" id="calendarGrid">
                        @include('user.partials.calendar-grid', ['weeks' => $weeks, 'initialDate' => $initialDate])
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-3 mt-3 fs-12 text-muted">
                <span><span class="mini-dot d-inline-block me-1"></span> Has activity</span>
                <span><span class="d-inline-block" style="width: 10px; height: 10px; border-radius: 3px; background: var(--dm-primary);"></span> Today</span>
                <span><span class="d-inline-block" style="width: 10px; height: 10px; border-radius: 3px; background: var(--dm-primary-soft); border: 1px solid #a5b4fc;"></span> Selected</span>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card calendar-detail">
                <div class="card-header">
                    <div>
                        <div class="detail-date" id="detailDate">Select a date</div>
                        <div class="detail-day" id="detailDay"></div>
                    </div>
                </div>
                <div class="card-body" id="detailContent">
                    <p class="fs-13 text-muted mb-0">Choose a date on the calendar to see scheduled activities.</p>
                </div>
                <div class="card-body border-top pt-3">
                    <a href="{{ route('user.schedules.create') }}" id="addForSelectedDate" class="btn btn-soft-primary w-100">
                        <i class="bi bi-plus-lg me-1"></i> Add Schedule
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            let events = @json($events);
            let initialDate = @json($initialDate);
            const createUrl = @json(route('user.schedules.create'));
            const dateTitle = document.getElementById('detailDate');
            const dateSubtitle = document.getElementById('detailDay');
            const content = document.getElementById('detailContent');
            const addButton = document.getElementById('addForSelectedDate');
            const calendarGrid = document.getElementById('calendarGrid');
            const monthLabel = document.getElementById('calendarMonthLabel');
            const prevLink = document.getElementById('calendarPrev');
            const nextLink = document.getElementById('calendarNext');

            function formatDate(dateString) {
                const date = new Date(dateString + 'T00:00:00');
                return {
                    title: date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }),
                    day: date.toLocaleDateString('en-US', { weekday: 'long' })
                };
            }

            function selectDate(dateString) {
                document.querySelectorAll('.calendar-day').forEach(function (day) {
                    day.classList.toggle('selected', day.getAttribute('data-date') === dateString);
                });

                const formatted = formatDate(dateString);
                dateTitle.textContent = formatted.title;
                dateSubtitle.textContent = formatted.day;
                addButton.href = createUrl + '?date=' + dateString;

                const dayEvents = events[dateString] || [];
                content.innerHTML = '';

                if (dayEvents.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'empty-state py-4';
                    empty.innerHTML = '<div class="empty-icon"><i class="bi bi-calendar-x"></i></div>'
                        + '<h6>No activities</h6><p>This day is free. Add a schedule to plan something meaningful.</p>';
                    content.appendChild(empty);
                    return;
                }

                const list = document.createElement('div');
                dayEvents.forEach(function (event) {
                    const item = document.createElement('div');
                    item.className = 'timeline-item';

                    const icon = document.createElement('span');
                    icon.className = 'timeline-icon';
                    icon.innerHTML = '<i class="bi bi-calendar-event"></i>';

                    const body = document.createElement('div');
                    body.className = 'flex-grow-1 min-w-0';

                    const title = document.createElement('p');
                    title.className = 'timeline-title';
                    title.textContent = event.title;

                    const meta = document.createElement('p');
                    meta.className = 'timeline-meta';
                    meta.textContent = event.time;

                    body.appendChild(title);
                    body.appendChild(meta);

                    if (event.description) {
                        const description = document.createElement('p');
                        description.className = 'timeline-meta mt-1';
                        description.textContent = event.description;
                        body.appendChild(description);
                    }

                    const edit = document.createElement('a');
                    edit.href = event.edit_url;
                    edit.className = 'btn btn-icon flex-shrink-0';
                    edit.style.cssText = 'width:30px;height:30px;';
                    edit.title = 'Edit schedule';
                    edit.innerHTML = '<i class="bi bi-pencil" style="font-size:0.75rem;"></i>';

                    item.appendChild(icon);
                    item.appendChild(body);
                    item.appendChild(edit);
                    list.appendChild(item);
                });

                content.appendChild(list);
            }

            function bindCalendarDays() {
                calendarGrid.querySelectorAll('.calendar-day').forEach(function (day) {
                    day.addEventListener('click', function () {
                        selectDate(day.getAttribute('data-date'));
                    });
                });
            }

            async function loadMonth(url) {
                calendarGrid.setAttribute('aria-busy', 'true');

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
                    calendarGrid.innerHTML = data.grid_html;
                    monthLabel.textContent = data.month_label;
                    prevLink.href = data.prev_url;
                    nextLink.href = data.next_url;
                    events = data.events;
                    initialDate = data.initial_date;
                    bindCalendarDays();
                    selectDate(initialDate);
                    history.pushState({ calendar: true }, '', url);
                } catch (error) {
                    window.location.href = url;
                } finally {
                    calendarGrid.removeAttribute('aria-busy');
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

            bindCalendarDays();
            selectDate(initialDate);
        })();
    </script>
@endpush
