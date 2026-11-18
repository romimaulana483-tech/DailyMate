@extends('layouts.app')

@section('title', 'Feedback Management')
@section('page-title', 'Feedback Management')
@section('page-subtitle', 'Review, categorise, and respond to user feedback.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Feedback Management</h2>
            <p>Feedback content is available here because handling user submissions is an administrative task.</p>
        </div>
    </div>

    <div class="row g-3 row-cols-2 row-cols-lg-4 mb-4">
        @foreach ($statuses as $value => $label)
            <div class="col">
                <x-stat-card :label="$label" :value="$counts->get($value, 0)" icon="bi-chat-square-text" :tone="$value === 'resolved' ? 'success' : ($value === 'pending' ? 'warning' : 'primary')" />
            </div>
        @endforeach
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.feedback.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="search" class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="Subject or message">
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All categories</option>
                        @foreach ($categories as $value => $label)
                            <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                    <a href="{{ route('admin.feedback.index') }}" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center gap-2">
            <h5>Feedback list</h5>
            <span class="badge badge-soft-primary">{{ $feedbacks->total() }} results</span>
        </div>
        <div class="card-body p-0">
            @if ($feedbacks->isEmpty())
                <div class="empty-state py-5">
                    <i class="bi bi-chat-square-text"></i>
                    <p class="mb-0">No feedback matches these filters.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Subject</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($feedbacks as $feedback)
                                <tr>
                                    <td>
                                        <span class="fw-600 fs-13">{{ $feedback->user?->name ?? 'Deleted user' }}</span>
                                        <span class="d-block text-muted fs-12">{{ $feedback->user?->email }}</span>
                                    </td>
                                    <td class="fs-13">{{ \Illuminate\Support\Str::limit($feedback->subject, 45) }}</td>
                                    <td><span class="badge badge-soft-primary">{{ $feedback->categoryLabel() }}</span></td>
                                    <td>
                                        <span class="badge badge-soft-{{ $feedback->status === 'resolved' ? 'success' : ($feedback->status === 'pending' ? 'warning' : 'primary') }}">
                                            {{ $feedback->statusLabel() }}
                                        </span>
                                    </td>
                                    <td class="fs-12 text-muted">{{ $feedback->created_at->format('M j, Y H:i') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.feedback.show', $feedback->id) }}" class="btn btn-icon" style="width:32px;height:32px;" aria-label="Open feedback">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $feedbacks->links() }}</div>
            @endif
        </div>
    </div>
@endsection
