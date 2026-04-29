<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A subscription plan photographers can enrol in.
 *
 * Plans are largely static data (seeded via migration + admin-editable).
 * The important thing is the `ai_features` JSON array — gating middleware
 * reads this to determine whether a given photographer can access a
 * particular AI endpoint.
 *
 * `storage_bytes` is copied onto the photographer's profile when they
 * subscribe so that the hot-path quota middleware never has to join.
 */
class SubscriptionPlan extends Model
{
    use HasFactory;

    // Well-known plan codes — keep in sync with migration seed.
    public const CODE_FREE     = 'free';
    public const CODE_STARTER  = 'starter';
    public const CODE_PRO      = 'pro';
    public const CODE_BUSINESS = 'business';
    public const CODE_STUDIO   = 'studio';

    protected $fillable = [
        'code',
        'name',
        'tagline',
        'description',
        'price_thb',
        'price_annual_thb',
        'billing_cycle',
        'storage_bytes',
        'ai_features',
        'max_concurrent_events',
        'max_team_seats',
        'monthly_ai_credits',
        'commission_pct',
        'badge',
        'color_hex',
        'sort_order',
        'features_json',
        'is_active',
        'is_default_free',
        'is_public',
    ];

    protected $casts = [
        'price_thb'             => 'decimal:2',
        'price_annual_thb'      => 'decimal:2',
        'storage_bytes'         => 'integer',
        'ai_features'           => 'array',
        'max_concurrent_events' => 'integer',
        'max_team_seats'        => 'integer',
        'monthly_ai_credits'    => 'integer',
        'commission_pct'        => 'decimal:2',
        'sort_order'            => 'integer',
        'features_json'         => 'array',
        'is_active'             => 'boolean',
        'is_default_free'       => 'boolean',
        'is_public'             => 'boolean',
    ];

    // ─── Scopes ──────────────────────────────────────────────────────────

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopePublic($q)
    {
        return $q->where('is_public', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    /**
     * Bootstrap-Icons class for the plan's status icon.
     *
     * Used everywhere a photographer's current plan is shown — the
     * subscription widget, the profile Account Status banner, the top
     * bar avatar, etc. Keep this synced with the icon used on the
     * `/photographer/subscription/plans` cards so the user always sees
     * the same visual language for "this is your tier".
     *
     *   free      bi-camera           (entry-level / portfolio only)
     *   starter   bi-rocket-takeoff   (lift-off, paid tier 1)
     *   pro       bi-stars            (flagship / power-user)
     *   business  bi-buildings        (multi-seat studio)
     *   studio    bi-gem              (premium / enterprise)
     *
     * Custom plan codes seeded by an admin fall back to the generic
     * stars icon so the UI never renders a missing-icon box.
     */
    public function iconClass(): string
    {
        return match ($this->code) {
            self::CODE_FREE     => 'bi-camera',
            self::CODE_STARTER  => 'bi-rocket-takeoff',
            self::CODE_PRO      => 'bi-stars',
            self::CODE_BUSINESS => 'bi-buildings',
            self::CODE_STUDIO   => 'bi-gem',
            default             => 'bi-stars',
        };
    }

    /**
     * Single-character glyph used as a textual fallback for the icon
     * (e.g. when the icon font isn't loaded, or for the avatar pill in
     * the top bar). Mirrors iconClass()'s plan→symbol mapping.
     */
    public function iconGlyph(): string
    {
        return match ($this->code) {
            self::CODE_FREE     => 'F',
            self::CODE_STARTER  => 'S',
            self::CODE_PRO      => 'P',
            self::CODE_BUSINESS => 'B',
            self::CODE_STUDIO   => 'X',
            default             => '★',
        };
    }

    /**
     * Accent hex colour for tinting per-plan UI bits (icon backgrounds,
     * stat borders, ribbons). Falls back to the auth-flow violet when
     * an admin hasn't set a custom colour on the plan row.
     */
    public function accentHex(): string
    {
        return $this->color_hex ?: '#7c3aed';
    }

    public function getStorageGbAttribute(): float
    {
        return round($this->storage_bytes / (1024 ** 3), 2);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->ai_features ?? [], true);
    }

    public function isFree(): bool
    {
        return (float) $this->price_thb <= 0.0;
    }

    public function isPaid(): bool
    {
        return !$this->isFree();
    }

    public function annualSavings(): float
    {
        if (!$this->price_annual_thb || !$this->price_thb) return 0;
        return max(0, ($this->price_thb * 12) - $this->price_annual_thb);
    }

    public static function defaultFree(): ?self
    {
        return static::query()
            ->where('is_default_free', true)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * Query scope — filter plans by their unique `code` column.
     *
     * Usage:
     *   SubscriptionPlan::byCode('pro')->first()              // any state
     *   SubscriptionPlan::active()->byCode('pro')->first()    // active only
     *   SubscriptionPlan::findByCode('pro')                   // direct lookup
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Convenience direct lookup — equivalent to `query()->byCode($code)->first()`.
     * Returns null if no plan exists for the given code.
     */
    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', $code)->first();
    }
}
