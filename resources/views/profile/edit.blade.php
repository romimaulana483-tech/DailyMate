@extends('layouts.app')

@section('title', 'Profile')
@section('page-title', 'Profile')
@section('page-subtitle', 'Manage your account details and password.')

@section('content')
    @php
        $isAdmin = $user->isAdmin();
        $updateRoute = $isAdmin ? 'admin.profile.update' : 'user.profile.update';
        $passwordRoute = $isAdmin ? 'admin.profile.password' : 'user.profile.password';
        $initials = collect(explode(' ', trim($user->name)))->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    @endphp

    <div class="page-heading">
        <div>
            <h2>My Profile</h2>
            <p>Keep your account information up to date.</p>
        </div>
    </div>

    <div class="card mb-4 overflow-hidden">
        <div class="profile-cover"></div>
        <div class="profile-head">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
                <div class="d-flex align-items-end gap-3">
                    <span class="avatar avatar-lg">{{ $initials }}</span>
                    <div class="pb-1">
                        <h3 class="mb-1" style="font-size: 1.25rem;">{{ $user->name }}</h3>
                        <span class="text-muted fs-13">{{ $user->email }}</span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 pb-1">
                    <span class="badge badge-soft-primary">{{ $isAdmin ? 'Administrator' : 'Member' }}</span>
                    <span class="badge {{ $user->status === 'active' ? 'badge-soft-success' : 'badge-soft-warning' }}">
                        {{ ucfirst($user->status) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Personal Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route($updateRoute) }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="name" class="form-label">Full name</label>
                            <input type="text"
                                   class="form-control @error('name') is-invalid @enderror"
                                   id="name"
                                   name="name"
                                   value="{{ old('name', $user->name) }}"
                                   required
                                   maxlength="100">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   id="email"
                                   name="email"
                                   value="{{ old('email', $user->email) }}"
                                   required
                                   maxlength="255">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">This email is used to sign in to your account.</div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route($passwordRoute) }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current password</label>
                            <input type="password"
                                   class="form-control @error('current_password') is-invalid @enderror"
                                   id="current_password"
                                   name="current_password"
                                   required
                                   autocomplete="current-password">
                            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">New password</label>
                                <input type="password"
                                       class="form-control @error('password') is-invalid @enderror"
                                       id="password"
                                       name="password"
                                       required
                                       autocomplete="new-password">
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="password_confirmation" class="form-label">Confirm new password</label>
                                <input type="password"
                                       class="form-control"
                                       id="password_confirmation"
                                       name="password_confirmation"
                                       required
                                       autocomplete="new-password">
                            </div>
                        </div>
                        <div class="form-text mt-2">Minimum 8 characters, including letters and numbers.</div>

                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="bi bi-shield-lock me-1"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Account Information</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-2 border-bottom fs-13">
                        <span class="text-muted">Account ID</span>
                        <span class="fw-600">#{{ $user->id }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom fs-13">
                        <span class="text-muted">Role</span>
                        <span class="fw-600">{{ $isAdmin ? 'Administrator' : 'User' }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom fs-13">
                        <span class="text-muted">Status</span>
                        <span class="badge {{ $user->status === 'active' ? 'badge-soft-success' : 'badge-soft-warning' }}">{{ ucfirst($user->status) }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom fs-13">
                        <span class="text-muted">Member since</span>
                        <span class="fw-600">{{ $user->created_at->format('M j, Y') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 fs-13">
                        <span class="text-muted">Last updated</span>
                        <span class="fw-600">{{ $user->updated_at->format('M j, Y') }}</span>
                    </div>
                </div>
            </div>

            @unless ($isAdmin)
                @include('profile.partials.whatsapp-settings')
            @endunless

            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="stat-icon success" style="width: 38px; height: 38px; font-size: 1rem;"><i class="bi bi-shield-check"></i></span>
                        <h6 class="mb-0">Your privacy</h6>
                    </div>
                    @if ($isAdmin)
                        <p class="fs-13 text-muted mb-0">
                            As an administrator you can manage accounts and view aggregate statistics, but you cannot
                            access any user's tasks, schedules or AI conversations.
                        </p>
                    @else
                        <p class="fs-13 text-muted mb-0">
                            Your tasks, schedules and AI conversations are private to your account. Administrators can
                            only see account-level information such as your name, email and status.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
