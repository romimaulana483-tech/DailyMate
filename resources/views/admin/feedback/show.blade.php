@extends('layouts.app')

@section('title', 'Feedback Detail')
@section('page-title', 'Feedback Detail')
@section('page-subtitle', 'Read the submission and update its status or response.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Feedback #{{ $feedback->id }}</h2>
            <p>Submitted {{ $feedback->created_at->format('M j, Y H:i') }}</p>
        </div>
        <a href="{{ route('admin.feedback.index') }}" class="btn btn-light">
            <i class="bi bi-arrow-left me-1"></i> Back to feedback
        </a>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card mb-4">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5>{{ $feedback->subject }}</h5>
                    <div class="d-flex gap-2">
                        <span class="badge badge-soft-primary">{{ $feedback->categoryLabel() }}</span>
                        <span class="badge badge-soft-{{ $feedback->status === 'resolved' ? 'success' : ($feedback->status === 'pending' ? 'warning' : 'primary') }}">{{ $feedback->statusLabel() }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                        <span class="avatar avatar-sm">{{ mb_substr($feedback->user?->name ?? '?', 0, 1) }}</span>
                        <div>
                            <span class="fw-600 fs-13 d-block">{{ $feedback->user?->name ?? 'Deleted user' }}</span>
                            <span class="text-muted fs-12">{{ $feedback->user?->email }}</span>
                        </div>
                    </div>
                    <p class="mb-0" style="white-space: pre-line;">{{ $feedback->message }}</p>
                </div>
            </div>

            @if ($feedback->admin_response)
                <div class="card">
                    <div class="card-header">
                        <h5>Current response</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2" style="white-space: pre-line;">{{ $feedback->admin_response }}</p>
                        <span class="fs-12 text-muted">
                            {{ $feedback->responder?->name ?? 'Administrator' }} · {{ $feedback->responded_at?->format('M j, Y H:i') }}
                        </span>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-5">
            <div class="card">
                <div class="card-header">
                    <h5>Manage feedback</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.feedback.update', $feedback->id) }}" novalidate>
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $feedback->status) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4">
                            <label for="admin_response" class="form-label">Response to user</label>
                            <textarea class="form-control @error('admin_response') is-invalid @enderror"
                                      id="admin_response" name="admin_response" rows="7" maxlength="5000"
                                      placeholder="Write a clear response. The user will see it in their feedback history.">{{ old('admin_response', $feedback->admin_response) }}</textarea>
                            @error('admin_response')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Save Feedback
                        </button>
                    </form>
                </div>
            </div>

            <div class="alert alert-light border mt-4 mb-0 fs-13">
                <i class="bi bi-shield-lock me-1"></i>
                Feedback is private to the submitting user. Do not share it with other users.
            </div>
        </div>
    </div>
@endsection
