<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdImpression extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['seen_at' => 'datetime', 'is_bot' => 'boolean'];
}
