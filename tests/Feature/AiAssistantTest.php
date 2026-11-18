<?php

namespace Tests\Feature;

use App\Models\ChatHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_gets_a_fallback_reply_and_chat_is_saved(): void
    {
        config(['services.gemini.key' => null]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('user.ai.send'), [
            'message' => 'What should I prioritise today?',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['id', 'message', 'response', 'time']);
        $this->assertNotEmpty($response->json('response'));

        $this->assertDatabaseHas('chat_histories', [
            'user_id' => $user->id,
            'message' => 'What should I prioritise today?',
        ]);
    }

    public function test_ai_message_is_required_and_limited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('user.ai.send'), ['message' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->actingAs($user)
            ->postJson(route('user.ai.send'), ['message' => str_repeat('a', 2001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_user_only_sees_their_own_chat_history(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        ChatHistory::create([
            'user_id' => $user->id,
            'message' => 'My secret question',
            'response' => 'My private answer',
        ]);
        ChatHistory::create([
            'user_id' => $other->id,
            'message' => 'Another user secret',
            'response' => 'Another private answer',
        ]);

        $response = $this->actingAs($user)->get(route('user.ai.index'));

        $response->assertOk();
        $response->assertSee('My secret question');
        $response->assertDontSee('Another user secret');
    }

    public function test_clearing_history_deletes_only_own_messages(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        ChatHistory::create(['user_id' => $user->id, 'message' => 'Mine', 'response' => 'Reply']);
        ChatHistory::create(['user_id' => $other->id, 'message' => 'Theirs', 'response' => 'Reply']);

        $this->actingAs($user)
            ->delete(route('user.ai.clear'))
            ->assertRedirect(route('user.ai.index'));

        $this->assertDatabaseMissing('chat_histories', ['user_id' => $user->id]);
        $this->assertDatabaseHas('chat_histories', ['user_id' => $other->id]);
    }

    public function test_admin_cannot_use_user_ai_endpoint(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('user.ai.send'), ['message' => 'Show me user chats'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseCount('chat_histories', 0);
    }

    public function test_history_shows_only_the_latest_100_messages_in_order(): void
    {
        $user = User::factory()->create();

        ChatHistory::create([
            'user_id' => $user->id,
            'message' => 'OLDEST-DROPPED-MARKER',
            'response' => 'Old reply',
        ]);

        for ($i = 1; $i <= 100; $i++) {
            ChatHistory::create([
                'user_id' => $user->id,
                'message' => 'History message '.$i,
                'response' => 'Reply '.$i,
            ]);
        }

        $response = $this->actingAs($user)->get(route('user.ai.index'));

        $response->assertOk();
        $response->assertDontSee('OLDEST-DROPPED-MARKER');

        $histories = $response->viewData('histories');
        $this->assertCount(100, $histories);
        $this->assertSame('History message 1', $histories->first()->message);
        $this->assertSame('History message 100', $histories->last()->message);
    }
}
