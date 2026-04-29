<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Hot rolling counter. The middleware reads this on every gated request,
 * so reads must always be primary-key lookups (user_id, resource, period,
 * period_key). Writes use atomic SQL via UsageMeter — never via $model->save().
 */
class UsageCounter extends Model
{
    public $timestamps      = false;
    public $incrementing    = false;
    protected $primaryKey   = ['user_id', 'resource', 'period', 'period_key'];
    protected $keyType      = 'string';
    protected $table        = 'usage_counters';

    protected $fillable = [
        'user_id', 'resource', 'period', 'period_key',
        'units', 'cost_microcents', 'updated_at',
    ];

    protected $casts = [
        'units'           => 'int',
        'cost_microcents' => 'int',
        'updated_at'      => 'datetime',
    ];
}
