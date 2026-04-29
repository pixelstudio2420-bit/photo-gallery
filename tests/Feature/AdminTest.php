<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(array $overrides = []): Admin
    {
        return Admin::create(array_merge([
            'email'         => 'admin-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('admin-secret'),
            'first_name'    => 'Super',
            'last_name'     => 'Admin',
            'role'          => 'superadmin',
            'permissions'   => null,
            'is_active'     => true,
        ], $overrides));
    }

    // ─── Admin Login Page ───

    public function test_admin_login_page_loads(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertStatus(200);
    }

    // ─── Non-Admin Cannot Access Dashboard ───

    public function test_non_admin_cannot_access_dashboard(): void
    {
        $user = User::create([
            'first_name'    => 'Regular',
            'last_name'     => 'User',
            'email'         => 'regular@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);

        // A regular user (even if authenticated on the web guard) should not
        // be able to reach the admin dashboard, which requires the admin guard.
        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        // The admin middleware should redirect to admin login
        $response->assertRedirect(route('admin.login'));
    }

    // ─── Admin Can Access Dashboard ───

    public function test_admin_can_access_dashboard(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertStatus(200);
    }
}
