<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $table = 'marketing_campaigns';

    protected $fillable = [
        'name', 'subject', 'channel',
        'body_markdown', 'body_html', 'segment', 'status',
        'scheduled_at', 'sent_at',
        'total_recipients', 'sent_count', 'open_count', 'click_count',
        'bounce_count', 'unsubscribe_count', 'created_by',
    ];

    protected $casts = [
        'segment'      => 'array',
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class, 'campaign_id');
    }

    // ── Scopes ────────────────────────────────────────────────
    public function scopeDraft($q)     { return $q->where('status', 'draft'); }
    public function scopeScheduled($q) { return $q->where('status', 'scheduled'); }
    public function scopeSent($q)      { return $q->where('status', 'sent'); }

    // ── Metric helpers ────────────────────────────────────────
    public function openRate(): float
    {
        return $this->sent_count > 0 ? round(100 * $this->open_count / $this->sent_count, 2) : 0;
    }
    public function clickRate(): float
    {
        return $this->sent_count > 0 ? round(100 * $this->click_count / $this->sent_count, 2) : 0;
    }
    public function ctr(): float
    {
        return $this->open_count > 0 ? round(100 * $this->click_count / $this->open_count, 2) : 0;
    }
}
