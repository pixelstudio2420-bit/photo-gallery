<?php

namespace Tests\Unit\Finance;

use App\Finance\Money;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Money is the foundation — every fintech bug we've ever heard of started
 * with a 1-satang drift in some arithmetic operation. These tests pin
 * down EXACTLY what each operation does, including the rounding rules,
 * so future changes to Money can't silently break them.
 */
class MoneyTest extends TestCase
{
    /* ─────────────────── Construction ─────────────────── */

    public function test_constructor_stores_minor_and_currency_verbatim(): void
    {
        $m = new Money(12345, 'THB');
        $this->assertSame(12345, $m->minor);
        $this->assertSame('THB',  $m->currency);
    }

    public function test_constructor_rejects_non_iso4217_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(100, 'BAHT');  // 4 letters
    }

    public function test_constructor_allows_negative_minor(): void
    {
        // Refunds / debits in some contexts produce negative running totals.
        $m = new Money(-500, 'THB');
        $this->assertTrue($m->isNegative());
    }

    /* ─────────────────── Factories ─────────────────── */

    public function test_thb_factory(): void
    {
        $this->assertSame(100, Money::thb(100)->minor);
        $this->assertSame('THB', Money::thb(0)->currency);
    }

    public function test_zero_factory_defaults_to_thb(): void
    {
        $this->assertTrue(Money::zero()->isZero());
        $this->assertSame('THB', Money::zero()->currency);
        $this->assertSame('USD', Money::zero('USD')->currency);
    }

    public function test_from_major_string_handles_2_decimals(): void
    {
        $this->assertSame(123456, Money::fromMajorString('1234.56', 'THB')->minor);
        $this->assertSame(0,      Money::fromMajorString('0', 'THB')->minor);
        $this->assertSame(0,      Money::fromMajorString('0.00', 'THB')->minor);
        $this->assertSame(5,      Money::fromMajorString('0.05', 'THB')->minor);
    }

    public function test_from_major_string_pads_single_decimal(): void
    {
        // '5.5' must mean 5 baht 50 satang = 550 satang, NOT 5.5 satang.
        $this->assertSame(550, Money::fromMajorString('5.5', 'THB')->minor);
    }

    public function test_from_major_string_handles_negative(): void
    {
        $this->assertSame(-1230, Money::fromMajorString('-12.30', 'THB')->minor);
    }

    public function test_from_major_string_rejects_three_decimals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromMajorString('1.234', 'THB');  // sub-satang precision is forbidden
    }

    public function test_from_major_string_rejects_floats(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // PHP float printf reveals the IEEE 754 mess — we MUST reject it.
        Money::fromMajorString('1.2e3', 'THB');
    }

    public function test_from_major_string_rejects_garbage(): void
    {
        $rejected = 0;
        foreach (['', 'abc', '1,234.56', '1.', '.50'] as $bad) {
            try {
                Money::fromMajorString($bad, 'THB');
                $this->fail("Expected reject for '{$bad}'");
            } catch (InvalidArgumentException $e) {
                $rejected++;
            }
        }
        // Explicit assertion to keep PHPUnit happy + document the contract.
        $this->assertSame(5, $rejected, 'All 5 garbage inputs must be rejected');
    }

    public function test_from_major_amount_int_multiplies_by_100(): void
    {
        $this->assertSame(50000, Money::fromMajorAmount(500, 'THB')->minor);
    }

    /* ─────────────────── Inspection ─────────────────── */

    public function test_equals_requires_same_currency_and_minor(): void
    {
        $this->assertTrue (Money::thb(100)->equals(Money::thb(100)));
        $this->assertFalse(Money::thb(100)->equals(Money::thb(101)));
        $this->assertFalse(Money::thb(100)->equals(new Money(100, 'USD')));
    }

    public function test_less_than_throws_on_currency_mismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::thb(100)->lessThan(new Money(100, 'USD'));
    }

    /* ─────────────────── Arithmetic ─────────────────── */

    public function test_plus_and_minus(): void
    {
        $a = Money::thb(100);
        $b = Money::thb(50);
        $this->assertSame(150, $a->plus($b)->minor);
        $this->assertSame(50,  $a->minus($b)->minor);
    }

    public function test_plus_rejects_currency_mismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::thb(100)->plus(new Money(100, 'USD'));
    }

    public function test_times(): void
    {
        $this->assertSame(500, Money::thb(100)->times(5)->minor);
        $this->assertSame(0,   Money::thb(100)->times(0)->minor);
        $this->assertSame(-100,Money::thb(100)->times(-1)->minor);
    }

    public function test_negate_and_abs(): void
    {
        $this->assertSame(-100, Money::thb(100)->negate()->minor);
        $this->assertSame(100,  Money::thb(-100)->abs()->minor);
    }

    /* ─────────────────── percentBps (the rounding showcase) ─────────────────── */

    public function test_percent_bps_exact_division(): void
    {
        // 1000 satang × 20% = 200 satang, no rounding needed
        $this->assertSame(200, Money::thb(1000)->percentBps(2000)->minor);
    }

    public function test_percent_bps_rounds_half_away_from_zero(): void
    {
        // 100 × 8.005% = 0.8005 ฿ = 80.05 satang → rounds to 81 (away from zero)
        // 100 satang × 8005 bps / 10000 = 80.05; +5000 then intdiv by 10000:
        //   (100*8005 + 5000) / 10000 = 805000 / 10000 = 80
        // Wait — let me recompute. 100 * 8005 = 800500. +5000 = 805500. /10000 = 80 remainder 5500.
        // So 80, not 81. Let me re-derive:
        //   amount=100, bps=8005:  exact = 80.05 satang
        //   our impl: (100 * 8005 + 5000) / 10000 = 805500 / 10000 → intdiv 80
        // So 80.05 rounds to 80? That's banker's-ish. Actually:
        //   800500 + 5000 = 805500; 805500 // 10000 = 80; remainder 5500.
        //   Half of 10000 is 5000. We added it. So if remainder < 5000 we round DOWN, ≥5000 we round UP.
        //   805000 + 5000 = 810000. 810000//10000 = 81.
        // So percentBps rounds UP at the half — that's "half-up" when product is positive.
        // Our test: 100 * 8005 = 800500. Add 5000 = 805500. //10000 = 80 (because 805500/10000 = 80.55, integer floor = 80).
        // Result is 80 satang, not 81. The exact value is 80.05 — it rounds DOWN.
        //
        // Let me pick a clearer case that's exactly half:
        //   amount=100, bps=2050:  exact = 20.5 satang
        //   product = 205000 + 5000 = 210000 → /10000 = 21
        // So 20.5 rounds UP to 21 (half-away-from-zero for positives).
        $this->assertSame(21, Money::thb(100)->percentBps(2050)->minor);
    }

    public function test_percent_bps_zero_returns_zero(): void
    {
        $this->assertSame(0, Money::thb(123456)->percentBps(0)->minor);
    }

    public function test_percent_bps_rejects_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::thb(100)->percentBps(-100);
    }

    /* ─────────────────── splitProportional (no satang lost) ─────────────────── */

    public function test_split_proportional_exact(): void
    {
        $shares = Money::thb(100)->splitProportional([20, 80]);
        $this->assertSame(20, $shares[0]->minor);
        $this->assertSame(80, $shares[1]->minor);
    }

    public function test_split_proportional_remainder_goes_to_last_share(): void
    {
        // 10001 satang split equally three ways: 3334+3334+3333 or some
        // permutation. The LAST share absorbs whatever's left.
        $shares = Money::thb(10001)->splitProportional([1, 1, 1]);
        $this->assertSame(10001, array_sum(array_map(fn ($s) => $s->minor, $shares)));
        $this->assertSame(3333,  $shares[0]->minor);
        $this->assertSame(3333,  $shares[1]->minor);
        $this->assertSame(3335,  $shares[2]->minor);  // last absorbs
    }

    public function test_split_proportional_three_unequal_shares_no_drift(): void
    {
        $shares = Money::thb(10000)->splitProportional([2000, 500, 7500]);  // 20:5:75
        $this->assertSame(10000, array_sum(array_map(fn ($s) => $s->minor, $shares)));
    }

    public function test_split_proportional_rejects_zero_total(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::thb(100)->splitProportional([0, 0]);
    }

    public function test_split_proportional_rejects_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::thb(100)->splitProportional([]);
    }

    /* ─────────────────── Formatting ─────────────────── */

    public function test_to_major_string_always_two_decimals(): void
    {
        $this->assertSame('1234.56', Money::thb(123456)->toMajorString());
        $this->assertSame('0.00',    Money::thb(0)->toMajorString());
        $this->assertSame('0.05',    Money::thb(5)->toMajorString());
        $this->assertSame('-12.30',  Money::thb(-1230)->toMajorString());
    }

    public function test_format_uses_currency_symbol(): void
    {
        $this->assertSame('฿1,234.56', Money::thb(123456)->format());
        $this->assertSame('-฿100.00',  Money::thb(-10000)->format());
    }

    /* ─────────────────── Edge cases ─────────────────── */

    public function test_large_amounts_dont_overflow_php_int(): void
    {
        // Up to ~9.2 quadrillion satang. Pick something well under but
        // big enough to break a 32-bit int.
        $huge = Money::thb(1_000_000_000_000);   // 10 billion baht
        $sum  = $huge->plus($huge);
        $this->assertSame(2_000_000_000_000, $sum->minor);
    }

    public function test_subtracting_to_zero_is_zero_not_negative_zero(): void
    {
        $a = Money::thb(100);
        $b = Money::thb(100);
        $diff = $a->minus($b);
        $this->assertTrue($diff->isZero());
        $this->assertSame(0, $diff->minor);
    }
}
