<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog entry: a purchasable bundle of upload credits.
 *
 * Lives in app_settings UI so admins can tweak prices, add promo packs,
 * or disable an option without a deploy. `is_active=false` hides from
 * photographers but preserves historical bundles that reference it.
 */
class UploadCreditPackage extends Model
{
    use HasFactory;

    protected $table = 'upload_credit_packages';

    protected $fillable = [
        'code',
        'name',
        'description',
        'credits',
        'price_thb',
        'validity_days',
        'badge',
        'color_hex',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'credits'       => 'integer',
        'price_thb'     => 'decimal:2',
        'validity_days' => 'integer',
        'sort_order'    => 'integer',
        'is_active'     => 'boolean',
    ];

    /** Baht per credit (for display: "฿1.50/ภาพ"). */
    public function getPricePerCreditAttribute(): float
    {
        $credits = max(1, (int) $this->credits);
        return round(((float) $this->price_thb) / $credits, 2);
    }

    /** Query scope: only the packages a photographer should see in the store. */
    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /** Default ordering: sort_order asc, then credits asc. */
    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('credits');
    }

    public function bundles()
    {
        return $this->hasMany(PhotographerCreditBundle::class, 'package_id');
    }
}
