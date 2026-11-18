<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ChatHistory;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_displays_aggregate_counts(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create();
        User::factory()->inactive()->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $this->assertSame(4, $response->viewData('totalUsers'));
        $this->assertSame(3, $response->viewData('activeUsers'));
        $this->assertSame(1, $response->viewData('inactiveUsers'));
    }

    public function test_admin_can_search_listed_users(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Findable Person', 'email' => 'find@example.com']);
        User::factory()->create(['name' => 'Someone Else', 'email' => 'other@example.com']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'Findable']));

        $response->assertOk();
        $response->assertSee('Findable Person');
        $response->assertDontSee('Someone Else');
    }

    public function test_admin_can_activate_and_deactivate_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.toggle-status', $user->id))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('activity_logs', [
            'admin_id' => $admin->id,
            'target_user_id' => $user->id,
            'action' => 'Deactivated account',
        ]);

        $this->actingAs($admin)->patch(route('admin.users.toggle-status', $user->id));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'active']);
    }

    public function test_deactivated_user_can_no_longer_log_in(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => 'Secret123',
        ]);

        $this->actingAs($admin)->patch(route('admin.users.toggle-status', $user->id));
        auth()->logout();

        $this->post(route('login.store'), [
            'email' => 'member@example.com',
            'password' => 'Secret123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deleting_user_cascades_private_data_and_keeps_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        Task::factory()->create(['user_id' => $user->id]);
        Schedule::factory()->create(['user_id' => $user->id]);
        ChatHistory::create(['user_id' => $user->id, 'message' => 'Hi', 'response' => 'Hello']);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user->id))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('tasks', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('schedules', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('chat_histories', ['user_id' => $user->id]);

        $log = ActivityLog::where('admin_id', $admin->id)->where('action', 'Deleted account')->first();
        $this->assertNotNull($log);
    }

    public function test_admin_cannot_view_private_user_content(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'Private Person']);

        Task::factory()->create(['user_id' => $user->id, 'title' => 'Top secret task']);
        Schedule::factory()->create(['user_id' => $user->id, 'title' => 'Top secret schedule']);
        ChatHistory::create([
            'user_id' => $user->id,
            'message' => 'Top secret chat message',
            'response' => 'Top secret chat response',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.show', $user->id));

        $response->assertOk();
        $response->assertSee('Private Person');
        $response->assertDontSee('Top secret task');
        $response->assertDontSee('Top secret schedule');
        $response->assertDontSee('Top secret chat message');
        $response->assertDontSee('Top secret chat response');

        $this->actingAs($admin)
            ->get(route('user.tasks.index'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_statistics_are_aggregated_only(): void
    {
        $admin = User::factory()->admin()->create();
        $users = User::factory()->count(2)->create();

        Task::factory()->count(3)->create(['user_id' => $users->first()->id]);
        Schedule::factory()->count(2)->create(['user_id' => $users->last()->id]);

        $response = $this->actingAs($admin)->get(route('admin.statistics'));

        $response->assertOk();
        $this->assertSame(2, $response->viewData('totalUsers'));
        $this->assertSame(3, $response->viewData('totalTasks'));
        $this->assertSame(2, $response->viewData('totalSchedules'));
    }

    public function test_admin_accounts_are_not_listed_in_user_management(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Super Admin']);
        User::factory()->create(['name' => 'Regular Member']);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertSee('Regular Member');
        $response->assertDontSee($admin->email);
    }
}
