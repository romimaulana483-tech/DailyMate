<?php

namespace Tests\Feature;

use App\Models\ReportLog;
use App\Models\ReportSetting;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsAppConfiguration;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_report_page_and_settings_are_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Admin Reports')
            ->assertSee('Recipient WhatsApp number');

        $this->actingAs($admin)->put(route('admin.reports.update'), [
            'recipient_number' => '08123456789',
            'send_time' => '08:00',
            'is_active' => '1',
        ])->assertSessionHasErrors('recipient_number');

        $this->actingAs($admin)->put(route('admin.reports.update'), [
            'recipient_number' => '+6281234567890',
            'send_time' => '08:00',
            'is_active' => '1',
            'daily_enabled' => '1',
        ])->assertRedirect(route('admin.reports.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('report_settings', [
            'recipient_number' => '+6281234567890',
            'is_active' => 1,
            'daily_enabled' => 1,
        ]);
    }

    public function test_manual_report_uses_aggregate_data_only_and_logs_delivery(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Private task title must never be in the report',
            'status' => 'todo',
        ]);

        ReportSetting::create([
            'recipient_number' => '+6281234567890',
            'is_active' => true,
            'send_time' => '08:00',
        ]);
        $this->configureWhatsApp();

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.report']],
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('admin.reports.send'))
            ->assertRedirect(route('admin.reports.index'))
            ->assertSessionHas('success');

        Http::assertSent(function ($request) {
            $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($body, 'DailyMate Admin Report')
                && str_contains($body, 'Tasks:')
                && str_contains($body, 'aggregate data only')
                && ! str_contains($body, 'Private task title must never be in the report');
        });

        $log = ReportLog::first();
        $this->assertSame('manual', $log->frequency);
        $this->assertSame('sent', $log->status);
        $this->assertSame('wamid.report', $log->provider_message_id);
    }

    public function test_manual_report_fails_cleanly_when_whatsapp_is_not_configured(): void
    {
        $admin = User::factory()->admin()->create();
        ReportSetting::create([
            'recipient_number' => '+6281234567890',
            'send_time' => '08:00',
        ]);

        Http::fake();

        $this->actingAs($admin)->post(route('admin.reports.send'))
            ->assertRedirect(route('admin.reports.index'))
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame('failed', ReportLog::first()->status);
    }

    public function test_scheduled_daily_report_is_sent_once_per_day(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 08:01:00'));
        $admin = User::factory()->admin()->create();
        ReportSetting::create([
            'recipient_number' => '+6281234567890',
            'is_active' => true,
            'daily_enabled' => true,
            'send_time' => '08:00',
        ]);
        $this->configureWhatsApp();

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.daily']],
            ], 200),
        ]);

        $this->artisan('reports:send-due')->assertSuccessful();
        $this->artisan('reports:send-due')->assertSuccessful();

        $this->assertSame(1, ReportLog::where('frequency', 'daily')->count());
        $this->assertNotNull(ReportSetting::current()->last_daily_sent_on);
        $this->actingAs($admin)->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Daily')
            ->assertSee('Sent');
    }

    public function test_report_page_does_not_show_private_task_titles(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'A very private task title',
        ]);

        $this->actingAs($admin)->get(route('admin.reports.index'))
            ->assertOk()
            ->assertDontSee('A very private task title');
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
