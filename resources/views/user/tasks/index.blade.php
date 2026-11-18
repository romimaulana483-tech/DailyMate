@extends('layouts.app')

@section('title', 'My Tasks')
@section('page-title', 'My Tasks')
@section('page-subtitle', 'Create, prioritise and complete your work.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>My Tasks</h2>
            <p>{{ $tasks->total() }} task{{ $tasks->total() === 1 ? '' : 's' }} in your workspace.</p>
        </div>
        <a href="{{ route('user.tasks.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add New Task
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('user.tasks.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-lg-3">
                    <label for="q" class="form-label">Search</label>
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" class="form-control" id="q" name="q" value="{{ $search }}" placeholder="Title, description, category">
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach (\App\Models\Task::statusLabels() as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label for="priority" class="form-label">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="">All priorities</option>
                        @foreach (\App\Models\Task::priorityLabels() as $value => $label)
                            <option value="{{ $value }}" @selected($priority === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected($selectedCategory === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-1">
                    <label for="sort" class="form-label">Sort</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="latest" @selected($sort === 'latest')>Latest</option>
                        <option value="oldest" @selected($sort === 'oldest')>Oldest</option>
                        <option value="deadline" @selected($sort === 'deadline')>Deadline</option>
                        <option value="priority" @selected($sort === 'priority')>Priority</option>
                        <option value="title" @selected($sort === 'title')>Title</option>
                    </select>
                </div>
                <div class="col-12 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                    <a href="{{ route('user.tasks.index') }}" class="btn btn-light" data-bs-toggle="tooltip" title="Reset filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>Task List</h5>
            <span class="fs-12 text-muted">Showing {{ $tasks->firstItem() ?? 0 }}–{{ $tasks->lastItem() ?? 0 }} of {{ $tasks->total() }}</span>
        </div>

        @if ($tasks->isEmpty())
            <div class="card-body">
                <x-empty-state icon="bi-check2-square" title="No tasks found" message="Try changing your filters, or create a new task to get started.">
                    <a href="{{ route('user.tasks.create') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Add New Task
                    </a>
                </x-empty-state>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th class="d-none d-xl-table-cell">Category</th>
                            <th class="d-none d-sm-table-cell">Priority</th>
                            <th>Status</th>
                            <th class="d-none d-lg-table-cell">Deadline</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tasks as $task)
                            <tr>
                                <td style="min-width: 240px;">
                                    <a href="{{ route('user.tasks.show', $task->id) }}" class="task-title-cell">{{ $task->title }}</a>
                                    @if ($task->description)
                                        <span class="task-meta d-block text-truncate" style="max-width: 320px;">{{ $task->description }}</span>
                                    @endif
                                    @if ($task->deadline)
                                        <span class="task-meta d-lg-none {{ $task->is_overdue ? 'text-danger' : '' }}">
                                            <i class="bi bi-clock me-1"></i>{{ $task->deadline->format('M j, H:i') }}{{ $task->is_overdue ? ' · Overdue' : '' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="d-none d-xl-table-cell"><span class="category-pill"><i class="bi bi-tag"></i> {{ $task->category }}</span></td>
                                <td class="d-none d-sm-table-cell"><x-priority-badge :priority="$task->priority" /></td>
                                <td><x-status-badge :status="$task->status" /></td>
                                <td class="d-none d-lg-table-cell">
                                    @if ($task->deadline)
                                        <span class="fs-13 {{ $task->is_overdue ? 'text-danger fw-600' : '' }}">
                                            {{ $task->deadline->format('M j, Y') }}
                                            <span class="d-block task-meta">{{ $task->deadline->format('H:i') }}@if ($task->is_overdue) · Overdue @endif</span>
                                        </span>
                                    @else
                                        <span class="task-meta">No deadline</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="{{ route('user.tasks.show', $task->id) }}" class="btn btn-icon" style="width: 32px; height: 32px;" data-bs-toggle="tooltip" title="View details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('user.tasks.edit', $task->id) }}" class="btn btn-icon" style="width: 32px; height: 32px;" data-bs-toggle="tooltip" title="Edit task">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="{{ route('user.tasks.status', $task->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $task->status === 'completed' ? 'in_progress' : 'completed' }}">
                                            <button type="submit" class="btn btn-icon {{ $task->status === 'completed' ? '' : 'text-success' }}" style="width: 32px; height: 32px;"
                                                    data-bs-toggle="tooltip" title="{{ $task->status === 'completed' ? 'Reopen task' : 'Mark completed' }}">
                                                <i class="bi {{ $task->status === 'completed' ? 'bi-arrow-counterclockwise' : 'bi-check2' }}"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('user.tasks.destroy', $task->id) }}" data-confirm="Delete this task? This action cannot be undone.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-icon text-danger" style="width: 32px; height: 32px;" data-bs-toggle="tooltip" title="Delete task">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($tasks->hasPages())
                <div class="card-body border-top pt-3">
                    {{ $tasks->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
