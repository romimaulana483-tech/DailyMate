@props(['schedule' => null])

<div class="row g-3">
    <div class="col-12">
        <label for="title" class="form-label">Schedule title <span class="text-danger">*</span></label>
        <input type="text"
               class="form-control @error('title') is-invalid @enderror"
               id="title"
               name="title"
               value="{{ old('title', $schedule->title ?? '') }}"
               placeholder="e.g. Team stand-up meeting"
               required
               maxlength="255">
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <textarea class="form-control @error('description') is-invalid @enderror"
                  id="description"
                  name="description"
                  rows="3"
                  maxlength="2000"
                  placeholder="Location, people involved or any notes">{{ old('description', $schedule->description ?? '') }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="schedule_date" class="form-label">Date <span class="text-danger">*</span></label>
        <input type="date"
               class="form-control @error('schedule_date') is-invalid @enderror"
               id="schedule_date"
               name="schedule_date"
               value="{{ old('schedule_date', isset($schedule) ? $schedule->schedule_date->toDateString() : $defaultDate ?? now()->toDateString()) }}"
               required>
        @error('schedule_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="start_time" class="form-label">Start time <span class="text-danger">*</span></label>
        <input type="time"
               class="form-control @error('start_time') is-invalid @enderror"
               id="start_time"
               name="start_time"
               value="{{ old('start_time', isset($schedule) ? substr($schedule->start_time, 0, 5) : '09:00') }}"
               required>
        @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="end_time" class="form-label">End time <span class="text-danger">*</span></label>
        <input type="time"
               class="form-control @error('end_time') is-invalid @enderror"
               id="end_time"
               name="end_time"
               value="{{ old('end_time', isset($schedule) ? substr($schedule->end_time, 0, 5) : '10:00') }}"
               required>
        @error('end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">The end time must be later than the start time.</div>
    </div>
</div>
