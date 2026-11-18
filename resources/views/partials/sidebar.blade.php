@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $initials = collect(explode(' ', trim($user->name)))->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp

<aside class="sidebar" id="appSidebar">
    <div class="sidebar-brand">
        <span class="brand-icon"><i class="bi bi-check2-square"></i></span>
        <div>
            <span class="brand-text">Daily<span>Mate</span></span>
            <span class="brand-sub">{{ $isAdmin ? __('Admin workspace') : __('Smart assistant') }}</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        @if ($isAdmin)
            <div class="nav-section">{{ __('Overview') }}</div>
            <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2"></i> {{ __('Dashboard') }}
            </a>

            <div class="nav-section">{{ __('Management') }}</div>
            <a href="{{ route('admin.users.index') }}" class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <i class="bi bi-people"></i> {{ __('User Management') }}
            </a>
            <a href="{{ route('admin.statistics') }}" class="sidebar-link {{ request()->routeIs('admin.statistics') ? 'active' : '' }}">
                <i class="bi bi-bar-chart-line"></i> {{ __('System Statistics') }}
            </a>
            <a href="{{ route('admin.notifications.index') }}" class="sidebar-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}">
                <i class="bi bi-bell"></i> {{ __('Notifications') }}
            </a>
            <a href="{{ route('admin.feedback.index') }}" class="sidebar-link {{ request()->routeIs('admin.feedback.*') ? 'active' : '' }}">
                <i class="bi bi-chat-square-text"></i> {{ __('Feedback') }}
            </a>
            <a href="{{ route('admin.reports.index') }}" class="sidebar-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
                <i class="bi bi-file-earmark-bar-graph"></i> {{ __('Admin Reports') }}
            </a>
            <a href="{{ route('friday.index') }}" class="sidebar-link {{ request()->routeIs('friday.*') ? 'active' : '' }}">
                <i class="bi bi-stars"></i> Friday AI
            </a>
            <a href="{{ route('admin.ai.edit') }}" class="sidebar-link {{ request()->routeIs('admin.ai.*') ? 'active' : '' }}">
                <i class="bi bi-cpu"></i> {{ __('Friday AI Settings') }}
            </a>
            <a href="{{ route('admin.whatsapp.edit') }}" class="sidebar-link {{ request()->routeIs('admin.whatsapp.*') ? 'active' : '' }}">
                <i class="bi bi-whatsapp"></i> {{ __('WhatsApp API') }}
            </a>

            <div class="nav-section">{{ __('Account') }}</div>
            <a href="{{ route('admin.profile.edit') }}" class="sidebar-link {{ request()->routeIs('admin.profile.*') ? 'active' : '' }}">
                <i class="bi bi-person-circle"></i> {{ __('Profile') }}
            </a>
        @else
            <div class="nav-section">{{ __('Overview') }}</div>
            <a href="{{ route('user.dashboard') }}" class="sidebar-link {{ request()->routeIs('user.dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2"></i> {{ __('Dashboard') }}
            </a>

            <div class="nav-section">{{ __('My Workspace') }}</div>
            <a href="{{ route('user.tasks.index') }}" class="sidebar-link {{ request()->routeIs('user.tasks.*') ? 'active' : '' }}">
                <i class="bi bi-check2-square"></i> {{ __('My Tasks') }}
            </a>
            <a href="{{ route('user.schedules.index') }}" class="sidebar-link {{ request()->routeIs('user.schedules.*') ? 'active' : '' }}">
                <i class="bi bi-clock-history"></i> {{ __('My Schedule') }}
            </a>
            <a href="{{ route('user.calendar') }}" class="sidebar-link {{ request()->routeIs('user.calendar') ? 'active' : '' }}">
                <i class="bi bi-calendar3"></i> {{ __('Calendar') }}
            </a>

            <div class="nav-section">{{ __('Insights & AI') }}</div>
            <a href="{{ route('user.statistics') }}" class="sidebar-link {{ request()->routeIs('user.statistics') ? 'active' : '' }}">
                <i class="bi bi-bar-chart-line"></i> {{ __('Productivity Statistics') }}
            </a>
            <a href="{{ route('friday.index') }}" class="sidebar-link {{ request()->routeIs('friday.*') || request()->routeIs('user.ai.*') ? 'active' : '' }}">
                <i class="bi bi-stars"></i> Friday AI
            </a>
            <a href="{{ route('user.notifications.index') }}" class="sidebar-link {{ request()->routeIs('user.notifications.*') ? 'active' : '' }}">
                <i class="bi bi-bell"></i> {{ __('Notifications') }}
            </a>

            <div class="nav-section">{{ __('Account') }}</div>
            <a href="{{ route('user.feedback.index') }}" class="sidebar-link {{ request()->routeIs('user.feedback.*') ? 'active' : '' }}">
                <i class="bi bi-chat-square-text"></i> {{ __('Feedback') }}
            </a>
            <a href="{{ route('user.profile.edit') }}" class="sidebar-link {{ request()->routeIs('user.profile.*') ? 'active' : '' }}">
                <i class="bi bi-person-circle"></i> {{ __('Profile') }}
            </a>
        @endif
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <span class="avatar avatar-sm">{{ $initials }}</span>
            <div class="min-w-0">
                <span class="name">{{ $user->name }}</span>
                <span class="role">{{ $isAdmin ? __('Administrator') : __('Member') }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-link">
                <i class="bi bi-box-arrow-left"></i> {{ __('Logout') }}
            </button>
        </form>
    </div>
</aside>
