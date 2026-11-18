<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_information(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->put(route('user.profile.update'), [
            'name' => 'New Name',
            'email' => 'newemail@example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'newemail@example.com',
        ]);
    }

    public function test_profile_email_must_be_unique(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'already@example.com']);

        $this->actingAs($user)->put(route('user.profile.update'), [
            'name' => $user->name,
            'email' => 'already@example.com',
        ])->assertSessionHasErrors('email');
    }

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword123']);

        $this->actingAs($user)->put(route('user.profile.password'), [
            'current_password' => 'OldPassword123',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword123']);

        $this->actingAs($user)->put(route('user.profile.password'), [
            'current_password' => 'WrongPassword123',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
    }

    public function test_admin_can_update_their_profile(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.profile.update'), [
            'name' => 'Updated Admin',
            'email' => 'updatedadmin@example.com',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'name' => 'Updated Admin']);
    }
}
