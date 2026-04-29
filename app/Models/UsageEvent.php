<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only usage ledger row. Never updated, only inserted.
 *
 * Don't use this for hot-path quota checks — read `usage_counters` instead.
 * This table is for analytics, audits, and post-hoc cost reports.
 */
class UsageEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'plan_code',
        'resource',
        'units',
        'cost_microcents',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'units'           => 'int',
        'cost_microcents' => 'int',
        'metadata'        => 'array',
        'occurred_at'     => 'datetime',
    ];
}
