<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogTag extends Model
{
    protected $table = 'blog_tags';

    protected $fillable = [
        'name', 'slug', 'post_count',
    ];

    protected $casts = [
        'post_count' => 'integer',
    ];

    /* ──────────────────────────── Relationships ──────────────────────────── */

    public function posts()
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tags', 'tag_id', 'post_id');
    }

    /* ──────────────────────────── Methods ──────────────────────────── */

    public function getUrl(): string
    {
        return url('/blog/tag/' . $this->slug);
    }
}
