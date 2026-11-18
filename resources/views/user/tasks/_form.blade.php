@props(['task' => null, 'categories' => collect()])

<div class="row g-3">
    <div class="col-12">
        <label for="title" class="form-label">Task title <span class="text-danger">*</span></label>
        <input type="text"
               class="form-control @error('title') is-invalid @enderror"
               id="title"
               name="title"
               value="{{ old('title', $task->title ?? '') }}"
               placeholder="e.g. Finish the project proposal"
               required
               maxlength="255">
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <textarea class="form-control @error('description') is-invalid @enderror"
                  id="description"
                  name="description"
                  rows="4"
                  maxlength="2000"
                  placeholder="Add details, notes or steps for this task">{{ old('description', $task->description ?? '') }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="category" class="form-label">Category</label>
        <input type="text"
               class="form-control @error('category') is-invalid @enderror"
               id="category"
               name="category"
               list="category-options"
               value="{{ old('category', $task->category ?? '') }}"
               placeholder="e.g. Work, Study, Personal"
               maxlength="50">
        <datalist id="category-options">
            @foreach ($categories as $category)
                <option value="{{ $category }}">
            @endforeach
        </datalist>
        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Leave empty to use the General category.</div>
    </div>

    <div class="col-md-6">
        <label for="deadline" class="form-label">Deadline</label>
        <input type="datetime-local"
               class="form-control @error('deadline') is-invalid @enderror"
               id="deadline"
               name="deadline"
               value="{{ old('deadline', isset($task) && $task->deadline ? $task->deadline->format('Y-m-d\TH:i') : '') }}">
        @error('deadline')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Optional. Overdue tasks are highlighted automatically.</div>
    </div>

    <div class="col-md-6">
        <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
        <select class="form-select @error('priority') is-invalid @enderror" id="priority" name="priority" required>
            @foreach (\App\Models\Task::priorityLabels() as $value => $label)
                <option value="{{ $value }}" @selected(old('priority', $task->priority ?? 'medium') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
            @foreach (\App\Models\Task::statusLabels() as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $task->status ?? 'todo') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
