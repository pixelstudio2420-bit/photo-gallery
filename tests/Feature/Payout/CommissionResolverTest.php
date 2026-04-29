<?php

namespace Tests\Feature\Payout;

use App\Models\AppSetting;
use App\Models\CommissionTier;
use App\Models\PhotographerPayout;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Services\Payout\CommissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Locks down CommissionResolver's priority cascade.
 *
 * Order:
 *   1. Tier rate (if tiers configured AND photographer crossed a min_revenue)
 *   2. Profile.commission_rate (admin override / VIP)
 *   3. Global default (100 - platform_commission AppSetting)
 *
 * The cascade also rounds in the photographer's favour: when both tier
 * and profile rates exist, the higher of the two wins.
 */
class CommissionResolverTest extends TestCase
{
    use RefreshDatabase;

    private CommissionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->resolver = new CommissionResolver();
    }

    private function makePhotographer(?float $profileRate = null): int
    {
        $user = User::create([
            'first_name'    => 'Comm',
            'last_name'     => 'Tester',
            'email'         => 'comm-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);
        if ($profileRate !== null) {
            PhotographerProfile::create([
                'user_id'           => $user->id,
                // photographer_code + display_name are NOT NULL.
                'photographer_code' => 'PH-T' . substr(uniqid(), -6),
                'display_name'      => 'Tester ' . $user->id,
                'commission_rate'   => $profileRate,
                'status'            => 'approved',
                'tier'              => 'pro',
            ]);
        }
        return $user->id;
    }

    private function payout(int $photographerId, float $gross, string $status = 'paid'): void
    {
        // photographer_payouts.order_id is NOT NULL — fabricate a minimal
        // order row so we can build payout history without dragging in the
        // full event/items machinery.
        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'user_id'      => $photographerId,
            'order_number' => 'O-' . uniqid(),
            'total'        => $gross,
            'status'       => 'paid',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        PhotographerPayout::create([
            'photographer_id' => $photographerId,
            'order_id'        => $orderId,
            'gross_amount'    => $gross,
            'commission_rate' => 80,
            'payout_amount'   => $gross * 0.8,
            'platform_fee'    => $gross * 0.2,
            'status'          => $status,
        ]);
    }

    /* ───────────────── Default fallback ───────────────── */

    public function test_returns_global_default_when_no_tiers_no_profile(): void
    {
        AppSetting::set('platform_commission', '20');
        AppSetting::flushCache();

        $pid = $this->makePhotographer();   // no profile

        $rate = $this->resolver->resolveKeepRate($pid);
        $this->assertEquals(80.0, $rate, 'No tiers + no profile = 100 - platform_commission(20) = 80%.');
    }

    public function test_global_default_changes_with_app_setting(): void
    {
        AppSetting::set('platform_commission', '15');
        AppSetting::flushCache();

        $pid = $this->makePhotographer();
        $this->assertEquals(85.0, $this->resolver->resolveKeepRate($pid));
    }

    /* ───────────────── Profile override ───────────────── */

    public function test_profile_rate_used_when_no_tiers(): void
    {
        AppSetting::set('platform_commission', '20');
        AppSetting::flushCache();

        $pid = $this->makePhotographer(profileRate: 90.0);

        $this->assertEquals(90.0, $this->resolver->resolveKeepRate($pid),
            'Profile.commission_rate beats global default when no tiers configured.');
    }

    /* ───────────────── Tier resolution ───────────────── */

    public function test_tier_resolved_by_lifetime_revenue(): void
    {
        // Three tiers: 0+ → 75%, 50k+ → 82%, 200k+ → 88%
        CommissionTier::create([
            'name' => 'Bronze', 'min_revenue' => 0, 'commission_rate' => 75.0, 'is_active' => true,
        ]);
        CommissionTier::create([
            'name' => 'Silver', 'min_revenue' => 50000, 'commission_rate' => 82.0, 'is_active' => true,
        ]);
        CommissionTier::create([
            'name' => 'Gold', 'min_revenue' => 200000, 'commission_rate' => 88.0, 'is_active' => true,
        ]);

        $pidNew     = $this->makePhotographer();   // 0 lifetime → Bronze
        $pidSilver  = $this->makePhotographer();
        $pidGold    = $this->makePhotographer();

        // Seed lifetime revenue
        $this->payout($pidSilver, 60000);
        $this->payout($pidGold, 250000);

        // Resolver re-reads after invalidate (created event already fired).
        $this->assertEquals(75.0, $this->resolver->resolveKeepRate($pidNew));
        $this->assertEquals(82.0, $this->resolver->resolveKeepRate($pidSilver));
        $this->assertEquals(88.0, $this->resolver->resolveKeepRate($pidGold));
    }

    public function test_inactive_tier_not_used(): void
    {
        // Active Bronze, INACTIVE Gold — Gold must not apply even though
        // photographer's revenue would qualify.
        CommissionTier::create([
            'name' => 'Bronze', 'min_revenue' => 0, 'commission_rate' => 75.0, 'is_active' => true,
        ]);
        CommissionTier::create([
            'name' => 'Gold', 'min_revenue' => 100000, 'commission_rate' => 90.0, 'is_active' => false,
        ]);

        $pid = $this->makePhotographer();
        $this->payout($pid, 500000);   // way past Gold

        $this->assertEquals(75.0, $this->resolver->resolveKeepRate($pid));
    }

    /* ───────────────── Hybrid: tier + profile floor ───────────────── */

    public function test_profile_rate_acts_as_floor_above_tier(): void
    {
        CommissionTier::create([
            'name' => 'Bronze', 'min_revenue' => 0, 'commission_rate' => 75.0, 'is_active' => true,
        ]);

        // VIP profile rate 92% — admin's bespoke contract beats Bronze.
        $pid = $this->makePhotographer(profileRate: 92.0);

        $this->assertEquals(92.0, $this->resolver->resolveKeepRate($pid),
            'Profile rate above the matched tier wins (admin VIP override).');
    }

    public function test_tier_rate_wins_when_above_profile(): void
    {
        CommissionTier::create([
            'name' => 'Gold', 'min_revenue' => 100000, 'commission_rate' => 88.0, 'is_active' => true,
        ]);

        // Profile says 80% (admin set this when photographer started),
        // but Gold tier qualified gives 88%. Photographer-friendliest wins.
        $pid = $this->makePhotographer(profileRate: 80.0);
        $this->payout($pid, 150000);

        $this->assertEquals(88.0, $this->resolver->resolveKeepRate($pid),
            'Earned tier above outdated profile rate wins.');
    }

    /* ───────────────── Cache invalidation ───────────────── */

    public function test_cache_invalidated_on_payout_create(): void
    {
        CommissionTier::create([
            'name' => 'Bronze', 'min_revenue' => 0, 'commission_rate' => 75.0, 'is_active' => true,
        ]);
        CommissionTier::create([
            'name' => 'Silver', 'min_revenue' => 50000, 'commission_rate' => 82.0, 'is_active' => true,
        ]);

        $pid = $this->makePhotographer();
        $this->assertEquals(75.0, $this->resolver->resolveKeepRate($pid));   // Bronze

        // Cross the Silver threshold via a payout — booted() event must
        // invalidate the cached lifetime revenue so the next resolver
        // call sees the upgrade.
        $this->payout($pid, 60000);

        $this->assertEquals(82.0, $this->resolver->resolveKeepRate($pid),
            'After lifetime crosses tier, resolver must re-read (cache invalidated by model event).');
    }

    public function test_reversed_payouts_excluded_from_lifetime(): void
    {
        CommissionTier::create([
            'name' => 'Bronze', 'min_revenue' => 0, 'commission_rate' => 75.0, 'is_active' => true,
        ]);
        CommissionTier::create([
            'name' => 'Gold', 'min_revenue' => 100000, 'commission_rate' => 88.0, 'is_active' => true,
        ]);

        $pid = $this->makePhotographer();

        // 80k pending + 80k reversed → only 80k counts toward lifetime
        $this->payout($pid, 80000, 'pending');
        $this->payout($pid, 80000, 'reversed');

        // 80k < 100k Gold threshold → still Bronze
        $this->assertEquals(75.0, $this->resolver->resolveKeepRate($pid),
            'Reversed (chargeback) payouts must NOT count toward tier qualification.');
    }
}
