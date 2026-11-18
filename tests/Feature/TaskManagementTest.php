<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_only_sees_their_own_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Task::factory()->create(['user_id' => $user->id, 'title' => 'My Private Task']);
        Task::factory()->create(['user_id' => $other->id, 'title' => 'Other User Task']);

        $response = $this->actingAs($user)->get(route('user.tasks.index'));

        $response->assertOk();
        $response->assertSee('My Private Task');
        $response->assertDontSee('Other User Task');
    }

    public function test_user_can_create_a_task(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('user.tasks.store'), [
            'title' => 'Finish assignment',
            'description' => 'Complete the Laravel module.',
            'category' => 'Study',
            'priority' => 'high',
            'status' => 'todo',
            'deadline' => now()->addDay()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('user.tasks.index'));
        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Finish assignment',
            'priority' => 'high',
        ]);
    }

    public function test_task_validation_errors_are_shown(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('user.tasks.create'))
            ->post(route('user.tasks.store'), [
                'title' => '',
                'priority' => 'urgent',
                'status' => 'unknown',
            ]);

        $response->assertRedirect(route('user.tasks.create'));
        $response->assertSessionHasErrors(['title', 'priority', 'status']);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_user_can_view_and_update_own_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Old title']);

        $this->actingAs($user)
            ->get(route('user.tasks.show', $task->id))
            ->assertOk()
            ->assertSee('Old title');

        $response = $this->actingAs($user)->put(route('user.tasks.update', $task->id), [
            'title' => 'New title',
            'description' => 'Updated',
            'category' => 'Work',
            'priority' => 'medium',
            'status' => 'in_progress',
            'deadline' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('user.tasks.show', $task->id));
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'New title',
            'status' => 'in_progress',
        ]);
    }

    public function test_user_can_complete_and_delete_own_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patch(route('user.tasks.status', $task->id), ['status' => 'completed'])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'completed']);

        $this->actingAs($user)
            ->delete(route('user.tasks.destroy', $task->id))
            ->assertRedirect(route('user.tasks.index'));

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_user_cannot_view_update_or_delete_another_users_task(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)->get(route('user.tasks.show', $task->id))->assertNotFound();
        $this->actingAs($user)->get(route('user.tasks.edit', $task->id))->assertNotFound();

        $this->actingAs($user)->put(route('user.tasks.update', $task->id), [
            'title' => 'Hijacked',
            'priority' => 'low',
            'status' => 'todo',
        ])->assertNotFound();

        $this->actingAs($user)->patch(route('user.tasks.status', $task->id), [
            'status' => 'completed',
        ])->assertNotFound();

        $this->actingAs($user)->delete(route('user.tasks.destroy', $task->id))->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => $task->title]);
    }

    public function test_tasks_can_be_searched_filtered_and_sorted(): void
    {
        $user = User::factory()->create();

        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Completed report',
            'status' => 'completed',
            'priority' => 'low',
            'category' => 'Work',
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Pending essay',
            'status' => 'todo',
            'priority' => 'high',
            'category' => 'Study',
        ]);

        $this->actingAs($user)
            ->get(route('user.tasks.index', ['status' => 'completed']))
            ->assertOk()
            ->assertSee('Completed report')
            ->assertDontSee('Pending essay');

        $this->actingAs($user)
            ->get(route('user.tasks.index', ['q' => 'essay']))
            ->assertOk()
            ->assertSee('Pending essay')
            ->assertDontSee('Completed report');

        $this->actingAs($user)
            ->get(route('user.tasks.index', ['priority' => 'high', 'sort' => 'priority']))
            ->assertOk()
            ->assertSee('Pending essay');
    }
}
