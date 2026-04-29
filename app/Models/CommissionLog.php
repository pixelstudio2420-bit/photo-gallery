<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionLog extends Model
{
    protected $table = 'commission_logs';

    protected $fillable = [
        'photographer_id', 'old_rate', 'new_rate', 'reason',
        'changed_by_type', 'changed_by_id', 'source',
    ];

    protected $casts = [
        'old_rate' => 'decimal:2',
        'new_rate' => 'decimal:2',
    ];

    public function photographer()
    {
        return $this->belongsTo(PhotographerProfile::class, 'photographer_id', 'user_id');
    }

    public function changedBy()
    {
        return $this->changed_by_type === 'admin'
            ? $this->belongsTo(Admin::class, 'changed_by_id')
            : null;
    }

    public static function record(int $photographerId, float $oldRate, float $newRate, string $source = 'manual', ?string $reason = null, ?int $adminId = null): self
    {
        return static::create([
            'photographer_id' => $photographerId,
            'old_rate'        => $oldRate,
            'new_rate'        => $newRate,
            'reason'          => $reason,
            'changed_by_type' => 'admin',
            'changed_by_id'   => $adminId,
            'source'          => $source,
        ]);
    }
}
