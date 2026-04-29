<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogNewsItem extends Model
{
    protected $table = 'blog_news_items';

    protected $fillable = [
        'source_id', 'title', 'url', 'original_content', 'ai_summary',
        'image_url', 'category_id', 'status', 'post_id',
        'relevance_score', 'published_at', 'fetched_at',
    ];

    protected $casts = [
        'source_id'       => 'integer',
        'category_id'     => 'integer',
        'post_id'         => 'integer',
        'relevance_score' => 'integer',
        'published_at'    => 'datetime',
        'fetched_at'      => 'datetime',
    ];

    /* ──────────────────────────── Relationships ──────────────────────────── */

    public function source()
    {
        return $this->belongsTo(BlogNewsSource::class, 'source_id');
    }

    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function post()
    {
        return $this->belongsTo(BlogPost::class, 'post_id');
    }

    /* ──────────────────────────── Scopes ──────────────────────────── */

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('status', 'fetched');
    }
}
