<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Three-state circuit breaker for outbound payment-gateway calls.
 *
 * Why this exists
 * ---------------
 * Payment gateways (Omise, PayPal, LinePay, Stripe, TrueMoney, 2C2P) are
 * called synchronously inside the request that the customer is waiting
 * on. If a gateway has a brownout (15s timeout, 502s, DNS issue), every
 * one of those waiting customers eats the timeout — a single payment
 * provider going down can saturate every PHP-FPM worker on the box and
 * take the whole site with it.
 *
 * The breaker short-circuits to a fast failure once a service has shown
 * itself to be degraded, so the user gets an instant "try another
 * method" response instead of a 15-second hang.
 *
 * State machine
 * -------------
 *   CLOSED    → normal. count failures.
 *   OPEN      → after N consecutive failures, refuse calls for `cooldown`.
 *   HALF_OPEN → after cooldown elapses, allow ONE probe call. On success,
 *               close again. On failure, re-open with fresh cooldown.
 *
 * Storage
 * -------
 * Uses the Cache facade — works with file/redis/database drivers. State
 * is keyed by gateway name so each provider has its own breaker.
 *
 * Why not use a 3rd-party package
 * -------------------------------
 * The shape of "circuit breaker" we need is small (~80 LOC) and depending
 * on a package would add another version-management burden. The trade-off
 * here is "small in-tree class" vs "library upgrade churn" — we prefer
 * the former for something this rarely needed but security-critical.
 *
 * Usage
 * -----
 *     $cb = new CircuitBreaker('omise');
 *     $result = $cb->call(
 *         fn() => Http::timeout(15)->post(...),
 *         fallback: ['success' => false, 'message' => 'Gateway temporarily unavailable'],
 *     );
 */
class CircuitBreaker
{
    private const STATE_CLOSED    = 'closed';
    private const STATE_OPEN      = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    public function __construct(
        private readonly string $service,
        private readonly int    $failureThreshold = 5,
        private readonly int    $cooldownSeconds  = 60,
    ) {}

    /**
     * Execute $fn, short-circuiting if the breaker is open.
     *
     * @template T
     * @param  \Closure(): T   $fn       the actual gateway call
     * @param  T|null          $fallback returned when the breaker is open
     * @return T|null
     * @throws \Throwable                rethrows when no $fallback supplied
     */
    public function call(\Closure $fn, mixed $fallback = null): mixed
    {
        $state = $this->loadState();

        // ── OPEN: refuse, but flip to HALF_OPEN if cooldown elapsed ──
        if ($state['state'] === self::STATE_OPEN) {
            if (time() < $state['open_until']) {
                Log::info('payment.circuit_breaker.short_circuit', [
                    'service'    => $this->service,
                    'until'      => $state['open_until'],
                    'failures'   => $state['failures'],
                ]);
                if (func_num_args() >= 2) {
                    return $fallback;
                }
                throw new \RuntimeException("Circuit open for {$this->service}");
            }
            $state['state'] = self::STATE_HALF_OPEN;
        }

        try {
            $result = $fn();
            $this->onSuccess($state);
            return $result;
        } catch (\Throwable $e) {
            $this->onFailure($state, $e);
            if (func_num_args() >= 2) {
                return $fallback;
            }
            throw $e;
        }
    }

    /**
     * Mark the last call as a failure even if it didn't throw.
     * Use this when the gateway returns 200 OK but the body indicates
     * an upstream error you want the breaker to count.
     */
    public function trip(string $reason = ''): void
    {
        $state = $this->loadState();
        $this->onFailure($state, new \RuntimeException($reason ?: 'manual trip'));
    }

    /**
     * Read the current state for inspection / dashboards.
     *
     * @return array{state:string,failures:int,open_until:int}
     */
    public function status(): array
    {
        $s = $this->loadState();
        return [
            'state'      => $s['state'],
            'failures'   => $s['failures'],
            'open_until' => $s['open_until'],
        ];
    }

    /**
     * Force the breaker back to CLOSED. Used by ops scripts after a known
     * recovery (e.g., after the provider sends an "all clear").
     */
    public function reset(): void
    {
        Cache::forget($this->key());
    }

    // ------------------------------------------------------------------ private

    private function key(): string
    {
        return "payment.cb.{$this->service}";
    }

    /**
     * @return array{state:string,failures:int,open_until:int}
     */
    private function loadState(): array
    {
        return Cache::get($this->key(), [
            'state'      => self::STATE_CLOSED,
            'failures'   => 0,
            'open_until' => 0,
        ]);
    }

    private function persist(array $state): void
    {
        // 1 hour TTL — enough that nothing sticks around if traffic dies.
        Cache::put($this->key(), $state, 3600);
    }

    private function onSuccess(array $state): void
    {
        if ($state['state'] === self::STATE_HALF_OPEN || $state['failures'] > 0) {
            Log::info('payment.circuit_breaker.recovered', [
                'service'  => $this->service,
                'previous' => $state['state'],
            ]);
        }
        $this->persist([
            'state'      => self::STATE_CLOSED,
            'failures'   => 0,
            'open_until' => 0,
        ]);
    }

    private function onFailure(array $state, \Throwable $e): void
    {
        $state['failures']++;

        if ($state['failures'] >= $this->failureThreshold) {
            $state['state']      = self::STATE_OPEN;
            $state['open_until'] = time() + $this->cooldownSeconds;

            Log::error('payment.circuit_breaker.opened', [
                'service'        => $this->service,
                'failures'       => $state['failures'],
                'cooldown_secs'  => $this->cooldownSeconds,
                'last_error'     => substr($e->getMessage(), 0, 200),
            ]);
        } else {
            Log::warning('payment.circuit_breaker.failure', [
                'service'  => $this->service,
                'failures' => $state['failures'],
                'error'    => substr($e->getMessage(), 0, 200),
            ]);
        }

        $this->persist($state);
    }
}
