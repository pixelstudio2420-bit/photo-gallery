<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only history of changes to seo_pages rows.
 *
 * One row per save (insert + update). The `snapshot` JSON column holds
 * the entire row's state at the time of the save, so a rollback is just
 * "find the revision, copy snapshot back into seo_pages".
 *
 * Why we store the full snapshot (not a diff)
 * -------------------------------------------
 * Diffs are space-efficient but require replaying the chain to restore
 * any specific version. Full snapshots cost ~2 KB each — at 100 pages
 * × 10 edits/year = 1000 rows = 2 MB. Trivial. And rollback becomes
 * UPDATE-from-JSON, no replay logic.
 */
class SeoPageRevision extends Model
{
    public const UPDATED_AT = null;   // append-only — no updates

    protected $fillable = [
        'seo_page_id', 'version', 'snapshot',
        'change_reason', 'changed_by',
    ];

    protected $casts = [
        'snapshot'   => 'array',
        'created_at' => 'datetime',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(SeoPage::class, 'seo_page_id');
    }
}
