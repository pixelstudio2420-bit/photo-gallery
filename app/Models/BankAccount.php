<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $table = 'bank_accounts';
    protected $fillable = ['bank_code','bank_name','bank_color','account_number','account_holder_name','branch','is_active','sort_order'];
    protected $casts = ['is_active'=>'boolean'];
    public function scopeActive($q) { return $q->where('is_active',true)->orderBy('sort_order'); }
}
