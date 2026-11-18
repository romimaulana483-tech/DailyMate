@extends('layouts.app')

@section('title', 'Edit Schedule')
@section('page-title', 'My Schedule')
@section('page-subtitle', 'Update your planned activity.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Edit Schedule</h2>
            <p>{{ $schedule->title }}</p>
        </div>
        <a href="{{ route('user.schedules.index') }}" class="btn btn-light">
            <i class="bi bi-arrow-left me-1"></i> Back to schedule
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5>Schedule Details</h5>
                    <span class="badge badge-soft-primary">Editing #{{ $schedule->id }}</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('user.schedules.update', $schedule->id) }}" novalidate>
                        @csrf
                        @method('PUT')
                        @include('user.schedules._form', ['schedule' => $schedule])

                        <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Save Changes
                            </button>
                            <a href="{{ route('user.schedules.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5>Schedule Info</h5></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-2 border-bottom fs-13">
                        <span class="text-muted">Date</span>
                        <span class="fw-600">{{ $schedule->schedule_date->format('M j, Y') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom fs-13">
                        <span class="text-muted">Time</span>
                        <span class="fw-600">{{ $schedule->time_range }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 fs-13">
                        <span class="text-muted">Created</span>
                        <span class="fw-600">{{ $schedule->created_at->format('M j, Y') }}</span>
                    </div>

                    <form method="POST" action="{{ route('user.schedules.destroy', $schedule->id) }}" class="mt-3" data-confirm="Delete this schedule?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-soft-danger w-100">
                            <i class="bi bi-trash me-1"></i> Delete Schedule
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
