<?php

namespace Tests\Unit\Marketing;

use App\Models\AppSetting;
use App\Models\Marketing\ReferralCode;
use App\Models\Marketing\ReferralRedemption;
use App\Models\User;
use App\Services\Marketing\ReferralService;

class ReferralServiceTest extends MarketingUnitTestCase
{
    protected function makeUser(string $email = 'owner@test.com'): User
    {
        return User::create([
            'username'      => 'u_' . uniqid(),
            'first_name'    => 'Test',
            'last_name'     => 'User',
            'email'         => $email,
            'password_hash' => 'x',
            'status'        => 'active',
        ]);
    }

    public function test_get_or_create_returns_null_when_disabled(): void
    {
        $user = $this->makeUser();
        $svc = app(ReferralService::class);
        $this->assertNull($svc->getOrCreateForUser($user));
    }

    public function test_get_or_create_creates_code_when_enabled(): void
    {
        $this->enableMarketing('referral');
        $user = $this->makeUser();

        $svc = app(ReferralService::class);
        $code = $svc->getOrCreateForUser($user);

        $this->assertNotNull($code);
        $this->assertSame($user->id, $code->owner_user_id);
        $this->assertSame(8, strlen($code->code));
        $this->assertTrue($code->is_active);
    }

    public function test_get_or_create_returns_existing_code(): void
    {
        $this->enableMarketing('referral');
        $user = $this->makeUser();

        $svc = app(ReferralService::class);
        $first  = $svc->getOrCreateForUser($user);
        $second = $svc->getOrCreateForUser($user);

        $this->assertSame($first->id, $second->id);
    }

    public function test_apply_fails_when_disabled(): void
    {
        $svc = app(ReferralService::class);
        $result = $svc->apply('ANYCODE', 1000);
        $this->assertFalse($result['ok']);
    }

    public function test_apply_fails_for_unknown_code(): void
    {
        $this->enableMarketing('referral');
        $svc = app(ReferralService::class);
        $result = $svc->apply('NOPE12345', 1000);
        $this->assertFalse($result['ok']);
    }

    public function test_apply_fails_when_owner_uses_own_code(): void
    {
        $this->enableMarketing('referral');
        $user = $this->makeUser();
        $svc = app(ReferralService::class);
        $code = $svc->getOrCreateForUser($user);

        $result = $svc->apply($code->code, 1000, $user->id);
        $this->assertFalse($result['ok']);
    }

    public function test_apply_calculates_percent_discount(): void
    {
        $this->enableMarketing('referral');
        AppSetting::set('marketing_referral_discount_type', 'percent');
        AppSetting::set('marketing_referral_discount_value', '10');
        AppSetting::flushCache();

        $owner = $this->makeUser('owner@test.com');
        $svc = app(ReferralService::class);
        $code = $svc->getOrCreateForUser($owner);

        $redeemer = $this->makeUser('redeem@test.com');
        $result = $svc->apply($code->code, 1000, $redeemer->id);

        $this->assertTrue($result['ok']);
        $this->assertEquals(100.0, (float) $result['discount']);
    }

    public function test_apply_calculates_fixed_discount(): void
    {
        $this->enableMarketing('referral');
        AppSetting::set('marketing_referral_discount_type', 'fixed');
        AppSetting::set('marketing_referral_discount_value', '50');
        AppSetting::flushCache();

        $owner = $this->makeUser('owner2@test.com');
        $svc = app(ReferralService::class);
        $code = $svc->getOrCreateForUser($owner);

        $redeemer = $this->makeUser('redeem2@test.com');
        $result = $svc->apply($code->code, 1000, $redeemer->id);

        $this->assertTrue($result['ok']);
        $this->assertEquals(50.0, (float) $result['discount']);
    }

    public function test_apply_fails_for_inactive_code(): void
    {
        $this->enableMarketing('referral');
        $owner = $this->makeUser('inactive@test.com');
        $svc = app(ReferralService::class);
        $code = $svc->getOrCreateForUser($owner);
        $code->update(['is_active' => false]);

        $result = $svc->apply($code->code, 1000);
        $this->assertFalse($result['ok']);
    }

    public function test_record_redemption_increments_uses(): void
    {
        $this->enableMarketing('referral');
        $owner = $this->makeUser('owner3@test.com');
        $svc = app(ReferralService::class);
        $code = $svc->getOrCreateForUser($owner);
        $before = $code->uses_count;

        $redemption = $svc->recordRedemption($code, 999, null, 100);

        $this->assertInstanceOf(ReferralRedemption::class, $redemption);
        $this->assertSame('pending', $redemption->status);
        $code->refresh();
        $this->assertSame($before + 1, (int) $code->uses_count);
    }

    public function test_reward_on_order_updates_redemption_status(): void
    {
        $this->enableMarketing('referral');
        $owner = $this->makeUser('rewardowner@test.com');
        $svc = app(ReferralService::class);
        $code = $svc->getOrCreateForUser($owner);

        $svc->recordRedemption($code, 5555, null, 100);

        $result = $svc->rewardOnOrder(5555);
        $this->assertTrue($result['ok']);

        $r = ReferralRedemption::where('order_id', 5555)->first();
        $this->assertSame('rewarded', $r->status);
        $this->assertNotNull($r->rewarded_at);
    }

    public function test_stats_for_user_returns_zeros_when_no_code(): void
    {
        $user = $this->makeUser('nocode@test.com');
        $svc = app(ReferralService::class);
        $stats = $svc->statsForUser($user);
        $this->assertNull($stats['code']);
        $this->assertSame(0, $stats['uses']);
    }

    public function test_stats_for_user_counts_redemptions(): void
    {
        $this->enableMarketing('referral');
        $owner = $this->makeUser('stats@test.com');
        $svc = app(ReferralService::class);
        $code = $svc->getOrCreateForUser($owner);

        $svc->recordRedemption($code, 10, null, 50);
        $svc->recordRedemption($code, 11, null, 50);
        $svc->rewardOnOrder(10);

        $stats = $svc->statsForUser($owner);
        $this->assertSame(2, $stats['uses']);
        $this->assertSame(1, $stats['rewarded']);
    }
}
