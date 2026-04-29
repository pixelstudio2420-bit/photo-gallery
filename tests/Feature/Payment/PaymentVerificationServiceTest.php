<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Models\PaymentSlip;
use App\Services\Payment\FraudDetectionService;
use App\Services\Payment\PaymentVerificationResult;
use App\Services\Payment\PaymentVerificationService;
use App\Services\Payment\SlipFingerprint;
use App\Services\Payment\SlipFingerprintService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentVerificationServiceTest extends TestCase
{
    private PaymentVerificationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSchema();
        $this->svc = new PaymentVerificationService(
            new SlipFingerprintService(),
            new FraudDetectionService(),
        );
    }

    private function bootSchema(): void
    {
        // Reuse SlipFingerprintServiceTest's schema (orders + payment_slips)
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function ($t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('user_id');
                $t->string('order_number')->nullable();
                $t->decimal('total', 10, 2)->default(0);
                $t->string('status', 32)->default('pending_payment');
                $t->timestamps();
            });
        } else {
            DB::table('orders')->truncate();
        }
        if (!Schema::hasTable('payment_slips')) {
            Schema::create('payment_slips', function ($t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('order_id');
                $t->string('slip_path')->nullable();
                $t->string('slip_hash', 64)->nullable()->index();
                $t->decimal('amount', 10, 2)->nullable();
                $t->string('reference_code')->nullable();
                $t->string('verify_status', 16)->default('pending');
                $t->integer('verify_score')->default(0);
                $t->timestamp('transfer_date')->nullable();
                $t->timestamp('verified_at')->nullable();
                $t->json('fraud_flags')->nullable();
                $t->json('verify_breakdown')->nullable();
                $t->string('slipok_trans_ref')->nullable();
                $t->string('receiver_account')->nullable();
                $t->string('receiver_name')->nullable();
                $t->string('sender_name')->nullable();
                $t->timestamps();
            });
        } else {
            DB::table('payment_slips')->truncate();
        }
    }

    private function makeOrder(int $userId, float $total = 1000.00, string $status = 'pending_payment'): Order
    {
        return Order::create([
            'user_id'      => $userId,
            'order_number' => 'O-' . uniqid(),
            'total'        => $total,
            'status'       => $status,
        ]);
    }

    private function fp(string $sha = 'a'): SlipFingerprint
    {
        return new SlipFingerprint(
            sha256: str_pad(str_repeat($sha, 64), 64, '0', STR_PAD_LEFT),
            bytes:  100_000,
            mime:   'image/jpeg',
        );
    }

    private function context(float $amount, ?string $slipDate = null, ?Order $order = null): array
    {
        return [
            'transfer_amount'   => $amount,
            'order_amount'      => (float) ($order->total ?? 1000.00),
            'transfer_date'     => $slipDate ?? Carbon::now()->toIso8601String(),
            'order_created_at'  => Carbon::now()->subMinutes(10)->toIso8601String(),
            'ref_code'          => null,
        ];
    }

    /* ─────────────────── Hard rejections ─────────────────── */

    public function test_rejects_when_order_already_paid(): void
    {
        $order = $this->makeOrder(1, 1000, 'paid');
        $r = $this->svc->verify(
            file: \Illuminate\Http\UploadedFile::fake()->image('s.jpg', 200, 300),
            order: $order,
            fingerprint: $this->fp(),
            context: $this->context(1000, order: $order),
        );

        $this->assertTrue($r->isRejected());
        $this->assertContains('order_not_payable', $r->fraudFlags);
    }

    public function test_rejects_cross_user_slip_reuse(): void
    {
        // User 1 has approved slip with hash X
        $orderA = $this->makeOrder(1, 1000);
        PaymentSlip::create([
            'order_id'      => $orderA->id,
            'slip_hash'     => $this->fp('a')->sha256,
            'amount'        => 1000,
            'verify_status' => 'approved',
        ]);

        // User 2 tries to upload SAME hash for THEIR order
        $orderB = $this->makeOrder(2, 1000);
        $r = $this->svc->verify(
            file: \Illuminate\Http\UploadedFile::fake()->image('s.jpg', 200, 300),
            order: $orderB,
            fingerprint: $this->fp('a'),
            context: $this->context(1000, order: $orderB),
        );

        $this->assertTrue($r->isRejected(), 'Cross-user reuse must HARD reject');
        $this->assertContains('duplicate_hash_cross_user', $r->fraudFlags);
    }

    public function test_rejects_same_user_different_order_reuse(): void
    {
        $userId = 5;
        $orderA = $this->makeOrder($userId, 1000);
        $orderB = $this->makeOrder($userId, 500);
        PaymentSlip::create([
            'order_id'      => $orderA->id,
            'slip_hash'     => $this->fp('b')->sha256,
            'amount'        => 1000,
            'verify_status' => 'approved',
        ]);

        $r = $this->svc->verify(
            file: \Illuminate\Http\UploadedFile::fake()->image('s.jpg'),
            order: $orderB,
            fingerprint: $this->fp('b'),
            context: $this->context(500, order: $orderB),
        );
        $this->assertTrue($r->isRejected());
        $this->assertContains('duplicate_hash_same_user', $r->fraudFlags);
    }

    public function test_rejects_underpaid_amount(): void
    {
        $order = $this->makeOrder(1, 1000);
        $r = $this->svc->verify(
            file: \Illuminate\Http\UploadedFile::fake()->image('s.jpg'),
            order: $order,
            fingerprint: $this->fp(),
            context: $this->context(900, order: $order),  // 10% underpaid
        );
        $this->assertTrue($r->isRejected());
        $this->assertContains('amount_underpaid', $r->fraudFlags);
    }

    public function test_rejects_slip_dated_before_order(): void
    {
        $order = $this->makeOrder(1, 1000);
        $r = $this->svc->verify(
            file: \Illuminate\Http\UploadedFile::fake()->image('s.jpg'),
            order: $order,
            fingerprint: $this->fp(),
            context: [
                'transfer_amount'   => 1000,
                'order_amount'      => 1000,
                'transfer_date'     => Carbon::now()->subDays(5)->toIso8601String(),  // BEFORE order
                'order_created_at'  => Carbon::now()->subMinute()->toIso8601String(),
                'ref_code'          => null,
            ],
        );
        $this->assertTrue($r->isRejected());
        $this->assertContains('slip_predates_order', $r->fraudFlags);
    }

    public function test_rejects_stale_slip_older_than_30_days(): void
    {
        $order = $this->makeOrder(1, 1000);
        $r = $this->svc->verify(
            file: \Illuminate\Http\UploadedFile::fake()->image('s.jpg'),
            order: $order,
            fingerprint: $this->fp(),
            context: [
                'transfer_amount'   => 1000,
                'order_amount'      => 1000,
                'transfer_date'     => Carbon::now()->subDays(45)->toIso8601String(),
                'order_created_at'  => Carbon::now()->subDays(50)->toIso8601String(),
                'ref_code'          => null,
            ],
        );
        $this->assertTrue($r->isRejected());
        $this->assertContains('slip_too_old', $r->fraudFlags);
    }

    public function test_rejects_slipok_ref_already_used_cross_user(): void
    {
        $orderA = $this->makeOrder(1, 1000);
        PaymentSlip::create([
            'order_id'         => $orderA->id,
            'slip_hash'        => str_repeat('z', 64),
            'amount'           => 1000,
            'slipok_trans_ref' => 'BANK-TRX-1',
            'verify_status'    => 'approved',
        ]);

        $orderB = $this->makeOrder(2, 1000);
        $r = $this->svc->verify(
            file: \Illuminate\Http\UploadedFile::fake()->image('s.jpg'),
            order: $orderB,
            fingerprint: $this->fp(),  // different hash
            context: $this->context(1000, order: $orderB),
            slipokResult: [
                'success'    => true,
                'trans_ref'  => 'BANK-TRX-1',  // SAME bank reference — fraud!
                'amount_verified' => true,
            ],
        );
        $this->assertTrue($r->isRejected());
        $this->assertContains('duplicate_slipok_trans_ref', $r->fraudFlags);
    }

    /* ─────────────────── Soft path ─────────────────── */

    public function test_clean_slip_with_default_manual_mode_goes_to_review(): void
    {
        // Default verify_mode='manual' → never auto-approve.
        $order = $this->makeOrder(1, 1000);
        $r = $this->svc->verify(
            file: \Illuminate\Http\UploadedFile::fake()->image('s.jpg'),
            order: $order,
            fingerprint: $this->fp(),
            context: $this->context(1000, order: $order),
        );
        $this->assertTrue($r->isManualReview());
    }
}
