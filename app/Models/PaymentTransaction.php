<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $table = 'payment_transactions';
    protected $fillable = ['transaction_id','order_id','user_id','payment_method_id','payment_gateway','gateway_transaction_id','amount','currency','status','paid_at','metadata'];
    protected $casts = ['amount'=>'decimal:2','paid_at'=>'datetime','metadata'=>'array'];
    public function order() { return $this->belongsTo(Order::class,'order_id'); }
    public function user() { return $this->belongsTo(User::class,'user_id'); }
    public function method() { return $this->belongsTo(PaymentMethod::class,'payment_method_id'); }
}
