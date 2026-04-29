<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';
    public $timestamps = false;
    protected $fillable = ['admin_id','user_id','action','target_type','target_id','description','ip_address','user_agent','old_values','new_values'];
    protected $casts = ['old_values'=>'array','new_values'=>'array','created_at'=>'datetime'];
    public function admin() { return $this->belongsTo(Admin::class,'admin_id'); }
}
