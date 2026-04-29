<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    protected $table = 'marketing_push_subscriptions';

    protected $fillable = [
        'user_id', 'endpoint', 'p256dh', 'auth', 'ua',
        'locale', 'tags', 'status', 'last_seen_at',
    ];

    protected $casts = [
        'tags'         => 'array',
        'last_seen_at' => 'datetime',
    ];

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function toWebPushArray(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'keys' => [
                'p256dh' => $this->p256dh,
                'auth'   => $this->auth,
            ],
        ];
    }
}
