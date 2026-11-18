<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeadlineReminderOffsetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_selected_offset_is_sent_once_at_the_right_time(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        $this->configureWhatsApp();

        $user = User::factory()->create([
            'whatsapp_number' => '+6281234567890',
            'whatsapp_notifications_enabled' => true,
            'whatsapp_consent_at' => now(),
            'whatsapp_reminder_offsets' => [60, 180],
        ]);

        $task = Task::factory()->create([
            'user_id' => $user->id,
            'status' => 'todo',
            'deadline' => now()->addHours(2),
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.offset']]], 200),
        ]);

        // At 10:00 the 3-hour reminder is due, but the 1-hour reminder is not.
        $this->artisan('whatsapp:send-reminders')->assertSuccessful();
        $this->assertSame(1, WhatsAppNotification::count());
        Http::assertSent(fn ($request) => str_contains($request['text']['body'] ?? '', '3 jam'));
        $this->assertSame(1, UserNotification::count());

        // Repeating the same run must not duplicate the 3-hour reminder.
        $this->artisan('whatsapp:send-reminders')->assertSuccessful();
        $this->assertSame(1, WhatsAppNotification::count());

        // At 11:00 the 1-hour reminder becomes due.
        $this->travelTo(Carbon::parse('2026-11-16 11:00:00'));
        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        $this->assertSame(2, WhatsAppNotification::count());
        $this->assertSame(2, UserNotification::count());
        $this->assertDatabaseHas('whatsapp_notifications', [
            'user_id' => $user->id,
            'event_type' => 'task_deadline',
            'dedupe_key' => 'task.deadline:'.$task->id.':'.$task->deadline->format('YmdHi').':60',
        ]);
    }

    public function test_completed_task_does_not_receive_another_offset_reminder(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        $this->configureWhatsApp();

        $user = User::factory()->create([
            'whatsapp_number' => '+6281234567890',
            'whatsapp_notifications_enabled' => true,
            'whatsapp_consent_at' => now(),
            'whatsapp_reminder_offsets' => [60, 180],
        ]);

        $task = Task::factory()->create([
            'user_id' => $user->id,
            'status' => 'todo',
            'deadline' => now()->addHours(2),
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.complete']]], 200),
        ]);

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();
        $this->assertSame(1, WhatsAppNotification::count());

        $task->update(['status' => 'completed']);
        $this->travelTo(Carbon::parse('2026-11-16 11:00:00'));
        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        $this->assertSame(1, WhatsAppNotification::count());
        $this->assertSame(1, UserNotification::count());
    }

    public function test_missing_whatsapp_configuration_still_records_in_app_notification(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));

        $user = User::factory()->create([
            'whatsapp_number' => '+6281234567890',
            'whatsapp_notifications_enabled' => true,
            'whatsapp_consent_at' => now(),
            'whatsapp_reminder_offsets' => [60],
        ]);

        Task::factory()->create([
            'user_id' => $user->id,
            'status' => 'todo',
            'deadline' => now()->addMinutes(30),
        ]);

        Queue::fake();
        Http::fake();

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        $this->assertSame(0, WhatsAppNotification::count());
        $this->assertSame(1, UserNotification::count());
        Http::assertNothingSent();
    }

    private function configureWhatsApp(): WhatsAppConfiguration
    {
        return WhatsAppConfiguration::create([
            'provider' => 'meta_cloud',
            'endpoint' => 'https://graph.facebook.com/v20.0',
            'token' => 'test-token',
            'phone_number_id' => '123456',
            'is_active' => true,
        ]);
    }
}
