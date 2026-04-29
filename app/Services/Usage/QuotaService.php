<?php

namespace App\Services\Usage;

use App\Models\PhotographerProfile;
use App\Support\FlatConfig;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * QuotaService — pure-function gate check.
 *
 * Compares the user's current counter to the cap defined by their plan
 * in config('usage.plan_caps'). Does NOT mutate state — call
 * UsageMeter::record() in the controller after the gated operation
 * succeeds. Separating "may I?" from "I did" makes the gate
 * idempotent and safer to retry.
 *
 * Hot-path latency: one `usage_counters` PK lookup + one breaker check.
 * Both are cached — typical p99 < 5ms.
 */
class QuotaService
{
    public function __construct(
        private readonly CircuitBreakerService $breakers,
    ) {}

    /**
     * Check if $user may perform 1+ units of $resource.
     *
     * Lookup order (first hit wins):
     *   1. Master switch off → OK
     *   2. Circuit breaker open → BREAKER
     *   3. Cap config missing for plan/resource → OK (no cap declared)
     *   4. Hard cap of 0 → DISABLED (feature off for this plan)
     *   5. Used + units > hard → HARD_BLOCK
     *   6. Used + units > soft → SOFT_WARN
     *   7. otherwise → OK
     */
    public function check(Authenticatable $user, string $resource, int $units = 1): QuotaResult
    {
        $period   = $this->resolvePeriod($user, $resource);
        $planCode = $this->planCodeFor($user);
        $cap      = $this->capFor($planCode, $resource);

        // 1. Master switch
        if (!config('usage.enforcement_enabled', true)) {
            return new QuotaResult(QuotaResult::STATE_OK, $resource, 0, null, null, $period);
        }

        // 2. Circuit breaker (e.g. AI feature kill-switch)
        if ($this->breakers->isOpen($resource)) {
            return new QuotaResult(
                QuotaResult::STATE_BREAKER, $resource, 0, null, null, $period,
                reason: "Feature temporarily unavailable — global cost ceiling reached",
            );
        }

        // 3. No cap declared for this plan/resource pair → allow
        if ($cap === null) {
            return new QuotaResult(QuotaResult::STATE_OK, $resource, 0, null, null, $period);
        }

        $hard = $cap['hard'] ?? null;
        $soft = $cap['soft'] ?? null;

        // 4. Feature disabled for this plan
        if ($hard === 0) {
            return new QuotaResult(
                QuotaResult::STATE_DISABLED, $resource, 0, 0, $soft, $period,
                reason: "ฟีเจอร์ '{$resource}' ไม่รวมอยู่ในแผน '{$planCode}' — กรุณาอัปเกรด",
            );
        }

        // 5/6/7. Read counter and compare
        $used = $this->currentUsage($user, $resource, $period);

        // null hard means "no cap" (handle with care — only Studio)
        if ($hard === null) {
            return new QuotaResult(QuotaResult::STATE_OK, $resource, $used, null, $soft, $period);
        }

        $projected = $used + $units;

        if ($projected > $hard) {
            return new QuotaResult(
                QuotaResult::STATE_HARD_BLOCK, $resource, $used, $hard, $soft, $period,
                reason: "เกินโควตา {$resource} ของแผน '{$planCode}' ({$used}/{$hard} ในช่วง {$period})",
            );
        }
        if ($soft !== null && $projected > $soft) {
            return new QuotaResult(QuotaResult::STATE_SOFT_WARN, $resource, $used, $hard, $soft, $period);
        }

        return new QuotaResult(QuotaResult::STATE_OK, $resource, $used, $hard, $soft, $period);
    }

    /**
     * The "lifetime" period stores cumulative counters in the month
     * bucket and sums across months — for storage, we need this view.
     */
    private function currentUsage(Authenticatable $user, string $resource, string $period): int
    {
        if ($period === 'lifetime') {
            return UsageMeter::lifetime((int) $user->getAuthIdentifier(), $resource);
        }
        return UsageMeter::counter((int) $user->getAuthIdentifier(), $resource, $period);
    }

    /** @return array{hard:?int,soft:?int,period:string}|null */
    private function capFor(string $planCode, string $resource): ?array
    {
        // Two-step literal-key lookup — both `plan_code` and `resource`
        // are flat keys with dots inside (e.g. 'pro' → 'ai.face_search').
        $caps = FlatConfig::array('usage.plan_caps', $planCode);
        return $caps[$resource] ?? null;
    }

    private function resolvePeriod(Authenticatable $user, string $resource): string
    {
        $planCode = $this->planCodeFor($user);
        $cap      = $this->capFor($planCode, $resource);
        return $cap['period'] ?? 'month';
    }

    private function planCodeFor(Authenticatable $user): string
    {
        // Photographer subscription (the primary case for this fork).
        if ($user instanceof \App\Models\User) {
            $profile = $user->photographerProfile ?? null;
            if ($profile instanceof PhotographerProfile && $profile->subscription_plan_code) {
                return (string) $profile->subscription_plan_code;
            }
        }
        return 'free';
    }
}
