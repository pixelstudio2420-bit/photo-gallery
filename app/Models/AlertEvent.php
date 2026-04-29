<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rule_id', 'triggered_at', 'value', 'severity', 'channels_sent', 'note',
    ];

    protected $casts = [
        'triggered_at'  => 'datetime',
        'value'         => 'decimal:4',
        'channels_sent' => 'array',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'rule_id');
    }
}
