<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_feedback_and_only_sees_their_own_history(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Feedback::create([
            'user_id' => $other->id,
            'subject' => 'Another user subject marker',
            'category' => 'saran',
            'message' => 'Another user message marker that must stay private.',
            'status' => 'pending',
        ]);

        $this->actingAs($user)->post(route('user.feedback.store'), [
            'subject' => 'My feedback subject',
            'category' => 'bug',
            'message' => 'The task page does not refresh after I change a status.',
        ])->assertRedirect(route('user.feedback.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('feedbacks', [
            'user_id' => $user->id,
            'subject' => 'My feedback subject',
            'category' => 'bug',
            'status' => 'pending',
        ]);

        $this->actingAs($user)->get(route('user.feedback.index'))
            ->assertOk()
            ->assertSee('My feedback subject')
            ->assertDontSee('Another user subject marker')
            ->assertDontSee('Another user message marker');
    }

    public function test_feedback_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('user.feedback.store'), [
            'subject' => 'x',
            'category' => 'unknown',
            'message' => 'short',
        ])->assertSessionHasErrors(['subject', 'category', 'message']);

        $this->assertDatabaseCount('feedbacks', 0);
    }

    public function test_admin_can_review_and_respond_to_feedback_and_user_sees_the_response(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $feedback = Feedback::create([
            'user_id' => $user->id,
            'subject' => 'Please add a dark mode',
            'category' => 'fitur',
            'message' => 'A dark mode would help me work at night.',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->get(route('admin.feedback.index'))
            ->assertOk()
            ->assertSee('Please add a dark mode')
            ->assertSee($user->name);

        $this->actingAs($admin)->patch(route('admin.feedback.update', $feedback->id), [
            'status' => 'in_progress',
            'admin_response' => 'Thank you. Dark mode is being planned for the next release.',
        ])->assertRedirect(route('admin.feedback.show', $feedback->id))
            ->assertSessionHas('success');

        $feedback->refresh();
        $this->assertSame('in_progress', $feedback->status);
        $this->assertSame($admin->id, $feedback->responded_by);
        $this->assertNotNull($feedback->responded_at);
        $this->assertDatabaseHas('activity_logs', [
            'admin_id' => $admin->id,
            'target_user_id' => $user->id,
        ]);

        $this->actingAs($user)->get(route('user.feedback.index'))
            ->assertOk()
            ->assertSee('Dark mode is being planned')
            ->assertSee('In Progress');
    }

    public function test_user_cannot_access_admin_feedback_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.feedback.index'))
            ->assertRedirect(route('user.dashboard'));
    }

    public function test_admin_cannot_open_user_feedback_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('user.feedback.index'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_feedback_filters_work(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        Feedback::create([
            'user_id' => $user->id,
            'subject' => 'Bug marker',
            'category' => 'bug',
            'message' => 'A detailed bug report for filtering.',
            'status' => 'pending',
        ]);
        Feedback::create([
            'user_id' => $user->id,
            'subject' => 'Suggestion marker',
            'category' => 'saran',
            'message' => 'A detailed suggestion for filtering.',
            'status' => 'resolved',
        ]);

        $this->actingAs($admin)->get(route('admin.feedback.index', ['category' => 'bug']))
            ->assertOk()
            ->assertSee('Bug marker')
            ->assertDontSee('Suggestion marker');

        $this->actingAs($admin)->get(route('admin.feedback.index', ['status' => 'resolved']))
            ->assertOk()
            ->assertSee('Suggestion marker')
            ->assertDontSee('Bug marker');
    }
}
