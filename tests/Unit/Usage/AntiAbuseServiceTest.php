<?php

namespace Tests\Unit\Usage;

use App\Services\Usage\AntiAbuseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AntiAbuseServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!Schema::hasTable('signup_signals')) {
            Schema::create('signup_signals', function ($t) {
                $t->bigIncrements('id');
                $t->string('email_hash', 64)->nullable()->index();
                $t->string('ip_hash', 64)->nullable()->index();
                $t->string('device_fingerprint', 64)->nullable()->index();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->unsignedSmallInteger('risk_score')->default(0);
                $t->boolean('flagged')->default(false);
                $t->json('metadata')->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        } else {
            DB::table('signup_signals')->truncate();
        }
    }

    public function test_disposable_email_provider_blocks_immediately(): void
    {
        $r = (new AntiAbuseService())->evaluateSignup(
            email: 'attacker@mailinator.com',
            ip:    '1.2.3.4',
        );

        $this->assertSame(AntiAbuseService::DECISION_BLOCK, $r['decision']);
        $this->assertSame(100, $r['score']);
    }

    public function test_clean_signup_passes_with_zero_score(): void
    {
        $r = (new AntiAbuseService())->evaluateSignup(
            email: 'genuine@example.com',
            ip:    '203.0.113.1',
        );

        $this->assertSame(AntiAbuseService::DECISION_OK, $r['decision']);
        $this->assertSame(0, $r['score']);
    }

    public function test_repeat_ip_pushes_into_verify_decision(): void
    {
        $svc = new AntiAbuseService();
        $ip  = '203.0.113.7';

        // Five previous signups from same IP — well over max_per_ip_per_day=3.
        // Each prior triggers +30 + min(40, hits*5), so 5 hits → +30 + 25 = 55
        // which crosses the flag threshold (50) but stays under block (80).
        for ($i = 0; $i < 5; $i++) {
            $svc->evaluateSignup(email: "user{$i}@example.com", ip: $ip);
        }
        $r = $svc->evaluateSignup(email: 'user_new@example.com', ip: $ip);

        $this->assertContains($r['decision'], [
            AntiAbuseService::DECISION_REQUIRE_VERIFY,
            AntiAbuseService::DECISION_BLOCK,
        ]);
        $this->assertGreaterThan(0, $r['score']);
    }

    public function test_email_alias_abuse_is_collapsed_to_same_stem(): void
    {
        $svc = new AntiAbuseService();

        // 'user+a@gmail.com' and 'user+b@gmail.com' have the SAME stem
        $svc->evaluateSignup(email: 'user+a@gmail.com', ip: '203.0.113.10');
        $r = $svc->evaluateSignup(email: 'user+b@gmail.com', ip: '203.0.113.11');

        // Different IPs, same email stem → score should rise
        $this->assertGreaterThan(0, $r['score']);
        $this->assertNotEmpty($r['reasons']);
    }

    public function test_disabled_anti_abuse_returns_ok_unconditionally(): void
    {
        config(['usage.anti_abuse.enabled' => false]);

        $r = (new AntiAbuseService())->evaluateSignup(
            email: 'attacker@mailinator.com', // would otherwise block
            ip:    '1.2.3.4',
        );
        $this->assertSame(AntiAbuseService::DECISION_OK, $r['decision']);
    }

    public function test_signal_row_records_decision_metadata(): void
    {
        (new AntiAbuseService())->evaluateSignup(
            email: 'genuine@example.com',
            ip:    '203.0.113.99',
        );

        $row = DB::table('signup_signals')->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->ip_hash);
        $this->assertNotNull($row->email_hash);
    }
}
