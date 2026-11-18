<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_creates_a_normal_user_and_logs_in(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'New Student',
            'email' => 'student@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ]);

        $response->assertRedirect(route('user.dashboard'));
        $this->assertAuthenticated();

        $user = User::where('email', 'student@example.com')->firstOrFail();
        $this->assertSame('user', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('Secret123', $user->password));
    }

    public function test_registration_rejects_duplicate_email_and_weak_password(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_registration_cannot_assign_admin_role(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Sneaky User',
            'email' => 'sneaky@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'role' => 'admin',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'sneaky@example.com',
            'role' => 'user',
        ]);
    }

    public function test_user_can_login_and_is_redirected_to_user_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => 'Secret123',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'member@example.com',
            'password' => 'Secret123',
        ]);

        $response->assertRedirect(route('user.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_can_login_and_is_redirected_to_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'boss@example.com',
            'password' => 'Admin12345',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'boss@example.com',
            'password' => 'Admin12345',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create([
            'email' => 'blocked@example.com',
            'password' => 'Secret123',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'blocked@example.com',
            'password' => 'Secret123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'member@example.com',
            'password' => 'Secret123',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'member@example.com',
            'password' => 'WrongPassword123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guest_cannot_access_dashboards(): void
    {
        $this->get(route('user.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
