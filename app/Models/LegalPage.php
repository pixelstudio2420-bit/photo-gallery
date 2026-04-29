<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Legal Page — Privacy Policy / Terms of Service / Refund Policy / etc.
 *
 * One row per public legal document, edited by admins with full version history.
 * The `slug` is the URL segment (e.g. /legal/privacy-policy).
 */
class LegalPage extends Model
{
    protected $table = 'legal_pages';

    protected $fillable = [
        'slug',
        'title',
        'content',
        'version',
        'effective_date',
        'is_published',
        'meta_description',
        'last_updated_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'is_published'   => 'boolean',
    ];

    /* ──────────────────────────── Relationships ──────────────────────────── */

    public function versions()
    {
        return $this->hasMany(LegalPageVersion::class, 'legal_page_id')->orderByDesc('created_at');
    }

    public function updatedBy()
    {
        return $this->belongsTo(Admin::class, 'last_updated_by');
    }

    /* ──────────────────────────── Scopes ──────────────────────────── */

    public function scopePublished($q)
    {
        return $q->where('is_published', true);
    }

    /* ──────────────────────────── Helpers ──────────────────────────── */

    /**
     * Snapshot the current content into a new version row.
     *
     * Called *before* applying new changes so that the history keeps
     * the pre-change state. Returns the created version.
     */
    public function snapshotCurrent(?int $adminId = null, ?string $changeNote = null): LegalPageVersion
    {
        return $this->versions()->create([
            'version'          => $this->version,
            'title'            => $this->title,
            'content'          => $this->content,
            'meta_description' => $this->meta_description,
            'effective_date'   => $this->effective_date,
            'updated_by'       => $adminId,
            'change_note'      => $changeNote,
        ]);
    }

    /**
     * Bump a semver-ish version string. "1.0" → "1.1", "2.3" → "2.4".
     * If the current string isn't parseable, resets to "1.0".
     */
    public static function bumpVersion(?string $current): string
    {
        if (!$current || !preg_match('/^(\d+)\.(\d+)$/', trim($current), $m)) {
            return '1.0';
        }
        return $m[1] . '.' . ((int) $m[2] + 1);
    }

    /**
     * Slugs we ship as "canonical" — the admin UI treats these as built-in
     * and warns before destroying them. New custom pages can be added freely.
     */
    public const CANONICAL_SLUGS = ['privacy-policy', 'terms-of-service', 'refund-policy'];

    public function isCanonical(): bool
    {
        return in_array($this->slug, self::CANONICAL_SLUGS, true);
    }
}
