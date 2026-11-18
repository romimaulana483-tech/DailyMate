<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatHistory;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        // Aggregate account data only. No private user content is queried.
        $base = User::where('role', 'user');

        $totalUsers = (clone $base)->count();
        $activeUsers = (clone $base)->where('status', 'active')->count();
        $inactiveUsers = (clone $base)->where('status', 'inactive')->count();
        $newUsers = (clone $base)->where('created_at', '>=', now()->startOfMonth())->count();

        $totalTasks = Task::count();
        $totalSchedules = Schedule::count();
        $totalChats = ChatHistory::count();

        $recentUsers = (clone $base)->latest()->limit(5)->get();

        // Registrations over the last six months for the mini chart.
        $start = now()->subMonths(5)->startOfMonth();
        $registrations = (clone $base)
            ->where('created_at', '>=', $start)
            ->get()
            ->groupBy(fn (User $user) => $user->created_at->format('Y-m'))
            ->map->count();

        $labels = [];
        $series = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->format('M Y');
            $series[] = $registrations->get($month->format('Y-m'), 0);
        }

        return view('admin.dashboard', compact(
            'totalUsers',
            'activeUsers',
            'inactiveUsers',
            'newUsers',
            'totalTasks',
            'totalSchedules',
            'totalChats',
            'recentUsers',
            'labels',
            'series'
        ));
    }
}
