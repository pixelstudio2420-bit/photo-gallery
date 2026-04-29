<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Services\Payment\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * End-to-end order lifecycle tests.
 *
 * These tests stitch together the public-facing user journey from
 * "logged-in user with a cart" through to "paid order with the
 * customer able to download" — covering the seams between
 * controllers, the OrderStateMachine, and the data layer.
 *
 * Why these exist alongside the unit-level service tests
 * ------------------------------------------------------
 * OrderStateMachineTest covers the state machine in isolation.
 * PaymentControllerWireUpTest covers the controller's static shape.
 * Neither catches the case where:
 *   • a route is renamed and the controller orphaned,
 *   • middleware silently blocks a real customer,
 *   • a transition is allowed by the SM but the surrounding code
 *     forgets to write the side-effect (paid_at, idempotency_key,
 *     activity log, etc).
 *
 * E2E HTTP tests catch all three.
 *
 * What's intentionally NOT covered here
 * --------------------------------------
 * The slip-upload happy path requires R2 + image fingerprinting +
 * the verification service + an admin reviewer. That's exercised in
 * PaymentVerificationServiceTest + manual QA. Here we stop at "order
 * exists, transitions through SM" because anything else risks coupling
 * E2E to infra that isn't available in the test container.
 */
class OrderLifecycleE2ETest extends TestCase
{
    use RefreshDatabase;

