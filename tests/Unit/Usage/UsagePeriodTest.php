<?php

namespace Tests\Unit\Usage;

use App\Services\Usage\UsagePeriod;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class UsagePeriodTest extends TestCase
{
    public function test_keys_match_legacy_hardcoded_formats(): void
    {
        // These are the EXACT format strings that used to be hardcoded
        // in UsageMeter / DetectUsageSpikesCommand / PruneUsageDataCommand.
        // If anyone changes them, every counter row written before that
        // change becomes orphaned. So this test is a regression guard.
        $at = Carbon::parse('2026-04-27 13:45:09');

        $this->assertSame('2026-04-27T13:45', UsagePeriod::key(UsagePeriod::MINUTE, $at));
        $this->assertSame('2026-04-27T13',    UsagePeriod::key(UsagePeriod::HOUR,   $at));
        $this->assertSame('2026-04-27',       UsagePeriod::key(UsagePeriod::DAY,    $at));
        $this->assertSame('2026-04',          UsagePeriod::key(UsagePeriod::MONTH,  $at));
    }

    public function test_unknown_period_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UsagePeriod::key('week');
    }

    public function test_all_keys_returns_one_entry_per_period(): void
    {
        $at = Carbon::parse('2026-04-27 13:45:09');
        $keys = UsagePeriod::allKeys($at);

        $this->assertCount(4, $keys);
        $this->assertSame([
            'minute' => '2026-04-27T13:45',
            'hour'   => '2026-04-27T13',
            'day'    => '2026-04-27',
            'month'  => '2026-04',
        ], $keys);
    }

    public function test_keys_sort_temporally_under_string_comparison(): void
    {
        // Production relies on this: PruneUsageDataCommand uses
        //   WHERE period_key < $threshold
        // which only works if string-compare order matches temporal order.
        $earlier = UsagePeriod::key(UsagePeriod::DAY, Carbon::parse('2025-12-31'));
        $later   = UsagePeriod::key(UsagePeriod::DAY, Carbon::parse('2026-01-01'));

        $this->assertTrue($earlier < $later, 'Day keys must sort temporally');

        $h1 = UsagePeriod::key(UsagePeriod::HOUR, Carbon::parse('2026-04-27 23:59'));
        $h2 = UsagePeriod::key(UsagePeriod::HOUR, Carbon::parse('2026-04-28 00:01'));
        $this->assertTrue($h1 < $h2, 'Hour keys must sort temporally across day boundary');
    }

    public function test_format_returns_the_literal_strftime_string(): void
    {
        $this->assertSame('Y-m-d\TH:i', UsagePeriod::format(UsagePeriod::MINUTE));
        $this->assertSame('Y-m',         UsagePeriod::format(UsagePeriod::MONTH));
    }

    public function test_format_throws_on_unknown_period(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UsagePeriod::format('quarter');
    }

    public function test_all_constant_lists_every_period_exactly_once(): void
    {
        $this->assertCount(4, UsagePeriod::ALL);
        $this->assertSame(['minute', 'hour', 'day', 'month'], UsagePeriod::ALL);
    }
}
