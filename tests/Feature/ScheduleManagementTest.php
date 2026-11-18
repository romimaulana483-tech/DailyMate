<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_only_sees_their_own_schedules(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Schedule::factory()->create(['user_id' => $user->id, 'title' => 'My dentist appointment']);
        Schedule::factory()->create(['user_id' => $other->id, 'title' => 'Secret meeting']);

        $response = $this->actingAs($user)->get(route('user.schedules.index'));

        $response->assertOk();
        $response->assertSee('My dentist appointment');
        $response->assertDontSee('Secret meeting');
    }

    public function test_user_can_create_a_schedule(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('user.schedules.store'), [
            'title' => 'Study session',
            'description' => 'Prepare for the exam',
            'schedule_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $response->assertRedirect(route('user.schedules.index'));
        $this->assertDatabaseHas('schedules', [
            'user_id' => $user->id,
            'title' => 'Study session',
            'start_time' => '09:00',
        ]);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('user.schedules.create'))
            ->post(route('user.schedules.store'), [
                'title' => 'Invalid schedule',
                'schedule_date' => now()->toDateString(),
                'start_time' => '12:00',
                'end_time' => '11:00',
            ]);

        $response->assertRedirect(route('user.schedules.create'));
        $response->assertSessionHasErrors('end_time');
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_user_can_update_and_delete_own_schedule(): void
    {
        $user = User::factory()->create();
        $schedule = Schedule::factory()->create(['user_id' => $user->id, 'title' => 'Old schedule']);

        $this->actingAs($user)->put(route('user.schedules.update', $schedule->id), [
            'title' => 'Updated schedule',
            'description' => null,
            'schedule_date' => now()->addDays(2)->toDateString(),
            'start_time' => '13:00',
            'end_time' => '14:30',
        ])->assertRedirect(route('user.schedules.index'));

        $this->assertDatabaseHas('schedules', ['id' => $schedule->id, 'title' => 'Updated schedule']);

        $this->actingAs($user)
            ->delete(route('user.schedules.destroy', $schedule->id))
            ->assertRedirect(route('user.schedules.index'));

        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_user_cannot_modify_another_users_schedule(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $schedule = Schedule::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)->get(route('user.schedules.edit', $schedule->id))->assertNotFound();
        $this->actingAs($user)->put(route('user.schedules.update', $schedule->id), [
            'title' => 'Hijacked',
            'schedule_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:00',
        ])->assertNotFound();
        $this->actingAs($user)->delete(route('user.schedules.destroy', $schedule->id))->assertNotFound();

        $this->assertDatabaseHas('schedules', ['id' => $schedule->id]);
    }

    public function test_schedules_can_be_filtered_by_date(): void
    {
        $user = User::factory()->create();
        $date = now()->addDays(3)->toDateString();

        Schedule::factory()->create([
            'user_id' => $user->id,
            'title' => 'Target day schedule',
            'schedule_date' => $date,
        ]);
        Schedule::factory()->create([
            'user_id' => $user->id,
            'title' => 'Different day schedule',
            'schedule_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('user.schedules.index', ['date' => $date]))
            ->assertOk()
            ->assertSee('Target day schedule')
            ->assertDontSee('Different day schedule');
    }

    public function test_user_can_open_calendar_and_only_their_events_are_embedded(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Schedule::factory()->create([
            'user_id' => $user->id,
            'title' => 'My calendar event',
            'schedule_date' => now()->toDateString(),
        ]);
        Schedule::factory()->create([
            'user_id' => $other->id,
            'title' => 'Other calendar event',
            'schedule_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('user.calendar'))
            ->assertOk()
            ->assertSee('My calendar event')
            ->assertDontSee('Other calendar event');
    }

    public function test_calendar_month_navigation_returns_only_the_calendar_fragment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('user.calendar', ['month' => '2026-12']))
            ->assertOk()
            ->assertJsonPath('month_label', 'December 2026')
            ->assertJsonStructure([
                'month_label',
                'grid_html',
                'events',
                'initial_date',
                'prev_url',
                'next_url',
            ])
            ->assertJsonPath('initial_date', '2026-12-01');
    }

    public function test_mini_calendar_month_navigation_returns_only_the_calendar_fragment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('user.schedules.index', ['month' => '2026-12']))
            ->assertOk()
            ->assertJsonPath('month_label', 'December 2026')
            ->assertJsonStructure([
                'month_label',
                'grid_html',
                'prev_url',
                'next_url',
            ]);
    }
}
