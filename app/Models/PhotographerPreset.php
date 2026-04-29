<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One Lightroom-style preset.
 *
 * Two flavours:
 *   • System preset (photographer_id = NULL, is_system = true)
 *     Seeded from BuiltInPresets — every photographer can pick from these.
 *   • Custom preset (photographer_id = X)
 *     Created from scratch in the UI, OR imported from a .xmp file the
 *     photographer dragged in from Lightroom.
 *
 * Settings keys (see migration for ranges):
 *   exposure, contrast, highlights, shadows, whites, blacks,
 *   vibrance, saturation, temperature, tint, clarity, sharpness,
 *   grayscale (bool), vignette
 */
class PhotographerPreset extends Model
{
    protected $fillable = [
        'photographer_id',
        'name',
        'description',
        'settings',
        'preview_path',
        'source_xmp_path',
        'is_system',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'settings'   => 'array',
        'is_system'  => 'boolean',
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Default neutral settings — anything missing from the imported preset
     * or hand-edited row falls back to "no change". Saves the renderer
     * from having to null-check every field.
     */
    public const DEFAULTS = [
        'exposure'    => 0,    // EV stops
        'contrast'    => 0,
        'highlights'  => 0,
        'shadows'     => 0,
        'whites'      => 0,
        'blacks'      => 0,
        'vibrance'    => 0,
        'saturation'  => 0,
        'temperature' => 0,
        'tint'        => 0,
        'clarity'     => 0,
        'sharpness'   => 0,
        'grayscale'   => false,
        'vignette'    => 0,
    ];

    public function getMergedSettingsAttribute(): array
    {
        return array_merge(self::DEFAULTS, $this->settings ?? []);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeSystem(Builder $q): Builder
    {
        return $q->where('is_system', true);
    }

    public function scopeForPhotographer(Builder $q, int $photographerId): Builder
    {
        return $q->where(function (Builder $w) use ($photographerId) {
            $w->where('is_system', true)
              ->orWhere('photographer_id', $photographerId);
        });
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderByDesc('is_system')->orderBy('sort_order')->orderBy('id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    public function isOwnedBy(int $photographerId): bool
    {
        return !$this->is_system && (int) $this->photographer_id === $photographerId;
    }

    public function isUsableBy(int $photographerId): bool
    {
        return $this->is_system || $this->isOwnedBy($photographerId);
    }
}
