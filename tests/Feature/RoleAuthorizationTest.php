<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_any_admin_page(): void
    {
        $user = User::factory()->create();

        foreach ([
            'admin.dashboard',
            'admin.users.index',
            'admin.statistics',
            'admin.notifications.index',
            'admin.ai.edit',
            'admin.whatsapp.edit',
            'admin.feedback.index',
            'admin.reports.index',
            'admin.profile.edit',
        ] as $route) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertRedirect(route('user.dashboard'));
        }
    }

    public function test_admin_cannot_access_user_workspace_pages(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            'user.dashboard',
            'user.tasks.index',
            'user.schedules.index',
            'user.calendar',
            'user.statistics',
            'user.ai.index',
            'user.notifications.index',
            'user.feedback.index',
        ] as $route) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertRedirect(route('admin.dashboard'));
        }
    }

    public function test_deactivated_user_session_is_terminated(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_admin_cannot_open_user_profile_route(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('user.profile.edit'))
            ->assertRedirect(route('admin.dashboard'));
    }
}
