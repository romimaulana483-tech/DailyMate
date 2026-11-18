<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta-description', 'DailyMate is a smart everyday assistant for tasks, schedules, productivity statistics and AI-powered planning.')">
    <title>@yield('title', 'Dashboard') · {{ config('app.name', 'DailyMate') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
    @php
        $currentUser = auth()->user();
        $isAdmin = $currentUser->isAdmin();
        $profileRoute = $isAdmin ? 'admin.profile.edit' : 'user.profile.edit';
        $notificationRoute = $isAdmin ? 'admin.notifications.index' : 'user.notifications.index';
        $unreadNotificationCount = $isAdmin
            ? \App\Models\WhatsAppNotification::whereIn('status', ['failed', 'pending'])->count()
            : $currentUser->userNotifications()->whereNull('read_at')->count();
        $initials = collect(explode(' ', trim($currentUser->name)))->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    @endphp

    <div class="app-shell">
        @include('partials.sidebar')

        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <div class="main-wrapper">
            <header class="topbar">
                <button type="button" class="btn-icon d-lg-none" id="sidebarToggle" aria-label="{{ __('Toggle navigation') }}">
                    <i class="bi bi-list fs-5"></i>
                </button>

                <div class="topbar-title">
                    <h1>@yield('page-title', __('Dashboard'))</h1>
                    <p>@yield('page-subtitle', __('Here is what is happening with your day.'))</p>
                </div>

                <div class="topbar-actions">
                    <div class="date-chip d-none d-md-flex">
                        <i class="bi bi-calendar3"></i>
                        {{ now()->translatedFormat('D, d M Y') }}
                    </div>
                    <div class="dropdown">
                        <button type="button" class="btn-icon" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('Change language') }}" aria-label="{{ __('Change language') }}">
                            <i class="bi bi-translate fs-5"></i>
                            <span class="d-none d-sm-inline ms-1 small text-uppercase">{{ app()->getLocale() }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">{{ __('Language') }}</h6></li>
                            <li>
                                <a class="dropdown-item {{ app()->getLocale() === 'id' ? 'active' : '' }}" href="{{ route('language.switch', 'id') }}">
                                    Bahasa Indonesia
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item {{ app()->getLocale() === 'en' ? 'active' : '' }}" href="{{ route('language.switch', 'en') }}">
                                    English
                                </a>
                            </li>
                        </ul>
                    </div>
                    <a href="{{ route($notificationRoute) }}" class="position-relative btn-icon text-decoration-none" data-bs-toggle="tooltip" title="{{ __('Notifications') }}" aria-label="{{ __('Notifications') }}">
                        <i class="bi bi-bell fs-5"></i>
                        @if ($unreadNotificationCount > 0)
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.62rem;">
                                {{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}
                            </span>
                        @endif
                    </a>
                    <a href="{{ route($profileRoute) }}" class="text-decoration-none" data-bs-toggle="tooltip" title="{{ __('Open profile') }}">
                        <span class="avatar avatar-sm">{{ $initials }}</span>
                    </a>
                </div>
            </header>

            <main class="page-content fade-in">
                @include('partials.alerts')
                @yield('content')
            </main>
        </div>
    </div>

    @include('partials.floating-ai')

    <script src="{{ asset('vendor/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    <script src="{{ asset('js/floating-ai.js') }}"></script>
    @stack('scripts')
</body>
</html>
