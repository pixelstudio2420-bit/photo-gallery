<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ThaiDistrict extends Model
{
    protected $table = 'thai_districts';
    public $timestamps = false;
    public $incrementing = false;
    protected $fillable = ['id', 'province_id', 'name_th', 'name_en'];

    public function province() { return $this->belongsTo(ThaiProvince::class, 'province_id'); }
    public function subdistricts() { return $this->hasMany(ThaiSubdistrict::class, 'district_id'); }
}
