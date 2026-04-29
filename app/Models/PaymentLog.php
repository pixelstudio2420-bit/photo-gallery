<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    protected $table = 'payment_logs';
    public $timestamps = false;
    protected $fillable = ['transaction_id','log_type','message','response_data'];
    protected $casts = ['response_data'=>'array','created_at'=>'datetime'];
}
