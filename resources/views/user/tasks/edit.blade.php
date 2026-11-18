@extends('layouts.app')

@section('title', 'Edit Task')
@section('page-title', 'My Tasks')
@section('page-subtitle', 'Update the details of your task.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Edit Task</h2>
            <p>{{ $task->title }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('user.tasks.show', $task->id) }}" class="btn btn-light">
                <i class="bi bi-eye me-1"></i> View
            </a>
            <a href="{{ route('user.tasks.index') }}" class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5>Task Details</h5>
                    <span class="badge badge-soft-primary">Editing #{{ $task->id }}</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('user.tasks.update', $task->id) }}" novalidate>
                        @csrf
                        @method('PUT')
                        @include('user.tasks._form', ['task' => $task, 'categories' => $categories])

                        <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Save Changes
                            </button>
                            <a href="{{ route('user.tasks.show', $task->id) }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Task information</h6>
                    <div class="d-flex justify-content-between py-2 border-bottom fs-13">
                        <span class="text-muted">Created</span>
                        <span class="fw-600">{{ $task->created_at->format('M j, Y') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom fs-13">
                        <span class="text-muted">Last updated</span>
                        <span class="fw-600">{{ $task->updated_at->format('M j, Y') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 fs-13">
                        <span class="text-muted">Current status</span>
                        <x-status-badge :status="$task->status" />
                    </div>
                    <div class="d-flex justify-content-between py-2 fs-13">
                        <span class="text-muted">Priority</span>
                        <x-priority-badge :priority="$task->priority" />
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
