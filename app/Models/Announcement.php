<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Announcement — short news/promo post for photographers and/or customers.
 *
 * Lifecycle (`status` column):
 *   draft     → admin still editing, invisible to users.
 *   published → visible to (audience) users when within the schedule window.
 *   archived  → past, hidden from feeds but kept for audit.
 *
 * Visibility query (used by both dashboards):
 *   status='published'
 *     AND (starts_at IS NULL OR starts_at <= NOW())
 *     AND (ends_at   IS NULL OR ends_at   >= NOW())
 *     AND audience IN (target, 'all')
 *
 * The `scopeVisibleTo()` builder method below encodes this, so feed
 * controllers stay thin.
 */
class Announcement extends Model
{
    use SoftDeletes;

    protected $table = 'announcements';

    public const AUDIENCE_PHOTOGRAPHER = 'photographer';
    public const AUDIENCE_CUSTOMER     = 'customer';
    public const AUDIENCE_ALL          = 'all';

    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH   = 'high';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED  = 'archived';

    protected $fillable = [
        'title', 'slug', 'excerpt', 'body',
        'cover_image_path',
        'audience', 'priority', 'cta_label', 'cta_url',
        'status', 'starts_at', 'ends_at', 'is_pinned',
        'created_by_admin_id', 'updated_by_admin_id',
        'view_count',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'is_pinned' => 'boolean',
        'view_count' => 'integer',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(AnnouncementAttachment::class)->orderBy('sort_order');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\Admin::class, 'created_by_admin_id');
    }

    public function updater()
    {
        return $this->belongsTo(\App\Models\Admin::class, 'updated_by_admin_id');
    }

    /**
     * Auto-generate a unique slug from title when one isn't supplied.
     * Falls back to "{slug}-{random}" if a collision is detected — so
     * admins typing identical titles don't get a UNIQUE-violation error.
     */
    protected static function booted(): void
    {
        static::saving(function (self $a) {
            if (empty($a->slug) && !empty($a->title)) {
                $base = Str::slug($a->title);
                $candidate = $base ?: ('a-' . Str::random(6));
                if (static::where('slug', $candidate)->where('id', '!=', $a->id ?? 0)->exists()) {
                    $candidate = $base . '-' . Str::lower(Str::random(4));
                }
                $a->slug = $candidate;
            }
        });
    }

    /* ─────────────────── Scopes ─────────────────── */

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Active = published AND within schedule window (starts/ends).
     */
    public function scopeActive(Builder $q): Builder
    {
        $now = now();
        return $q->where('status', self::STATUS_PUBLISHED)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    /**
     * Filter to announcements visible to a specific audience type.
     * Pass 'photographer' or 'customer' — the scope automatically
     * includes 'all'-audience rows too.
     */
    public function scopeVisibleTo(Builder $q, string $audience): Builder
    {
        $audience = in_array($audience, [self::AUDIENCE_PHOTOGRAPHER, self::AUDIENCE_CUSTOMER], true)
            ? $audience
            : self::AUDIENCE_ALL;
        return $q->active()->whereIn('audience', [$audience, self::AUDIENCE_ALL]);
    }

    /**
     * Default sort for feed listings:
     *   pinned first, then high-priority, then most recently published.
     */
    public function scopeForFeed(Builder $q): Builder
    {
        return $q->orderByDesc('is_pinned')
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->orderByDesc('starts_at')
            ->orderByDesc('id');
    }

    /* ─────────────────── Helpers ─────────────────── */

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isLive(): bool
    {
        if (!$this->isPublished()) return false;
        $now = now();
        if ($this->starts_at && $this->starts_at->gt($now)) return false;
        if ($this->ends_at && $this->ends_at->lt($now)) return false;
        return true;
    }

    public function bumpViewCount(): void
    {
        $this->increment('view_count');
    }
}
