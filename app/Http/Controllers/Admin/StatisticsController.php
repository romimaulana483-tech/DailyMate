<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatHistory;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;

class StatisticsController extends Controller
{
    public function index()
    {
        $users = User::where('role', 'user');
        $totalUsers = (clone $users)->count();
        $activeUsers = (clone $users)->where('status', 'active')->count();
        $inactiveUsers = (clone $users)->where('status', 'inactive')->count();

        $totalTasks = Task::count();
        $totalSchedules = Schedule::count();
        $totalChats = ChatHistory::count();

        // Twelve-month registration trend.
        $start = now()->subMonths(11)->startOfMonth();
        $registrations = (clone $users)
            ->where('created_at', '>=', $start)
            ->get()
            ->groupBy(fn (User $user) => $user->created_at->format('Y-m'))
            ->map->count();

        $labels = [];
        $series = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->format('M Y');
            $series[] = $registrations->get($month->format('Y-m'), 0);
        }

        return view('admin.statistics', compact(
            'totalUsers',
            'activeUsers',
            'inactiveUsers',
            'totalTasks',
            'totalSchedules',
            'totalChats',
            'labels',
            'series'
        ));
    }
}
