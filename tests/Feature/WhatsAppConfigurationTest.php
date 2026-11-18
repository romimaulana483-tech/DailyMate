<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_whatsapp_configuration_page_without_exposing_token(): void
    {
        $admin = User::factory()->admin()->create();
        WhatsAppConfiguration::create([
            'provider' => 'meta_cloud',
            'endpoint' => 'https://graph.facebook.com/v20.0',
            'token' => 'super-secret-token',
            'phone_number_id' => '123456',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.whatsapp.edit'))
            ->assertOk()
            ->assertSee('WhatsApp API Configuration')
            ->assertSee('Token configured')
            ->assertDontSee('super-secret-token');
    }

    public function test_non_admin_cannot_access_whatsapp_configuration(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.whatsapp.edit'))
            ->assertRedirect(route('user.dashboard'));
    }

    public function test_token_is_encrypted_and_blank_update_keeps_existing_token(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.whatsapp.update'), [
            'provider' => 'meta_cloud',
            'endpoint' => 'https://graph.facebook.com/v20.0',
            'token' => 'first-secret-token',
            'phone_number_id' => '123456',
            'is_active' => '1',
        ])->assertRedirect(route('admin.whatsapp.edit'));

        $rawToken = DB::table('whatsapp_configurations')->value('token');
        $this->assertNotSame('first-secret-token', $rawToken);

        $this->assertSame('first-secret-token', WhatsAppConfiguration::current()->token);

        $this->actingAs($admin)->put(route('admin.whatsapp.update'), [
            'provider' => 'meta_cloud',
            'endpoint' => 'https://graph.facebook.com/v20.0',
            'token' => '',
            'phone_number_id' => '123456',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('first-secret-token', WhatsAppConfiguration::current()->token);
    }

    public function test_test_connection_uses_real_http_request_and_returns_success(): void
    {
        $admin = User::factory()->admin()->create();
        WhatsAppConfiguration::create([
            'provider' => 'fonnte',
            'endpoint' => 'https://api.fonnte.com/send',
            'token' => 'fonnte-token',
            'is_active' => true,
        ]);

        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => true,
                'device' => '6282227097005',
                'device_status' => 'connect',
                'quota' => '78',
                'expired' => '18 November 2029',
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('admin.whatsapp.test-connection'), [
            'provider' => 'fonnte',
            'endpoint' => 'https://api.fonnte.com/send',
            'token' => '',
            'is_active' => '1',
        ])->assertRedirect(route('admin.whatsapp.edit'))
            ->assertSessionHas('success');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.fonnte.com/device'
                && $request->hasHeader('Authorization', 'fonnte-token');
        });
    }

    public function test_fonnte_disconnected_device_is_reported_as_failure(): void
    {
        $admin = User::factory()->admin()->create();
        WhatsAppConfiguration::create([
            'provider' => 'fonnte',
            'endpoint' => 'https://api.fonnte.com/send',
            'token' => 'fonnte-token',
            'is_active' => true,
        ]);

        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => true,
                'device' => '6282227097005',
                'device_status' => 'disconnect',
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('admin.whatsapp.test-connection'), [
            'provider' => 'fonnte',
            'endpoint' => 'https://api.fonnte.com/send',
            'token' => '',
            'is_active' => '1',
        ])->assertRedirect(route('admin.whatsapp.edit'))
            ->assertSessionHas('error');

        $this->assertSame('failed', WhatsAppConfiguration::current()->last_test_status);
    }

    public function test_fonnte_send_uses_documented_form_payload(): void
    {
        $admin = User::factory()->admin()->create();
        WhatsAppConfiguration::create([
            'provider' => 'fonnte',
            'endpoint' => 'https://api.fonnte.com/send',
            'token' => 'fonnte-token',
            'is_active' => true,
        ]);

        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
                'id' => ['80367170'],
                'process' => 'pending',
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('admin.whatsapp.test-notification'), [
            'test_number' => '+6281234567890',
            'test_message' => 'Pesan uji Fonnte.',
        ])->assertRedirect()->assertSessionHas('success');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.fonnte.com/send'
                && $request->hasHeader('Authorization', 'fonnte-token')
                && $request['target'] === '6281234567890'
                && $request['message'] === 'Pesan uji Fonnte.'
                && $request['countryCode'] === '0';
        });
    }

    public function test_fonnte_http_200_with_status_false_is_not_treated_as_success(): void
    {
        $admin = User::factory()->admin()->create();
        WhatsAppConfiguration::create([
            'provider' => 'fonnte',
            'endpoint' => 'https://api.fonnte.com/send',
            'token' => 'fonnte-token',
            'is_active' => true,
        ]);

        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => false,
                'reason' => 'insufficient quota',
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('admin.whatsapp.test-notification'), [
            'test_number' => '+6281234567890',
            'test_message' => 'Pesan uji Fonnte.',
        ])->assertRedirect()->assertSessionHas('error');
    }

    public function test_fonnte_send_uses_api_endpoint_even_when_a_dashboard_url_is_saved(): void
    {
        $admin = User::factory()->admin()->create();
        WhatsAppConfiguration::create([
            'provider' => 'fonnte',
            'endpoint' => 'https://md.fonnte.com/new/device.php',
            'token' => 'fonnte-token',
            'is_active' => true,
        ]);

        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
                'id' => ['80367170'],
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('admin.whatsapp.test-notification'), [
            'test_number' => '+6281234567890',
            'test_message' => 'Pesan uji Fonnte.',
        ])->assertRedirect()->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === WhatsAppConfiguration::FONNTE_SEND_ENDPOINT
            && $request->hasHeader('Authorization', 'fonnte-token'));
    }

    public function test_fonnte_login_page_response_is_reported_as_a_configuration_failure(): void
    {
        $admin = User::factory()->admin()->create();
        WhatsAppConfiguration::create([
            'provider' => 'fonnte',
            'endpoint' => WhatsAppConfiguration::FONNTE_SEND_ENDPOINT,
            'token' => 'fonnte-token',
            'is_active' => true,
        ]);

        Http::fake([
            'api.fonnte.com/*' => Http::response(
                '<!doctype html><html><body><form>Login Back to homepage Login Your Account Your Password</form></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.whatsapp.test-notification'), [
            'test_number' => '+6281234567890',
            'test_message' => 'Pesan uji Fonnte.',
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertStringContainsString('login/web page', session('error'));
        $this->assertStringContainsString(WhatsAppConfiguration::FONNTE_SEND_ENDPOINT, session('error'));
        $this->assertStringNotContainsString('Login Back to homepage', session('error'));
        $this->assertStringNotContainsString('Provider rejected the message', session('error'));
    }

    public function test_saving_fonnte_replaces_a_dashboard_url_with_the_api_endpoint(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.whatsapp.update'), [
            'provider' => 'fonnte',
            'endpoint' => 'https://md.fonnte.com/new/device.php',
            'token' => 'fonnte-token',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            WhatsAppConfiguration::FONNTE_SEND_ENDPOINT,
            WhatsAppConfiguration::current()->endpoint
        );
    }

    public function test_test_connection_returns_real_failure_status(): void
    {
        $admin = User::factory()->admin()->create();
        WhatsAppConfiguration::create([
            'provider' => 'meta_cloud',
            'endpoint' => 'https://graph.facebook.com/v20.0',
            'token' => 'bad-token',
            'phone_number_id' => '123456',
            'is_active' => true,
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 401),
        ]);

        $this->actingAs($admin)->post(route('admin.whatsapp.test-connection'), [
            'provider' => 'meta_cloud',
            'endpoint' => 'https://graph.facebook.com/v20.0',
            'token' => '',
            'phone_number_id' => '123456',
            'is_active' => '1',
        ])->assertRedirect(route('admin.whatsapp.edit'))
            ->assertSessionHas('error');

        $this->assertSame('failed', WhatsAppConfiguration::current()->last_test_status);
    }

    public function test_test_notification_sends_to_the_provider_payload(): void
    {
        $admin = User::factory()->admin()->create();
        WhatsAppConfiguration::create([
            'provider' => 'meta_cloud',
            'endpoint' => 'https://graph.facebook.com/v20.0',
            'token' => 'meta-token',
            'phone_number_id' => '123456',
            'is_active' => true,
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.test']],
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('admin.whatsapp.test-notification'), [
            'test_number' => '+6281234567890',
            'test_message' => 'Pesan uji DailyMate.',
        ])->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://graph.facebook.com/v20.0/123456/messages'
                && $request->hasHeader('Authorization', 'Bearer meta-token')
                && $request['to'] === '6281234567890'
                && $request['text']['body'] === 'Pesan uji DailyMate.';
        });
    }

    public function test_admin_can_save_custom_provider_payload_template(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put(route('admin.whatsapp.update'), [
            'provider' => 'custom',
            'endpoint' => 'https://example.test/send',
            'token' => 'custom-token',
            'is_active' => '1',
            'custom_method' => 'POST',
            'custom_auth_header' => 'X-Api-Key',
            'custom_auth_scheme' => '',
            'custom_payload_template' => '{"recipient":"{{to}}","text":"{{message}}"}',
            'test_method' => 'GET',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('whatsapp_configurations', 1);

        $config = WhatsAppConfiguration::current();
        $this->assertSame('custom', $config->provider);
        $this->assertSame('X-Api-Key', $config->custom_auth_header);
        $this->assertSame('custom-token', $config->token);
    }
}
