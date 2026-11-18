@extends('layouts.app')

@section('title', __('Productivity Statistics'))
@section('page-title', __('Productivity Statistics'))
@section('page-subtitle', __('Understand your personal productivity patterns.'))

@section('content')
    <div class="page-heading">
        <div>
            <h2>{{ __('Your Productivity') }}</h2>
            <p>{{ __('All statistics are calculated from your own tasks only.') }}</p>
        </div>
        <a href="{{ route('user.tasks.index') }}" class="btn btn-light">
            <i class="bi bi-list-check me-1"></i> {{ __('Manage tasks') }}
        </a>
    </div>

    <div class="row g-3 mb-4 row-cols-2 row-cols-lg-4">
        <div class="col">
            <x-stat-card :label="__('Total Tasks')" :value="$totalTasks" icon="bi-list-check" tone="primary" :hint="__('All tasks you have created')" />
        </div>
        <div class="col">
            <x-stat-card :label="__('Completed Tasks')" :value="$completedTasks" icon="bi-check2-circle" tone="success" :hint="$tasksThisWeek.' '.__('Completed this week')" />
        </div>
        <div class="col">
            <x-stat-card :label="__('Pending Tasks')" :value="$pendingTasks" icon="bi-hourglass-split" tone="warning" :hint="$inProgressTasks.' '.__('In progress')" />
        </div>
        <div class="col">
            <x-stat-card :label="__('Completion Rate')" :value="$completionRate.'%'" icon="bi-graph-up-arrow" tone="violet" :hint="$overdueTasks.' '.__('Overdue Tasks')" />
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h5>{{ __('Tasks Completed by Date') }}</h5>
                        <span class="fs-12 text-muted">{{ __('Your completed tasks over the last 14 days') }}</span>
                    </div>
                    <span class="badge badge-soft-success">{{ __('Last 14 days') }}</span>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="completedChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5>{{ __('Priority Distribution') }}</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container mx-auto" style="height: 220px; max-width: 240px;">
                        <canvas id="priorityChart"></canvas>
                    </div>
                    <div class="d-flex justify-content-center gap-3 mt-3 fs-12">
                        <span><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#DC2626;"></span>{{ __('High') }} ({{ $priorityCounts['high'] }})</span>
                        <span><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#D97706;"></span>{{ __('Medium') }} ({{ $priorityCounts['medium'] }})</span>
                        <span><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#059669;"></span>{{ __('Low') }} ({{ $priorityCounts['low'] }})</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5>{{ __('Status Breakdown') }}</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container mx-auto" style="height: 220px; max-width: 240px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                    <div class="d-flex justify-content-center gap-3 mt-3 fs-12">
                        <span><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#4F46E5;"></span>{{ __('To Do') }}</span>
                        <span><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#0284C7;"></span>{{ __('In Progress') }}</span>
                        <span><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#059669;"></span>{{ __('Completed') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5>{{ __('Tasks by Category') }}</h5>
                    <span class="fs-12 text-muted">{{ __('Top :count categories', ['count' => $categoryCounts->count()]) }}</span>
                </div>
                <div class="card-body">
                    @if ($categoryCounts->isEmpty())
                        <x-empty-state icon="bi-bar-chart" :title="__('No category data yet')" :message="__('Create a few tasks and your category breakdown will appear here.')" />
                    @else
                        <div class="chart-container" style="height: 260px;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
    <script>
        (function () {
            const font = { family: 'Inter', size: 11 };

            // Completed tasks trend
            new Chart(document.getElementById('completedChart'), {
                type: 'bar',
                data: {
                    labels: @json($dateLabels),
                    datasets: [{
                        label: @json(__('Completed tasks')),
                        data: @json($completedSeries),
                        backgroundColor: 'rgba(79, 70, 229, 0.75)',
                        hoverBackgroundColor: '#4F46E5',
                        borderRadius: 6,
                        maxBarThickness: 28
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
                            bodyFont: font,
                            titleFont: font
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, color: '#94A3B8', font: font },
                            grid: { color: '#F1F5F9' }
                        },
                        x: {
                            ticks: { color: '#94A3B8', font: font, maxRotation: 0, autoSkip: true },
                            grid: { display: false }
                        }
                    }
                }
            });

            // Priority doughnut
            new Chart(document.getElementById('priorityChart'), {
                type: 'doughnut',
                data: {
                    labels: @json([__('High'), __('Medium'), __('Low')]),
                    datasets: [{
                        data: @json(array_values($priorityCounts)),
                        backgroundColor: ['#DC2626', '#D97706', '#059669'],
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
                        tooltip: { backgroundColor: '#1E293B', padding: 10, cornerRadius: 8, bodyFont: font }
                    }
                }
            });

            // Status doughnut
            new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: {
                    labels: @json([__('To Do'), __('In Progress'), __('Completed')]),
                    datasets: [{
                        data: @json(array_values($statusCounts)),
                        backgroundColor: ['#4F46E5', '#0284C7', '#059669'],
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
                        tooltip: { backgroundColor: '#1E293B', padding: 10, cornerRadius: 8, bodyFont: font }
                    }
                }
            });

            // Category bar
            const categoryCanvas = document.getElementById('categoryChart');
            if (categoryCanvas) {
                new Chart(categoryCanvas, {
                    type: 'bar',
                    data: {
                        labels: @json($categoryCounts->keys()),
                        datasets: [{
                            label: @json(__('Tasks')),
                            data: @json($categoryCounts->values()),
                            backgroundColor: 'rgba(124, 58, 237, 0.7)',
                            hoverBackgroundColor: '#7C3AED',
                            borderRadius: 6,
                            maxBarThickness: 36
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { backgroundColor: '#1E293B', padding: 10, cornerRadius: 8, bodyFont: font }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0, color: '#94A3B8', font: font },
                                grid: { color: '#F1F5F9' }
                            },
                            x: {
                                ticks: { color: '#94A3B8', font: font },
                                grid: { display: false }
                            }
                        }
                    }
                });
            }
        })();
    </script>
@endpush
