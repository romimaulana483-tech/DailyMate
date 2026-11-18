@extends('layouts.app')

@section('title', 'Create Task')
@section('page-title', 'My Tasks')
@section('page-subtitle', 'Add a new task to your workspace.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Add New Task</h2>
            <p>Capture what needs to get done and give it a clear priority.</p>
        </div>
        <a href="{{ route('user.tasks.index') }}" class="btn btn-light">
            <i class="bi bi-arrow-left me-1"></i> Back to tasks
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5>Task Details</h5>
                    <span class="badge badge-soft-primary">New</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('user.tasks.store') }}" novalidate>
                        @csrf
                        @include('user.tasks._form', ['task' => null, 'categories' => $categories])

                        <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Create Task
                            </button>
                            <a href="{{ route('user.tasks.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="stat-icon primary" style="width: 38px; height: 38px; font-size: 1rem;"><i class="bi bi-lightbulb"></i></span>
                        <h6 class="mb-0">Task tips</h6>
                    </div>
                    <ul class="fs-13 text-muted ps-3 mb-0 d-flex flex-column gap-2">
                        <li>Use a specific title that starts with a verb.</li>
                        <li>Set <strong>High</strong> priority only for truly urgent work.</li>
                        <li>Break large tasks into smaller, actionable steps.</li>
                        <li>Add a realistic deadline so reminders stay useful.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
