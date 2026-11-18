<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_between_indonesian_and_english(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('user.dashboard'))
            ->get(route('language.switch', 'id'))
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHas('locale', 'id');

        $this->actingAs($user)
            ->withSession(['locale' => 'id'])
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('lang="id"', false)
            ->assertSee('Dasbor')
            ->assertSee('Bahasa Indonesia')
            ->assertSee('English');

        $this->actingAs($user)
            ->from(route('user.dashboard'))
            ->get(route('language.switch', 'en'))
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHas('locale', 'en');

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('Dashboard')
            ->assertSee('Bahasa Indonesia')
            ->assertSee('English');
    }

    public function test_admin_can_see_and_use_the_same_language_menu(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->withSession(['locale' => 'id'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Bahasa Indonesia')
            ->assertSee('English')
            ->assertSee('Dasbor Admin')
            ->assertSee('Manajemen Pengguna');

        $this->actingAs($admin)
            ->from(route('admin.dashboard'))
            ->get(route('language.switch', 'en'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('locale', 'en');
    }

    public function test_only_supported_locales_are_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('language.switch', 'fr'))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('language.switch', 'id'))
            ->assertRedirect();

        $this->post(route('logout'))->assertRedirect();

        $this->get(route('language.switch', 'id'))
            ->assertRedirect(route('login'));
    }
}
