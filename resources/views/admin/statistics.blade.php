@extends('layouts.app')

@section('title', __('System Statistics'))
@section('page-title', __('System Statistics'))
@section('page-subtitle', __('Aggregated platform usage. No personal content.'))

@section('content')
    <div class="page-heading">
        <div>
            <h2>{{ __('System Statistics') }}</h2>
            <p>{{ __('Platform-wide totals and growth trends based on aggregate data.') }}</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-light">
            <i class="bi bi-grid-1x2 me-1"></i> {{ __('Dashboard') }}
        </a>
    </div>

    <div class="row g-3 mb-4 row-cols-2 row-cols-lg-3">
        <div class="col">
            <x-stat-card :label="__('Total Users')" :value="$totalUsers" icon="bi-people" tone="primary" :hint="__('All registered member accounts')" />
        </div>
        <div class="col">
            <x-stat-card :label="__('Active Users')" :value="$activeUsers" icon="bi-person-check" tone="success" :hint="__('Accounts with access enabled')" />
        </div>
        <div class="col">
            <x-stat-card :label="__('Inactive Users')" :value="$inactiveUsers" icon="bi-person-dash" tone="warning" :hint="__('Accounts with access disabled')" />
        </div>
    </div>

    <div class="row g-3 mb-4 row-cols-1 row-cols-md-3">
        <div class="col">
            <x-stat-card :label="__('Tasks Created')" :value="number_format($totalTasks)" icon="bi-check2-square" tone="violet" :hint="__('Aggregate across all users')" />
        </div>
        <div class="col">
            <x-stat-card :label="__('Schedules Created')" :value="number_format($totalSchedules)" icon="bi-calendar3" tone="info" :hint="__('Aggregate across all users')" />
        </div>
        <div class="col">
            <x-stat-card :label="__('AI Messages Sent')" :value="number_format($totalChats)" icon="bi-chat-square-dots" tone="success" :hint="__('Conversation count, contents private')" />
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h5>{{ __('Registrations per Month') }}</h5>
                        <span class="fs-12 text-muted">{{ __('New member accounts over the last 12 months') }}</span>
                    </div>
                    <span class="badge badge-soft-primary">{{ __('12 months') }}</span>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 320px;">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5>{{ __('Account Status') }}</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container mx-auto" style="height: 230px; max-width: 250px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                    <div class="d-flex justify-content-center gap-3 mt-3 fs-12">
                        <span><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#059669;"></span>{{ __('Active') }} ({{ $activeUsers }})</span>
                        <span><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#D97706;"></span>{{ __('Inactive') }} ({{ $inactiveUsers }})</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">
                    <h5>{{ __('Feature Usage Summary') }}</h5>
                </div>
                <div class="card-body">
                    @php $totalUsage = $totalTasks + $totalSchedules + $totalChats; @endphp
                    @foreach ([
                        ['label' => __('Task management'), 'value' => $totalTasks, 'color' => '#4F46E5', 'icon' => 'bi-check2-square'],
                        ['label' => __('Schedule planning'), 'value' => $totalSchedules, 'color' => '#7C3AED', 'icon' => 'bi-calendar3'],
                        ['label' => __('AI assistant'), 'value' => $totalChats, 'color' => '#059669', 'icon' => 'bi-stars'],
                    ] as $usage)
                        @php $percentage = $totalUsage > 0 ? round(($usage['value'] / $totalUsage) * 100) : 0; @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1 fs-13">
                                <span class="fw-600"><i class="bi {{ $usage['icon'] }} me-1" style="color: {{ $usage['color'] }};"></i>{{ $usage['label'] }}</span>
                                <span class="text-muted">{{ number_format($usage['value']) }} · {{ $percentage }}%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" style="width: {{ $percentage }}%; background: {{ $usage['color'] }};"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="stat-icon primary" style="width: 38px; height: 38px; font-size: 1rem;"><i class="bi bi-shield-check"></i></span>
                        <h6 class="mb-0">{{ __('Privacy by design') }}</h6>
                    </div>
                    <p class="fs-13 text-muted mb-3">
                        {{ __("These statistics are aggregated counts only. Administrators cannot view any user's task content, schedule details, personal notes or AI conversation history.") }}
                    </p>
                    <div class="divider"></div>
                    <div class="d-flex justify-content-between py-1 fs-13">
                        <span class="text-muted">{{ __('Average tasks per user') }}</span>
                        <span class="fw-700">{{ $totalUsers > 0 ? number_format($totalTasks / $totalUsers, 1) : 0 }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 fs-13">
                        <span class="text-muted">{{ __('Average schedules per user') }}</span>
                        <span class="fw-700">{{ $totalUsers > 0 ? number_format($totalSchedules / $totalUsers, 1) : 0 }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 fs-13">
                        <span class="text-muted">{{ __('Average AI messages per user') }}</span>
                        <span class="fw-700">{{ $totalUsers > 0 ? number_format($totalChats / $totalUsers, 1) : 0 }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
    <script>
        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: @json($labels),
                datasets: [{
                    label: @json(__('Registrations')),
                    data: @json($series),
                    backgroundColor: 'rgba(79, 70, 229, 0.75)',
                    hoverBackgroundColor: '#4F46E5',
                    borderRadius: 7,
                    maxBarThickness: 32
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
                        ticks: { color: '#94A3B8', font: { family: 'Inter', size: 11 }, maxRotation: 45, minRotation: 0 },
                        grid: { display: false }
                    }
                }
            }
        });

        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: @json([__('Active'), __('Inactive')]),
                datasets: [{
                    data: [{{ $activeUsers }}, {{ $inactiveUsers }}],
                    backgroundColor: ['#059669', '#D97706'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1E293B',
                        padding: 10,
                        cornerRadius: 8,
                        bodyFont: { family: 'Inter', size: 11 }
                    }
                }
            }
        });
    </script>
@endpush
