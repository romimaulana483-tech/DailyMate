@extends('layouts.app')

@section('title', 'Feedback & Suggestions')
@section('page-title', 'Feedback & Suggestions')
@section('page-subtitle', 'Send criticism, suggestions, bug reports, or feature requests to the DailyMate team.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Feedback &amp; Suggestions</h2>
            <p>Your feedback is visible only to you and the administrator handling it.</p>
        </div>
        <span class="badge badge-soft-primary align-self-start">{{ $feedbacks->total() }} total</span>
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header">
                    <h5>Send feedback</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('user.feedback.store') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control @error('subject') is-invalid @enderror"
                                   id="subject" name="subject" value="{{ old('subject') }}" maxlength="150" required>
                            @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select @error('category') is-invalid @enderror" id="category" name="category" required>
                                @foreach ($categories as $value => $label)
                                    <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control @error('message') is-invalid @enderror"
                                      id="message" name="message" rows="6" maxlength="5000" required
                                      placeholder="Describe your feedback, the problem, or the feature you need.">{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Minimum 10 characters. Do not include passwords or API keys.</div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i> Send Feedback
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="row g-3 row-cols-2 mb-4">
                <div class="col"><x-stat-card label="Pending" :value="$feedbackCounts->get('pending', 0)" icon="bi-hourglass-split" tone="warning" /></div>
                <div class="col"><x-stat-card label="Resolved" :value="$feedbackCounts->get('resolved', 0)" icon="bi-check2-circle" tone="success" /></div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>My feedback history</h5>
                </div>
                <div class="card-body p-0">
                    @if ($feedbacks->isEmpty())
                        <div class="empty-state py-5">
                            <i class="bi bi-chat-square-text"></i>
                            <p class="mb-0">You have not sent feedback yet.</p>
                        </div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach ($feedbacks as $feedback)
                                <div class="list-group-item py-3">
                                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                                        <div class="d-flex flex-wrap gap-2">
                                            <span class="badge badge-soft-primary">{{ $feedback->categoryLabel() }}</span>
                                            <span class="badge badge-soft-{{ $feedback->status === 'resolved' ? 'success' : ($feedback->status === 'pending' ? 'warning' : 'primary') }}">
                                                {{ $feedback->statusLabel() }}
                                            </span>
                                        </div>
                                        <span class="fs-12 text-muted">{{ $feedback->created_at->format('M j, Y H:i') }}</span>
                                    </div>
                                    <h6 class="mb-1">{{ $feedback->subject }}</h6>
                                    <p class="fs-13 text-muted mb-2">{!! nl2br(e($feedback->message)) !!}</p>
                                    @if ($feedback->admin_response)
                                        <div class="alert alert-light border mb-0 fs-13">
                                            <strong><i class="bi bi-reply me-1"></i> Admin response</strong>
                                            <p class="mb-1 mt-1">{!! nl2br(e($feedback->admin_response)) !!}</p>
                                            <span class="fs-12 text-muted">{{ $feedback->responded_at?->format('M j, Y H:i') }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="p-3">{{ $feedbacks->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
