<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Admin;
use App\Models\PhotographerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhotographerTest extends TestCase
{
    use RefreshDatabase;

    // ─── Registration Form Loads ───

    public function test_photographer_registration_form_loads(): void
    {
        $response = $this->get(route('photographer.register'));
        $response->assertStatus(200);
    }

    // ─── Login Form Loads ───

    public function test_photographer_login_form_loads(): void
    {
        $response = $this->get(route('photographer.login'));
        $response->assertStatus(200);
    }

    // ─── Submit Photographer Application ───

    public function test_user_can_submit_photographer_application(): void
    {
        $response = $this->post(route('photographer.register.post'), [
            'first_name'            => 'Test',
            'last_name'             => 'Photographer',
            'email'                 => 'newphoto-' . uniqid() . '@example.com',
            'password'              => 'password1234',
            'password_confirmation' => 'password1234',
            'display_name'          => 'Photo Pro',
        ]);

        // Should redirect (to login or dashboard)
        $response->assertRedirect();
    }

    // ─── Admin Can Approve Photographer ───

    public function test_admin_can_approve_photographer(): void
    {
        $admin = Admin::create([
            'email'         => 'admin-photo@test.com',
            'password_hash' => Hash::make('password123'),
            'first_name'    => 'Admin',
            'last_name'     => 'Approver',
            'role'          => 'superadmin',
            'is_active'     => true,
        ]);

        $user = User::create([
            'first_name'    => 'Pending',
            'last_name'     => 'Photographer',
            'email'         => 'pending-photo@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);

        $profile = PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH' . str_pad($user->id, 4, '0', STR_PAD_LEFT),
            'display_name'      => 'Pending Photographer',
            'status'            => 'pending',
        ]);

        Auth::guard('admin')->login($admin);

        $response = $this->post(route('admin.photographers.approve', $profile->id));

        $response->assertRedirect();

        $profile->refresh();
        $this->assertEquals('approved', $profile->status);
    }

    // ─── Blocked Photographer Cannot Access Dashboard ───

    public function test_blocked_photographer_cannot_access_dashboard(): void
    {
        // Note: this used to test 'pending' → no access, but the new tier
        // model (see PhotographerAuth docblock) intentionally lets pending
        // photographers into the dashboard with a nudge to finish their
        // profile. Only admin-blocked statuses (rejected/suspended/banned)
        // bounce out. Lock the bounce-out behaviour here.
        $user = User::create([
            'first_name'    => 'Blocked',
            'last_name'     => 'Photo',
            'email'         => 'blocked@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);

        PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH9999',
            'display_name'      => 'Blocked',
            'status'            => 'suspended',
        ]);

        $response = $this->actingAs($user)->get(route('photographer.dashboard'));

        // Suspended → middleware logs them out and redirects to login.
        $response->assertRedirect(route('photographer.login'));
    }

    // ─── Pending Photographer Can Land On Dashboard (creator tier nudge) ───

    public function test_pending_photographer_lands_on_dashboard_with_nudge(): void
    {
        // Reflects the new tier model: pending photographers are not
        // blocked — they see the dashboard so they know what to do next.
        \App\Models\AppSetting::set('photographer_require_google_link', '0');
        \App\Models\AppSetting::flushCache();

        $user = User::create([
            'first_name'    => 'Pending',
            'last_name'     => 'Photo',
            'email'         => 'pending-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);

        PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH-PEND-' . strtoupper(substr(uniqid(), -4)),
            'display_name'      => 'Pending',
            'status'            => 'pending',
        ]);

        $response = $this->actingAs($user)->get(route('photographer.dashboard'));

        $response->assertStatus(200);
    }
}
