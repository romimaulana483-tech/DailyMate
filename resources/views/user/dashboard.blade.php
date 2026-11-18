@extends('layouts.app')

@section('title', __('Dashboard'))
@section('page-title', __('Dashboard'))
@section('page-subtitle', __('Your daily overview at a glance.'))

@section('content')
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="hero-card h-100">
                <h2>{{ $greeting }}, {{ explode(' ', trim($user->name))[0] }} 👋</h2>
                <p>
                    @if ($todayTasks > 0)
                        {{ __('You have :count task(s) due today', ['count' => $todayTasks]) }}
                    @else
                        {{ __('Nothing is due today') }}
                    @endif
                    {{ __('and :count upcoming schedule(s).', ['count' => $upcomingSchedules->count()]) }}
                    {{ __('Let\'s make today count.') }}
                </p>
                <div class="hero-actions">
                    <a href="{{ route('user.tasks.create') }}" class="btn btn-light btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> {{ __('Add New Task') }}
                    </a>
                    <a href="{{ route('user.schedules.create') }}" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-calendar-plus me-1"></i> {{ __('Add Schedule') }}
                    </a>
                    <a href="{{ route('user.ai.index') }}" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-stars me-1"></i> {{ __('Ask AI Assistant') }}
                    </a>
                </div>

                <div class="hero-progress">
                    <div class="d-flex justify-content-between mb-1">
                        <small>{{ __('Overall completion') }}</small>
                        <small class="fw-700">{{ $completionRate }}%</small>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: {{ $completionRate }}%"></div>
                    </div>
                    <small class="d-block mt-1">{{ __(':completed of :total tasks completed', ['completed' => $completedTasks, 'total' => $totalTasks]) }}</small>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="stat-label mb-1">{{ __("Today's snapshot") }}</div>
                            <h5 class="mb-0">{{ now()->translatedFormat('l') }}</h5>
                            <span class="fs-13 text-muted">{{ now()->translatedFormat('d F Y') }}</span>
                        </div>
                        <span class="stat-icon violet"><i class="bi bi-sun"></i></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center py-2 border-top">
                        <span class="fs-13 text-muted">{{ __('Due today') }}</span>
                        <span class="fw-700">{{ $todayTasks }} {{ __('Tasks') }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-top">
                        <span class="fs-13 text-muted">{{ __('Overdue') }}</span>
                        <span class="fw-700 {{ $overdueTasks > 0 ? 'text-danger' : '' }}">{{ $overdueTasks }} {{ __('Tasks') }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-top border-bottom">
                        <span class="fs-13 text-muted">{{ __('In progress') }}</span>
                        <span class="fw-700">{{ $inProgressTasks }} {{ __('Tasks') }}</span>
                    </div>

                    <a href="{{ route('user.statistics') }}" class="btn btn-soft-primary w-100 mt-3">
                        <i class="bi bi-graph-up-arrow me-1"></i> {{ __('View productivity') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4 row-cols-2 row-cols-lg-4">
        <div class="col">
            <x-stat-card :label="__('Total Tasks')" :value="$totalTasks" icon="bi-list-check" tone="primary" :hint="__('Everything in your workspace')" />
        </div>
        <div class="col">
            <x-stat-card :label="__('Completed')" :value="$completedTasks" icon="bi-check2-circle" tone="success" :hint="$completionRate.'% '.__('Completion Rate')" />
        </div>
        <div class="col">
            <x-stat-card :label="__('Pending')" :value="$pendingTasks" icon="bi-hourglass-split" tone="warning" :hint="__('Waiting to be started')" />
        </div>
        <div class="col">
            <x-stat-card :label="__('In Progress')" :value="$inProgressTasks" icon="bi-arrow-repeat" tone="info" :hint="__('Currently being worked on')" />
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h5>{{ __('Productivity Overview') }}</h5>
                        <span class="fs-12 text-muted">{{ __('Completed vs created tasks over the last 7 days') }}</span>
                    </div>
                    <span class="badge badge-soft-primary">{{ __('Last 7 days') }}</span>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 280px;">
                        <canvas id="productivityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5>{{ __('Quick Actions') }}</h5>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <a href="{{ route('user.tasks.create') }}" class="quick-action">
                        <span class="qa-icon"><i class="bi bi-plus-lg"></i></span>
                        <span>
                            <span class="qa-title">{{ __('Add New Task') }}</span>
                            <span class="qa-desc">{{ __('Capture something to do') }}</span>
                        </span>
                    </a>
                    <a href="{{ route('user.schedules.create') }}" class="quick-action">
                        <span class="qa-icon success"><i class="bi bi-calendar-plus"></i></span>
                        <span>
                            <span class="qa-title">{{ __('Add Schedule') }}</span>
                            <span class="qa-desc">{{ __('Block time for an activity') }}</span>
                        </span>
                    </a>
                    <a href="{{ route('user.ai.index') }}" class="quick-action">
                        <span class="qa-icon violet"><i class="bi bi-stars"></i></span>
                        <span>
                            <span class="qa-title">{{ __('Open AI Assistant') }}</span>
                            <span class="qa-desc">{{ __('Get help planning your day') }}</span>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5>{{ __('Upcoming Schedule') }}</h5>
                    <a href="{{ route('user.calendar') }}" class="fs-12 fw-600">{{ __('View calendar') }} <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    @forelse ($upcomingSchedules as $schedule)
                        <div class="timeline-item">
                            <span class="timeline-icon"><i class="bi bi-calendar-event"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <p class="timeline-title">{{ $schedule->title }}</p>
                                <p class="timeline-meta">
                                    {{ $schedule->schedule_date->format('D, M j') }} · {{ $schedule->time_range }}
                                </p>
                            </div>
                            @if ($schedule->schedule_date->isToday())
                                <span class="badge badge-soft-primary align-self-center">{{ __('Today') }}</span>
                            @elseif ($schedule->schedule_date->isTomorrow())
                                <span class="badge badge-soft-warning align-self-center">{{ __('Tomorrow') }}</span>
                            @endif
                        </div>
                    @empty
                        <x-empty-state icon="bi-calendar-x" :title="__('No upcoming schedules')" :message="__('Add your first schedule to start planning your time.')">
                            <a href="{{ route('user.schedules.create') }}" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-lg me-1"></i> {{ __('Add Schedule') }}
                            </a>
                        </x-empty-state>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5>{{ __('Recent Tasks') }}</h5>
                    <a href="{{ route('user.tasks.index') }}" class="fs-12 fw-600">{{ __('All tasks') }} <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    @forelse ($recentTasks as $task)
                        <div class="d-flex align-items-start gap-3 py-2 border-bottom">
                            <span class="stat-icon {{ $task->status === 'completed' ? 'success' : ($task->priority === 'high' ? 'danger' : 'primary') }}"
                                  style="width: 34px; height: 34px; font-size: 0.9rem;">
                                <i class="bi {{ $task->status === 'completed' ? 'bi-check-lg' : 'bi-check2-square' }}"></i>
                            </span>
                            <div class="flex-grow-1 min-w-0">
                                <a href="{{ route('user.tasks.show', $task->id) }}" class="task-title-cell">{{ $task->title }}</a>
                                <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                                    <x-status-badge :status="$task->status" />
                                    <x-priority-badge :priority="$task->priority" />
                                    @if ($task->deadline)
                                        <span class="task-meta">
                                            <i class="bi bi-clock me-1"></i>{{ $task->deadline->format('M j, H:i') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <x-empty-state icon="bi-check2-square" :title="__('No tasks yet')" :message="__('Create your first task and start making progress.')">
                            <a href="{{ route('user.tasks.create') }}" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-lg me-1"></i> {{ __('Add Task') }}
                            </a>
                        </x-empty-state>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
    <script>
        (function () {
            const ctx = document.getElementById('productivityChart');
            if (!ctx) return;

            const labels = @json($labels);
            const completed = @json($completedSeries);
            const created = @json($createdSeries);

            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: @json(__('Completed')),
                            data: completed,
                            borderColor: '#4F46E5',
                            backgroundColor: 'rgba(79, 70, 229, 0.08)',
                            borderWidth: 2.5,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 3,
                            pointBackgroundColor: '#4F46E5',
                            pointHoverRadius: 5
                        },
                        {
                            label: @json(__('Created')),
                            data: created,
                            borderColor: '#7C3AED',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            borderDash: [5, 4],
                            tension: 0.4,
                            pointRadius: 3,
                            pointBackgroundColor: '#7C3AED',
                            pointHoverRadius: 5
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8, font: { size: 11, family: 'Inter' }, color: '#64748B' }
                        },
                        tooltip: {
                            backgroundColor: '#1E293B',
                            padding: 10,
                            titleFont: { family: 'Inter', size: 12 },
                            bodyFont: { family: 'Inter', size: 12 },
                            cornerRadius: 8,
                            displayColors: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, color: '#94A3B8', font: { size: 11, family: 'Inter' } },
                            grid: { color: '#F1F5F9', drawBorder: false }
                        },
                        x: {
                            ticks: { color: '#94A3B8', font: { size: 11, family: 'Inter' } },
                            grid: { display: false }
                        }
                    }
                }
            });
        })();
    </script>
@endpush
