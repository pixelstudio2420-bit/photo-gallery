<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogAiTask extends Model
{
    protected $table = 'blog_ai_tasks';

    protected $fillable = [
        'type', 'title', 'prompt', 'input_data', 'output_data',
        'provider', 'model', 'status', 'post_id', 'admin_id',
        'tokens_input', 'tokens_output', 'cost_usd',
        'processing_time_ms', 'error_message',
    ];

    protected $casts = [
        'input_data'        => 'array',
        // output_data kept as text (can be very large)
        'tokens_input'      => 'integer',
        'tokens_output'     => 'integer',
        'cost_usd'          => 'decimal:6',
        'processing_time_ms' => 'integer',
        'post_id'           => 'integer',
        'admin_id'          => 'integer',
    ];

    /* ──────────────────────────── Relationships ──────────────────────────── */

    public function post()
    {
        return $this->belongsTo(BlogPost::class, 'post_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /* ──────────────────────────── Scopes ──────────────────────────── */

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
