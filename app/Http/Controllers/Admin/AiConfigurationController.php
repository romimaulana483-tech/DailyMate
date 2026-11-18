<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AiConfiguration;
use App\Services\QwenService;
use Illuminate\Http\Request;

class AiConfigurationController extends Controller
{
    public function edit()
    {
        $configuration = AiConfiguration::current() ?? AiConfiguration::defaults();
        $keyConfigured = $configuration->exists && $configuration->hasApiKey();

        return view('admin.ai', compact('configuration', 'keyConfigured'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate($this->rules());
        $configuration = AiConfiguration::current() ?? new AiConfiguration();
        $data = $validated;
        $data['provider'] = 'qwen';
        $data['is_active'] = $request->boolean('is_active');

        if (blank($validated['api_key'] ?? null)) {
            unset($data['api_key']);
        } else {
            $data['api_key'] = trim($validated['api_key']);
        }

        $configuration->fill($data)->save();

        ActivityLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'Updated Qwen AI configuration',
            'target_user_id' => null,
        ]);

        return redirect()->route('admin.ai.edit')
            ->with('success', 'Qwen AI configuration saved.');
    }

    public function testConnection(Request $request, QwenService $qwen)
    {
        $validated = $request->validate($this->rules());
        $configuration = AiConfiguration::current() ?? new AiConfiguration();
        $data = $validated;
        $data['provider'] = 'qwen';
        $data['is_active'] = $request->boolean('is_active');

        if (blank($validated['api_key'] ?? null)) {
            unset($data['api_key']);
        } else {
            $data['api_key'] = trim($validated['api_key']);
        }

        $configuration->fill($data);
        $result = $qwen->testConnection($configuration);

        $stored = AiConfiguration::current();

        if ($stored) {
            $stored->update([
                'last_tested_at' => now(),
                'last_test_status' => $result['success'] ? 'success' : 'failed',
                'last_test_message' => mb_substr($result['message'], 0, 1000),
            ]);
        }

        return redirect()->route('admin.ai.edit')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    private function rules(): array
    {
        return [
            'provider' => ['nullable', 'string', 'max:30'],
            'endpoint' => ['required', 'url:http,https', 'max:2048'],
            'model' => ['required', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
