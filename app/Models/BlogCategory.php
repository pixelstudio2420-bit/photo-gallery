<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogCategory extends Model
{
    protected $table = 'blog_categories';

    protected $fillable = [
        'name', 'slug', 'description', 'meta_title', 'meta_description',
        'parent_id', 'icon', 'color', 'sort_order', 'is_active', 'post_count',
    ];

    protected $casts = [
        'parent_id'  => 'integer',
        'sort_order'  => 'integer',
        'is_active'   => 'boolean',
        'post_count'  => 'integer',
    ];

    /* ──────────────────────────── Relationships ──────────────────────────── */

    public function posts()
    {
        return $this->hasMany(BlogPost::class, 'category_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function newsItems()
    {
        return $this->hasMany(BlogNewsItem::class, 'category_id');
    }

    public function newsSources()
    {
        return $this->hasMany(BlogNewsSource::class, 'category_id');
    }

    /* ──────────────────────────── Scopes ──────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /* ──────────────────────────── Methods ──────────────────────────── */

    public function getUrl(): string
    {
        return url('/blog/category/' . $this->slug);
    }

    public function incrementPostCount(): void
    {
        $this->increment('post_count');
    }

    public function decrementPostCount(): void
    {
        if ($this->post_count > 0) {
            $this->decrement('post_count');
        }
    }
}
