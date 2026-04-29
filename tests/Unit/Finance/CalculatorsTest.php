<?php

namespace Tests\Unit\Finance;

use App\Finance\Calculators\CommissionCalculator;
use App\Finance\Calculators\TaxCalculator;
use App\Finance\Money;
use InvalidArgumentException;
use Tests\TestCase;

class CalculatorsTest extends TestCase
{
    /* ─────────────────── CommissionCalculator ─────────────────── */

    public function test_commission_split_20_percent_clean(): void
    {
        $c = new CommissionCalculator();
        // 1000 baht × 20% = 200 baht platform fee, 800 baht photographer.
        $r = $c->split(Money::thb(100_000), 2000);
        $this->assertSame(20_000, $r['platform_fee']->minor);
        $this->assertSame(80_000, $r['photographer_net']->minor);
    }

    public function test_commission_split_with_rounding_preserves_total(): void
    {
        // Awkward amount — verifies platform_fee + photographer_net == gross.
        $c = new CommissionCalculator();
        $gross = Money::thb(12_345);          // ฿123.45
        $r = $c->split($gross, 2000);          // 20% platform

        $this->assertSame(
            $gross->minor,
            $r['platform_fee']->minor + $r['photographer_net']->minor,
            'Sum of split must equal gross — no satang lost or invented',
        );
    }

    public function test_commission_split_zero_percent_means_photographer_keeps_everything(): void
    {
        $c = new CommissionCalculator();
        $r = $c->split(Money::thb(50_000), 0);
        $this->assertTrue($r['platform_fee']->isZero());
        $this->assertSame(50_000, $r['photographer_net']->minor);
    }

    public function test_commission_split_rejects_bps_above_10000(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CommissionCalculator())->split(Money::thb(100), 10_001);
    }

    public function test_commission_split_rejects_negative_gross(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CommissionCalculator())->split(Money::thb(-100), 2000);
    }

    public function test_multi_party_split_three_ways(): void
    {
        $c = new CommissionCalculator();
        $r = $c->multiSplit(Money::thb(10_000), [
            'platform'     => 2000,    // 20%
            'affiliate'    => 500,     // 5%
            'photographer' => 7500,    // 75%
        ]);
        $this->assertSame(2000, $r['platform']->minor);
        $this->assertSame(500,  $r['affiliate']->minor);
        $this->assertSame(7500, $r['photographer']->minor);
    }

    public function test_multi_party_split_rejects_bad_total(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CommissionCalculator())->multiSplit(Money::thb(100), [
            'a' => 5000,
            'b' => 4000,   // sums to 9000, not 10000
        ]);
    }

    public function test_multi_party_split_preserves_gross_with_rounding(): void
    {
        // Awkward gross + uneven shares → rounding remainder must land
        // on the last party so the sum equals gross.
        $r = (new CommissionCalculator())->multiSplit(Money::thb(10_001), [
            'a' => 3333,
            'b' => 3333,
            'c' => 3334,
        ]);
        $sum = $r['a']->plus($r['b'])->plus($r['c']);
        $this->assertSame(10_001, $sum->minor);
    }

    /* ─────────────────── TaxCalculator ─────────────────── */

    public function test_vat_exclusive_7_percent(): void
    {
        $t = new TaxCalculator();
        $r = $t->exclusive(Money::thb(100_000), 700);   // 7%
        $this->assertSame(100_000, $r['net']->minor);
        $this->assertSame(  7_000, $r['tax']->minor);
        $this->assertSame(107_000, $r['gross']->minor);
    }

    public function test_vat_inclusive_7_percent_back_calculates_net(): void
    {
        $t = new TaxCalculator();
        // ฿1,070 inclusive of 7% VAT → ฿1,000 net + ฿70 VAT.
        $r = $t->inclusive(Money::thb(107_000), 700);
        $this->assertSame(100_000, $r['net']->minor);
        $this->assertSame(  7_000, $r['tax']->minor);
        $this->assertSame(107_000, $r['gross']->minor);
    }

    public function test_vat_inclusive_preserves_total_under_rounding(): void
    {
        // ฿123.45 with 7% VAT — back-calc may round, but net+tax must
        // still equal the original gross to the satang.
        $t = new TaxCalculator();
        $gross = Money::thb(12_345);
        $r = $t->inclusive($gross, 700);

        $this->assertSame(
            $gross->minor,
            $r['net']->minor + $r['tax']->minor,
            'net + tax must equal gross — no satang lost in inclusive split',
        );
    }

    public function test_vat_zero_rate_returns_amount_unchanged(): void
    {
        $t = new TaxCalculator();
        $r = $t->inclusive(Money::thb(50_000), 0);
        $this->assertSame(50_000, $r['net']->minor);
        $this->assertSame(0,      $r['tax']->minor);
        $this->assertSame(50_000, $r['gross']->minor);
    }

    public function test_vat_rejects_invalid_rate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new TaxCalculator())->exclusive(Money::thb(100), 12_000);
    }

    public function test_vat_rejects_negative_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new TaxCalculator())->exclusive(Money::thb(-100), 700);
    }
}
