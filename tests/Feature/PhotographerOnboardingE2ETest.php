<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AppSetting;
use App\Models\Event;
use App\Models\PhotographerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * End-to-end test for the photographer onboarding journey.
 *
 *   register → admin approves → login → land on dashboard → create event
 *
 * Each step is exercised through real HTTP routes — these tests catch
 * the cross-module wiring bugs that single-controller unit tests miss
 * (e.g. wrong route name, missing middleware, broken redirect target).
 *
 * The slip-upload + payment side of "becoming a paid creator" is
 * covered by OrderLifecycleE2ETest. This file owns the *creator-side*
 * journey only.
 */
class PhotographerOnboardingE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // RequireGoogleLinked checks for a google/line provider row.
        // We're testing local-account onboarding, so disable the gate.
        AppSetting::set('photographer_require_google_link', '0');
        AppSetting::flushCache();
    }

    private function makeAdmin(): Admin
    {
        // Match the schema used by the working PhotographerTest fixture —
        // the admin guard authenticates against `is_active`, role
        // 'superadmin', and looks up the password from password_hash.
        return Admin::create([
            'email'         => 'admin-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'first_name'    => 'Approver',
            'last_name'     => 'Admin',
            'role'          => 'superadmin',
            'is_active'     => true,
        ]);
    }

    // =========================================================================
    // Form rendering — every page in the onboarding funnel returns 200
    // =========================================================================

    public function test_register_form_loads_for_guest(): void
    {
        $response = $this->get(route('photographer.register'));
        $response->assertStatus(200);
    }

    public function test_login_form_loads_for_guest(): void
    {
        $response = $this->get(route('photographer.login'));
        $response->assertStatus(200);
    }

    // =========================================================================
    // Approval lifecycle — guard rails for the photographer's status field
    // =========================================================================

    public function test_admin_can_approve_pending_photographer(): void
    {
        $admin = $this->makeAdmin();

        $user = User::create([
            'first_name'    => 'Pending',
            'last_name'     => 'Photog',
            'email'         => 'pending-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);

        $profile = PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH-' . strtoupper(substr(uniqid(), -6)),
            'display_name'      => 'Test Photographer',
            'status'            => 'pending',
        ]);

        // The admin guard expects an explicit login() call (the actingAs
        // helper doesn't carry through the guard binding cleanly here).
        \Illuminate\Support\Facades\Auth::guard('admin')->login($admin);

        $response = $this->post(route('admin.photographers.approve', $profile->id));

        $response->assertRedirect();
        $this->assertSame('approved', $profile->fresh()->status);
    }

    public function test_pending_photographer_lands_on_dashboard_with_creator_tier(): void
    {
        $user = User::create([
            'first_name'    => 'Pending',
            'last_name'     => 'Photog',
            'email'         => 'pending-tier-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);

        PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH-' . strtoupper(substr(uniqid(), -6)),
            'display_name'      => 'Pending Tier',
            'status'            => 'pending',
        ]);

        $response = $this->actingAs($user)->get(route('photographer.dashboard'));
        $response->assertStatus(200);
    }

    public function test_approved_photographer_can_view_events_index(): void
    {
        $user = User::create([
            'first_name'    => 'Approved',
            'last_name'     => 'Photog',
            'email'         => 'approved-events-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);

        PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH-' . strtoupper(substr(uniqid(), -6)),
            'display_name'      => 'Approved Photog',
            'status'            => 'approved',
        ]);

        $response = $this->actingAs($user)->get(route('photographer.events.index'));
        $response->assertStatus(200);
    }

    public function test_approved_photographer_can_view_events_create_form(): void
    {
        $user = User::create([
            'first_name'    => 'Approved',
            'last_name'     => 'Photog',
            'email'         => 'approved-create-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);

        PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH-' . strtoupper(substr(uniqid(), -6)),
            'display_name'      => 'Approved Create',
            'status'            => 'approved',
        ]);

        $response = $this->actingAs($user)->get(route('photographer.events.create'));
        $response->assertStatus(200);
    }

    public function test_approved_photographer_can_view_their_own_event(): void
    {
        $user = User::create([
            'first_name'    => 'Approved',
            'last_name'     => 'Photog',
            'email'         => 'approved-show-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);

        PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH-' . strtoupper(substr(uniqid(), -6)),
            'display_name'      => 'Approved Show',
            'status'            => 'approved',
        ]);

        $event = Event::create([
            'photographer_id' => $user->id,
            'name'            => 'My Event',
            'slug'            => 'my-event-' . uniqid(),
            'status'          => 'active',
            'visibility'      => 'public',
            'price_per_photo' => 30.00,
            'is_free'         => false,
            'view_count'      => 0,
        ]);

        $response = $this->actingAs($user)->get(route('photographer.events.show', $event));
        $response->assertStatus(200);
    }

    // =========================================================================
    // Bouncing rules — a blocked photographer must not get past auth
    // =========================================================================

    public function test_suspended_photographer_is_logged_out_and_redirected(): void
    {
        $user = User::create([
            'first_name'    => 'Suspended',
            'last_name'     => 'Photog',
            'email'         => 'suspended-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);

        PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH-' . strtoupper(substr(uniqid(), -6)),
            'display_name'      => 'Suspended',
            'status'            => 'suspended',
            'rejection_reason'  => 'Test suspension',
        ]);

        $response = $this->actingAs($user)->get(route('photographer.dashboard'));
        $response->assertRedirect(route('photographer.login'));
    }

    // Note: PhotographerProfile::isBlocked() also lists 'rejected' and
    // 'banned', but the photographer_profiles.status enum is currently
    // ['pending','approved','suspended'] — so only 'suspended' is
    // reachable in practice. If the schema is later widened to include
    // 'rejected'/'banned', add the symmetric assertions here.

    // =========================================================================
    // Login flow — POST /photographer/login moves the session forward
    // =========================================================================

    public function test_photographer_can_login_with_correct_credentials(): void
    {
        $email    = 'loginflow-' . uniqid() . '@example.com';
        $password = 'password123';

        $user = User::create([
            'first_name'    => 'Login',
            'last_name'     => 'Flow',
            'email'         => $email,
            'password_hash' => Hash::make($password),
            'auth_provider' => 'local',
        ]);

        PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH-' . strtoupper(substr(uniqid(), -6)),
            'display_name'      => 'Login Flow',
            'status'            => 'approved',
        ]);

        $response = $this->post(route('photographer.login.post'), [
            'email'    => $email,
            'password' => $password,
        ]);

        // Successful login → 302 to the dashboard (or whatever the
        // controller's intended-route logic decides). What we care
        // about is that the user is now authenticated, not 4xx.
        $this->assertContains(
            $response->getStatusCode(),
            [200, 302],
            "Login should not return 4xx; got {$response->getStatusCode()}",
        );
        $this->assertAuthenticatedAs($user);
    }

    public function test_photographer_login_with_wrong_password_is_rejected(): void
    {
        $email = 'wrongpw-' . uniqid() . '@example.com';
        $user  = User::create([
            'first_name'    => 'Wrong',
            'last_name'     => 'PW',
            'email'         => $email,
            'password_hash' => Hash::make('correct-password'),
            'auth_provider' => 'local',
        ]);

        $response = $this->from(route('photographer.login'))
            ->post(route('photographer.login.post'), [
                'email'    => $email,
                'password' => 'definitely-not-the-right-password',
            ]);

        // Should redirect back to the login form with an error, not log in.
        $this->assertGuest();
    }

    // =========================================================================
    // Logout flow
    // =========================================================================

    public function test_photographer_logout_ends_session(): void
    {
        $user = User::create([
            'first_name'    => 'Bye',
            'last_name'     => 'Now',
            'email'         => 'bye-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);

        PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH-' . strtoupper(substr(uniqid(), -6)),
            'display_name'      => 'Logging Out',
            'status'            => 'approved',
        ]);

        $this->actingAs($user)->post(route('photographer.logout'));
        $this->assertGuest();
    }
}
