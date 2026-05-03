<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Festival extends Model
{
    use SoftDeletes;

    protected $table = 'festivals';

    protected $fillable = [
        'slug',
        'name',
        'short_name',
        'theme_variant',
        'emoji',
        'starts_at',
        'ends_at',
        'popup_lead_days',
        'is_recurring',
        'headline',
        'body_md',
        'cta_label',
        'cta_url',
        'cover_image_path',
        'target_province_id',
        'enabled',
        'show_priority',
    ];

    protected $casts = [
        'starts_at'       => 'date',
        'ends_at'         => 'date',
        'popup_lead_days' => 'integer',
        'is_recurring'    => 'boolean',
        'enabled'         => 'boolean',
        'show_priority'   => 'integer',
    ];

    /**
     * Effective popup window: (starts_at - lead_days) → ends_at.
     * The popup_starts_at is a derived attribute, not a column, so
     * admin only manages the celebration dates and lead time
     * separately.
     */
    public function getPopupStartsAtAttribute()
    {
        return $this->starts_at?->copy()->subDays($this->popup_lead_days);
    }

    /**
     * Currently in the popup window — between (starts_at - lead) and
     * ends_at, inclusive on both ends.
     */
    public function isActiveNow(): bool
    {
        if (!$this->enabled) return false;
        $now = now()->startOfDay();
        return $now->betweenIncluded($this->popup_starts_at, $this->ends_at);
    }

    /**
     * Days until starts_at (negative if already started).
     * Useful for "X days to go" copy in upcoming-festival lists.
     */
    public function daysUntilStart(): int
    {
        return now()->startOfDay()->diffInDays($this->starts_at, false);
    }

    public function scopeEnabled($q) { return $q->where('enabled', true); }
}
