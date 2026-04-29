<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EventPhotoCache extends Model
{
    protected $table = 'event_photos_cache';
    public $timestamps = false;
    protected $fillable = ['event_id','drive_file_id','filename','mime_type','file_size','width','height','thumbnail_link','synced_at'];
    protected $casts = ['synced_at'=>'datetime'];
    public function event() { return $this->belongsTo(Event::class,'event_id'); }
}
