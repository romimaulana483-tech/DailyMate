<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Ownership is always enforced through the authenticated user's
     * relationship, never through a bare Task::find().
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $search = $this->queryString($request, 'q');
        $query = $user->tasks()->search($search);

        $status = $this->queryString($request, 'status');
        if (filled($status) && array_key_exists($status, Task::statusLabels())) {
            $query->where('status', $status);
        }

        $priority = $this->queryString($request, 'priority');
        if (filled($priority) && array_key_exists($priority, Task::priorityLabels())) {
            $query->where('priority', $priority);
        }

        $selectedCategory = $this->queryString($request, 'category');
        if (filled($selectedCategory)) {
            $query->where('category', $selectedCategory);
        }

        $sort = $this->queryString($request, 'sort', 'latest');
        match ($sort) {
            'oldest' => $query->oldest(),
            'deadline' => $query->orderByRaw('deadline IS NULL, deadline ASC'),
            'priority' => $query->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")->latest(),
            'title' => $query->orderBy('title'),
            default => $query->latest(),
        };

        $tasks = $query->paginate(9)->withQueryString();

        $categories = $user->tasks()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('user.tasks.index', compact('tasks', 'categories', 'sort', 'search', 'status', 'priority', 'selectedCategory'));
    }

    public function create(Request $request)
    {
        $categories = $request->user()->tasks()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('user.tasks.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateTask($request);

        $request->user()->tasks()->create($validated);

        return redirect()->route('user.tasks.index')
            ->with('success', 'Task created successfully.');
    }

    public function show(Request $request, int $id)
    {
        $task = $request->user()->tasks()->findOrFail($id);

        return view('user.tasks.show', compact('task'));
    }

    public function edit(Request $request, int $id)
    {
        $task = $request->user()->tasks()->findOrFail($id);

        $categories = $request->user()->tasks()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('user.tasks.edit', compact('task', 'categories'));
    }

    public function update(Request $request, int $id)
    {
        $task = $request->user()->tasks()->findOrFail($id);

        $validated = $this->validateTask($request);

        $task->update($validated);

        return redirect()->route('user.tasks.show', $task->id)
            ->with('success', 'Task updated successfully.');
    }

    public function updateStatus(Request $request, int $id)
    {
        $task = $request->user()->tasks()->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'in:todo,in_progress,completed'],
        ]);

        $task->update($validated);

        $message = $validated['status'] === 'completed'
            ? 'Great job! Task marked as completed.'
            : 'Task status updated.';

        return back()->with('success', $message);
    }

    public function destroy(Request $request, int $id)
    {
        $task = $request->user()->tasks()->findOrFail($id);
        $task->delete();

        return redirect()->route('user.tasks.index')
            ->with('success', 'Task deleted successfully.');
    }

    /**
     * Shared validation for creating and updating a task.
     */
    private function validateTask(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:50'],
            'priority' => ['required', 'in:low,medium,high'],
            'status' => ['required', 'in:todo,in_progress,completed'],
            'deadline' => ['nullable', 'date'],
        ]);

        $validated['category'] = filled($validated['category'] ?? null)
            ? trim($validated['category'])
            : 'General';

        return $validated;
    }
}
