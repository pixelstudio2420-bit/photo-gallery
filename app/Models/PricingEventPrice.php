<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PricingEventPrice extends Model
{
    protected $table = 'pricing_event_prices';
    protected $primaryKey = 'event_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['event_id', 'price_per_photo', 'set_by_admin', 'updated_at'];
    protected $casts = ['price_per_photo' => 'decimal:2', 'set_by_admin' => 'boolean', 'updated_at' => 'datetime'];
    public function event() { return $this->belongsTo(Event::class, 'event_id'); }
}
