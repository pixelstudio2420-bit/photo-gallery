<?php

namespace App\Services\Usage;

/**
 * Outcome of a quota gate check. Distinguishes hard refusal (block) from
 * soft warning (allow + signal) so the middleware + UI can react
 * differently.
 */
final class QuotaResult
{
    public const STATE_OK         = 'ok';
    public const STATE_SOFT_WARN  = 'soft_warn';
    public const STATE_HARD_BLOCK = 'hard_block';
    public const STATE_BREAKER    = 'breaker';
    public const STATE_DISABLED   = 'disabled';

    public function __construct(
        public readonly string $state,
        public readonly string $resource,
        public readonly int    $used,
        public readonly ?int   $hard,
        public readonly ?int   $soft,
        public readonly string $period,
        public readonly ?string $reason = null,
    ) {}

    public function allowed(): bool
    {
        return in_array($this->state, [self::STATE_OK, self::STATE_SOFT_WARN], true);
    }

    public function blocked(): bool
    {
        return !$this->allowed();
    }

    public function remaining(): ?int
    {
        return $this->hard === null ? null : max(0, $this->hard - $this->used);
    }

    public function utilizationPct(): ?float
    {
        if ($this->hard === null || $this->hard === 0) return null;
        return round($this->used / $this->hard * 100, 1);
    }

    public function statusCode(): int
    {
        return match ($this->state) {
            self::STATE_HARD_BLOCK => 402,  // Payment Required (quota exceeded)
            self::STATE_BREAKER    => 503,  // Service Unavailable (cost-circuit open)
            self::STATE_DISABLED   => 403,  // Forbidden (feature off for this plan)
            default                => 200,
        };
    }
}
