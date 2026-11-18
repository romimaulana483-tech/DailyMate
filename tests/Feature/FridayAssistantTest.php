<?php

namespace Tests\Feature;

use App\Models\AiConfiguration;
use App\Models\ChatHistory;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FridayAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_friday(): void
    {
        $this->get(route('friday.index'))->assertRedirect(route('login'));
        $this->postJson(route('friday.send'), ['message' => 'Hello'])->assertUnauthorized();
    }

    public function test_user_and_admin_can_open_friday_page(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($user)->get(route('friday.index'))
            ->assertOk()
            ->assertSee('Friday')
            ->assertSee('Private conversation');

        $this->actingAs($admin)->get(route('friday.index'))
            ->assertOk()
            ->assertSee('Friday')
            ->assertSee('Admin history only');
    }

    public function test_user_friday_only_sends_the_current_users_private_context_to_qwen(): void
    {
        $this->configureQwen();

        $user = User::factory()->create(['name' => 'Alice Example']);
        $other = User::factory()->create();
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Own private task marker',
            'status' => 'todo',
        ]);
        Task::factory()->create([
            'user_id' => $other->id,
            'title' => 'Other user secret marker',
            'status' => 'todo',
        ]);

        Http::fake([
            'dashscope-intl.aliyuncs.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Kerjakan Own private task marker terlebih dahulu.']]],
            ], 200),
        ]);

        $this->actingAs($user)->postJson(route('friday.send'), [
            'message' => 'What should I work on first?',
        ])->assertOk()
            ->assertJsonPath('response', 'Kerjakan Own private task marker terlebih dahulu.');

        Http::assertSent(function ($request) {
            $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($body, 'Own private task marker')
                && ! str_contains($body, 'Other user secret marker')
                && str_contains($body, 'Role: User')
                && str_contains($body, 'Bahasa Indonesia');
        });

        $this->assertDatabaseHas('chat_histories', [
            'user_id' => $user->id,
            'message' => 'What should I work on first?',
        ]);
    }

    public function test_admin_friday_context_is_aggregate_only(): void
    {
        $this->configureQwen();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Never expose this private task',
            'status' => 'todo',
        ]);

        Http::fake([
            'dashscope-intl.aliyuncs.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Mulai dengan memeriksa ringkasan sistem.']]],
            ], 200),
        ]);

        $this->actingAs($admin)->postJson(route('friday.send'), [
            'message' => 'Bantu saya menyusun rencana kerja admin',
        ])->assertOk()
            ->assertJsonPath('response', 'Mulai dengan memeriksa ringkasan sistem.');

        Http::assertSent(function ($request) {
            $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($body, 'Data resmi agregat')
                && str_contains($body, 'Total akun')
                && ! str_contains($body, 'Never expose this private task')
                && str_contains($body, 'Role: Admin');
        });
    }

    public function test_offline_friday_refuses_clearly_unrelated_topics(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('friday.send'), [
            'message' => 'Tell me a joke about the weather',
        ])->assertOk()
            ->assertJsonPath('response', fn ($value) => str_starts_with($value, 'Maaf,')
                && str_contains($value, 'hanya dapat membantu pertanyaan seputar DailyMate'));
    }

    public function test_configured_friday_does_not_send_out_of_scope_questions_to_qwen(): void
    {
        $this->configureQwen();
        $user = User::factory()->create();

        Http::fake();

        $this->actingAs($user)->postJson(route('friday.send'), [
            'message' => 'Write me a poem about the weather',
        ])->assertOk();

        Http::assertNothingSent();
        $response = (string) $user->chatHistories()->latest('id')->value('response');

        $this->assertStringStartsWith('Maaf,', $response);
        $this->assertStringContainsString(
            'hanya dapat membantu pertanyaan seputar DailyMate',
            $response
        );
    }

    public function test_friday_history_is_scoped_to_each_account(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        ChatHistory::create(['user_id' => $user->id, 'message' => 'User secret', 'response' => 'R1']);
        ChatHistory::create(['user_id' => $admin->id, 'message' => 'Admin note', 'response' => 'R2']);

        $this->actingAs($user)->getJson(route('friday.history'))
            ->assertOk()
            ->assertJsonCount(1, 'histories')
            ->assertJsonPath('histories.0.message', 'User secret')
            ->assertDontSee('Admin note');

        $this->actingAs($admin)->getJson(route('friday.history'))
            ->assertOk()
            ->assertJsonCount(1, 'histories')
            ->assertJsonPath('histories.0.message', 'Admin note')
            ->assertDontSee('User secret');
    }

    private function configureQwen(): AiConfiguration
    {
        return AiConfiguration::create([
            'provider' => 'qwen',
            'endpoint' => AiConfiguration::DEFAULT_ENDPOINT,
            'model' => 'qwen-plus',
            'api_key' => 'test-qwen-key',
            'is_active' => true,
        ]);
    }
}
