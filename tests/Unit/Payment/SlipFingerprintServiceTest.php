<?php

namespace Tests\Unit\Payment;

use App\Models\Order;
use App\Models\PaymentSlip;
use App\Services\Payment\SlipFingerprintService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SlipFingerprintServiceTest extends TestCase
{
    private SlipFingerprintService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSchema();
        $this->svc = new SlipFingerprintService();
    }

    private function bootSchema(): void
    {
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

    public function test_fingerprint_returns_sha256_bytes_and_mime(): void
    {
        $file = UploadedFile::fake()->image('slip.jpg', 200, 300);
        $fp   = $this->svc->fingerprint($file);

        $this->assertSame(64, strlen($fp->sha256));
        $this->assertGreaterThan(0, $fp->bytes);
        $this->assertStringStartsWith('image/', $fp->mime);
    }

    public function test_fingerprint_phash_is_present_when_gd_available(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not loaded');
        }
        $file = UploadedFile::fake()->image('slip.jpg', 200, 300);
        $fp   = $this->svc->fingerprint($file);

        $this->assertNotNull($fp->pHash);
        $this->assertSame(16, strlen($fp->pHash), 'pHash must be 16 hex chars (64 bits)');
    }

    public function test_cross_user_duplicate_detected(): void
    {
        // User A's order with slip
        $orderA = $this->makeOrder(userId: 1);
        PaymentSlip::create([
            'order_id'      => $orderA->id,
            'slip_hash'     => str_repeat('a', 64),
            'amount'        => 100.00,
            'verify_status' => 'approved',
        ]);

        // User B's order tries to reuse the SAME image
        $dup = $this->svc->findCrossUserDuplicate(str_repeat('a', 64), userId: 2);
        $this->assertNotNull($dup);
        $this->assertSame($orderA->id, (int) $dup->order_id);
    }

    public function test_cross_user_duplicate_excludes_rejected_slips(): void
    {
        $orderA = $this->makeOrder(userId: 1);
        PaymentSlip::create([
            'order_id'      => $orderA->id,
            'slip_hash'     => str_repeat('b', 64),
            'amount'        => 100.00,
            'verify_status' => 'rejected',  // rejected → user B can re-upload (false-positive recovery)
        ]);

        $dup = $this->svc->findCrossUserDuplicate(str_repeat('b', 64), userId: 2);
        $this->assertNull($dup, 'Rejected slips must NOT block other users');
    }

    public function test_cross_user_duplicate_does_not_match_same_user(): void
    {
        // Same hash, same user, different order — that's a different
        // helper (findSameUserDifferentOrder) — cross-user must NOT fire.
        $orderA = $this->makeOrder(userId: 5);
        PaymentSlip::create([
            'order_id'      => $orderA->id,
            'slip_hash'     => str_repeat('c', 64),
            'amount'        => 100.00,
            'verify_status' => 'approved',
        ]);

        $dup = $this->svc->findCrossUserDuplicate(str_repeat('c', 64), userId: 5);
        $this->assertNull($dup);
    }

    public function test_same_user_different_order_detected(): void
    {
        $userId = 7;
        $orderA = $this->makeOrder(userId: $userId);
        $orderB = $this->makeOrder(userId: $userId);
        PaymentSlip::create([
            'order_id'      => $orderA->id,
            'slip_hash'     => str_repeat('d', 64),
            'amount'        => 100.00,
            'verify_status' => 'approved',
        ]);

        $dup = $this->svc->findSameUserDifferentOrder(str_repeat('d', 64), $userId, $orderB->id);
        $this->assertNotNull($dup);
        $this->assertSame($orderA->id, (int) $dup->order_id);
    }

    public function test_slipok_ref_duplicate_detected(): void
    {
        $orderA = $this->makeOrder(userId: 1);
        PaymentSlip::create([
            'order_id'         => $orderA->id,
            'slip_hash'        => str_repeat('e', 64),
            'amount'           => 100.00,
            'slipok_trans_ref' => 'TRX-ABC-123',
            'verify_status'    => 'approved',
        ]);

        $dup = $this->svc->findSlipokRefDuplicate('TRX-ABC-123');
        $this->assertNotNull($dup);
        $this->assertSame('TRX-ABC-123', $dup->slipok_trans_ref);

        // Excluding by id should miss the only match
        $dup2 = $this->svc->findSlipokRefDuplicate('TRX-ABC-123', excludeSlipId: $dup->id);
        $this->assertNull($dup2);
    }

    public function test_empty_hash_returns_null(): void
    {
        $this->assertNull($this->svc->findCrossUserDuplicate('', userId: 1));
        $this->assertNull($this->svc->findSameUserDifferentOrder('', userId: 1, orderId: 1));
        $this->assertNull($this->svc->findSlipokRefDuplicate(null));
    }

    private function makeOrder(int $userId): Order
    {
        return Order::create([
            'user_id'      => $userId,
            'order_number' => 'TST-' . uniqid(),
            'total'        => 100.00,
            'status'       => 'pending_payment',
        ]);
    }
}
