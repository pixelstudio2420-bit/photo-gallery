<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit log for pricing_packages mutations.
 *
 * Every CRUD touch on PricingPackage produces one log row via
 * PricingPackageAuditObserver. Rows are never updated after insert
 * (they're the audit; mutating them defeats the purpose), so we
 * disable Laravel's `updated_at` to keep the schema minimal.
 *
 * Use cases:
 *   • Forensic — "who changed bundle X's price last week?"
 *   • Anti-fraud — surface anomaly patterns to admins (price flips,
 *     fast revert-after-sale, bulk edits across events).
 *   • Operator self-serve — photographer sees their own change log
 *     as part of an event's bundle history.
 */
class PricingPackageLog extends Model
{
    protected $table = 'pricing_package_logs';

    public $timestamps = false; // only created_at; rows are immutable

    protected $fillable = [
        'package_id',
        'event_id',
        'action',
        'old_values',
        'new_values',
        'changed_by',
        'changed_by_role',
        'reason',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /* ───────── Relations ───────── */

    public function package()
    {
        return $this->belongsTo(PricingPackage::class, 'package_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /* ───────── Scopes ───────── */

    public function scopeForEvent($q, int $eventId)
    {
        return $q->where('event_id', $eventId)->orderByDesc('created_at');
    }

    public function scopeForPackage($q, int $packageId)
    {
        return $q->where('package_id', $packageId)->orderByDesc('created_at');
    }

    public function scopeRecent($q, int $hours = 24)
    {
        return $q->where('created_at', '>=', now()->subHours($hours))->orderByDesc('created_at');
    }

    /* ───────── Helpers for diff rendering ───────── */

    /**
     * Return only the fields that actually changed between old and new,
     * filtering noise (timestamps, denormalized counters). Used by the
     * audit-log UI so admins see "price 270 → 540" instead of a wall
     * of unchanged fields.
     */
    public function diff(): array
    {
        $skip = ['updated_at', 'created_at', 'purchase_count'];
        $old  = $this->old_values ?? [];
        $new  = $this->new_values ?? [];

        $diff = [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        foreach ($keys as $k) {
            if (in_array($k, $skip, true)) continue;
            $oldVal = $old[$k] ?? null;
            $newVal = $new[$k] ?? null;
            if ($oldVal !== $newVal) {
                $diff[$k] = ['old' => $oldVal, 'new' => $newVal];
            }
        }
        return $diff;
    }
}
