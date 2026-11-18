@foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $weekday)
    <div class="calendar-weekday">{{ $weekday }}</div>
@endforeach

@foreach ($weeks as $week)
    @foreach ($week as $day)
        <button type="button"
                class="calendar-day {{ $day['in_month'] ? '' : 'outside' }} {{ $day['is_today'] ? 'today' : '' }} {{ $day['date'] === $initialDate ? 'selected' : '' }}"
                data-date="{{ $day['date'] }}"
                aria-label="{{ \Carbon\Carbon::parse($day['date'])->format('F j, Y') }} — {{ $day['events']->count() }} schedule(s)">
            <span class="day-number">{{ $day['day'] }}</span>

            @foreach ($day['events']->take(2) as $event)
                <span class="event-dot"><i class="bi bi-clock" style="font-size: 0.6rem;"></i> {{ $event['time'] }}</span>
            @endforeach

            @if ($day['events']->count() > 2)
                <span class="event-dot more">+{{ $day['events']->count() - 2 }} more</span>
            @endif

            @if ($day['events']->isNotEmpty())
                <span class="mobile-dot"></span>
            @endif
        </button>
    @endforeach
@endforeach
