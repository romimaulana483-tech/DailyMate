<?php

namespace Tests\Feature;

use App\Models\AiConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QwenAiConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_ai_settings_without_exposing_the_key(): void
    {
        $admin = User::factory()->admin()->create();
        AiConfiguration::create([
            'provider' => 'qwen',
            'endpoint' => AiConfiguration::DEFAULT_ENDPOINT,
            'model' => 'qwen-plus',
            'api_key' => 'super-secret-qwen-key',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ai.edit'))
            ->assertOk()
            ->assertSee('Friday AI Settings')
            ->assertSee('API key configured')
            ->assertDontSee('super-secret-qwen-key');
    }

    public function test_non_admin_cannot_access_ai_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.ai.edit'))
            ->assertRedirect(route('user.dashboard'));
    }

    public function test_api_key_is_encrypted_and_blank_update_keeps_existing_key(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.ai.update'), [
            'endpoint' => AiConfiguration::DEFAULT_ENDPOINT,
            'model' => 'qwen-plus',
            'api_key' => 'first-qwen-key',
            'is_active' => '1',
        ])->assertRedirect(route('admin.ai.edit'));

        $raw = DB::table('ai_configurations')->value('api_key');
        $this->assertNotSame('first-qwen-key', $raw);
        $this->assertSame('first-qwen-key', AiConfiguration::current()->api_key);

        $this->actingAs($admin)->put(route('admin.ai.update'), [
            'endpoint' => AiConfiguration::DEFAULT_ENDPOINT,
            'model' => 'qwen-plus',
            'api_key' => '',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('first-qwen-key', AiConfiguration::current()->api_key);
    }

    public function test_test_connection_calls_qwen_with_bearer_token_and_records_success(): void
    {
        $admin = User::factory()->admin()->create();
        AiConfiguration::create([
            'provider' => 'qwen',
            'endpoint' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions',
            'model' => 'qwen-plus',
            'api_key' => 'secret-key',
            'is_active' => true,
        ]);

        Http::fake([
            'dashscope-intl.aliyuncs.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'OK']]],
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('admin.ai.test-connection'), [
            'endpoint' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions',
            'model' => 'qwen-plus',
            'api_key' => '',
            'is_active' => '1',
        ])->assertRedirect(route('admin.ai.edit'))
            ->assertSessionHas('success');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer secret-key')
                && $request['model'] === 'qwen-plus';
        });

        $this->assertSame('success', AiConfiguration::current()->last_test_status);
    }

    public function test_test_connection_reports_real_api_failure_without_leaking_key(): void
    {
        $admin = User::factory()->admin()->create();
        AiConfiguration::create([
            'provider' => 'qwen',
            'endpoint' => AiConfiguration::DEFAULT_ENDPOINT,
            'model' => 'qwen-plus',
            'api_key' => 'secret-key',
            'is_active' => true,
        ]);

        Http::fake([
            'dashscope-intl.aliyuncs.com/*' => Http::response([
                'error' => ['message' => 'Incorrect API key provided.'],
            ], 401),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.ai.test-connection'), [
            'endpoint' => AiConfiguration::DEFAULT_ENDPOINT,
            'model' => 'qwen-plus',
            'api_key' => '',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.ai.edit'))->assertSessionHas('error');
        $this->assertStringNotContainsString('secret-key', (string) session('error'));
        $this->assertSame('failed', AiConfiguration::current()->last_test_status);
    }
}
