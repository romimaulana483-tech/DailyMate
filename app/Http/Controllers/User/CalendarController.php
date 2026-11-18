<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $month = now()->startOfMonth();
        $monthFilter = $this->queryString($request, 'month');
        if (filled($monthFilter) && preg_match('/^\d{4}-\d{2}$/', $monthFilter)) {
            try {
                $month = Carbon::createFromFormat('Y-m-d', $monthFilter.'-01')->startOfMonth();
            } catch (\Exception $e) {
                $month = now()->startOfMonth();
            }
        }

        $gridStart = $month->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        // Only the authenticated user's schedules are loaded.
        $schedules = $user->schedules()
            ->whereBetween('schedule_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->orderBy('start_time')
            ->get();

        $events = $schedules->groupBy(fn ($schedule) => $schedule->schedule_date->toDateString())
            ->map(fn ($items) => $items->map(fn ($schedule) => [
                'id' => $schedule->id,
                'title' => $schedule->title,
                'time' => $schedule->time_range,
                'description' => $schedule->description,
                'edit_url' => route('user.schedules.edit', $schedule->id),
            ])->values());

        $days = [];
        for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay()) {
            $days[] = [
                'date' => $day->toDateString(),
                'day' => $day->day,
                'in_month' => $day->month === $month->month,
                'is_today' => $day->isToday(),
                'events' => $events->get($day->toDateString(), collect()),
            ];
        }

        // Split into calendar weeks for the Blade grid.
        $weeks = array_chunk($days, 7);
        $initialDate = $month->isCurrentMonth() ? today()->toDateString() : $month->toDateString();
        $prevMonth = $month->copy()->subMonth()->format('Y-m');
        $nextMonth = $month->copy()->addMonth()->format('Y-m');

        // Month navigation updates only the calendar panel, without a full
        // page reload. The normal HTML response is still used as a fallback.
        if ($request->expectsJson()) {
            return response()->json([
                'month_label' => $month->format('F Y'),
                'grid_html' => view('user.partials.calendar-grid', [
                    'weeks' => $weeks,
                    'initialDate' => $initialDate,
                ])->render(),
                'events' => $events,
                'initial_date' => $initialDate,
                'prev_url' => route('user.calendar', ['month' => $prevMonth]),
                'next_url' => route('user.calendar', ['month' => $nextMonth]),
            ]);
        }

        return view('user.calendar', [
            'month' => $month,
            'weeks' => $weeks,
            'events' => $events,
            'initialDate' => $initialDate,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'currentMonth' => now()->format('Y-m'),
        ]);
    }
}
