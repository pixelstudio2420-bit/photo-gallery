<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PaymentRefund extends Model
{
    protected $table = 'payment_refunds';
    protected $fillable = ['order_id','transaction_id','user_id','amount','reason','status','requested_by','approved_by','approved_at','processed_at','refund_method','refund_reference','note'];
    protected $casts = ['amount'=>'decimal:2','approved_at'=>'datetime','processed_at'=>'datetime'];
    public function order() { return $this->belongsTo(Order::class,'order_id'); }
    public function user() { return $this->belongsTo(User::class,'user_id'); }
}
