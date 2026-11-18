<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppNotification;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WhatsAppController extends Controller
{
    public function edit()
    {
        $configuration = WhatsAppConfiguration::current()
            ?? new WhatsAppConfiguration([
                'provider' => 'fonnte',
                'endpoint' => 'https://api.fonnte.com/send',
                'is_active' => true,
                'custom_method' => 'POST',
                'custom_auth_header' => 'Authorization',
                'custom_auth_scheme' => '',
                'test_method' => 'POST',
                'test_endpoint' => 'https://api.fonnte.com/device',
            ]);

        $providers = WhatsAppConfiguration::providerLabels();
        $tokenConfigured = $configuration->exists && $configuration->hasToken();

        $deliveryStats = [
            'sent' => WhatsAppNotification::where('status', 'sent')->count(),
            'pending' => WhatsAppNotification::where('status', 'pending')->count(),
            'failed' => WhatsAppNotification::where('status', 'failed')->count(),
            'skipped' => WhatsAppNotification::where('status', 'skipped')->count(),
        ];

        $recentNotifications = WhatsAppNotification::with('user')
            ->latest()
            ->limit(12)
            ->get();

        return view('admin.whatsapp', compact(
            'configuration',
            'providers',
            'tokenConfigured',
            'deliveryStats',
            'recentNotifications'
        ));
    }

    public function update(Request $request)
    {
        $validated = $request->validate($this->configurationRules());

        $configuration = WhatsAppConfiguration::current() ?? new WhatsAppConfiguration();
        $data = $validated;

        // Fonnte's dashboard/device URL is not an API endpoint. Always store
        // the documented send endpoint so notifications do not hit a login page.
        if (($data['provider'] ?? null) === 'fonnte') {
            $data['endpoint'] = WhatsAppConfiguration::FONNTE_SEND_ENDPOINT;
        }

        $data['is_active'] = $request->boolean('is_active');
        $data['custom_method'] = strtoupper($validated['custom_method'] ?? 'POST');
        $data['custom_auth_header'] = filled($validated['custom_auth_header'] ?? null)
            ? $validated['custom_auth_header']
            : 'Authorization';
        $data['custom_auth_scheme'] = $validated['custom_auth_scheme'] ?? '';
        $data['test_method'] = strtoupper($validated['test_method'] ?? 'GET');

        // A blank token field means "keep the token that is already stored".
        if (blank($validated['token'] ?? null)) {
            unset($data['token']);
        } else {
            $data['token'] = trim($validated['token']);
        }

        $configuration->fill($data)->save();

        ActivityLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'Updated WhatsApp API configuration',
            'target_user_id' => null,
        ]);

        return redirect()->route('admin.whatsapp.edit')
            ->with('success', 'WhatsApp API configuration saved.');
    }

    public function testConnection(Request $request, WhatsAppService $whatsApp)
    {
        $validated = $request->validate($this->configurationRules());

        $configuration = WhatsAppConfiguration::current() ?? new WhatsAppConfiguration();
        $data = $validated;
        $data['is_active'] = $request->boolean('is_active');
        $data['custom_method'] = strtoupper($validated['custom_method'] ?? 'POST');
        $data['custom_auth_header'] = filled($validated['custom_auth_header'] ?? null)
            ? $validated['custom_auth_header']
            : 'Authorization';
        $data['custom_auth_scheme'] = $validated['custom_auth_scheme'] ?? '';
        $data['test_method'] = strtoupper($validated['test_method'] ?? 'GET');

        if (blank($validated['token'] ?? null)) {
            unset($data['token']);
        } else {
            $data['token'] = trim($validated['token']);
        }

        $configuration->fill($data);

        $result = $whatsApp->testConnection($configuration);

        $stored = WhatsAppConfiguration::current();

        if ($stored) {
            $stored->update([
                'last_tested_at' => now(),
                'last_test_status' => $result['success'] ? 'success' : 'failed',
                'last_test_message' => mb_substr($result['message'], 0, 1000),
            ]);
        }

        return redirect()->route('admin.whatsapp.edit')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function testNotification(Request $request, WhatsAppService $whatsApp)
    {
        $validated = $request->validate([
            'test_number' => ['required', 'string', 'max:20', 'regex:/^\+[1-9]\d{7,14}$/'],
            'test_message' => ['nullable', 'string', 'max:500'],
        ], [
            'test_number.regex' => 'Use an international number starting with +, for example +6281234567890.',
        ]);

        $configuration = WhatsAppConfiguration::current();

        if (! $configuration) {
            return back()->with('error', 'Save the WhatsApp API configuration before sending a test notification.');
        }

        $number = preg_replace('/\D+/', '', $validated['test_number']) ?? '';
        $message = trim($validated['test_message'] ?? '')
            ?: 'Halo! Ini pesan uji dari DailyMate. Konfigurasi WhatsApp berhasil terhubung.';

        $result = $whatsApp->sendMessage($configuration, $number, $message);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['success']
                ? 'Test notification accepted by the provider. '.$result['message']
                : 'Test notification failed. '.$result['message']
        );
    }

    private function configurationRules(): array
    {
        return [
            'provider' => ['required', Rule::in(array_keys(WhatsAppConfiguration::PROVIDERS))],
            'endpoint' => ['required', 'url:http,https', 'max:2048'],
            'token' => ['nullable', 'string', 'max:4000'],
            'phone_number_id' => ['nullable', 'string', 'max:100', 'required_if:provider,meta_cloud'],
            'sender_number' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
            'custom_method' => ['nullable', Rule::in(['POST', 'PUT', 'PATCH'])],
            'custom_auth_header' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9-]+$/'],
            'custom_auth_scheme' => ['nullable', 'string', 'max:50'],
            'custom_payload_template' => ['nullable', 'string', 'max:5000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (blank($value)) {
                    return;
                }

                $sample = str_replace(
                    ['{{to}}', '{{message}}'],
                    ['6281234567890', 'DailyMate test message'],
                    (string) $value
                );

                json_decode($sample, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $fail('The custom payload template must produce valid JSON after replacing {{to}} and {{message}}.');
                }
            }],
            'test_method' => ['nullable', Rule::in(['GET', 'HEAD', 'POST'])],
            'test_endpoint' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }
}
