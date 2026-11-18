<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueryParameterRobustnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_filter_arrays_do_not_cause_server_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('user.tasks.index', [
                'q' => ['unexpected'],
                'status' => ['todo'],
                'priority' => ['high'],
                'category' => ['Work'],
                'sort' => ['latest'],
            ]))
            ->assertOk();
    }

    public function test_schedule_and_calendar_arrays_do_not_cause_server_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('user.schedules.index', [
                'date' => ['2026-11-15'],
                'month' => ['2026-11'],
            ]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('user.schedules.create', ['date' => ['2026-11-15']]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('user.calendar', ['month' => ['2026-11']]))
            ->assertOk();
    }

    public function test_admin_user_filter_arrays_do_not_cause_server_errors(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index', [
                'q' => ['unexpected'],
                'status' => ['active'],
            ]))
            ->assertOk();
    }
}
