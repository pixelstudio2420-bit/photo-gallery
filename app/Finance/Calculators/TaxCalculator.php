<?php

namespace App\Finance\Calculators;

use App\Finance\Money;
use InvalidArgumentException;

/**
 * Tax calculation that NEVER conflates tax with revenue.
 *
 * Two models supported:
 *
 *   EXCLUSIVE — list price doesn't include tax. Buyer pays list + tax.
 *     net = list, tax = net × rateBps/10000, gross = net + tax.
 *
 *   INCLUSIVE — list price includes tax (Thailand B2C convention).
 *     gross = list, net = gross / (1 + rateBps/10000), tax = gross - net.
 *
 * Rounding: tax is computed by integer math. The exact split between
 * net and tax may shift by 1 satang in the inclusive case due to
 * back-calculation; we always make `net + tax == gross` hold by
 * deriving tax as `gross - net` AFTER computing net.
 */
final class TaxCalculator
{
    /**
     * @return array{net: Money, tax: Money, gross: Money}
     */
    public function exclusive(Money $listPriceNet, int $rateBps): array
    {
        $this->validate($listPriceNet, $rateBps);
        $tax   = $listPriceNet->percentBps($rateBps);
        $gross = $listPriceNet->plus($tax);
        return ['net' => $listPriceNet, 'tax' => $tax, 'gross' => $gross];
    }

    /**
     * @return array{net: Money, tax: Money, gross: Money}
     */
    public function inclusive(Money $listPriceGross, int $rateBps): array
    {
        $this->validate($listPriceGross, $rateBps);
        if ($rateBps === 0) {
            return [
                'net'   => $listPriceGross,
                'tax'   => Money::zero($listPriceGross->currency),
                'gross' => $listPriceGross,
            ];
        }
        // net = gross × 10000 / (10000 + rateBps), with banker's rounding via intdiv + half.
        // The half is positive (gross is non-negative under our caller contract).
        $denominator = 10_000 + $rateBps;
        $netMinor    = intdiv($listPriceGross->minor * 10_000 + intdiv($denominator, 2), $denominator);
        $net         = new Money($netMinor, $listPriceGross->currency);
        // Derive tax by subtraction so net + tax == gross exactly.
        $tax = $listPriceGross->minus($net);
        return ['net' => $net, 'tax' => $tax, 'gross' => $listPriceGross];
    }

    private function validate(Money $amount, int $rateBps): void
    {
        if ($rateBps < 0 || $rateBps > 10_000) {
            throw new InvalidArgumentException("Tax rateBps must be 0..10000, got {$rateBps}");
        }
        if ($amount->isNegative()) {
            throw new InvalidArgumentException('Tax cannot be calculated on a negative amount');
        }
    }
}
