<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verifies the OrderIntegrityObserver refuses to mutate locked fields
 * once an order has reached a terminal payment state.
 */
class OrderIntegrityObserverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function ($t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('user_id');
                $t->string('order_number')->nullable();
                $t->decimal('total', 10, 2)->default(0);
                $t->decimal('subtotal', 10, 2)->default(0);
                $t->decimal('discount_amount', 10, 2)->default(0);
                $t->string('status', 32)->default('pending_payment');
                $t->string('idempotency_key', 128)->nullable();
                $t->timestamp('paid_at')->nullable();
                $t->timestamps();
            });
        } else {
            DB::table('orders')->truncate();
        }
    }

    private function makeOrder(string $status, float $total = 100.00): Order
    {
        return Order::create([
            'user_id'      => 1,
            'order_number' => 'TST-' . uniqid(),
            'total'        => $total,
            'subtotal'     => $total,
            'status'       => $status,
        ]);
    }

    public function test_pending_order_total_can_be_changed(): void
    {
        $order = $this->makeOrder('pending_payment', 100);
        $order->update(['total' => 150]);  // pre-paid → mutable
        $this->assertSame('150.00', (string) $order->fresh()->total);
    }

    public function test_paid_order_total_mutation_is_refused(): void
    {
        $order = $this->makeOrder('paid', 100);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/immutable/i');
        $order->update(['total' => 999]);  // hostile change attempt
    }

    public function test_paid_order_subtotal_mutation_is_refused(): void
    {
        $order = $this->makeOrder('paid', 100);
        $this->expectException(\DomainException::class);
        $order->update(['subtotal' => 200]);
    }

    public function test_paid_order_discount_mutation_is_refused(): void
    {
        $order = $this->makeOrder('paid', 100);
        $this->expectException(\DomainException::class);
        $order->update(['discount_amount' => 50]);
    }

    public function test_paid_order_can_still_change_other_fields(): void
    {
        $order = $this->makeOrder('paid', 100);
        // Order number is just an audit string — mutable.
        $order->update(['order_number' => 'NEW-NUMBER']);
        $this->assertSame('NEW-NUMBER', $order->fresh()->order_number);
    }

    public function test_save_quietly_bypasses_observer_for_admin_overrides(): void
    {
        $order = $this->makeOrder('paid', 100);
        // Documented escape hatch: explicit ops override.
        $order->forceFill(['total' => 99])->saveQuietly();
        $this->assertSame('99.00', (string) $order->fresh()->total);
    }

    public function test_refunded_order_is_also_locked(): void
    {
        $order = $this->makeOrder('refunded', 100);
        $this->expectException(\DomainException::class);
        $order->update(['total' => 1]);
    }
}
