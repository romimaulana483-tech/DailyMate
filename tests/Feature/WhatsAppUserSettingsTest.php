<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppUserSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_enable_whatsapp_notifications_with_consent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('user.whatsapp.update'), [
            'whatsapp_number' => '+62 812-3456-7890',
            'whatsapp_notifications_enabled' => '1',
            'whatsapp_consent' => '1',
        ])->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('+6281234567890', $user->whatsapp_number);
        $this->assertTrue($user->whatsapp_notifications_enabled);
        $this->assertNotNull($user->whatsapp_consent_at);
        $this->assertTrue($user->hasWhatsAppNotifications());
    }

    public function test_user_can_disable_notifications_but_keep_number(): void
    {
        $user = User::factory()->create([
            'whatsapp_number' => '+6281234567890',
            'whatsapp_notifications_enabled' => true,
            'whatsapp_consent_at' => now(),
        ]);

        $this->actingAs($user)->put(route('user.whatsapp.update'), [
            'whatsapp_number' => '+6281234567890',
            'whatsapp_notifications_enabled' => '0',
        ])->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('+6281234567890', $user->whatsapp_number);
        $this->assertFalse($user->whatsapp_notifications_enabled);
        $this->assertNull($user->whatsapp_consent_at);
        $this->assertFalse($user->hasWhatsAppNotifications());
    }

    public function test_international_number_format_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('user.whatsapp.update'), [
            'whatsapp_number' => '081234567890',
            'whatsapp_notifications_enabled' => '1',
            'whatsapp_consent' => '1',
        ])->assertSessionHasErrors('whatsapp_number');

        $this->assertFalse($user->fresh()->whatsapp_notifications_enabled);
    }

    public function test_consent_is_required_to_enable_notifications(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('user.whatsapp.update'), [
            'whatsapp_number' => '+6281234567890',
            'whatsapp_notifications_enabled' => '1',
        ])->assertSessionHasErrors('whatsapp_consent');

        $this->assertFalse($user->fresh()->whatsapp_notifications_enabled);
    }

    public function test_number_is_required_to_enable_notifications(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('user.whatsapp.update'), [
            'whatsapp_number' => '',
            'whatsapp_notifications_enabled' => '1',
            'whatsapp_consent' => '1',
        ])->assertSessionHasErrors('whatsapp_number');

        $this->assertFalse($user->fresh()->whatsapp_notifications_enabled);
    }

    public function test_admin_cannot_use_user_whatsapp_settings_endpoint(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('user.whatsapp.update'), [
            'whatsapp_number' => '+6281234567890',
            'whatsapp_notifications_enabled' => '1',
            'whatsapp_consent' => '1',
        ])->assertRedirect(route('admin.dashboard'));
    }
}
