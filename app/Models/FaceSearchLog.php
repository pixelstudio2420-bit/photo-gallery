<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only ledger of every face-search attempt.
 *
 * See the migration at 2026_04_22_100000_create_face_search_logs_table for
 * field docs. The model exists mainly to let FaceSearchBudget and the admin
 * dashboard use Eloquent query-builder sugar; we never update rows (no
 * timestamps → no updated_at).
 */
class FaceSearchLog extends Model
{
    protected $table = 'face_search_logs';

    /** Rows are append-only; we only manage created_at explicitly. */
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'user_id',
        'ip_address',
        'selfie_hash',
        'search_type',
        'api_calls',
        'face_count',
        'match_count',
        'duration_ms',
        'status',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'event_id'    => 'integer',
        'user_id'     => 'integer',
        'api_calls'   => 'integer',
        'face_count'  => 'integer',
        'match_count' => 'integer',
        'duration_ms' => 'integer',
        'created_at'  => 'datetime',
    ];

    /** Outcomes that count as a successful paid AWS call. */
    public const SUCCESS_STATUSES = ['success', 'no_face'];

    /** Outcomes where we refused to call AWS at all (no cost incurred). */
    public const DENIED_STATUSES = [
        'denied_kill_switch',
        'denied_daily_cap_event',
        'denied_daily_cap_user',
        'denied_daily_cap_ip',
        'denied_monthly_global',
        'fallback_too_large',
    ];

    /** Served from cache — 0 AWS calls, still counted for quota purposes. */
    public const CACHE_STATUS = 'cache_hit';
}
