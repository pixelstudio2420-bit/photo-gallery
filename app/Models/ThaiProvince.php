<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ThaiProvince extends Model
{
    protected $table = 'thai_provinces';
    public $timestamps = false;
    public $incrementing = false;
    protected $fillable = ['id', 'name_th', 'name_en', 'geography_group'];

    public function districts() { return $this->hasMany(ThaiDistrict::class, 'province_id'); }
}
