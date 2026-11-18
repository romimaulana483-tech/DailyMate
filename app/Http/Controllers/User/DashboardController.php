<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;

        // Core counters for the stat cards.
        $totalTasks = Task::ownedBy($userId)->count();
        $completedTasks = Task::ownedBy($userId)->where('status', 'completed')->count();
        $pendingTasks = Task::ownedBy($userId)->where('status', 'todo')->count();
        $inProgressTasks = Task::ownedBy($userId)->where('status', 'in_progress')->count();
        $overdueTasks = Task::ownedBy($userId)
            ->where('status', '!=', 'completed')
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->count();

        $completionRate = $totalTasks > 0
            ? (int) round(($completedTasks / $totalTasks) * 100)
            : 0;

        $upcomingSchedules = Schedule::ownedBy($userId)
            ->where('schedule_date', '>=', today())
            ->orderBy('schedule_date')
            ->orderBy('start_time')
            ->limit(5)
            ->get();

        $recentTasks = Task::ownedBy($userId)
            ->latest()
            ->limit(5)
            ->get();

        $todayTasks = Task::ownedBy($userId)
            ->whereDate('deadline', today())
            ->where('status', '!=', 'completed')
            ->count();

        // Seven-day productivity chart data (completed vs created).
        $chartStart = now()->subDays(6)->startOfDay();
        $labels = [];
        $completedSeries = [];
        $createdSeries = [];

        $completedByDate = Task::ownedBy($userId)
            ->where('status', 'completed')
            ->where('updated_at', '>=', $chartStart)
            ->get()
            ->groupBy(fn (Task $task) => $task->updated_at->toDateString())
            ->map->count();

        $createdByDate = Task::ownedBy($userId)
            ->where('created_at', '>=', $chartStart)
            ->get()
            ->groupBy(fn (Task $task) => $task->created_at->toDateString())
            ->map->count();

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('D, M j');
            $completedSeries[] = $completedByDate->get($date->toDateString(), 0);
            $createdSeries[] = $createdByDate->get($date->toDateString(), 0);
        }

        $greeting = match (true) {
            $request->user() && now()->hour < 12 => 'Good morning',
            now()->hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        return view('user.dashboard', compact(
            'user',
            'totalTasks',
            'completedTasks',
            'pendingTasks',
            'inProgressTasks',
            'overdueTasks',
            'completionRate',
            'upcomingSchedules',
            'recentTasks',
            'todayTasks',
            'labels',
            'completedSeries',
            'createdSeries',
            'greeting'
        ));
    }
}
