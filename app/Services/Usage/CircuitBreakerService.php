<?php

namespace App\Services\Usage;

use App\Models\CircuitBreaker;
use App\Support\FlatConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CircuitBreakerService — last-line cost defence.
 *
 * Each declared feature ('ai.face_search', 'ai.preset_generate', …) has
 * a monthly THB ceiling in config('usage.breakers'). Every time a
 * gated request runs, charge() bumps spent_thb. Once it reaches the
 * ceiling, the breaker trips OPEN and isOpen() returns true for every
 * subsequent caller — the feature returns 503 Service Unavailable
 * across all users until either:
 *   • the period rolls over (auto-reset), or
 *   • an admin manually resets via reset()
 *
 * Why a hard kill-switch
 * ----------------------
 * Per-user caps (EnforceUsageQuota) prevent ONE user from racking up
 * cost. They don't prevent 10,000 users coordinating, or a logic bug
 * causing every user to retry 100×. The breaker is the platform-wide
 * "stop the bleeding" lever — it accepts that we'd rather take a
 * feature offline than wake up to a $20k AWS bill.
 *
 * Reads are cached for 5 seconds — every gated request reads here, so
 * a million concurrent users shouldn't hammer the breaker row. Writes
 * (charge / trip / reset) bypass the cache.
 */
class CircuitBreakerService
{
    private const CACHE_TTL_SECONDS = 5;

    /**
     * Is the breaker for $feature open right now?
     * Hot-path read: cached, no row lock.
     */
    public function isOpen(string $feature): bool
    {
        return Cache::remember(
            "cb:open:{$feature}",
            self::CACHE_TTL_SECONDS,
            function () use ($feature) {
                $row = CircuitBreaker::find($feature);
                if (!$row) return false;

                // Auto-reset on period rollover — saves an admin from
                // having to babysit at midnight on the 1st.
                if ($row->state === CircuitBreaker::STATE_OPEN
                    && $row->period_ends
                    && $row->period_ends->isPast()) {
                    $this->resetForNewPeriod($row);
                    return false;
                }

                return $row->state === CircuitBreaker::STATE_OPEN;
            },
        );
    }

