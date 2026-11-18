@extends('layouts.app')

@section('title', 'User Management')
@section('page-title', 'User Management')
@section('page-subtitle', 'Manage member accounts and access.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>User Management</h2>
            <p>View account details, enable or disable access, and remove accounts when required.</p>
        </div>
    </div>

    <div class="alert alert-info mb-4">
        <i class="bi bi-shield-lock-fill mt-1"></i>
        <div>
            <strong>Privacy policy.</strong>
            This page only shows account-level information. Personal tasks, schedules and AI conversations are private
            and cannot be accessed by administrators.
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-6">
                    <label for="q" class="form-label">Search users</label>
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" class="form-control" id="q" name="q" value="{{ $search }}" placeholder="Search by name or email">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <label for="status" class="form-label">Account status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        <option value="active" @selected($status === 'active')>Active</option>
                        <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-light" data-bs-toggle="tooltip" title="Reset filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>Registered Members</h5>
            <span class="fs-12 text-muted">{{ $users->total() }} account{{ $users->total() === 1 ? '' : 's' }}</span>
        </div>

        @if ($users->isEmpty())
            <div class="card-body">
                <x-empty-state icon="bi-people" title="No users found" message="Try a different search term or clear the status filter." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 70px;" class="d-none d-sm-table-cell">ID</th>
                            <th>Name</th>
                            <th class="d-none d-md-table-cell">Email</th>
                            <th>Status</th>
                            <th class="d-none d-lg-table-cell">Registered</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td class="task-meta d-none d-sm-table-cell">#{{ $user->id }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar avatar-sm avatar-soft">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                        <div class="min-w-0">
                                            <span class="fw-600 fs-13 d-block text-truncate" style="max-width: 170px;">{{ $user->name }}</span>
                                            <span class="d-md-none task-meta d-block text-truncate" style="max-width: 170px;">{{ $user->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="fs-13 text-muted d-none d-md-table-cell">{{ $user->email }}</td>
                                <td>
                                    <span class="badge {{ $user->status === 'active' ? 'badge-soft-success' : 'badge-soft-warning' }}">
                                        <i class="bi {{ $user->status === 'active' ? 'bi-check-circle' : 'bi-pause-circle' }} me-1"></i>
                                        {{ ucfirst($user->status) }}
                                    </span>
                                </td>
                                <td class="fs-13 text-muted d-none d-lg-table-cell">{{ $user->created_at->format('M j, Y') }}</td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="{{ route('admin.users.show', $user->id) }}" class="btn btn-icon" style="width: 32px; height: 32px;" data-bs-toggle="tooltip" title="View account">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.users.toggle-status', $user->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-icon" style="width: 32px; height: 32px;" data-bs-toggle="tooltip" title="{{ $user->status === 'active' ? 'Deactivate account' : 'Activate account' }}">
                                                <i class="bi {{ $user->status === 'active' ? 'bi-person-dash' : 'bi-person-check' }}"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}" data-confirm="Delete {{ $user->name }}? This permanently removes the account and all associated private data.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-icon text-danger" style="width: 32px; height: 32px;" data-bs-toggle="tooltip" title="Delete account">
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

            @if ($users->hasPages())
                <div class="card-body border-top pt-3">
                    {{ $users->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
