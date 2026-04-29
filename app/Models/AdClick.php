<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdClick extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['clicked_at' => 'datetime', 'is_suspicious' => 'boolean'];
}
