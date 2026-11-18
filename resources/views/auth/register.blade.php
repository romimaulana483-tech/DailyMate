@extends('layouts.guest')

@section('title', 'Create account')

@section('content')
    <h2>Create your account</h2>
    <p class="auth-sub">Start organising your day in less than a minute.</p>

    <form method="POST" action="{{ route('register.store') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="name" class="form-label">Full name</label>
            <input type="text"
                   class="form-control @error('name') is-invalid @enderror"
                   id="name"
                   name="name"
                   value="{{ old('name') }}"
                   placeholder="Your full name"
                   required
                   autofocus
                   autocomplete="name">
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input type="email"
                   class="form-control @error('email') is-invalid @enderror"
                   id="email"
                   name="email"
                   value="{{ old('email') }}"
                   placeholder="you@example.com"
                   required
                   autocomplete="email">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="position-relative">
                <input type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       id="password"
                       name="password"
                       placeholder="At least 8 characters"
                       required
                       autocomplete="new-password"
                       style="padding-right: 2.6rem;">
                <button type="button" class="password-toggle" data-toggle-password="password" aria-label="Show password">
                    <i class="bi bi-eye"></i>
                </button>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-text">Use at least 8 characters with letters and numbers.</div>
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirm password</label>
            <input type="password"
                   class="form-control"
                   id="password_confirmation"
                   name="password_confirmation"
                   placeholder="Repeat your password"
                   required
                   autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-person-plus me-1"></i> Create account
        </button>
    </form>

    <p class="auth-footer-link">
        Already have an account? <a href="{{ route('login') }}" class="fw-600">Sign in</a>
    </p>
@endsection
