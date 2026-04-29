<?php

namespace Tests\Feature\Payment;

use App\Services\Payment\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Tests for the payment circuit breaker primitive.
 *
 * The breaker is on the path of every refund + checkout that goes
 * through Omise / PayPal. If it returns the wrong state, calls either:
 *   • run when they shouldn't (defeats the breaker), or
 *   • short-circuit when they should run (blocks legitimate payments).
 *
 * Both modes are losses — the breaker exists to ensure we know which
 * one we're choosing at any given moment.
 *
 * The state transitions enforced here are:
 *   CLOSED → (N failures) → OPEN
 *   OPEN   → (cooldown elapsed + 1 success) → CLOSED
 *   OPEN   → (cooldown elapsed + 1 failure) → OPEN (re-armed)
 */
class CircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Each test gets a clean cache so prior trips don't leak.
        Cache::flush();
    }

    public function test_passes_through_calls_in_closed_state(): void
    {
        $cb = new CircuitBreaker('test_pass', failureThreshold: 3);

        $result = $cb->call(fn() => 'OK');

        $this->assertSame('OK', $result);
        $this->assertSame('closed', $cb->status()['state']);
    }

    public function test_opens_after_threshold_consecutive_failures(): void
    {
        $cb = new CircuitBreaker('test_open', failureThreshold: 3, cooldownSeconds: 30);

        // 3 failures → breaker opens.
        for ($i = 0; $i < 3; $i++) {
            $r = $cb->call(
                fn() => throw new \RuntimeException("boom {$i}"),
                fallback: 'FB',
            );
            $this->assertSame('FB', $r);
        }

        $status = $cb->status();
        $this->assertSame('open', $status['state']);
        $this->assertSame(3, $status['failures']);
        $this->assertGreaterThan(time(), $status['open_until']);
    }

    public function test_short_circuits_while_open(): void
    {
        $cb = new CircuitBreaker('test_short', failureThreshold: 2, cooldownSeconds: 30);

        // Trip it.
        $cb->call(fn() => throw new \RuntimeException('a'), fallback: null);
        $cb->call(fn() => throw new \RuntimeException('b'), fallback: null);
        $this->assertSame('open', $cb->status()['state']);

        // Now the inner closure should NOT execute.
        $executed = false;
        $r = $cb->call(
            function () use (&$executed) {
                $executed = true;
                return 'should-not-run';
            },
            fallback: 'SHORT',
        );
        $this->assertFalse($executed, 'closure must not run while breaker is open');
        $this->assertSame('SHORT', $r);
    }

    public function test_throws_when_open_and_no_fallback_provided(): void
    {
        $cb = new CircuitBreaker('test_throw', failureThreshold: 1, cooldownSeconds: 30);

        // 1 failure → open.
        $cb->call(fn() => throw new \RuntimeException('x'), fallback: null);

        $this->expectException(\RuntimeException::class);
        $cb->call(fn() => 'unreachable');
    }

    public function test_recovers_to_closed_after_successful_probe(): void
    {
        $cb = new CircuitBreaker('test_recover', failureThreshold: 2, cooldownSeconds: 1);

        $cb->call(fn() => throw new \RuntimeException('a'), fallback: null);
        $cb->call(fn() => throw new \RuntimeException('b'), fallback: null);
        $this->assertSame('open', $cb->status()['state']);

        // Wait out the cooldown. (1s is short so the test stays fast.)
        sleep(2);

        // First call after cooldown is the half-open probe — if it
        // succeeds we go back to CLOSED.
        $r = $cb->call(fn() => 'recovered');
        $this->assertSame('recovered', $r);
        $this->assertSame('closed', $cb->status()['state']);
        $this->assertSame(0, $cb->status()['failures']);
    }

    public function test_reopens_if_probe_fails(): void
    {
        $cb = new CircuitBreaker('test_reopen', failureThreshold: 2, cooldownSeconds: 1);

        $cb->call(fn() => throw new \RuntimeException('a'), fallback: null);
        $cb->call(fn() => throw new \RuntimeException('b'), fallback: null);
        sleep(2);

        // Probe still failing → must re-open with a fresh cooldown.
        $cb->call(fn() => throw new \RuntimeException('still bad'), fallback: null);
        $this->assertSame('open', $cb->status()['state']);
    }

    public function test_manual_trip_increments_failures(): void
    {
        $cb = new CircuitBreaker('test_trip', failureThreshold: 2, cooldownSeconds: 30);

        // No exception thrown by gateway — but body indicated upstream error.
        // Gateway code calls trip() to count it.
        $cb->trip('upstream returned 500-shaped success');
        $this->assertSame(1, $cb->status()['failures']);
        $this->assertSame('closed', $cb->status()['state']);

        $cb->trip('again');
        $this->assertSame('open', $cb->status()['state']);
    }

    public function test_reset_clears_state(): void
    {
        $cb = new CircuitBreaker('test_reset', failureThreshold: 1, cooldownSeconds: 30);

        $cb->call(fn() => throw new \RuntimeException('x'), fallback: null);
        $this->assertSame('open', $cb->status()['state']);

        $cb->reset();
        $this->assertSame('closed', $cb->status()['state']);
        $this->assertSame(0, $cb->status()['failures']);
    }

    public function test_separate_services_have_independent_breakers(): void
    {
        $omise  = new CircuitBreaker('test_iso_omise',  failureThreshold: 1, cooldownSeconds: 30);
        $paypal = new CircuitBreaker('test_iso_paypal', failureThreshold: 1, cooldownSeconds: 30);

        // Trip omise only.
        $omise->call(fn() => throw new \RuntimeException('omise dead'), fallback: null);

        $this->assertSame('open',   $omise->status()['state']);
        $this->assertSame('closed', $paypal->status()['state'],
            'one provider going down must not block the other');
    }
}
