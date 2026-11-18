@extends('layouts.app')

@section('title', 'User Details')
@section('page-title', 'User Management')
@section('page-subtitle', 'Account information and audit history.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Account Details</h2>
            <p>Member #{{ $user->id }} · account-level information only.</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn btn-light">
            <i class="bi bi-arrow-left me-1"></i> Back to users
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="profile-cover"></div>
                <div class="profile-head">
                    <span class="avatar avatar-lg">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-3">
                        <div>
                            <h3 class="mb-1" style="font-size: 1.25rem;">{{ $user->name }}</h3>
                            <div class="text-muted fs-13">{{ $user->email }}</div>
                        </div>
                        <span class="badge {{ $user->status === 'active' ? 'badge-soft-success' : 'badge-soft-warning' }}">
                            {{ ucfirst($user->status) }}
                        </span>
                    </div>
                </div>

                <div class="card-body border-top">
                    <h6 class="mb-3">Account Information</h6>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="fs-12 text-muted">Account ID</div>
                            <div class="fw-600 fs-13">#{{ $user->id }}</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="fs-12 text-muted">Role</div>
                            <div class="fw-600 fs-13">User</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="fs-12 text-muted">Registered</div>
                            <div class="fw-600 fs-13">{{ $user->created_at->format('F j, Y · H:i') }}</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="fs-12 text-muted">Last account update</div>
                            <div class="fw-600 fs-13">{{ $user->updated_at->format('F j, Y · H:i') }}</div>
                        </div>
                    </div>
                </div>

                <div class="card-body border-top d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.users.toggle-status', $user->id) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn {{ $user->status === 'active' ? 'btn-soft-danger' : 'btn-soft-primary' }}">
                            <i class="bi {{ $user->status === 'active' ? 'bi-person-dash' : 'bi-person-check' }} me-1"></i>
                            {{ $user->status === 'active' ? 'Deactivate Account' : 'Activate Account' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}" data-confirm="Delete {{ $user->name }}? This permanently removes the account and all associated private data.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-soft-danger">
                            <i class="bi bi-trash me-1"></i> Delete Account
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="alert alert-info">
                <i class="bi bi-shield-lock-fill mt-1"></i>
                <div>
                    <strong>Private data is protected.</strong>
                    This administrator view never displays the user's tasks, schedules, personal notes or AI conversation history.
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Account Audit History</h5>
                    <span class="badge badge-soft-primary">{{ $logs->count() }} recent</span>
                </div>
                <div class="card-body">
                    @forelse ($logs as $log)
                        <div class="timeline-item">
                            <span class="timeline-icon"><i class="bi bi-clock-history"></i></span>
                            <div class="flex-grow-1">
                                <p class="timeline-title">{{ $log->action }}</p>
                                <p class="timeline-meta">
                                    by {{ $log->admin?->name ?? 'Deleted admin' }} · {{ $log->created_at->format('M j, Y H:i') }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4">
                            <p class="fs-13 text-muted mb-0">No administrative actions recorded for this account yet.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