    /**
     * Charge $thb against the feature's budget. Trips open if the
     * cumulative spend reaches the ceiling for this period.
     *
     * Uses raw SQL with row-level locking so concurrent writers can't
     * race past the ceiling.
     */
    public function charge(string $feature, float $thb): void
    {
        $cfg = $this->cfgFor($feature);
        if (!is_array($cfg)) {
            // No breaker declared for this feature — silently skip.
            return;
        }

        try {
            DB::transaction(function () use ($feature, $thb, $cfg) {
                $row = CircuitBreaker::lockForUpdate()->find($feature);
                if (!$row) {
                    $row = $this->ensureRow($feature, $cfg);
                }

                // Period rollover within the locked txn.
                if ($row->period_ends?->isPast()) {
                    $row->spent_thb     = 0;
                    $row->state         = CircuitBreaker::STATE_CLOSED;
                    $row->period_starts = $this->periodStart($cfg['reset_period'] ?? 'month');
                    $row->period_ends   = $this->periodEnd($cfg['reset_period'] ?? 'month');
                }

                $row->spent_thb = (float) $row->spent_thb + $thb;

                if ($row->spent_thb >= $row->threshold_thb && $row->state !== CircuitBreaker::STATE_OPEN) {
                    $row->state     = CircuitBreaker::STATE_OPEN;
                    $row->opened_at = now();
                    $row->notes     = sprintf(
                        'Auto-tripped at ฿%s of ฿%s (%s)',
                        number_format($row->spent_thb, 2),
                        number_format($row->threshold_thb, 2),
                        now()->toIso8601String(),
                    );
                    $this->reportTripped($row);
                }

                $row->save();
            });

            Cache::forget("cb:open:{$feature}");
        } catch (\Throwable $e) {
            // Don't let a breaker DB failure block the actual feature —
            // log + carry on; the next charge() will retry.
            Log::warning('CircuitBreakerService::charge failed', [
                'feature' => $feature,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Admin-only manual reset. State goes from OPEN → HALF_OPEN so the
     * next charge() that exceeds threshold trips again (defence against
     * an admin closing it just to find the cause is still active).
     */
    public function reset(string $feature, ?string $note = null): void
    {
        $row = CircuitBreaker::find($feature);
        if (!$row) return;

        $row->state       = CircuitBreaker::STATE_HALF_OPEN;
        $row->reopened_at = now();
        $row->notes       = $note ? "Manually reset: {$note}" : 'Manually reset';
        $row->save();

        Cache::forget("cb:open:{$feature}");
    }

    /**
     * Force a breaker open — operator panic button.
     */
    public function trip(string $feature, ?string $note = null): void
    {
        $cfg = $this->cfgFor($feature) ?? [];
        $row = CircuitBreaker::find($feature) ?? $this->ensureRow($feature, $cfg);
        $row->state     = CircuitBreaker::STATE_OPEN;
        $row->opened_at = now();
        $row->notes     = $note ? "Manually tripped: {$note}" : 'Manually tripped';
        $row->save();

        Cache::forget("cb:open:{$feature}");
    }

    /**
     * Snapshot for the admin dashboard: every declared breaker plus its
     * current state and spend. Read-only.
     *
     * @return array<int, array{feature:string,state:string,spent_thb:float,threshold_thb:float,period_ends:?string,utilization_pct:float}>
     */
    public function snapshot(): array
    {
        $features = array_keys(FlatConfig::section('usage.breakers'));
        $rows     = CircuitBreaker::whereIn('feature', $features)->get()->keyBy('feature');

        return collect($features)->map(function (string $feature) use ($rows) {
            $row = $rows->get($feature);
            $cfg = $this->cfgFor($feature);
            $threshold = (float) ($cfg['monthly_thb_ceiling'] ?? 0);
            $spent     = (float) ($row->spent_thb ?? 0);

            return [
                'feature'         => $feature,
                'state'           => $row->state ?? CircuitBreaker::STATE_CLOSED,
                'spent_thb'       => $spent,
                'threshold_thb'   => $threshold,
                'period_ends'     => optional($row?->period_ends)->toIso8601String(),
                'utilization_pct' => $threshold > 0 ? round($spent / $threshold * 100, 1) : 0.0,
            ];
        })->all();
    }

    /* ─────────────────── Internals ─────────────────── */

    /**
     * Surface a tripped breaker to operators.
     *
     * Three signal channels (in order of severity):
     *   1. Log::error — captured by Sentry's Laravel integration when
     *      SENTRY_LARAVEL_DSN is set (see bootstrap/app.php).
     *   2. Sentry rich event — explicit `captureMessage()` with structured
     *      tags so the alert in Sentry can be filtered by feature.
     *   3. Future: Slack/Discord webhook — left as a no-op until ops adds
     *      a webhook URL to AppSetting.
     *
     * Fails silently — a tripped breaker is itself an emergency; we don't
     * want a downstream alerting failure to mask it from logs.
     */
    private function reportTripped(CircuitBreaker $row): void
    {
        $context = [
            'feature'         => $row->feature,
            'spent_thb'       => (float) $row->spent_thb,
            'threshold_thb'   => (float) $row->threshold_thb,
            'utilization_pct' => $row->threshold_thb > 0
                ? round((float) $row->spent_thb / (float) $row->threshold_thb * 100, 1)
                : null,
            'period_starts'   => optional($row->period_starts)->toIso8601String(),
            'period_ends'     => optional($row->period_ends)->toIso8601String(),
        ];

        // 1) Log::error — this also feeds Sentry via the Laravel integration
        //    when SENTRY_LARAVEL_DSN is set. The `tag.feature` makes it
        //    pivotable in the Sentry UI.
        Log::error("Circuit breaker tripped: {$row->feature}", $context);

        // 2) Explicit Sentry event with tags. The integration class is
        //    only present when sentry/sentry-laravel is installed AND
        //    a DSN is configured — guard accordingly so this is a true
        //    no-op on dev/test.
        if (function_exists('\\Sentry\\captureMessage')
            && function_exists('\\Sentry\\configureScope')) {
            try {
                \Sentry\configureScope(function ($scope) use ($row, $context) {
                    $scope->setTag('breaker.feature', $row->feature);
                    $scope->setTag('breaker.state',   'open');
                    $scope->setExtra('breaker', $context);
                });
                \Sentry\captureMessage(
                    "Circuit breaker OPEN: {$row->feature} (spent ฿"
                    . number_format((float) $row->spent_thb, 2)
                    . ' of ฿' . number_format((float) $row->threshold_thb, 2) . ')',
                    \Sentry\Severity::error(),
                );
            } catch (\Throwable) {
                // Sentry transport may be unreachable — already logged above.
            }
        }
    }

    /**
     * Literal-key lookup into config('usage.breakers'). Going via
     * config('usage.breakers.ai.face_search') would interpret each dot
     * as nesting; the resource taxonomy uses dots inside keys.
     */
    private function cfgFor(string $feature): ?array
    {
        $cfg = FlatConfig::get('usage.breakers', $feature);
        return is_array($cfg) ? $cfg : null;
    }

    private function ensureRow(string $feature, array $cfg): CircuitBreaker
    {
        $period   = $cfg['reset_period'] ?? 'month';
        return CircuitBreaker::create([
            'feature'       => $feature,
            'state'         => CircuitBreaker::STATE_CLOSED,
            'threshold_thb' => $cfg['monthly_thb_ceiling'] ?? 0,
            'spent_thb'     => 0,
            'period_starts' => $this->periodStart($period),
            'period_ends'   => $this->periodEnd($period),
        ]);
    }

    private function resetForNewPeriod(CircuitBreaker $row): void
    {
        $row->spent_thb     = 0;
        $row->state         = CircuitBreaker::STATE_CLOSED;
        $row->period_starts = now();
        $row->period_ends   = now()->addMonth()->startOfMonth();
        $row->notes         = 'Auto-reset on new period';
        $row->save();
    }

    private function periodStart(string $period): CarbonImmutable
    {
        return match ($period) {
            'day'   => CarbonImmutable::now()->startOfDay(),
            'week'  => CarbonImmutable::now()->startOfWeek(),
            'month' => CarbonImmutable::now()->startOfMonth(),
            default => CarbonImmutable::now()->startOfMonth(),
        };
    }

    private function periodEnd(string $period): CarbonImmutable
    {
        return match ($period) {
            'day'   => CarbonImmutable::now()->endOfDay(),
            'week'  => CarbonImmutable::now()->endOfWeek(),
            'month' => CarbonImmutable::now()->endOfMonth(),
            default => CarbonImmutable::now()->endOfMonth(),
        };
    }
}
