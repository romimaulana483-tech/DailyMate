<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_statistics_only_use_own_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Task::factory()->count(2)->completed()->create(['user_id' => $user->id]);
        Task::factory()->pending()->create(['user_id' => $user->id]);
        Task::factory()->count(4)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->get(route('user.statistics'));

        $response->assertOk();
        $this->assertSame(3, $response->viewData('totalTasks'));
        $this->assertSame(2, $response->viewData('completedTasks'));
        $this->assertSame(1, $response->viewData('pendingTasks'));
        $this->assertSame(67, $response->viewData('completionRate'));
    }

    public function test_user_dashboard_uses_authenticated_users_data(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Task::factory()->count(2)->completed()->create(['user_id' => $user->id]);
        Task::factory()->count(3)->pending()->create(['user_id' => $user->id]);
        Task::factory()->count(5)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->get(route('user.dashboard'));

        $response->assertOk();
        $this->assertSame(5, $response->viewData('totalTasks'));
        $this->assertSame(2, $response->viewData('completedTasks'));
        $this->assertSame(3, $response->viewData('pendingTasks'));
        $this->assertSame(40, $response->viewData('completionRate'));
    }

    public function test_empty_statistics_do_not_divide_by_zero(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('user.statistics'));

        $response->assertOk();
        $this->assertSame(0, $response->viewData('completionRate'));
    }
}
