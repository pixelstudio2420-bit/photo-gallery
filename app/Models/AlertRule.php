<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertRule extends Model
{
    protected $fillable = [
        'name', 'description',
        'metric', 'operator', 'threshold',
        'channels', 'severity', 'cooldown_minutes',
        'last_triggered_at', 'last_value', 'last_checked_at',
        'is_active', 'firing', 'resolved_at',
    ];

    protected $casts = [
        'channels'           => 'array',
        'threshold'          => 'decimal:4',
        'last_value'         => 'decimal:4',
        'cooldown_minutes'   => 'integer',
        'is_active'          => 'boolean',
        'firing'             => 'boolean',
        'last_triggered_at'  => 'datetime',
        'last_checked_at'    => 'datetime',
        'resolved_at'        => 'datetime',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(AlertEvent::class, 'rule_id');
    }

    public static function operators(): array
    {
        return ['>' => '>', '>=' => '≥', '<' => '<', '<=' => '≤', '=' => '='];
    }

    public static function severities(): array
    {
        return [
            'info'     => 'Info',
            'warn'     => 'Warning',
            'critical' => 'Critical',
        ];
    }

    public static function channelOptions(): array
    {
        return [
            'admin' => ['label' => 'Admin Notification', 'icon' => 'bi-bell'],
            'email' => ['label' => 'อีเมลแอดมิน',         'icon' => 'bi-envelope'],
            'line'  => ['label' => 'LINE',                 'icon' => 'bi-chat-dots'],
            'push'  => ['label' => 'Web Push',             'icon' => 'bi-bell-fill'],
        ];
    }

    /** True if cooldown window has passed (or never triggered). */
    public function canTrigger(): bool
    {
        if (!$this->last_triggered_at) return true;
        return $this->last_triggered_at->addMinutes((int) $this->cooldown_minutes) <= now();
    }

    /**
     * Pure-math evaluation — given a current metric value, does this rule fire?
     */
    public function matches(float $value): bool
    {
        $t = (float) $this->threshold;
        return match ($this->operator) {
            '>'  => $value >  $t,
            '>=' => $value >= $t,
            '<'  => $value <  $t,
            '<=' => $value <= $t,
            '='  => abs($value - $t) < 0.0001,
            default => false,
        };
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
