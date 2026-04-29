<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CampaignRecipient extends Model
{
    protected $table = 'marketing_campaign_recipients';

    protected $fillable = [
        'campaign_id', 'email', 'user_id', 'subscriber_id', 'status',
        'tracking_token', 'sent_at', 'opened_at', 'clicked_at', 'error',
    ];

    protected $casts = [
        'sent_at'    => 'datetime',
        'opened_at'  => 'datetime',
        'clicked_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public static function newTrackingToken(): string
    {
        return Str::random(40);
    }
}
