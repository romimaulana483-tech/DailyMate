<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Welcome') · {{ config('app.name', 'DailyMate') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="auth-page">
        <div class="auth-visual">
            <a href="{{ route('home') }}" class="text-decoration-none d-flex align-items-center gap-2">
                <span class="brand-icon"><i class="bi bi-check2-square"></i></span>
                <div>
                    <span class="brand-text">Daily<span>Mate</span></span>
                    <span class="brand-sub">Smart everyday assistant</span>
                </div>
            </a>

            <div class="auth-copy">
                <h1>Plan your day.<br>Achieve more.</h1>
                <p>DailyMate brings your tasks, schedule, productivity insights and a personal AI assistant into one calm, focused workspace.</p>

                <div class="auth-feature"><i class="bi bi-check-lg"></i> Organise tasks by priority and deadline</div>
                <div class="auth-feature"><i class="bi bi-check-lg"></i> Plan your week with a clear calendar</div>
                <div class="auth-feature"><i class="bi bi-check-lg"></i> Track your completion rate over time</div>
                <div class="auth-feature"><i class="bi bi-check-lg"></i> Ask the AI assistant for a daily plan</div>
            </div>

            <div class="auth-visual-footer">
                &copy; {{ date('Y') }} DailyMate. Built for calmer, more productive days.
            </div>
        </div>

        <div class="auth-form-side">
            <div class="auth-card fade-in">
                <a href="{{ route('home') }}" class="auth-logo-mobile text-decoration-none">
                    <span class="brand-icon"><i class="bi bi-check2-square"></i></span>
                    <div>
                        <span class="brand-text">Daily<span>Mate</span></span>
                        <span class="brand-sub">Smart everyday assistant</span>
                    </div>
                </a>

                @include('partials.alerts')

                @yield('content')
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/bootstrap.bundle.min.js') }}"></script>
    <script>
        document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
            button.addEventListener('click', function () {
                const input = document.getElementById(button.getAttribute('data-toggle-password'));
                if (!input) return;
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                button.innerHTML = isPassword ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
            });
        });
    </script>
</body>
</html>
