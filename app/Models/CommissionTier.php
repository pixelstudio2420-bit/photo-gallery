<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionTier extends Model
{
    protected $table = 'commission_tiers';

    protected $fillable = [
        'name', 'min_revenue', 'commission_rate', 'color', 'icon',
        'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'min_revenue'     => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('min_revenue');
    }

    /**
     * Find the matching tier for a given total revenue.
     */
    public static function resolveForRevenue(float $revenue): ?self
    {
        return static::active()
            ->where('min_revenue', '<=', $revenue)
            ->orderByDesc('min_revenue')
            ->first();
    }
}
