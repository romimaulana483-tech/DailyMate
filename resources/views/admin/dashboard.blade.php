@extends('layouts.app')

@section('title', __('Admin Dashboard'))
@section('page-title', __('Admin Dashboard'))
@section('page-subtitle', __('System overview and account statistics.'))

@section('content')
    <div class="page-heading">
        <div>
            <h2>{{ __('Welcome back, :name 👋', ['name' => auth()->user()->name]) }}</h2>
            <p>{{ __('Here is the overall health of the DailyMate platform.') }}</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn btn-primary">
            <i class="bi bi-people me-1"></i> {{ __('Manage Users') }}
        </a>
    </div>

    <div class="row g-3 mb-4 row-cols-2 row-cols-lg-4">
        <div class="col">
            <x-stat-card :label="__('Total Users')" :value="$totalUsers" icon="bi-people" tone="primary" :hint="__('Registered member accounts')" />
        </div>
        <div class="col">
            <x-stat-card :label="__('Active Users')" :value="$activeUsers" icon="bi-person-check" tone="success" :hint="__('Accounts that can sign in')" />
        </div>
        <div class="col">
            <x-stat-card :label="__('Inactive Users')" :value="$inactiveUsers" icon="bi-person-dash" tone="warning" :hint="__('Accounts currently disabled')" />
        </div>
        <div class="col">
            <x-stat-card :label="__('New This Month')" :value="$newUsers" icon="bi-person-plus" tone="violet" :hint="__('Registrations this month')" />
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h5>{{ __('User Registrations') }}</h5>
                        <span class="fs-12 text-muted">{{ __('New member accounts over the last 6 months') }}</span>
                    </div>
                    <span class="badge badge-soft-primary">{{ __('6 months') }}</span>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 290px;">
                        <canvas id="registrationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5>{{ __('Platform Usage') }}</h5>
                </div>
                <div class="card-body d-flex flex-column gap-3">
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-dm" style="background: var(--dm-primary-soft);">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check2-square" style="color: var(--dm-primary);"></i>
                            <span class="fs-13 fw-600">{{ __('Tasks created') }}</span>
                        </div>
                        <span class="fw-800" style="font-size: 1.1rem;">{{ number_format($totalTasks) }}</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-dm" style="background: var(--dm-violet-soft);">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-calendar3" style="color: var(--dm-secondary);"></i>
                            <span class="fs-13 fw-600">{{ __('Schedules created') }}</span>
                        </div>
                        <span class="fw-800" style="font-size: 1.1rem;">{{ number_format($totalSchedules) }}</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-dm" style="background: var(--dm-success-soft);">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-chat-square-dots" style="color: var(--dm-success);"></i>
                            <span class="fs-13 fw-600">{{ __('AI messages sent') }}</span>
                        </div>
                        <span class="fw-800" style="font-size: 1.1rem;">{{ number_format($totalChats) }}</span>
                    </div>

                    <a href="{{ route('admin.statistics') }}" class="btn btn-light mt-auto">
                        <i class="bi bi-bar-chart-line me-1"></i> {{ __('Full system statistics') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5>{{ __('Recent Registrations') }}</h5>
                    <a href="{{ route('admin.users.index') }}" class="fs-12 fw-600">{{ __('All users') }} <i class="bi bi-arrow-right"></i></a>
                </div>
                @if ($recentUsers->isEmpty())
                    <div class="card-body">
                        <x-empty-state icon="bi-people" :title="__('No users yet')" :message="__('New member accounts will appear here.')" />
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('User') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Registered') }}</th>
                                    <th class="text-end">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentUsers as $recent)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="avatar avatar-sm avatar-soft">{{ strtoupper(substr($recent->name, 0, 1)) }}</span>
                                                <span class="fw-600 fs-13">{{ $recent->name }}</span>
                                            </div>
                                        </td>
                                        <td class="fs-13 text-muted">{{ $recent->email }}</td>
                                        <td>
                                            <span class="badge {{ $recent->status === 'active' ? 'badge-soft-success' : 'badge-soft-warning' }}">
                                                {{ ucfirst($recent->status) }}
                                            </span>
                                        </td>
                                        <td class="fs-13 text-muted">{{ $recent->created_at->format('M j, Y') }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.users.show', $recent->id) }}" class="btn btn-icon" style="width: 32px; height: 32px;" aria-label="{{ __('View user') }}">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="stat-icon primary" style="width: 38px; height: 38px; font-size: 1rem;"><i class="bi bi-shield-lock"></i></span>
                        <h6 class="mb-0">{{ __('Privacy reminder') }}</h6>
                    </div>
                    <p class="fs-13 text-muted mb-0">
                        {{ __('Admin tools only expose account information and aggregate statistics. Tasks, schedules, personal notes and AI conversations are private to each user and are never displayed here.') }}
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5>{{ __('Quick Actions') }}</h5></div>
                <div class="card-body d-flex flex-column gap-2">
                    <a href="{{ route('admin.users.index', ['status' => 'active']) }}" class="quick-action">
                        <span class="qa-icon success"><i class="bi bi-person-check"></i></span>
                        <span><span class="qa-title">{{ __('Active users') }}</span><span class="qa-desc">{{ __('Review enabled accounts') }}</span></span>
                    </a>
                    <a href="{{ route('admin.users.index', ['status' => 'inactive']) }}" class="quick-action">
                        <span class="qa-icon" style="background: var(--dm-warning-soft); color: var(--dm-warning);"><i class="bi bi-person-dash"></i></span>
                        <span><span class="qa-title">{{ __('Inactive users') }}</span><span class="qa-desc">{{ __('Review disabled accounts') }}</span></span>
                    </a>
                    <a href="{{ route('admin.statistics') }}" class="quick-action">
                        <span class="qa-icon violet"><i class="bi bi-bar-chart-line"></i></span>
                        <span><span class="qa-title">{{ __('System statistics') }}</span><span class="qa-desc">{{ __('See platform-wide trends') }}</span></span>
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
    <script>
        new Chart(document.getElementById('registrationChart'), {
            type: 'bar',
            data: {
                labels: @json($labels),
                datasets: [{
                    label: @json(__('New users')),
                    data: @json($series),
                    backgroundColor: 'rgba(79, 70, 229, 0.75)',
                    hoverBackgroundColor: '#4F46E5',
                    borderRadius: 8,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1E293B',
                        padding: 10,
                        cornerRadius: 8,
                        bodyFont: { family: 'Inter', size: 11 },
                        titleFont: { family: 'Inter', size: 11 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: '#94A3B8', font: { family: 'Inter', size: 11 } },
                        grid: { color: '#F1F5F9' }
                    },
                    x: {
                        ticks: { color: '#94A3B8', font: { family: 'Inter', size: 11 } },
                        grid: { display: false }
                    }
                }
            }
        });
    </script>
@endpush
