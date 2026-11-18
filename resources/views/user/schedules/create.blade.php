@extends('layouts.app')

@section('title', 'Add Schedule')
@section('page-title', 'My Schedule')
@section('page-subtitle', 'Block time for an upcoming activity.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Add Schedule</h2>
            <p>Give your activity a date and a realistic time range.</p>
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
                    <span class="badge badge-soft-primary">New</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('user.schedules.store') }}" novalidate>
                        @csrf
                        @include('user.schedules._form', ['schedule' => null, 'defaultDate' => $defaultDate])

                        <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Create Schedule
                            </button>
                            <a href="{{ route('user.schedules.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="stat-icon violet" style="width: 38px; height: 38px; font-size: 1rem;"><i class="bi bi-calendar-week"></i></span>
                        <h6 class="mb-0">Planning checklist</h6>
                    </div>
                    <ul class="fs-13 text-muted ps-3 mb-0 d-flex flex-column gap-2">
                        <li>Check that the date is correct.</li>
                        <li>Leave a buffer before your next activity.</li>
                        <li>Add a short description with the location.</li>
                        <li>Keep your calendar visible on the Calendar page.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
