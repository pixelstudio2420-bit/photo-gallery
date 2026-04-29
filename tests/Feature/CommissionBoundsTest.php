<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AppSetting;
use App\Models\PhotographerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Admin commission-rate bounds enforcement.
 *
 * The ResolvesCommissionBounds trait reads admin-configured
 * min_commission_rate / max_commission_rate from app_settings and applies
 * them to every endpoint that takes a commission_rate. Before this trait
 * existed, an admin could set a photographer to 99% even when the ceiling
 * was 95% because each form validator hard-coded min:0|max:100.
 *
 * These tests make sure a value outside the configured band is rejected,
 * a value inside is accepted, and the settings form itself rejects a
 * configuration where min > max (which would brick every commission form
 * downstream).
 *
 * 2FA note
 * --------
 * Admin routes live under [admin, admin.2fa.setup, admin.2fa, no.back].
 * Superadmins must have 2FA enrolled; once enrolled, every session needs
 * to pass an OTP challenge. Tests satisfy both by:
 *   1. Inserting a fake admin_2fa row so RequireTwoFactorSetup passes.
 *   2. Seeding the session flag admin_2fa_passed so RequireTwoFactor passes.
 * This is strictly test plumbing — the middleware behaviour itself is
 * covered by separate 2FA tests.
 */
class CommissionBoundsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'email'         => 'commission-admin-' . uniqid() . '@test.com',
            'password_hash' => Hash::make('secret'),
            'first_name'    => 'Commission',
            'last_name'     => 'Admin',
            'role'          => 'superadmin',
            'is_active'     => true,
        ]);

        // Satisfy RequireTwoFactorSetup for superadmin — mark 2FA as enrolled.
        if (Schema::hasTable('admin_2fa')) {
            DB::table('admin_2fa')->insert([
                'admin_id'   => $this->admin->id,
                'secret_key' => 'TESTSECRETBASE32',
                'is_enabled' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->user = User::create([
            'first_name'    => 'Candidate',
            'last_name'     => 'Photographer',
            'email'         => 'candidate-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('secret'),
            'auth_provider' => 'local',
        ]);

        // Tight band so the defaults (0-100) can't accidentally mask a bug.
        AppSetting::set('min_commission_rate', 50);
        AppSetting::set('max_commission_rate', 95);
        AppSetting::flushCache();
    }

    /**
     * All admin requests go through this helper so they carry the
     * 2FA-passed session flag — otherwise RequireTwoFactor bounces
     * them to the OTP challenge before the controller is reached.
     */
    private function asAdmin()
    {
        return $this
            ->actingAs($this->admin, 'admin')
            ->withSession(['admin_2fa_passed' => true]);
    }

    // ─── Photographer store: rate above ceiling is rejected ───

    public function test_admin_cannot_set_commission_rate_above_ceiling_when_creating_photographer(): void
    {
        $response = $this->asAdmin()
            ->from(route('admin.photographers.create'))
            ->post(route('admin.photographers.store'), [
                'user_id'         => $this->user->id,
                'display_name'    => 'Over Cap',
                'commission_rate' => 99, // max is 95 — must fail
                'status'          => 'pending',
            ]);

        $response->assertSessionHasErrors('commission_rate');
        $this->assertDatabaseMissing('photographer_profiles', [
            'user_id' => $this->user->id,
        ]);
    }

    // ─── Photographer store: rate below floor is rejected ───

    public function test_admin_cannot_set_commission_rate_below_floor_when_creating_photographer(): void
    {
        $response = $this->asAdmin()
            ->from(route('admin.photographers.create'))
            ->post(route('admin.photographers.store'), [
                'user_id'         => $this->user->id,
                'display_name'    => 'Under Floor',
                'commission_rate' => 10, // min is 50 — must fail
                'status'          => 'pending',
            ]);

        $response->assertSessionHasErrors('commission_rate');
        $this->assertDatabaseMissing('photographer_profiles', [
            'user_id' => $this->user->id,
        ]);
    }

    // ─── Photographer store: rate inside band is accepted ───

    public function test_admin_can_set_commission_rate_inside_configured_band(): void
    {
        $response = $this->asAdmin()
            ->post(route('admin.photographers.store'), [
                'user_id'         => $this->user->id,
                'display_name'    => 'Valid Rate',
                'commission_rate' => 80, // inside 50..95
                'status'          => 'pending',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('photographer_profiles', [
            'user_id'         => $this->user->id,
            'commission_rate' => 80,
        ]);
    }

    // ─── Photographer update: same enforcement on the update path ───

    public function test_admin_cannot_raise_existing_photographer_commission_above_ceiling(): void
    {
        $profile = PhotographerProfile::create([
            'user_id'           => $this->user->id,
            'photographer_code' => 'PH-TEST-' . uniqid(),
            'display_name'      => 'Existing',
            'commission_rate'   => 80,
            'status'            => 'approved',
        ]);

        $response = $this->asAdmin()
            ->from(route('admin.photographers.edit', $profile))
            ->put(route('admin.photographers.update', $profile), [
                'display_name'    => 'Existing',
                'commission_rate' => 99, // cap is 95
                'status'          => 'approved',
            ]);

        $response->assertSessionHasErrors('commission_rate');

        $profile->refresh();
        $this->assertEquals(80.0, (float) $profile->commission_rate);
    }

    // ─── Settings: min > max is blocked so the band can't invert ───

    public function test_commission_settings_reject_max_below_min(): void
    {
        $response = $this->asAdmin()
            ->from(route('admin.commission.settings'))
            ->post(route('admin.commission.settings.update'), [
                'platform_commission' => 20,
                'min_commission_rate' => 80,
                'max_commission_rate' => 60, // less than min — must be rejected (gte)
            ]);

        $response->assertSessionHasErrors('max_commission_rate');

        // Settings must not have been overwritten with the bad values.
        AppSetting::flushCache();
        $this->assertEquals(50.0, (float) AppSetting::get('min_commission_rate'));
        $this->assertEquals(95.0, (float) AppSetting::get('max_commission_rate'));
    }

    // ─── Bounds trait degrades gracefully when settings are missing ───

    public function test_missing_bounds_settings_fall_back_to_full_range(): void
    {
        // Wipe the bounds so the trait has to fall back to defaults (0..100).
        AppSetting::where('key', 'min_commission_rate')->delete();
        AppSetting::where('key', 'max_commission_rate')->delete();
        AppSetting::flushCache();

        $response = $this->asAdmin()
            ->post(route('admin.photographers.store'), [
                'user_id'         => $this->user->id,
                'display_name'    => 'Fallback',
                'commission_rate' => 99, // allowed when band is missing
                'status'          => 'pending',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('photographer_profiles', [
            'user_id'         => $this->user->id,
            'commission_rate' => 99,
        ]);
    }
}
