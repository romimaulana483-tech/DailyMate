@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h2>Welcome back</h2>
    <p class="auth-sub">Sign in to continue planning a productive day.</p>

    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <div class="position-relative">
                <input type="email"
                       class="form-control @error('email') is-invalid @enderror"
                       id="email"
                       name="email"
                       value="{{ old('email') }}"
                       placeholder="you@example.com"
                       required
                       autofocus
                       autocomplete="email">
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <label for="password" class="form-label">Password</label>
            </div>
            <div class="position-relative">
                <input type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       id="password"
                       name="password"
                       placeholder="Enter your password"
                       required
                       autocomplete="current-password"
                       style="padding-right: 2.6rem;">
                <button type="button" class="password-toggle" data-toggle-password="password" aria-label="Show password">
                    <i class="bi bi-eye"></i>
                </button>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
            <label class="form-check-label fs-13" for="remember">Keep me signed in</label>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
        </button>
    </form>

    <div class="demo-credentials">
        <div class="fw-700 mb-1"><i class="bi bi-info-circle me-1"></i> Demo accounts</div>
        <div><strong>User:</strong> user@dailymate.test &nbsp;/&nbsp; User12345</div>
        <div><strong>Admin:</strong> admin@dailymate.test &nbsp;/&nbsp; Admin12345</div>
    </div>

    <p class="auth-footer-link">
        Don't have an account? <a href="{{ route('register') }}" class="fw-600">Create one for free</a>
    </p>
@endsection
