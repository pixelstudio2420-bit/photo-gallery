<?php

namespace App\Services\Usage;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Period name + key formatting for the usage tracking system.
 *
 * Why this exists
 * ---------------
 * The (period, period_key) tuple in `usage_counters` must use ONE
 * canonical format per period type — otherwise UsageMeter writes
 * '2026-04-27' but DetectUsageSpikesCommand reads '2026/04/27' and
 * the counter looks empty.
 *
 * Previously the formats were copy-pasted in 9 locations across:
 *   - UsageMeter::periodKey + ::periodKeys
 *   - DetectUsageSpikesCommand (3 places)
 *   - PruneUsageDataCommand (4 places)
 *
 * One typo in any of them would silently desync. This class is the
 * single source of truth.
 *
 * The format strings are deliberately ISO8601 prefixes so string
 * comparison works as range comparison (lexicographic === temporal
 * for these formats), which lets PruneUsageDataCommand do
 * `WHERE period_key < ?` without parsing.
 */
final class UsagePeriod
{
    public const MINUTE = 'minute';
    public const HOUR   = 'hour';
    public const DAY    = 'day';
    public const MONTH  = 'month';

    /** All period names in coarsening order. */
    public const ALL = [
        self::MINUTE,
        self::HOUR,
        self::DAY,
        self::MONTH,
    ];

    /**
     * Format strings for each period — sorted lexicographically AND
     * temporally (ISO8601 prefixes).
     *
     * @var array<string, string>
     */
    private const FORMATS = [
        self::MINUTE => 'Y-m-d\TH:i',
        self::HOUR   => 'Y-m-d\TH',
        self::DAY    => 'Y-m-d',
        self::MONTH  => 'Y-m',
    ];

    /**
     * Format $at into the canonical key for $period.
     *
     * @throws InvalidArgumentException for unknown $period.
     */
    public static function key(string $period, ?Carbon $at = null): string
    {
        $fmt = self::FORMATS[$period] ?? null;
        if ($fmt === null) {
            throw new InvalidArgumentException("Unknown period: {$period}");
        }
        return ($at ?? now())->format($fmt);
    }

    /**
     * Return every (period => key) pair for the given timestamp. Used
     * by UsageMeter::record() to bump all four buckets in one txn.
     *
     * @return array<string, string>
     */
    public static function allKeys(?Carbon $at = null): array
    {
        $at ??= now();
        $out = [];
        foreach (self::FORMATS as $period => $fmt) {
            $out[$period] = $at->format($fmt);
        }
        return $out;
    }

    /**
     * Inverse of key() — used by the prune command to decide which
     * keys are too old. Returns the literal format string for a period.
     *
     * @throws InvalidArgumentException for unknown $period.
     */
    public static function format(string $period): string
    {
        $fmt = self::FORMATS[$period] ?? null;
        if ($fmt === null) {
            throw new InvalidArgumentException("Unknown period: {$period}");
        }
        return $fmt;
    }
}
