<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only history row for a LegalPage.
 *
 * Every save on LegalPage first snapshots the previous state here, so admins
 * can view the timeline of changes and restore a prior version if needed.
 */
class LegalPageVersion extends Model
{
    protected $table = 'legal_page_versions';

    public const UPDATED_AT = null; // snapshot is immutable once written

    protected $fillable = [
        'legal_page_id',
        'version',
        'title',
        'content',
        'meta_description',
        'effective_date',
        'updated_by',
        'change_note',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'created_at'     => 'datetime',
    ];

    public function page()
    {
        return $this->belongsTo(LegalPage::class, 'legal_page_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }
}
