<?php

namespace App\Finance\Calculators;

use App\Finance\Money;
use InvalidArgumentException;

/**
 * Splits a gross amount into platform fee + photographer net.
 *
 * Why basis points (bps) and not percent?
 *   100 bps = 1%. Storing as integer eliminates the float
 *   round-trip on percentage values — `0.20` is exact only as bps=2000,
 *   never as a PHP float.
 *
 * Determinism:
 *   The platform fee rounds half-away-from-zero (Money::percentBps).
 *   The photographer net is `gross - platform_fee` — pure subtraction,
 *   no second rounding. This guarantees `platform_fee + net == gross`
 *   always — no satang lost or invented.
 */
final class CommissionCalculator
{
    /**
     * @param  Money  $gross           Gross amount the customer paid (after discounts).
     * @param  int    $platformFeeBps  Platform's take in basis points (2000 = 20%).
     * @return array{platform_fee: Money, photographer_net: Money}
     */
    public function split(Money $gross, int $platformFeeBps): array
    {
        if ($platformFeeBps < 0 || $platformFeeBps > 10_000) {
            throw new InvalidArgumentException(
                "platformFeeBps must be 0..10000, got {$platformFeeBps}"
            );
        }
        if ($gross->isNegative()) {
            throw new InvalidArgumentException('Gross cannot be negative for a commission split');
        }

        $platformFee     = $gross->percentBps($platformFeeBps);
        $photographerNet = $gross->minus($platformFee);

        return [
            'platform_fee'     => $platformFee,
            'photographer_net' => $photographerNet,
        ];
    }

    /**
     * Multi-party split. Common case: platform + affiliate + photographer.
     * Each party gets a bps share; remaining bps after platform/affiliate
     * is the photographer's. Must total 10000 bps.
     *
     *   $calc->multiSplit($gross, ['platform' => 2000, 'affiliate' => 500, 'photographer' => 7500])
     *
     * @param  array<string, int>  $shares  party => bps
     * @return array<string, Money>          party => share, summing to gross
     */
    public function multiSplit(Money $gross, array $shares): array
    {
        if (empty($shares)) {
            throw new InvalidArgumentException('shares cannot be empty');
        }
        $totalBps = 0;
        foreach ($shares as $bps) {
            if (!is_int($bps) || $bps < 0) {
                throw new InvalidArgumentException('Each share must be a non-negative integer (bps)');
            }
            $totalBps += $bps;
        }
        if ($totalBps !== 10_000) {
            throw new InvalidArgumentException(
                "Shares must sum to exactly 10000 bps, got {$totalBps}. " .
                'Set the photographer share so the total is exactly 100%.'
            );
        }

        // Use Money::splitProportional so the LAST party absorbs the
        // satang remainder. We sort the keys deterministically so the
        // "last party" is stable across PHP versions and array re-orders.
        $orderedKeys = array_keys($shares);
        $weights = array_map(fn ($k) => $shares[$k], $orderedKeys);
        $portions = $gross->splitProportional($weights);

        $out = [];
        foreach ($orderedKeys as $i => $key) {
            $out[$key] = $portions[$i];
        }
        return $out;
    }
}
