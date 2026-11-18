@extends('layouts.app')

@section('title', 'Task Details')
@section('page-title', 'My Tasks')
@section('page-subtitle', 'Full details for this task.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Task Details</h2>
            <p>Task #{{ $task->id }} · created {{ $task->created_at->diffForHumans() }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('user.tasks.index') }}" class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <a href="{{ route('user.tasks.edit', $task->id) }}" class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i> Edit Task
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <x-status-badge :status="$task->status" />
                        <x-priority-badge :priority="$task->priority" />
                        <span class="category-pill"><i class="bi bi-tag"></i> {{ $task->category }}</span>
                        @if ($task->is_overdue)
                            <span class="badge badge-soft-danger"><i class="bi bi-exclamation-triangle me-1"></i>Overdue</span>
                        @endif
                    </div>

                    <h3 class="mb-3" style="font-size: 1.35rem;">{{ $task->title }}</h3>

                    <div class="divider"></div>

                    <h6 class="mb-2">Description</h6>
                    @if ($task->description)
                        <div class="fs-13 text-muted" style="line-height: 1.8;">{!! nl2br(e($task->description)) !!}</div>
                    @else
                        <p class="fs-13 text-muted mb-0">No description was added for this task.</p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Update Status</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('user.tasks.status', $task->id) }}" class="row g-2 align-items-end">
                        @csrf
                        @method('PATCH')
                        <div class="col-sm-8">
                            <label for="status" class="form-label">Task status</label>
                            <select class="form-select" id="status" name="status">
                                @foreach (\App\Models\Task::statusLabels() as $value => $label)
                                    <option value="{{ $value }}" @selected($task->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <button type="submit" class="btn btn-soft-primary w-100">
                                <i class="bi bi-arrow-repeat me-1"></i> Update
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Task Information</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-2 border-bottom fs-13">
                        <span class="text-muted">Deadline</span>
                        <span class="fw-600 text-end {{ $task->is_overdue ? 'text-danger' : '' }}">
                            @if ($task->deadline)
                                {{ $task->deadline->format('M j, Y') }}<br>
                                <span class="fw-400 text-muted">{{ $task->deadline->format('H:i') }}</span>
                            @else
                                No deadline
                            @endif
                        </span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom fs-13">
                        <span class="text-muted">Created</span>
                        <span class="fw-600">{{ $task->created_at->format('M j, Y H:i') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 fs-13">
                        <span class="text-muted">Last updated</span>
                        <span class="fw-600">{{ $task->updated_at->format('M j, Y H:i') }}</span>
                    </div>

                    <form method="POST" action="{{ route('user.tasks.destroy', $task->id) }}" class="mt-3" data-confirm="Delete this task? This action cannot be undone.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-soft-danger w-100">
                            <i class="bi bi-trash me-1"></i> Delete Task
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-2">
                        <span class="stat-icon warning" style="width: 38px; height: 38px; font-size: 1rem;"><i class="bi bi-stars"></i></span>
                        <div>
                            <h6 class="mb-1">Need help prioritising?</h6>
                            <p class="fs-12 text-muted mb-2">Ask the AI assistant how to fit this task into your day.</p>
                            <a href="{{ route('user.ai.index') }}" class="fs-12 fw-600">Open AI Assistant <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
