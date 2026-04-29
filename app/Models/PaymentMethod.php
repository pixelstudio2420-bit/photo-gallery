<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $table = 'payment_methods';
    public $timestamps = false;
    protected $fillable = ['method_name','method_type','is_active','sort_order'];
    protected $casts = ['is_active'=>'boolean'];
    public function scopeActive($q) { return $q->where('is_active',true)->orderBy('sort_order'); }
}
