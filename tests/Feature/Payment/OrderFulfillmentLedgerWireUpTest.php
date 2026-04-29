<?php

namespace Tests\Feature\Payment;

use App\Services\OrderFulfillmentService;
use Tests\TestCase;

/**
 * Verifies OrderFulfillmentService gained a `postLedgerEntry()`
 * shadow-write hook. The shadow MUST:
 *   - bail silently when the financial_* tables don't exist
 *     (Postgres deploy still pending)
 *   - never throw — payout creation must not be coupled to ledger health
 *
 * We use reflection to assert the method exists with the right
 * shape; running the actual flow requires the full R2/order schema
 * which the test container can't boot.
 */
class OrderFulfillmentLedgerWireUpTest extends TestCase
{
    public function test_post_ledger_entry_method_exists(): void
    {
        $this->assertTrue(
            method_exists(OrderFulfillmentService::class, 'postLedgerEntry'),
            'OrderFulfillmentService must have postLedgerEntry() method to shadow legacy payouts',
        );
    }

    public function test_post_ledger_entry_is_private_or_protected(): void
    {
        $reflect = new \ReflectionMethod(OrderFulfillmentService::class, 'postLedgerEntry');
        $this->assertFalse(
            $reflect->isPublic(),
            'postLedgerEntry should not be public — it is an internal shadow-write hook',
        );
    }

    public function test_post_ledger_entry_signature_accepts_required_parameters(): void
    {
        $reflect = new \ReflectionMethod(OrderFulfillmentService::class, 'postLedgerEntry');
        $params  = array_map(fn ($p) => $p->getName(), $reflect->getParameters());

        // The legacy payout fields → ledger contract
        $this->assertContains('order',              $params);
        $this->assertContains('photographerUserId', $params);
        $this->assertContains('photographerGross',  $params);
        $this->assertContains('platformFee',        $params);
    }
}
