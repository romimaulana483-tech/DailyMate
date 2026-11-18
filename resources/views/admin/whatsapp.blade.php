@extends('layouts.app')

@section('title', 'WhatsApp API Configuration')
@section('page-title', 'WhatsApp API Configuration')
@section('page-subtitle', 'Connect DailyMate to a WhatsApp provider and monitor delivery health.')

@section('content')
    <div class="page-heading">
        <div>
            <h2>WhatsApp API Configuration</h2>
            <p>Store provider credentials securely and test the connection against the real API.</p>
        </div>
        <span class="badge {{ $configuration->is_active ? 'badge-soft-success' : 'badge-soft-warning' }} align-self-start">
            {{ $configuration->is_active ? 'Integration active' : 'Integration inactive' }}
        </span>
    </div>

    <div class="alert alert-info mb-4">
        <i class="bi bi-shield-lock-fill mt-1"></i>
        <div>
            <strong>Credentials are encrypted at rest.</strong>
            The API token is never sent back to the browser. Leave the token field blank when saving or testing to keep
            the token already stored on the server.
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <h5>Provider credentials</h5>
                    <span class="badge {{ $tokenConfigured ? 'badge-soft-success' : 'badge-soft-warning' }}">
                        {{ $tokenConfigured ? 'Token configured' : 'Token not configured' }}
                    </span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.whatsapp.update') }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="provider" class="form-label">API Provider</label>
                            <select class="form-select @error('provider') is-invalid @enderror" id="provider" name="provider" required>
                                @foreach ($providers as $value => $label)
                                    <option value="{{ $value }}" @selected(old('provider', $configuration->provider) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('provider')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="endpoint" class="form-label">API Endpoint / URL</label>
                            <input type="url"
                                   class="form-control @error('endpoint') is-invalid @enderror"
                                   id="endpoint"
                                   name="endpoint"
                                   value="{{ old('endpoint', $configuration->endpoint) }}"
                                   placeholder="https://api.fonnte.com/send"
                                   maxlength="2048"
                                   required>
                            @error('endpoint')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text" id="providerEndpointHelp">
                                Fonnte uses the full send endpoint https://api.fonnte.com/send. Meta Cloud API uses its base URL.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="token" class="form-label">API Token / Access Token</label>
                            <div class="input-group">
                                <input type="password"
                                       class="form-control @error('token') is-invalid @enderror"
                                       id="token"
                                       name="token"
                                       placeholder="{{ $tokenConfigured ? '••••••••  Leave blank to keep the saved token' : 'Paste the API token' }}"
                                       maxlength="4000"
                                       autocomplete="new-password">
                                <button class="btn btn-light token-toggle" type="button" id="toggleToken" aria-label="Show or hide token">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            @error('token')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">
                                The token is encrypted in the database. It is never displayed, logged, or stored in browser storage.
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="phone_number_id" class="form-label">Phone Number ID</label>
                                <input type="text"
                                       class="form-control @error('phone_number_id') is-invalid @enderror"
                                       id="phone_number_id"
                                       name="phone_number_id"
                                       value="{{ old('phone_number_id', $configuration->phone_number_id) }}"
                                       placeholder="Required for Meta Cloud API"
                                       maxlength="100">
                                @error('phone_number_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="sender_number" class="form-label">Sender Number <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="text"
                                       class="form-control @error('sender_number') is-invalid @enderror"
                                       id="sender_number"
                                       name="sender_number"
                                       value="{{ old('sender_number', $configuration->sender_number) }}"
                                       placeholder="+6281234567890"
                                       maxlength="30">
                                @error('sender_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check form-switch my-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                                   @checked((bool) old('is_active', $configuration->is_active))>
                            <label class="form-check-label" for="is_active">Enable WhatsApp reminder delivery</label>
                        </div>

                        <details class="advanced-box mb-4">
                            <summary><i class="bi bi-sliders me-1"></i> Advanced provider and test options</summary>
                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label for="test_method" class="form-label">Connection Test Method</label>
                                    <select class="form-select" id="test_method" name="test_method">
                                        @foreach (['GET', 'HEAD', 'POST'] as $method)
                                            <option value="{{ $method }}" @selected(old('test_method', $configuration->test_method ?: 'POST') === $method)>{{ $method }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="test_endpoint" class="form-label">Connection Test URL <span class="text-muted fw-normal">(optional)</span></label>
                                    <input type="url" class="form-control @error('test_endpoint') is-invalid @enderror"
                                           id="test_endpoint" name="test_endpoint"
                                           value="{{ old('test_endpoint', $configuration->test_endpoint) }}"
                                           placeholder="https://api.fonnte.com/device" maxlength="2048">
                                    @error('test_endpoint')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <div class="form-text">
                                        For Fonnte, Test Connection calls https://api.fonnte.com/device and verifies that the
                                        device is connected. Meta Cloud API uses the phone number ID automatically when this is blank.
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <label for="custom_method" class="form-label">Custom Send Method</label>
                                    <select class="form-select" id="custom_method" name="custom_method">
                                        @foreach (['POST', 'PUT', 'PATCH'] as $method)
                                            <option value="{{ $method }}" @selected(old('custom_method', $configuration->custom_method ?: 'POST') === $method)>{{ $method }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="custom_auth_header" class="form-label">Custom Auth Header</label>
                                    <input type="text" class="form-control" id="custom_auth_header" name="custom_auth_header"
                                           value="{{ old('custom_auth_header', $configuration->custom_auth_header ?: 'Authorization') }}"
                                           maxlength="100">
                                </div>
                                <div class="col-md-4">
                                    <label for="custom_auth_scheme" class="form-label">Custom Auth Prefix</label>
                                    <input type="text" class="form-control" id="custom_auth_scheme" name="custom_auth_scheme"
                                           value="{{ old('custom_auth_scheme', $configuration->custom_auth_scheme ?: 'Bearer') }}"
                                           maxlength="50" placeholder="Bearer">
                                </div>
                                <div class="col-12">
                                    <label for="custom_payload_template" class="form-label">Custom JSON Payload Template</label>
                                    <textarea class="form-control font-monospace @error('custom_payload_template') is-invalid @enderror"
                                              id="custom_payload_template" name="custom_payload_template" rows="4" maxlength="5000"
                                              placeholder='{"to":"@{{to}}","message":"@{{message}}"}'>{{ old('custom_payload_template', $configuration->custom_payload_template) }}</textarea>
                                    @error('custom_payload_template')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-text">
                                        Only used by Custom HTTP API. Placeholders <code>@{{to}}</code> and
                                        <code>@{{message}}</code> are replaced with JSON-safe values. Leave blank to use the default payload.
                                    </div>
                                </div>
                            </div>
                        </details>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Save Configuration
                            </button>
                            <button type="submit"
                                    class="btn btn-light"
                                    formaction="{{ route('admin.whatsapp.test-connection') }}"
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

            <div class="card mt-4">
                <div class="card-header">
                    <h5>Test Notification</h5>
                </div>
                <div class="card-body">
                    <p class="fs-13 text-muted">
                        This sends a real message using the saved configuration. Save any credential changes first.
                    </p>
                    <form method="POST" action="{{ route('admin.whatsapp.test-notification') }}" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="test_number" class="form-label">Recipient WhatsApp number</label>
                            <input type="tel" class="form-control @error('test_number') is-invalid @enderror"
                                   id="test_number" name="test_number" value="{{ old('test_number') }}"
                                   placeholder="+6281234567890" maxlength="20" inputmode="tel" required>
                            @error('test_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="test_message" class="form-label">Test message <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea class="form-control @error('test_message') is-invalid @enderror" id="test_message"
                                      name="test_message" rows="3" maxlength="500"
                                      placeholder="Halo! Ini pesan uji dari DailyMate.">{{ old('test_message') }}</textarea>
                            @error('test_message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-soft-primary">
                            <i class="bi bi-send me-1"></i> Send Test Notification
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="row g-3 row-cols-2 mb-4">
                <div class="col"><x-stat-card label="Sent" :value="$deliveryStats['sent']" icon="bi-check2-circle" tone="success" /></div>
                <div class="col"><x-stat-card label="Pending" :value="$deliveryStats['pending']" icon="bi-hourglass-split" tone="warning" /></div>
                <div class="col"><x-stat-card label="Failed" :value="$deliveryStats['failed']" icon="bi-exclamation-triangle" tone="danger" /></div>
                <div class="col"><x-stat-card label="Skipped" :value="$deliveryStats['skipped']" icon="bi-dash-circle" tone="violet" /></div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5>Provider setup guide</h5>
                </div>
                <div class="card-body">
                    <div class="provider-guide">
                        <div><strong>Meta Cloud API</strong><span>Base URL, Phone Number ID, and a permanent access token.</span></div>
                        <div><strong>Fonnte</strong><span>Full send endpoint (for example https://api.fonnte.com/send) and device token.</span></div>
                        <div><strong>Wablas</strong><span>Full send endpoint and the token from your Wablas dashboard.</span></div>
                        <div><strong>Custom HTTP</strong><span>Full endpoint, auth header/prefix, method, and optional JSON template.</span></div>
                    </div>
                    <p class="fs-12 text-muted mb-0 mt-3">
                        Provider formats are handled separately. A test result is shown exactly as returned by the provider;
                        DailyMate never fakes a successful status.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <h5>Recent delivery status</h5>
                    <span class="badge badge-soft-primary">No message content</span>
                </div>
                <div class="card-body p-0">
                    @if ($recentNotifications->isEmpty())
                        <div class="empty-state py-5">
                            <i class="bi bi-whatsapp"></i>
                            <p class="mb-0">No WhatsApp delivery attempts yet.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 delivery-table">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Reminder</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentNotifications as $notification)
                                        <tr>
                                            <td>
                                                <span class="fw-600 fs-13">{{ $notification->user?->name ?? 'Deleted user' }}</span>
                                                @if ($notification->status === 'failed')
                                                    <span class="d-block text-muted fs-12">Technical details in server log</span>
                                                @elseif ($notification->status === 'skipped')
                                                    <span class="d-block text-muted fs-12">User preference or number unavailable</span>
                                                @endif
                                            </td>
                                            <td class="fs-13">{{ $notification->eventLabel() }}</td>
                                            <td>
                                                <span class="badge badge-soft-{{ $notification->status === 'sent' ? 'success' : ($notification->status === 'failed' ? 'danger' : ($notification->status === 'skipped' ? 'warning' : 'primary')) }}">
                                                    {{ $notification->statusLabel() }}
                                                </span>
                                            </td>
                                            <td class="fs-12 text-muted">
                                                {{ ($notification->sent_at ?? $notification->created_at)?->format('M j, H:i') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="alert alert-light border mt-4 mb-0 fs-13">
                <i class="bi bi-info-circle me-1"></i>
                Administrators can see technical delivery status only. Private task titles, schedule details, phone numbers,
                and AI conversations are never shown here.
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const provider = document.getElementById('provider');
            const endpointHelp = document.getElementById('providerEndpointHelp');
            const token = document.getElementById('token');
            const toggleToken = document.getElementById('toggleToken');

            const help = {
                meta_cloud: 'Use the Meta base API URL, usually https://graph.facebook.com/v20.0. Phone Number ID is required.',
                fonnte: 'Use the full Fonnte send endpoint, usually https://api.fonnte.com/send.',
                wablas: 'Use the full Wablas send endpoint from your dashboard.',
                custom: 'Use the full endpoint for your custom provider. Configure the payload template under advanced options.'
            };

            function updateHelp() {
                if (endpointHelp) {
                    endpointHelp.textContent = help[provider.value] || 'Enter the provider API URL.';
                }
            }

            provider?.addEventListener('change', updateHelp);
            updateHelp();

            toggleToken?.addEventListener('click', function () {
                const showing = token.type === 'text';
                token.type = showing ? 'password' : 'text';
                toggleToken.innerHTML = showing ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
                token.focus({ preventScroll: true });
            });
        })();
    </script>
@endpush
