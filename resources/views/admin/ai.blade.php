@extends('layouts.app')

@section('title', 'Friday AI Settings')
@section('page-title', 'Friday AI Settings')
@section('page-subtitle', 'Configure the Qwen API used by Friday for Admin and User accounts.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Friday AI Settings</h2>
            <p>The API key is encrypted server-side and is never sent to the browser or user accounts.</p>
        </div>
        <span class="badge {{ $configuration->is_active && $keyConfigured ? 'badge-soft-success' : 'badge-soft-warning' }} align-self-start">
            {{ $configuration->is_active && $keyConfigured ? 'Friday ready' : 'Configuration incomplete' }}
        </span>
    </div>

    <div class="alert alert-info mb-4">
        <i class="bi bi-shield-lock-fill mt-1"></i>
        <div>
            <strong>Use a fresh Qwen API key.</strong>
            Paste the key only here. It is encrypted in the database, hidden from every response, and never written to
            frontend JavaScript or localStorage. Revoke any key that was shared in a chat.
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <h5>Qwen API configuration</h5>
                    <span class="badge {{ $keyConfigured ? 'badge-soft-success' : 'badge-soft-warning' }}">
                        {{ $keyConfigured ? 'API key configured' : 'API key not configured' }}
                    </span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.ai.update') }}" novalidate>
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="provider" value="qwen">

                        <div class="mb-3">
                            <label for="endpoint" class="form-label">Qwen API endpoint</label>
                            <input type="url"
                                   class="form-control @error('endpoint') is-invalid @enderror"
                                   id="endpoint"
                                   name="endpoint"
                                   value="{{ old('endpoint', $configuration->endpoint ?: \App\Models\AiConfiguration::DEFAULT_ENDPOINT) }}"
                                   placeholder="https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions"
                                   maxlength="2048"
                                   required>
                            @error('endpoint')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                Use the OpenAI-compatible chat completions URL from your Model Studio region. A base URL
                                ending in <code>/compatible-mode/v1</code> is also accepted.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="model" class="form-label">Model</label>
                            <input type="text"
                                   class="form-control @error('model') is-invalid @enderror"
                                   id="model"
                                   name="model"
                                   value="{{ old('model', $configuration->model ?: \App\Models\AiConfiguration::DEFAULT_MODEL) }}"
                                   placeholder="qwen-plus"
                                   maxlength="100"
                                   required>
                            @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Examples: <code>qwen-plus</code>, <code>qwen-max</code>, <code>qwen-turbo</code>. Use a model available in your Qwen region.</div>
                        </div>

                        <div class="mb-3">
                            <label for="api_key" class="form-label">Qwen API key</label>
                            <div class="input-group">
                                <input type="password"
                                       class="form-control @error('api_key') is-invalid @enderror"
                                       id="api_key"
                                       name="api_key"
                                       placeholder="{{ $keyConfigured ? '••••••••  Leave blank to keep the saved key' : 'Paste the new Qwen API key' }}"
                                       maxlength="4000"
                                       autocomplete="new-password">
                                <button class="btn btn-light" type="button" id="toggleAiKey" aria-label="Show or hide API key">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            @error('api_key')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">
                                The key is encrypted with Laravel's encrypted cast. It is never shown after saving.
                            </div>
                        </div>

                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check form-switch my-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                                   @checked((bool) old('is_active', $configuration->is_active))>
                            <label class="form-check-label" for="is_active">Enable Friday AI for both roles</label>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Save Configuration
                            </button>
                            <button type="submit"
                                    class="btn btn-light"
                                    formaction="{{ route('admin.ai.test-connection') }}"
                                    formmethod="POST">
                                <i class="bi bi-plug me-1"></i> Test Connection
                            </button>
                        </div>
                    </form>

                    @if ($configuration->last_tested_at)
                        <div class="last-test-result mt-4">
                            <span class="badge {{ $configuration->last_test_status === 'success' ? 'badge-soft-success' : 'badge-soft-danger' }}">
                                Last test: {{ ucfirst($configuration->last_test_status ?? 'unknown') }}
                            </span>
                            <span class="text-muted fs-13 ms-1">{{ $configuration->last_tested_at->format('M j, Y H:i') }}</span>
                            @if ($configuration->last_test_message)
                                <p class="fs-13 text-muted mb-0 mt-2">{{ $configuration->last_test_message }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>How Friday handles data</h5>
                </div>
                <div class="card-body">
                    <div class="provider-guide">
                        <div><strong>User Friday</strong><span>Reads only the signed-in user's tasks, schedules, and statistics. Chat history is scoped to that account.</span></div>
                        <div><strong>Admin Friday</strong><span>Reads aggregate account/system data only. It cannot access task titles, schedules, phone numbers, or user AI chats.</span></div>
                        <div><strong>No direct database access</strong><span>Friday receives a server-built context. The model never executes SQL and cannot request other accounts.</span></div>
                        <div><strong>Failure handling</strong><span>If Qwen is unavailable, Friday uses the built-in offline assistant and logs only technical details.</span></div>
                    </div>
                </div>
            </div>

            <div class="alert alert-light border mb-0 fs-13">
                <i class="bi bi-info-circle me-1"></i>
                Friday is available as a floating assistant on every authenticated Admin and User page.
                Login and registration pages never include it.
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const key = document.getElementById('api_key');
            const toggle = document.getElementById('toggleAiKey');

            toggle?.addEventListener('click', function () {
                const showing = key.type === 'text';
                key.type = showing ? 'password' : 'text';
                toggle.innerHTML = showing ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
                key.focus({ preventScroll: true });
            });
        })();
    </script>
@endpush
