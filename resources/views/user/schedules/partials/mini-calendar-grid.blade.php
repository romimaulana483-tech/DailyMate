@foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
    <div class="calendar-weekday">{{ $weekday }}</div>
@endforeach

@foreach ($calendarDays as $day)
    <a href="{{ route('user.schedules.index', ['date' => $day->toDateString(), 'month' => $day->format('Y-m')]) }}"
       class="calendar-day {{ $day->month !== $calendarMonth->month ? 'outside' : '' }} {{ $day->isToday() ? 'today' : '' }} {{ $selectedDate && $selectedDate->isSameDay($day) ? 'selected' : '' }} {{ isset($monthSchedules[$day->toDateString()]) ? 'has-event' : '' }}"
       title="{{ $day->format('M j, Y') }}">
        <span class="day-number">{{ $day->day }}</span>
        @if (isset($monthSchedules[$day->toDateString()]))
            <span class="mini-dot d-block mx-auto"></span>
        @endif
    </a>
@endforeach
