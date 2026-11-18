<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = $user->schedules();
        $selectedDate = null;

        $dateFilter = $this->queryString($request, 'date');
        if (filled($dateFilter) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
            $selectedDate = Carbon::createFromFormat('Y-m-d', $dateFilter);
            $query->whereDate('schedule_date', $selectedDate->toDateString());
        }

        $schedules = $query
            ->orderByRaw('CASE WHEN schedule_date >= ? THEN 0 ELSE 1 END', [today()->toDateString()])
            ->orderBy('schedule_date')
            ->orderBy('start_time')
            ->paginate(10)
            ->withQueryString();

        // Compact calendar shown next to the schedule list.
        $calendarMonth = now()->startOfMonth();
        $monthFilter = $this->queryString($request, 'month');
        if (filled($monthFilter) && preg_match('/^\d{4}-\d{2}$/', $monthFilter)) {
            try {
                $calendarMonth = Carbon::createFromFormat('Y-m-d', $monthFilter.'-01')->startOfMonth();
            } catch (\Exception $e) {
                $calendarMonth = now()->startOfMonth();
            }
        }

        $monthSchedules = $user->schedules()
            ->whereBetween('schedule_date', [
                $calendarMonth->copy()->startOfMonth()->toDateString(),
                $calendarMonth->copy()->endOfMonth()->toDateString(),
            ])
            ->get()
            ->groupBy(fn (Schedule $schedule) => $schedule->schedule_date->toDateString());

        $calendarStart = $calendarMonth->copy()->startOfWeek(Carbon::SUNDAY);
        $calendarEnd = $calendarMonth->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        $calendarDays = [];

        for ($day = $calendarStart->copy(); $day->lte($calendarEnd); $day->addDay()) {
            $calendarDays[] = $day->copy();
        }

        $prevMonth = $calendarMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $calendarMonth->copy()->addMonth()->format('Y-m');

        if ($request->expectsJson()) {
            return response()->json([
                'month_label' => $calendarMonth->format('F Y'),
                'grid_html' => view('user.schedules.partials.mini-calendar-grid', compact(
                    'calendarDays',
                    'calendarMonth',
                    'selectedDate',
                    'monthSchedules'
                ))->render(),
                'prev_url' => route('user.schedules.index', ['month' => $prevMonth]),
                'next_url' => route('user.schedules.index', ['month' => $nextMonth]),
            ]);
        }

        return view('user.schedules.index', compact(
            'schedules',
            'selectedDate',
            'calendarMonth',
            'calendarDays',
            'monthSchedules'
        ));
    }

    public function create(Request $request)
    {
        $date = $this->queryString($request, 'date');
        $defaultDate = filled($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            ? $date
            : now()->toDateString();

        return view('user.schedules.create', compact('defaultDate'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateSchedule($request);

        $request->user()->schedules()->create($validated);

        return redirect()->route('user.schedules.index')
            ->with('success', 'Schedule created successfully.');
    }

    public function edit(Request $request, int $id)
    {
        $schedule = $request->user()->schedules()->findOrFail($id);

        return view('user.schedules.edit', compact('schedule'));
    }

    public function update(Request $request, int $id)
    {
        $schedule = $request->user()->schedules()->findOrFail($id);

        $validated = $this->validateSchedule($request);

        $schedule->update($validated);

        return redirect()->route('user.schedules.index')
            ->with('success', 'Schedule updated successfully.');
    }

    public function destroy(Request $request, int $id)
    {
        $schedule = $request->user()->schedules()->findOrFail($id);
        $schedule->delete();

        return redirect()->route('user.schedules.index')
            ->with('success', 'Schedule deleted successfully.');
    }

    private function validateSchedule(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'schedule_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);
    }
}
