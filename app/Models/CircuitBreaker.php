<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CircuitBreaker extends Model
{
    public $incrementing  = false;
    protected $primaryKey = 'feature';
    protected $keyType    = 'string';

    public const STATE_CLOSED    = 'closed';
    public const STATE_OPEN      = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    protected $fillable = [
        'feature', 'state',
        'opened_at', 'reopened_at',
        'threshold_thb', 'spent_thb',
        'period_starts', 'period_ends',
        'notes',
    ];

    protected $casts = [
        'threshold_thb' => 'decimal:2',
        'spent_thb'     => 'decimal:2',
        'opened_at'     => 'datetime',
        'reopened_at'   => 'datetime',
        'period_starts' => 'datetime',
        'period_ends'   => 'datetime',
    ];

    public function isOpen(): bool
    {
        return $this->state === self::STATE_OPEN;
    }
}
