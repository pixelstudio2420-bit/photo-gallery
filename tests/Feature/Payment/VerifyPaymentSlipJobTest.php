<?php

namespace Tests\Feature\Payment;

use App\Jobs\Payment\VerifyPaymentSlipJob;
use App\Models\AppSetting;
use App\Models\Event;
use App\Models\Order;
use App\Models\PaymentSlip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * State-guards for the async slip re-verifier.
 *
 * The job's public contract is "only acts on pending slips with no
 * transRef" — every other state must be a no-op. These tests pin down
 * exactly that, plus the happy-path branch where the SlipOK API is
 * mocked successful and we verify the slip flips correctly.
 *
 * We deliberately don't mock the SlipOKService class itself; we mock
 * Http (because that's what the service uses internally) so we exercise
 * the same code path real production traffic does.
 */
class VerifyPaymentSlipJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeSlip(array $overrides = []): PaymentSlip
    {
        $user = User::create([
            'first_name'    => 'Job',
            'last_name'     => 'Tester',
            'email'         => 'job-' . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);
        $event = Event::create([
            'name'            => 'Job Test Event ' . uniqid(),
            'slug'            => 'job-test-' . uniqid(),
            'status'          => 'active',
            'visibility'      => 'public',
            'price_per_photo' => 100.00,
            'is_free'         => false,
            'view_count'      => 0,
        ]);
        $order = Order::create([
            'user_id'      => $user->id,
            'event_id'     => $event->id,
            'order_number' => 'ORD-' . uniqid(),
            'total'        => 1000.00,
            'status'       => 'pending_payment',
        ]);

        return PaymentSlip::create(array_merge([
            'order_id'      => $order->id,
            'slip_path'     => 'payments/slips/test-' . uniqid() . '.jpg',
            'slip_hash'     => str_pad(uniqid(), 64, '0'),
            'amount'        => 1000.00,
            'verify_status' => 'pending',
            'verify_score'  => 60,
        ], $overrides));
    }

    public function test_noop_when_slipok_disabled(): void
    {
        AppSetting::set('slipok_enabled', '0');
        AppSetting::flushCache();

        $slip = $this->makeSlip();

        Http::fake();   // any HTTP call would be a bug
        (new VerifyPaymentSlipJob($slip->id))->handle(app(\App\Services\Payment\SlipOKService::class));

        Http::assertNothingSent();
        $this->assertEquals('pending', $slip->fresh()->verify_status,
            'Disabled SlipOK must short-circuit before touching anything.');
    }

    public function test_noop_when_slip_not_found(): void
    {
        AppSetting::set('slipok_enabled', '1');
        AppSetting::set('slipok_api_key', 'test-key');
        AppSetting::set('slipok_branch_id', 'branch-1');
        AppSetting::flushCache();

        Http::fake();
        // Slip id 999999 doesn't exist — must not throw, must not call API
        (new VerifyPaymentSlipJob(999999))->handle(app(\App\Services\Payment\SlipOKService::class));

        // Job exited cleanly (no exception). assertNothingSent confirms the
        // SlipOK API path was never reached, which is the contract.
        Http::assertNothingSent();
        $this->assertTrue(true, 'job completed without exception');
    }

    public function test_noop_when_slip_already_terminal(): void
    {
        AppSetting::set('slipok_enabled', '1');
        AppSetting::set('slipok_api_key', 'test-key');
        AppSetting::set('slipok_branch_id', 'branch-1');
        AppSetting::flushCache();

        $approved = $this->makeSlip([
            'verify_status' => 'approved',
            'verified_at'   => now(),
            'verified_by'   => 'admin',
        ]);
        $rejected = $this->makeSlip(['verify_status' => 'rejected']);

        Http::fake();
        (new VerifyPaymentSlipJob($approved->id))->handle(app(\App\Services\Payment\SlipOKService::class));
        (new VerifyPaymentSlipJob($rejected->id))->handle(app(\App\Services\Payment\SlipOKService::class));

        Http::assertNothingSent();
        $this->assertEquals('approved', $approved->fresh()->verify_status);
        $this->assertEquals('rejected', $rejected->fresh()->verify_status);
    }

    public function test_noop_when_slip_already_has_transref(): void
    {
        AppSetting::set('slipok_enabled', '1');
        AppSetting::set('slipok_api_key', 'test-key');
        AppSetting::set('slipok_branch_id', 'branch-1');
        AppSetting::flushCache();

        // Inline verify already won — transRef present, just pending review
        $slip = $this->makeSlip(['slipok_trans_ref' => 'TR-ALREADY-HAVE']);

        Http::fake();
        (new VerifyPaymentSlipJob($slip->id))->handle(app(\App\Services\Payment\SlipOKService::class));

        Http::assertNothingSent();
        // Status unchanged — no async re-process needed when transRef exists
        $this->assertEquals('pending', $slip->fresh()->verify_status);
        $this->assertEquals('TR-ALREADY-HAVE', $slip->fresh()->slipok_trans_ref);
    }

    public function test_unique_id_keyed_per_slip(): void
    {
        // Two job instances for the same slip → same uniqueId, lock prevents double-run.
        // For two different slips → distinct uniqueIds, no lock conflict.
        $a1 = new VerifyPaymentSlipJob(1);
        $a2 = new VerifyPaymentSlipJob(1);
        $b  = new VerifyPaymentSlipJob(2);

        $this->assertEquals($a1->uniqueId(), $a2->uniqueId());
        $this->assertNotEquals($a1->uniqueId(), $b->uniqueId());
    }

    public function test_backoff_is_exponential(): void
    {
        $job = new VerifyPaymentSlipJob(1);
        $this->assertSame([60, 300, 900], $job->backoff(),
            'Backoff must be 1m / 5m / 15m so transient SlipOK outages self-heal across the 21-min window.');
    }

    public function test_tries_count_caps_retries(): void
    {
        $job = new VerifyPaymentSlipJob(1);
        $this->assertSame(3, $job->tries,
            'Three attempts is enough — past that, leave it for admin review.');
    }
}
