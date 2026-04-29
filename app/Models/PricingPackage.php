<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingPackage extends Model
{
    protected $table = 'pricing_packages';

    protected $fillable = [
        'name',
        'photo_count',
        'price',
        'description',
        'is_active',
        'event_id',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'price'       => 'decimal:2',
        'photo_count' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('photo_count');
    }

    public function event()
    {
        return $this->belongsTo(\App\Models\Event::class);
    }
}
