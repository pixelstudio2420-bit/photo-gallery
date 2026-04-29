<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class PushCampaign extends Model
{
    protected $table = 'marketing_push_campaigns';

    protected $fillable = [
        'title', 'body', 'icon', 'click_url',
        'segment', 'segment_value',
        'targets', 'sent', 'failed', 'clicks',
        'status', 'author_id', 'sent_at',
    ];

    protected $casts = [
        'targets' => 'integer',
        'sent'    => 'integer',
        'failed'  => 'integer',
        'clicks'  => 'integer',
        'sent_at' => 'datetime',
    ];

    public function deliveryRate(): float
    {
        if ($this->targets <= 0) return 0.0;
        return round(($this->sent / $this->targets) * 100, 2);
    }

    public function clickRate(): float
    {
        if ($this->sent <= 0) return 0.0;
        return round(($this->clicks / $this->sent) * 100, 2);
    }

    public function statusBadgeColor(): string
    {
        return match ($this->status) {
            'draft'   => 'bg-slate-500/20 text-slate-400 border-slate-500/30',
            'sending' => 'bg-indigo-500/20 text-indigo-400 border-indigo-500/30',
            'sent'    => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
            'failed'  => 'bg-rose-500/20 text-rose-400 border-rose-500/30',
            default   => 'bg-slate-500/20 text-slate-400 border-slate-500/30',
        };
    }
}
