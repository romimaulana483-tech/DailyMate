<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_only_sees_their_own_notifications_and_unread_count(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        UserNotification::create([
            'user_id' => $user->id,
            'dedupe_key' => 'mine',
            'type' => 'task_deadline',
            'title' => 'My reminder marker',
            'body' => 'Private body marker',
        ]);
        UserNotification::create([
            'user_id' => $other->id,
            'dedupe_key' => 'theirs',
            'type' => 'task_deadline',
            'title' => 'Another user marker',
            'body' => 'Not mine',
        ]);

        $this->actingAs($user)->get(route('user.notifications.index'))
            ->assertOk()
            ->assertSee('My reminder marker')
            ->assertSee('Private body marker')
            ->assertDontSee('Another user marker')
            ->assertDontSee('Not mine');
    }

    public function test_user_can_mark_own_notification_as_read_but_not_another_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = UserNotification::create([
            'user_id' => $user->id,
            'dedupe_key' => 'mine-read',
            'type' => 'schedule_start',
            'title' => 'Mine',
            'body' => 'Body',
        ]);
        $theirs = UserNotification::create([
            'user_id' => $other->id,
            'dedupe_key' => 'theirs-read',
            'type' => 'schedule_start',
            'title' => 'Theirs',
            'body' => 'Body',
        ]);

        $this->actingAs($user)
            ->patch(route('user.notifications.read', $mine->id))
            ->assertRedirect();

        $this->assertNotNull($mine->fresh()->read_at);

        $this->actingAs($user)
            ->patch(route('user.notifications.read', $theirs->id))
            ->assertNotFound();

        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_mark_all_as_read_only_updates_the_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        UserNotification::create(['user_id' => $user->id, 'dedupe_key' => 'a', 'type' => 'task_deadline', 'title' => 'A', 'body' => 'A']);
        UserNotification::create(['user_id' => $user->id, 'dedupe_key' => 'b', 'type' => 'task_deadline', 'title' => 'B', 'body' => 'B']);
        UserNotification::create(['user_id' => $other->id, 'dedupe_key' => 'c', 'type' => 'task_deadline', 'title' => 'C', 'body' => 'C']);

        $this->actingAs($user)
            ->patch(route('user.notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, UserNotification::where('user_id', $user->id)->whereNull('read_at')->count());
        $this->assertSame(1, UserNotification::where('user_id', $other->id)->whereNull('read_at')->count());
    }

    public function test_user_can_save_the_four_deadline_reminder_offsets(): void
    {
        $user = User::factory()->create([
            'whatsapp_number' => '+6281234567890',
            'whatsapp_notifications_enabled' => true,
            'whatsapp_consent_at' => now(),
        ]);

        $this->actingAs($user)->put(route('user.whatsapp.update'), [
            'whatsapp_number' => '+6281234567890',
            'whatsapp_notifications_enabled' => '1',
            'whatsapp_consent' => '1',
            'whatsapp_reminder_offsets' => [60, 180, 720, 1440],
        ])->assertSessionHasNoErrors();

        $this->assertSame([60, 180, 720, 1440], $user->fresh()->reminderOffsets());
    }

    public function test_admin_cannot_open_user_notifications(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('user.notifications.index'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_notification_page_does_not_show_private_task_content(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        \App\Models\Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Private task title must not appear',
        ]);

        $this->actingAs($admin)->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('System Notifications')
            ->assertDontSee('Private task title must not appear');
    }
}
