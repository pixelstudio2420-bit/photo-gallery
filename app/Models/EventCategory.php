<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EventCategory extends Model
{
    protected $table = 'event_categories';
    public $timestamps = false;
    protected $fillable = ['name','slug','icon','status'];
    public function events() { return $this->hasMany(Event::class,'category_id'); }
    public function scopeActive($q) { return $q->where('status','active'); }
}
