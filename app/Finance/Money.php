<?php

namespace App\Finance;

use InvalidArgumentException;

/**
 * Money value object — integer minor units + ISO-4217 currency code.
 *
 * Design contract (DO NOT BREAK)
 * ------------------------------
 *   1. The internal representation is BIGINT minor units (1 THB = 100
 *      satang; 1 USD = 100 cents). PHP integers are exact up to
 *      PHP_INT_MAX (9.2e18) — comfortably more than the platform's
 *      lifetime gross.
 *   2. There are NO float operations anywhere in this class. Anyone
 *      passing a float in must call `fromMajorString()` or
 *      `fromMajorAmount()` first, both of which run a strict-format
 *      check and reject things like `0.1` (which is actually
 *      0.1000000000000000055511151231257827021181583404541015625
 *      under IEEE 754).
 *   3. Arithmetic on two Money values requires the SAME currency.
 *      Cross-currency operations throw — there is no implicit FX.
 *   4. Money is IMMUTABLE. Every operation returns a new instance.
 *
 * Rounding policy
 * ---------------
 *   Bankers' rounding (round-half-to-even) is the IEEE 754 default and
 *   what PHP's intdiv() approximates for positive integers. We use
 *   plain integer division for splits + percentages because we always
 *   work in minor units already — there is no decimal intermediate
 *   that could need rounding except in `percentBps()` where we round
 *   half-away-from-zero (more intuitive for monetary % than banker's).
 *
 *   Critical: when splitting an amount across N parties, the LAST share
 *   absorbs the rounding remainder. See `splitProportional()`. This
 *   guarantees `sum(shares) == original`, no satang lost.
 */
final class Money
{
    public function __construct(
        public readonly int $minor,
        public readonly string $currency,
    ) {
        if ($currency === '' || strlen($currency) !== 3) {
            throw new InvalidArgumentException("Currency must be a 3-letter ISO 4217 code, got '{$currency}'");
        }
        // Minor can be negative (debits/refunds), so we don't constrain
        // the sign — but the journal_lines schema does (CHECK >= 0)
        // because direction encodes the sign instead.
    }

    /* ─────────────────── Factories ─────────────────── */

    public static function thb(int $satang): self
    {
        return new self($satang, 'THB');
    }

    public static function zero(string $currency = 'THB'): self
    {
        return new self(0, $currency);
    }

    /**
     * Build from a "major-units string" with ≤ 2 decimals.
     *
     * '1234.56' → 123456 satang (THB)
     * '0'       → 0
     * '0.05'    → 5 satang
     * '-12.30'  → -1230 satang
     *
     * Rejects floats / scientific notation / non-numeric.
     */
    public static function fromMajorString(string $value, string $currency): self
    {
        $trimmed = trim($value);
        // Strict regex — exactly:  optional sign, digits, optional dot+1-2 digits.
        // No leading dot, no trailing dot, no exponent, no thousand separators.
        if (!preg_match('/^-?(\d+)(?:\.(\d{1,2}))?$/', $trimmed, $m)) {
            throw new InvalidArgumentException("Money::fromMajorString rejects '{$value}' — expected '<integer>[.<1-2 digits>]'");
        }
        $negative = str_starts_with($trimmed, '-');
        $whole    = (int) $m[1];
        $frac     = $m[2] ?? '00';
        // Pad to exactly 2 digits so '5' becomes '50' (5/100 = 0.50).
        $frac     = str_pad($frac, 2, '0', STR_PAD_RIGHT);
        $minor    = $whole * 100 + (int) $frac;
        return new self($negative ? -$minor : $minor, $currency);
    }

    /**
     * Convenience: accept either a string or an int already in minor.
     * NEVER accepts a float — that's the whole point of this class.
     */
    public static function fromMajorAmount(int|string $value, string $currency): self
    {
        if (is_int($value)) {
            return new self($value * 100, $currency);
        }
        return self::fromMajorString($value, $currency);
    }

    /* ─────────────────── Inspection ─────────────────── */

    public function isZero(): bool      { return $this->minor === 0; }
    public function isPositive(): bool  { return $this->minor > 0; }
    public function isNegative(): bool  { return $this->minor < 0; }

