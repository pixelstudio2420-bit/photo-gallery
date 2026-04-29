<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ThaiSubdistrict extends Model
{
    protected $table = 'thai_subdistricts';
    public $timestamps = false;
    public $incrementing = false;
    protected $fillable = ['id', 'district_id', 'name_th', 'name_en', 'zip_code'];

    public function district() { return $this->belongsTo(ThaiDistrict::class, 'district_id'); }
}
