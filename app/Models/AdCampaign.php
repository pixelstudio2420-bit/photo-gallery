<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdCampaign extends Model
{
    public const PRICING_CPM         = 'cpm';
    public const PRICING_CPC         = 'cpc';
    public const PRICING_FLAT_DAILY  = 'flat_daily';
    public const PRICING_FLAT_MONTH  = 'flat_monthly';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_EXHAUSTED = 'exhausted';   // hit budget cap
    public const STATUS_ENDED     = 'ended';

    protected $fillable = [
        'name', 'advertiser', 'contact_email',
        'pricing_model', 'rate_thb',
        'budget_cap_thb', 'spent_thb',
        'starts_at', 'ends_at', 'status',
        'order_id', 'created_by',
    ];

    protected $casts = [
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
        'rate_thb'       => 'decimal:4',
        'budget_cap_thb' => 'decimal:2',
        'spent_thb'      => 'decimal:2',
    ];

    public function creatives(): HasMany
    {
        return $this->hasMany(AdCreative::class, 'campaign_id');
    }

    public function activeCreatives()
    {
        return $this->creatives()->where('is_active', true);
    }

    public function isServeable(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) return false;
        $now = now();
        if ($this->starts_at && $this->starts_at->gt($now)) return false;
        if ($this->ends_at   && $this->ends_at->lt($now))   return false;
        if ($this->budget_cap_thb !== null && $this->spent_thb >= $this->budget_cap_thb) return false;
        return true;
    }
}
