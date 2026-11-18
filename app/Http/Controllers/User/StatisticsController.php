<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $base = Task::ownedBy($userId);

        $totalTasks = (clone $base)->count();
        $completedTasks = (clone $base)->where('status', 'completed')->count();
        $pendingTasks = (clone $base)->where('status', 'todo')->count();
        $inProgressTasks = (clone $base)->where('status', 'in_progress')->count();
        $overdueTasks = (clone $base)
            ->where('status', '!=', 'completed')
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->count();

        $completionRate = $totalTasks > 0
            ? (int) round(($completedTasks / $totalTasks) * 100)
            : 0;

        // Completed tasks per day for the last 14 days.
        $chartStart = now()->subDays(13)->startOfDay();
        $completedByDate = (clone $base)
            ->where('status', 'completed')
            ->where('updated_at', '>=', $chartStart)
            ->get()
            ->groupBy(fn (Task $task) => $task->updated_at->toDateString())
            ->map->count();

        $dateLabels = [];
        $completedSeries = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateLabels[] = $date->format('M j');
            $completedSeries[] = $completedByDate->get($date->toDateString(), 0);
        }

        $priorityCounts = [
            'low' => (clone $base)->where('priority', 'low')->count(),
            'medium' => (clone $base)->where('priority', 'medium')->count(),
            'high' => (clone $base)->where('priority', 'high')->count(),
        ];

        $statusCounts = [
            'todo' => $pendingTasks,
            'in_progress' => $inProgressTasks,
            'completed' => $completedTasks,
        ];

        $categoryCounts = (clone $base)
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(6)
            ->pluck('total', 'category');

        $tasksThisWeek = (clone $base)
            ->where('status', 'completed')
            ->where('updated_at', '>=', now()->startOfWeek())
            ->count();

        return view('user.statistics', compact(
            'totalTasks',
            'completedTasks',
            'pendingTasks',
            'inProgressTasks',
            'overdueTasks',
            'completionRate',
            'dateLabels',
            'completedSeries',
            'priorityCounts',
            'statusCounts',
            'categoryCounts',
            'tasksThisWeek'
        ));
    }
}
