<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $table = 'order_items';
    public $timestamps = false;
    protected $fillable = ['order_id','event_id','photo_id','thumbnail_url','price'];
    protected $casts = ['price'=>'decimal:2', 'event_id'=>'integer'];
    public function order() { return $this->belongsTo(Order::class,'order_id'); }
    public function event() { return $this->belongsTo(Event::class,'event_id'); }
}
