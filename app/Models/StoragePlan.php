<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A consumer cloud-storage plan that end-users subscribe to.
 *
 * Parallels SubscriptionPlan (the photographer-side tiers) but scoped to
 * the "buy N GB of cloud space" business. `storage_bytes` is the total
 * account quota; `max_file_size_bytes` caps single uploads.
 *
 * `features` is a JSON array of capability flags (e.g. 'public_links',
 * 'versioning') — FileManagerService reads this before allowing the
 * associated action.
 */
class StoragePlan extends Model
{
    use HasFactory;

    protected $table = 'storage_plans';

    // Well-known plan codes — keep in sync with the seed migration.
    public const CODE_FREE     = 'free';
    public const CODE_PERSONAL = 'personal';
    public const CODE_PLUS     = 'plus';
    public const CODE_PRO      = 'pro';
    public const CODE_MAX      = 'max';

    protected $fillable = [
        'code',
        'name',
        'tagline',
        'description',
        'price_thb',
        'price_annual_thb',
        'billing_cycle',
        'storage_bytes',
        'max_file_size_bytes',
        'max_files',
        'features',
        'badge',
        'color_hex',
        'sort_order',
        'features_json',
        'is_active',
        'is_default_free',
        'is_public',
    ];

    protected $casts = [
        'price_thb'           => 'decimal:2',
        'price_annual_thb'    => 'decimal:2',
        'storage_bytes'       => 'integer',
        'max_file_size_bytes' => 'integer',
        'max_files'           => 'integer',
        'features'            => 'array',
        'features_json'       => 'array',
        'sort_order'          => 'integer',
        'is_active'           => 'boolean',
        'is_default_free'     => 'boolean',
        'is_public'           => 'boolean',
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

    public function scopeByCode($q, string $code)
    {
        return $q->where('code', $code);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    public function getStorageGbAttribute(): float
    {
        return round($this->storage_bytes / (1024 ** 3), 2);
    }

    public function getMaxFileSizeMbAttribute(): ?float
    {
        return $this->max_file_size_bytes
            ? round($this->max_file_size_bytes / (1024 ** 2), 1)
            : null;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
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

    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', $code)->first();
    }
}
