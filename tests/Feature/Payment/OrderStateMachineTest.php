<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Services\Payment\OrderStateMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderStateMachineTest extends TestCase
{
    private OrderStateMachine $sm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSchema();
        $this->sm = new OrderStateMachine();
    }

    private function bootSchema(): void
    {
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
        // ActivityLogger needs the table; create a minimal stub.
        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function ($t) {
                $t->bigIncrements('id');
                $t->string('action');
                $t->text('description')->nullable();
                $t->string('actor_type', 32)->nullable();
                $t->unsignedBigInteger('actor_id')->nullable();
                $t->string('target_type', 64)->nullable();
                $t->unsignedBigInteger('target_id')->nullable();
                $t->json('old_values')->nullable();
                $t->json('new_values')->nullable();
                $t->ipAddress('ip_address')->nullable();
                $t->text('user_agent')->nullable();
                $t->timestamps();
            });
        }
    }

    private function makeOrder(string $status = 'pending_payment'): Order
    {
        return Order::create([
            'user_id'      => 1,
            'order_number' => 'TST-' . uniqid(),
            'total'        => 100.00,
            'subtotal'     => 100.00,
            'status'       => $status,
        ]);
    }

    public function test_pending_payment_to_paid_transition_succeeds(): void
    {
        $order = $this->makeOrder('pending_payment');

        $changed = $this->sm->transitionToPaid($order->id, 'order.' . $order->id . '.paid');

        $this->assertTrue($changed);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_repeated_transition_to_same_state_is_idempotent_no_op(): void
    {
        $order = $this->makeOrder('pending_payment');
        $this->sm->transitionToPaid($order->id, 'order.' . $order->id . '.paid');
        $paidAtFirst = $order->fresh()->paid_at;

        // Second call with the same idempotency key — should NOT throw,
        // should NOT update paid_at again.
        $changed = $this->sm->transitionToPaid($order->id, 'order.' . $order->id . '.paid');

        $this->assertFalse($changed, 'Repeated transition must return false');
        $this->assertSame((string) $paidAtFirst, (string) $order->fresh()->paid_at);
    }

    public function test_paid_to_pending_payment_is_refused(): void
    {
        $order = $this->makeOrder('paid');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Invalid order transition/');

        $this->sm->transition($order->id, 'pending_payment', 'should-fail');
    }

    public function test_cart_can_only_go_to_pending_payment_or_cancelled(): void
    {
        $order = $this->makeOrder('cart');

        // Allowed
        $this->sm->transition($order->id, 'pending_payment', 'a');
        $this->assertSame('pending_payment', $order->fresh()->status);

        // Invalid: cart → paid (must go through pending first)
        $order2 = $this->makeOrder('cart');
        $this->expectException(\DomainException::class);
        $this->sm->transition($order2->id, 'paid', 'b');
    }

    public function test_paid_can_be_refunded(): void
    {
        $order = $this->makeOrder('paid');
        $this->sm->transition($order->id, 'refunded', 'order-' . $order->id . '-refunded');
        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_failed_can_retry_to_pending_payment(): void
    {
        $order = $this->makeOrder('failed');
        $this->sm->transition($order->id, 'pending_payment', 'retry-' . $order->id);
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_idempotency_key_is_persisted(): void
    {
        $order = $this->makeOrder('pending_payment');
        $key   = 'webhook.omise.charge_xyz.paid';
        $this->sm->transitionToPaid($order->id, $key);

        $this->assertSame($key, $order->fresh()->idempotency_key);
    }
}