    private OrderStateMachine $sm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sm = new OrderStateMachine();
    }

    private function makeUser(string $tag = 'e2e'): User
    {
        return User::create([
            'first_name'    => 'E2E',
            'last_name'     => 'Tester',
            'email'         => "{$tag}-" . uniqid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);
    }

    private function makeEvent(): Event
    {
        return Event::create([
            'name'            => 'E2E Test Event',
            'slug'            => 'e2e-event-' . uniqid(),
            'status'          => 'active',
            'visibility'      => 'public',
            'price_per_photo' => 25.00,
            'is_free'         => false,
            'view_count'      => 0,
        ]);
    }

    /**
     * Direct-DB order seed. We deliberately don't go through POST /orders
     * because the controller has cart-dependent logic that would couple
     * these E2E tests to cart session state. The path-under-test in this
     * file is "what happens AFTER an order exists" — state transitions,
     * page renders, refund flow.
     */
    private function makeOrder(User $user, Event $event, string $status = 'pending_payment', float $total = 75.00): Order
    {
        return Order::create([
            'user_id'      => $user->id,
            'event_id'     => $event->id,
            'order_number' => 'E2E-' . strtoupper(uniqid()),
            'total'        => $total,
            'status'       => $status,
        ]);
    }

    // =========================================================================
    // Page rendering: each major page in the customer journey loads cleanly
    // =========================================================================

    public function test_logged_in_user_can_view_cart_index(): void
    {
        $user = $this->makeUser('cart');
        $response = $this->actingAs($user)->get(route('cart.index'));
        $response->assertStatus(200);
    }

    public function test_logged_in_user_can_view_orders_index(): void
    {
        $user = $this->makeUser('idx');
        $response = $this->actingAs($user)->get(route('orders.index'));
        $response->assertStatus(200);
    }

    public function test_logged_in_user_can_view_their_own_pending_order(): void
    {
        $user  = $this->makeUser('show');
        $event = $this->makeEvent();
        $order = $this->makeOrder($user, $event);

        $response = $this->actingAs($user)->get(route('orders.show', $order->id));
        $response->assertStatus(200);
    }

    public function test_logged_in_user_can_view_their_own_paid_order(): void
    {
        $user  = $this->makeUser('paid-show');
        $event = $this->makeEvent();
        $order = $this->makeOrder($user, $event, 'paid');

        $response = $this->actingAs($user)->get(route('orders.show', $order->id));
        $response->assertStatus(200);
    }

    // =========================================================================
    // State machine flow: pending_payment → pending_review → paid → refunded
    // =========================================================================

    public function test_state_machine_drives_order_through_full_paid_lifecycle(): void
    {
        $user  = $this->makeUser('flow');
        $event = $this->makeEvent();
        $order = $this->makeOrder($user, $event);

        // pending_payment → pending_review (slip uploaded)
        $changed = $this->sm->transition(
            orderId:        $order->id,
            toStatus:       'pending_review',
            idempotencyKey: 'e2e.flow.review',
        );
        $this->assertTrue($changed);
        $this->assertSame('pending_review', $order->fresh()->status);

        // pending_review → paid (admin approves)
        $changed = $this->sm->transitionToPaid(
            orderId:        $order->id,
            idempotencyKey: 'e2e.flow.paid',
        );
        $this->assertTrue($changed);

        $fresh = $order->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertNotNull($fresh->paid_at, 'paid_at must be stamped when transitioning to paid');
        $this->assertNotEmpty($fresh->idempotency_key, 'idempotency_key must persist');
    }

    public function test_state_machine_blocks_invalid_transition_with_domain_exception(): void
    {
        $user  = $this->makeUser('blocked');
        $event = $this->makeEvent();
        // Refunded is terminal — anything goes from there should fail.
        $order = $this->makeOrder($user, $event, 'refunded');

        $this->expectException(\DomainException::class);
        $this->sm->transitionToPaid(
            orderId:        $order->id,
            idempotencyKey: 'e2e.illegal',
        );
    }

    public function test_paying_already_paid_order_is_idempotent_no_op(): void
    {
        $user  = $this->makeUser('idem');
        $event = $this->makeEvent();
        $order = $this->makeOrder($user, $event, 'paid');

        // Same idempotency key, same target — must be a quiet false.
        $changed = $this->sm->transitionToPaid(
            orderId:        $order->id,
            idempotencyKey: 'e2e.replay',
        );

        $this->assertFalse($changed, 'second paid transition must be a no-op (idempotent retry)');
    }

    public function test_paid_order_can_transition_to_refunded(): void
    {
        $user  = $this->makeUser('refund');
        $event = $this->makeEvent();
        $order = $this->makeOrder($user, $event, 'paid');

        $changed = $this->sm->transition(
            orderId:        $order->id,
            toStatus:       'refunded',
            idempotencyKey: 'e2e.refund',
            auditContext:   ['reason' => 'customer requested'],
        );

        $this->assertTrue($changed);
        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_failed_order_can_be_retried_back_to_pending_payment(): void
    {
        $user  = $this->makeUser('retry');
        $event = $this->makeEvent();
        $order = $this->makeOrder($user, $event, 'failed');

        // ALLOWED_TRANSITIONS allows failed → pending_payment for retry.
        $changed = $this->sm->transition(
            orderId:        $order->id,
            toStatus:       'pending_payment',
            idempotencyKey: 'e2e.retry',
        );

        $this->assertTrue($changed);
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_cancelled_order_is_terminal(): void
    {
        $user  = $this->makeUser('cancel-term');
        $event = $this->makeEvent();
        $order = $this->makeOrder($user, $event, 'cancelled');

        $this->expectException(\DomainException::class);
        $this->sm->transitionToPaid(
            orderId:        $order->id,
            idempotencyKey: 'e2e.cancel-after',
        );
    }

    // =========================================================================
    // Audit + ledger side-effects fire on terminal transitions
    // =========================================================================

    public function test_paid_transition_writes_activity_log_row(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('activity_logs')) {
            $this->markTestSkipped('activity_logs table not present in this environment');
        }

        $user  = $this->makeUser('audit');
        $event = $this->makeEvent();
        $order = $this->makeOrder($user, $event);

        $this->sm->transitionToPaid(
            orderId:        $order->id,
            idempotencyKey: 'e2e.audit.paid',
            auditContext:   ['source' => 'e2e-test'],
        );

        $row = DB::table('activity_logs')
            ->where('action', 'order.transitioned.paid')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($row, 'activity_logs must record state-machine transitions');
    }

    // =========================================================================
    // Authorisation: a user cannot view another user's order
    // =========================================================================

    public function test_user_cannot_view_other_users_order(): void
    {
        $owner    = $this->makeUser('owner');
        $stranger = $this->makeUser('stranger');
        $event    = $this->makeEvent();
        $order    = $this->makeOrder($owner, $event);

        $response = $this->actingAs($stranger)->get(route('orders.show', $order->id));

        // Either 403/404 — both prevent leakage. 200 would be a
        // serious data-confidentiality bug.
        $this->assertContains(
            $response->getStatusCode(),
            [302, 403, 404],
            'stranger should not see another user\'s order',
        );
    }

    // =========================================================================
    // Guest is bounced from authenticated areas
    // =========================================================================

    public function test_guest_is_bounced_from_orders_index(): void
    {
        $response = $this->get(route('orders.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_guest_is_bounced_from_cart_index(): void
    {
        // /cart is gated behind auth; without it the cart page would be
        // semi-public and abusable.
        $response = $this->get(route('cart.index'));
        $response->assertRedirect(route('login'));
    }
}
