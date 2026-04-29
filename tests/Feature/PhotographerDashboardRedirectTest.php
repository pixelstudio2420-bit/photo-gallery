<?php

namespace Tests\Feature;

use App\Models\PhotographerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * /photographer/dashboard redirect alias.
 *
 * Context
 * -------
 * The canonical photographer dashboard lives at /photographer (route name
 * photographer.dashboard). But old approval notifications, bookmarks, and
 * users' muscle memory send them to /photographer/dashboard — which used
 * to 404.
 *
 * The fix was a redirect route inside the photographer middleware group
 * that points /photographer/dashboard at /photographer. These tests lock
 * both the redirect AND the middleware behaviour in place so nobody
 * accidentally drops the redirect, and so nobody accidentally pulls the
 * redirect out of the middleware group (which would expose /dashboard to
 * guests and leak a photographer-area URL).
 */
class PhotographerDashboardRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function makeApprovedPhotographer(): User
    {
        // RequireGoogleLinked middleware checks auth_social_logins for a
        // google/line provider row. Disabling the global toggle keeps
        // these tests focused on the redirect-alias contract rather than
        // dragging in social-login fixtures.
        \App\Models\AppSetting::set('photographer_require_google_link', '0');
        \App\Models\AppSetting::flushCache();

        $user = User::create([
            'first_name'    => 'Dashboard',
            'last_name'     => 'Photographer',
            'email'         => 'dash-photo-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);

        PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH-' . strtoupper(substr(uniqid(), -6)),
            'display_name'      => 'Dash Pro',
            'status'            => 'approved',
        ]);

        return $user;
    }

    // ─── Guest is bounced to login, not 404'd ───

    public function test_guest_hitting_dashboard_alias_is_redirected_to_photographer_login(): void
    {
        $response = $this->get('/photographer/dashboard');

        // The auth middleware runs first, so the guest must be kicked to
        // login — not 404, and definitely not leaked through.
        $response->assertRedirect(route('photographer.login'));
    }

    // ─── Approved photographer is redirected to the canonical dashboard ───

    public function test_approved_photographer_hitting_dashboard_alias_is_redirected_to_canonical_url(): void
    {
        $user = $this->makeApprovedPhotographer();

        $response = $this->actingAs($user)->get('/photographer/dashboard');

        $response->assertRedirect(route('photographer.dashboard'));
    }

    // ─── Following the redirect lands on a real 200 page ───

    public function test_approved_photographer_following_redirect_lands_on_dashboard(): void
    {
        $user = $this->makeApprovedPhotographer();

        $response = $this
            ->actingAs($user)
            ->followingRedirects()
            ->get('/photographer/dashboard');

        // If the redirect target is wrong (e.g. points at a route that
        // doesn't exist or a page the photographer can't reach), this
        // assertion fails with the real downstream status. We want 200.
        $response->assertStatus(200);
    }

    // ─── Canonical URL itself still works (we didn't break /photographer) ───

    public function test_canonical_photographer_dashboard_still_resolves(): void
    {
        $user = $this->makeApprovedPhotographer();

        $response = $this->actingAs($user)->get('/photographer');

        $response->assertStatus(200);
    }

    // ─── Route name points at the canonical URL, not the alias ───

    public function test_photographer_dashboard_route_name_resolves_to_canonical_path(): void
    {
        // The notification helper calls route('photographer.dashboard') —
        // if that ever resolved to /photographer/dashboard we'd be in a
        // redirect loop. Lock the expected URL shape in.
        $this->assertStringEndsWith('/photographer', route('photographer.dashboard'));
    }
}
