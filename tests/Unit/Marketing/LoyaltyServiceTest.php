<?php

namespace Tests\Unit\Marketing;

use App\Models\AppSetting;
use App\Models\Marketing\LoyaltyAccount;
use App\Models\Marketing\LoyaltyTransaction;
use App\Models\User;
use App\Services\Marketing\LoyaltyService;

class LoyaltyServiceTest extends MarketingUnitTestCase
{
    protected function makeUser(string $email = 'loyalty@test.com'): User
    {
        return User::create([
            'username'      => 'u_' . uniqid(),
            'first_name'    => 'Loyal',
            'last_name'     => 'User',
            'email'         => $email,
            'password_hash' => 'x',
            'status'        => 'active',
        ]);
    }

    public function test_earn_returns_null_when_disabled(): void
    {
        $u = $this->makeUser();
        $svc = app(LoyaltyService::class);
        $this->assertNull($svc->earnFromOrder($u->id, 1000, 1));
    }

    public function test_get_or_create_creates_default_account(): void
    {
        $u = $this->makeUser();
        $svc = app(LoyaltyService::class);
        $acc = $svc->getOrCreate($u->id);

        $this->assertInstanceOf(LoyaltyAccount::class, $acc);
        $this->assertSame(0, (int) $acc->points_balance);
        $this->assertSame('bronze', $acc->tier);
    }

    public function test_earn_from_order_credits_points(): void
    {
        $this->enableMarketing('loyalty');
        AppSetting::set('marketing_loyalty_earn_rate', '1');
        AppSetting::flushCache();

        $u = $this->makeUser();
        $svc = app(LoyaltyService::class);
        $tx = $svc->earnFromOrder($u->id, 500, 101);

        $this->assertInstanceOf(LoyaltyTransaction::class, $tx);
        $this->assertSame(500, (int) $tx->points);

        $acc = $svc->getOrCreate($u->id);
        $this->assertSame(500, (int) $acc->points_balance);
        $this->assertSame(500.0, (float) $acc->lifetime_spend);
    }

    public function test_redeem_fails_below_min(): void
    {
        $this->enableMarketing('loyalty');
        AppSetting::set('marketing_loyalty_min_redeem', '100');
        AppSetting::flushCache();

        $u = $this->makeUser();
        $svc = app(LoyaltyService::class);
        $result = $svc->redeem($u->id, 50);

        $this->assertFalse($result['ok']);
    }

    public function test_redeem_fails_when_balance_insufficient(): void
    {
        $this->enableMarketing('loyalty');
        AppSetting::set('marketing_loyalty_min_redeem', '100');
        AppSetting::flushCache();

        $u = $this->makeUser();
        $svc = app(LoyaltyService::class);
        $result = $svc->redeem($u->id, 200);

        $this->assertFalse($result['ok']);
    }

    public function test_redeem_deducts_points_and_returns_discount(): void
    {
        $this->enableMarketing('loyalty');
        AppSetting::set('marketing_loyalty_earn_rate', '1');
        AppSetting::set('marketing_loyalty_redeem_rate', '10');
        AppSetting::set('marketing_loyalty_min_redeem', '100');
        AppSetting::flushCache();

        $u = $this->makeUser();
        $svc = app(LoyaltyService::class);
        $svc->earnFromOrder($u->id, 1000, 11);

        $result = $svc->redeem($u->id, 500);
        $this->assertTrue($result['ok']);
        $this->assertEquals(50.0, (float) $result['discount']);

        $acc = $svc->getOrCreate($u->id);
        $this->assertSame(500, (int) $acc->points_balance);
        $this->assertSame(500, (int) $acc->points_redeemed_total);
    }

    public function test_tier_silver_at_3000_spend(): void
    {
        $this->enableMarketing('loyalty');
        AppSetting::set('marketing_loyalty_earn_rate', '1');
        AppSetting::flushCache();

        $u = $this->makeUser();
        $svc = app(LoyaltyService::class);
        $svc->earnFromOrder($u->id, 3000, 12);

        $acc = $svc->getOrCreate($u->id);
        $this->assertSame('silver', $acc->tier);
    }

    public function test_tier_gold_at_15000_spend(): void
    {
        $this->enableMarketing('loyalty');
        AppSetting::set('marketing_loyalty_earn_rate', '1');
        AppSetting::flushCache();

        $u = $this->makeUser();
        $svc = app(LoyaltyService::class);
        $svc->earnFromOrder($u->id, 15000, 13);

        $acc = $svc->getOrCreate($u->id);
        $this->assertSame('gold', $acc->tier);
    }

    public function test_tier_platinum_at_50000_spend(): void
    {
        $this->enableMarketing('loyalty');
        AppSetting::set('marketing_loyalty_earn_rate', '1');
        AppSetting::flushCache();

        $u = $this->makeUser();
        $svc = app(LoyaltyService::class);
        $svc->earnFromOrder($u->id, 50000, 14);

        $acc = $svc->getOrCreate($u->id);
        $this->assertSame('platinum', $acc->tier);
    }

    public function test_reverse_on_refund_creates_reverse_transaction(): void
    {
        $this->enableMarketing('loyalty');
        AppSetting::set('marketing_loyalty_earn_rate', '1');
        AppSetting::flushCache();

        $u = $this->makeUser();
        $svc = app(LoyaltyService::class);
        $svc->earnFromOrder($u->id, 1000, 77);

        $svc->reverseOnRefund(77);

        $acc = $svc->getOrCreate($u->id);
        $this->assertSame(0, (int) $acc->points_balance);

        $reverseTx = LoyaltyTransaction::where('order_id', 77)->where('type', 'reverse')->first();
        $this->assertNotNull($reverseTx);
    }

    public function test_summary_reports_totals(): void
    {
        $this->enableMarketing('loyalty');
        AppSetting::set('marketing_loyalty_earn_rate', '1');
        AppSetting::flushCache();

        $u1 = $this->makeUser('sum1@test.com');
        $u2 = $this->makeUser('sum2@test.com');
        $svc = app(LoyaltyService::class);
        $svc->earnFromOrder($u1->id, 500, 21);
        $svc->earnFromOrder($u2->id, 800, 22);

        $sum = $svc->summary();
        $this->assertSame(2, (int) $sum['totalAccounts']);
        $this->assertSame(1300, (int) $sum['totalPoints']);
        $this->assertEquals(1300.0, (float) $sum['totalSpent']);
    }

    public function test_adjust_with_zero_points_returns_null(): void
    {
        $this->enableMarketing('loyalty');
        $u = $this->makeUser();
        $svc = app(LoyaltyService::class);
        $this->assertNull($svc->adjust($u->id, 0, 'earn'));
    }
}
