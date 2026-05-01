<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Photo bundle pricing.
 *
 * One row = one purchasable bundle. Three flavors (`bundle_type`):
 *   • count       — buyer selects N photos, pays a fixed bundle price
 *   • face_match  — buyer runs face search; price = base × found-count × discount,
 *                   capped at max_price. Photo count is per-buyer dynamic.
 *   • event_all   — flat-fee "download every photo" pass for the event
 *
 * Marketing fields (badge, is_featured, original_price, bundle_subtitle) drive
 * the public-facing card UI; they're optional and the cards still render
 * sensibly without them.
 */
class PricingPackage extends Model
{
    protected $table = 'pricing_packages';

    public const TYPE_COUNT       = 'count';
    public const TYPE_FACE_MATCH  = 'face_match';
    public const TYPE_EVENT_ALL   = 'event_all';

    protected $fillable = [
        'name',
        'photo_count',
        'price',
        'description',
        'is_active',
        'event_id',
        // Bundle-marketing fields (added 2026-05-01)
        'bundle_type',
        'discount_pct',
        'max_price',
        'original_price',
        'badge',
        'is_featured',
        'sort_order',
        'bundle_subtitle',
        'purchase_count',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'is_featured'    => 'boolean',
        'price'          => 'decimal:2',
        'original_price' => 'decimal:2',
        'max_price'      => 'decimal:2',
        'discount_pct'   => 'decimal:2',
        'photo_count'    => 'integer',
        'sort_order'     => 'integer',
        'purchase_count' => 'integer',
    ];

    /**
     * Register the audit observer at model-boot time instead of relying
     * on AppServiceProvider::boot(). Service-provider registration
     * occasionally misfires on Laravel Cloud (boot ordering / opcache
     * staleness against newly-deployed code), and observers attached
     * here run regardless of provider state — they're tied directly
     * to the model class loading.
     *
     * Idempotent: Laravel's observer system de-duplicates by observer
     * class name, so even if AppServiceProvider also registers the
     * same observer we don't get double-fires.
     */
    protected static function booted(): void
    {
        static::observe(\App\Observers\PricingPackageAuditObserver::class);
    }

    /* ───────── Scopes ───────── */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('photo_count');
    }

    /** Bundles that apply to a given event (event-specific OR global fallback). */
    public function scopeForEvent(Builder $q, int $eventId): Builder
    {
        return $q->where(fn ($w) => $w->where('event_id', $eventId)->orWhereNull('event_id'));
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('bundle_type', $type);
    }

    public function scopeCountBundles(Builder $q): Builder
    {
        return $q->where('bundle_type', self::TYPE_COUNT);
    }

    public function scopeFaceMatch(Builder $q): Builder
    {
        return $q->where('bundle_type', self::TYPE_FACE_MATCH);
    }

    public function scopeEventAll(Builder $q): Builder
    {
        return $q->where('bundle_type', self::TYPE_EVENT_ALL);
    }

    /* ───────── Computed helpers ───────── */

    /** True if the bundle stores a fixed price (count + event_all) vs computed (face_match). */
    public function hasFixedPrice(): bool
    {
        return $this->bundle_type !== self::TYPE_FACE_MATCH;
    }

    /** Per-photo price for display ("เพียง ฿80/รูป"). Null for face_match (dynamic). */
    public function getPerPhotoPriceAttribute(): ?float
    {
        if (!$this->photo_count || $this->photo_count <= 0) return null;
        return round((float) $this->price / $this->photo_count, 2);
    }

    /** Savings amount (original − price). Used for "ประหยัด ฿X" badge. */
    public function getSavingsAmountAttribute(): float
    {
        if (!$this->original_price) return 0.0;
        return max(0, (float) $this->original_price - (float) $this->price);
    }

    /** Savings percentage shown as "-25%". Returns 0 when not on sale. */
    public function getSavingsPctAttribute(): float
    {
        if (!$this->original_price || $this->original_price <= 0) return 0.0;
        return round(((float) $this->original_price - (float) $this->price) / (float) $this->original_price * 100, 0);
    }

    /* ───────── Audit metadata (set transiently before save) ───────── */

    /**
     * Free-text reason recorded into pricing_package_logs.reason on the
     * next save(). Set by controllers like:
     *   $pkg->auditReason = 'admin bulk recalc';
     *   $pkg->save();
     * Cleared after each save so it doesn't leak across model lifecycles.
     */
    public ?string $auditReason = null;

    /**
     * Override the actor-role guess in PricingPackageAuditObserver
     * (defaults to inferring from auth guards). Useful for system /
     * cron writes that want explicit "system" tagging.
     */
    public ?string $auditRole = null;

    /* ───────── Anti-tamper: orders pending on this bundle ───────── */

    /**
     * True when an unpaid Order references this package_id and would be
     * affected by a price change. Used by the photographer UI to lock
     * the edit + delete actions and warn the photographer that they
     * need to wait for the pending sale to resolve.
     *
     * "Affected" means status in {pending, awaiting_slip} — orders
     * already paid have their prices snapshotted into Order.total +
     * OrderItem.price and can't drift. Cancelled / refunded are also
     * safe to ignore since their financial path is closed.
     */
    public function hasPendingOrders(): bool
    {
        return \App\Models\Order::where('package_id', $this->id)
            ->whereIn('status', ['pending', 'awaiting_slip'])
            ->exists();
    }

    public function pendingOrderCount(): int
    {
        return \App\Models\Order::where('package_id', $this->id)
            ->whereIn('status', ['pending', 'awaiting_slip'])
            ->count();
    }

    /* ───────── Relations ───────── */

    public function event()
    {
        return $this->belongsTo(\App\Models\Event::class);
    }

    public function logs()
    {
        return $this->hasMany(PricingPackageLog::class, 'package_id');
    }
}
