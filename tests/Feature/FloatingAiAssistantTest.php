<?php

namespace Tests\Feature;

use App\Models\ChatHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloatingAiAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_floating_friday_is_available_on_user_and_admin_pages(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('id="aiWidget"', false)
            ->assertSee('floating-ai.js')
            ->assertSee(route('friday.history'), false);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('id="aiWidget"', false)
            ->assertSee('floating-ai.js')
            ->assertSee('Admin assistant', false);
    }

    public function test_history_endpoint_returns_only_the_authenticated_users_messages(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        ChatHistory::create(['user_id' => $user->id, 'message' => 'My private question', 'response' => 'My private reply']);
        ChatHistory::create(['user_id' => $other->id, 'message' => 'Someone else secret', 'response' => 'Not mine']);

        $response = $this->actingAs($user)->getJson(route('user.ai.history'));

        $response->assertOk()
            ->assertJsonCount(1, 'histories')
            ->assertJsonPath('histories.0.message', 'My private question')
            ->assertJsonPath('histories.0.response', 'My private reply')
            ->assertDontSee('Someone else secret');
    }

    public function test_history_endpoint_returns_latest_messages_in_chronological_order(): void
    {
        $user = User::factory()->create();

        ChatHistory::create(['user_id' => $user->id, 'message' => 'OLDEST-DROPPED', 'response' => 'Old']);

        for ($i = 1; $i <= 100; $i++) {
            ChatHistory::create([
                'user_id' => $user->id,
                'message' => 'Message '.$i,
                'response' => 'Reply '.$i,
            ]);
        }

        $response = $this->actingAs($user)->getJson(route('user.ai.history'));

        $response->assertOk()->assertJsonCount(100, 'histories');
        $this->assertSame('Message 1', $response->json('histories.0.message'));
        $this->assertSame('Message 100', $response->json('histories.99.message'));
        $response->assertDontSee('OLDEST-DROPPED');
    }

    public function test_user_can_clear_history_with_json_request(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        ChatHistory::create(['user_id' => $user->id, 'message' => 'Mine', 'response' => 'Reply']);
        ChatHistory::create(['user_id' => $other->id, 'message' => 'Theirs', 'response' => 'Reply']);

        $this->actingAs($user)
            ->deleteJson(route('user.ai.clear'))
            ->assertOk()
            ->assertJson(['message' => 'Conversation history cleared.']);

        $this->assertDatabaseMissing('chat_histories', ['user_id' => $user->id]);
        $this->assertDatabaseHas('chat_histories', ['user_id' => $other->id]);
    }

    public function test_guest_cannot_read_floating_history(): void
    {
        $this->getJson(route('user.ai.history'))
            ->assertUnauthorized();
    }

    public function test_admin_friday_history_is_isolated_from_user_history(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        ChatHistory::create(['user_id' => $user->id, 'message' => 'Private chat', 'response' => 'Private reply']);
        ChatHistory::create(['user_id' => $admin->id, 'message' => 'Admin question', 'response' => 'Admin reply']);

        $this->actingAs($admin)
            ->getJson(route('friday.history'))
            ->assertOk()
            ->assertJsonCount(1, 'histories')
            ->assertJsonPath('histories.0.message', 'Admin question')
            ->assertDontSee('Private chat');

        $this->actingAs($admin)
            ->deleteJson(route('friday.clear'))
            ->assertOk();

        $this->assertDatabaseMissing('chat_histories', ['user_id' => $admin->id]);
        $this->assertDatabaseHas('chat_histories', ['user_id' => $user->id]);
    }
}
