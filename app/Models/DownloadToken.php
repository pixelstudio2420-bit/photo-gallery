<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DownloadToken extends Model
{
    protected $table = 'download_tokens';
    public $timestamps = false;
    protected $fillable = ['token','order_id','user_id','photo_id','expires_at','max_downloads','download_count'];
    protected $casts = ['expires_at'=>'datetime','created_at'=>'datetime'];
    public function order() { return $this->belongsTo(Order::class,'order_id'); }
    public function user() { return $this->belongsTo(User::class,'user_id'); }
    public function isValid() { return $this->expires_at->isFuture() && $this->download_count < $this->max_downloads; }
}
