<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_task_deadline_is_sent_once_with_private_task_content(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));

        $this->configureWhatsApp();
        $user = $this->notifiableUser();
        $task = Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Menyelesaikan Laporan Praktikum Database',
            'priority' => 'high',
            'status' => 'todo',
            'deadline' => now()->addMinutes(30),
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.deadline']]], 200),
        ]);

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();
        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        $this->assertDatabaseCount('whatsapp_notifications', 1);
        $notification = WhatsAppNotification::first();
        $this->assertSame('sent', $notification->status);
        $this->assertSame('task_deadline', $notification->event_type);
        $this->assertSame($task->id, $notification->notifiable_id);
        $this->assertSame('wamid.deadline', $notification->provider_message_id);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($task) {
            $body = $request['text']['body'] ?? '';

            return str_contains($body, $task->title)
                && str_contains($body, 'Prioritas: High')
                && str_contains($body, 'Hari ini pukul 10.30');
        });
    }

    public function test_schedule_reminder_is_sent_before_activity_starts(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));

        $this->configureWhatsApp();
        $user = $this->notifiableUser();
        $schedule = Schedule::factory()->create([
            'user_id' => $user->id,
            'title' => 'Belajar Pemrograman',
            'schedule_date' => today()->toDateString(),
            'start_time' => '10:10:00',
            'end_time' => '11:00:00',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.schedule']]], 200),
        ]);

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        $notification = WhatsAppNotification::first();
        $this->assertNotNull($notification);
        $this->assertSame('schedule_start', $notification->event_type);
        $this->assertSame($schedule->id, $notification->notifiable_id);
        $this->assertSame('sent', $notification->status);
    }

    public function test_high_priority_summary_is_sent_once_per_day(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 08:00:00'));

        $this->configureWhatsApp();
        $user = $this->notifiableUser();
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'High priority report',
            'priority' => 'high',
            'status' => 'todo',
            'deadline' => now()->addDay(),
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.high']]], 200),
        ]);

        $this->artisan('whatsapp:send-reminders --high-priority')->assertSuccessful();
        $this->artisan('whatsapp:send-reminders --high-priority')->assertSuccessful();

        $this->assertDatabaseCount('whatsapp_notifications', 1);
        $this->assertDatabaseHas('whatsapp_notifications', [
            'user_id' => $user->id,
            'event_type' => 'task_high_priority',
            'status' => 'sent',
        ]);
        Http::assertSentCount(1);
    }

    public function test_disabled_user_receives_no_reminder(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));

        $this->configureWhatsApp();
        $user = User::factory()->create([
            'whatsapp_number' => '+6281234567890',
            'whatsapp_notifications_enabled' => false,
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'status' => 'todo',
            'deadline' => now()->addMinutes(30),
        ]);

        Http::fake();

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('whatsapp_notifications', 0);
    }

    public function test_user_without_number_receives_no_reminder(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));

        $this->configureWhatsApp();
        $user = User::factory()->create([
            'whatsapp_number' => null,
            'whatsapp_notifications_enabled' => true,
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'status' => 'todo',
            'deadline' => now()->addMinutes(30),
        ]);

        Http::fake();

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('whatsapp_notifications', 0);
    }

    public function test_failed_delivery_is_logged(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));

        $this->configureWhatsApp();
        $user = $this->notifiableUser();
        Task::factory()->create([
            'user_id' => $user->id,
            'status' => 'todo',
            'deadline' => now()->addMinutes(30),
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Temporary failure']], 500),
        ]);

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        $notification = WhatsAppNotification::first();
        $this->assertNotNull($notification);
        $this->assertSame('failed', $notification->status);
        $this->assertNotNull($notification->error_message);
        $this->assertSame(1, $notification->attempts);
    }

    public function test_reminder_is_pushed_to_queue_when_enabled(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));

        $this->configureWhatsApp();
        $user = $this->notifiableUser();
        Task::factory()->create([
            'user_id' => $user->id,
            'status' => 'todo',
            'deadline' => now()->addMinutes(30),
        ]);

        Queue::fake();
        Http::fake();

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        Queue::assertPushed(SendWhatsAppNotification::class);
        Http::assertNothingSent();
        $this->assertDatabaseHas('whatsapp_notifications', ['status' => 'pending']);
    }

    public function test_missing_configuration_prevents_reminders(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));

        $user = $this->notifiableUser();
        Task::factory()->create([
            'user_id' => $user->id,
            'status' => 'todo',
            'deadline' => now()->addMinutes(30),
        ]);

        Http::fake();

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('whatsapp_notifications', 0);
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

    private function notifiableUser(): User
    {
        return User::factory()->create([
            'whatsapp_number' => '+6281234567890',
            'whatsapp_notifications_enabled' => true,
            'whatsapp_consent_at' => now(),
        ]);
    }
}