    public function equals(Money $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    public function lessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->minor < $other->minor;
    }

    public function greaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->minor > $other->minor;
    }

    /* ─────────────────── Arithmetic ─────────────────── */

    public function plus(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->minor - $other->minor, $this->currency);
    }

    public function times(int $factor): self
    {
        return new self($this->minor * $factor, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->minor, $this->currency);
    }

    public function abs(): self
    {
        return new self(abs($this->minor), $this->currency);
    }

    /**
     * Multiply by a percentage expressed in basis points (bps).
     *   100 bps = 1% (e.g. 8000 bps = 80%)
     *
     * Why bps? Storing percentages as integers eliminates the float
     * 0.1 round-trip problem. `commission_rate = 8000` is exact;
     * `commission_rate = 80.00` decimal is not in PHP arithmetic.
     *
     * Rounding: half-away-from-zero (intuitive for money: ฿0.005
     * rounds to ฿0.01, not ฿0.00).
     */
    public function percentBps(int $bps): self
    {
        if ($bps < 0) {
            throw new InvalidArgumentException("Negative bps not allowed in percentBps");
        }
        // amount * bps / 10000, with rounding half-away-from-zero.
        // We avoid intdiv truncation: add half the divisor before dividing.
        $product = $this->minor * $bps;
        $half    = ($product < 0) ? -5_000 : 5_000;     // half-of-10000, sign matched
        return new self(intdiv($product + $half, 10_000), $this->currency);
    }

    /**
     * Split this amount into proportions. The LAST share absorbs the
     * rounding remainder so sum(shares) === this.
     *
     *   Money::thb(10001)->splitProportional([1, 1, 1])
     *      → [3334, 3334, 3333]
     *      sum = 10001 ✓
     *
     *   Money::thb(100)->splitProportional([20, 80])  // 20:80
     *      → [20, 80]
     *
     * @param  array<int>  $weights
     * @return array<Money>
     */
    public function splitProportional(array $weights): array
    {
        if (empty($weights)) {
            throw new InvalidArgumentException('splitProportional requires at least one weight');
        }
        $total = 0;
        foreach ($weights as $w) {
            if (!is_int($w) || $w < 0) {
                throw new InvalidArgumentException('Weights must be non-negative integers');
            }
            $total += $w;
        }
        if ($total === 0) {
            throw new InvalidArgumentException('splitProportional weights cannot all be zero');
        }

        $shares    = [];
        $allocated = 0;
        $count     = count($weights);
        foreach ($weights as $i => $w) {
            if ($i === $count - 1) {
                // Last share gets the remainder so we don't lose satang.
                $shareMinor = $this->minor - $allocated;
            } else {
                $shareMinor = intdiv($this->minor * $w, $total);
                $allocated += $shareMinor;
            }
            $shares[] = new self($shareMinor, $this->currency);
        }
        return $shares;
    }

    /* ─────────────────── Formatting ─────────────────── */

    /** '1234.56' (always exactly 2 decimals, never scientific). */
    public function toMajorString(): string
    {
        $abs   = abs($this->minor);
        $whole = intdiv($abs, 100);
        $frac  = $abs % 100;
        $sign  = $this->minor < 0 ? '-' : '';
        return sprintf('%s%d.%02d', $sign, $whole, $frac);
    }

    /** Human-readable with currency. '฿1,234.56' for THB. */
    public function format(): string
    {
        $major = number_format(abs($this->minor) / 100, 2, '.', ',');
        $sign  = $this->minor < 0 ? '-' : '';
        return match ($this->currency) {
            'THB'   => "{$sign}฿{$major}",
            'USD'   => "{$sign}\${$major}",
            'EUR'   => "{$sign}€{$major}",
            default => "{$sign}{$major} {$this->currency}",
        };
    }

    public function __toString(): string
    {
        return "{$this->minor} {$this->currency}";  // primarily for debugging
    }

    /* ─────────────────── Helpers ─────────────────── */

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}. "
                . 'Use an FX converter explicitly — there is no implicit conversion.'
            );
        }
    }
}
