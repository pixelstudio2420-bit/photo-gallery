<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use SoftDeletes;

    protected $table = 'blog_posts';

    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'featured_image',
        'category_id', 'author_id', 'status', 'visibility', 'post_password',
        'meta_title', 'meta_description', 'og_image', 'canonical_url',
        'focus_keyword', 'secondary_keywords', 'schema_type',
        'reading_time', 'word_count', 'seo_score', 'readability_score',
        'is_featured', 'is_affiliate_post', 'allow_comments',
        'view_count', 'share_count', 'table_of_contents', 'internal_links',
        'ai_generated', 'ai_provider', 'ai_model',
        'published_at', 'scheduled_at', 'last_modified_at',
    ];

    protected $casts = [
        'secondary_keywords' => 'array',
        'table_of_contents'  => 'array',
        'internal_links'     => 'array',
        'is_featured'        => 'boolean',
        'is_affiliate_post'  => 'boolean',
        'allow_comments'     => 'boolean',
        'ai_generated'       => 'boolean',
        'reading_time'       => 'integer',
        'word_count'         => 'integer',
        'seo_score'          => 'integer',
        'readability_score'  => 'integer',
        'view_count'         => 'integer',
        'share_count'        => 'integer',
        'published_at'       => 'datetime',
        'scheduled_at'       => 'datetime',
        'last_modified_at'   => 'datetime',
    ];

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ Lifecycle hooks â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

    /**
     * Remove featured + OG images (and any other file under blog/posts/{id})
     * when the post is force-deleted. We deliberately only react to
     * `forceDeleting` â€” soft deletes keep files in place so the same post can
     * be restored without broken images. If you want the files gone when
     * soft-deleting too, call ->forceDelete() instead of ->delete().
     */
    protected static function booted(): void
    {
        static::forceDeleted(function (self $post) {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            if ($post->featured_image) {
                try { $disk->delete($post->featured_image); } catch (\Throwable) {}
            }
            if ($post->og_image) {
                try { $disk->delete($post->og_image); } catch (\Throwable) {}
            }
            try {
                app(\App\Services\StorageManager::class)
                    ->purgeDirectory("blog/posts/{$post->id}");
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    "BlogPost#{$post->id} directory purge failed: " . $e->getMessage()
                );
            }
        });
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ Relationships â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function tags()
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tags', 'post_id', 'tag_id');
    }

    public function author()
    {
        return $this->belongsTo(Admin::class, 'author_id');
    }

    public function affiliateClicks()
    {
        return $this->hasMany(BlogAffiliateClick::class, 'post_id');
    }

    public function aiTasks()
    {
        return $this->hasMany(BlogAiTask::class, 'post_id');
    }

    public function ctaButtons()
    {
        return $this->hasMany(BlogCtaButton::class, 'post_id');
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ Scopes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->where('visibility', 'public')
                     ->where('published_at', '<=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeAffiliate($query)
    {
        return $query->where('is_affiliate_post', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'ilike', "%{$term}%")
              ->orWhere('excerpt', 'ilike', "%{$term}%")
              ->orWhere('content', 'ilike', "%{$term}%")
              ->orWhere('focus_keyword', 'ilike', "%{$term}%");
        });
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ Accessors â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

    public function getReadingTimeTextAttribute(): string
    {
        $minutes = $this->reading_time;
        if ($minutes < 1) {
            return 'Less than 1 min read';
        }
        return $minutes . ' min read';
    }

    public function getUrlAttribute(): string
    {
        return url('/blog/' . $this->slug);
    }

    public function getSeoScoreColorAttribute(): string
    {
        if ($this->seo_score >= 80) return '#22c55e'; // green
        if ($this->seo_score >= 50) return '#f59e0b'; // amber
        return '#ef4444'; // red
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ Methods â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

    public function generateSlug(): void
    {
        $slug = Str::slug($this->title);
        $original = $slug;
        $count = 1;

        while (static::withTrashed()->where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = $original . '-' . $count++;
        }

        $this->slug = $slug;
    }

    public function calculateReadingTime(): void
    {
        $wordCount = $this->calculateWordCount();
        $this->reading_time = max(1, (int) ceil($wordCount / 200));
    }

    public function calculateWordCount(): int
    {
        $text = strip_tags($this->content ?? '');
        $this->word_count = str_word_count($text);
        return $this->word_count;
    }

    public function generateTableOfContents(): array
    {
        $toc = [];
        if (empty($this->content)) {
            $this->table_of_contents = $toc;
            return $toc;
        }

        preg_match_all('/<h([2-4])[^>]*>(.*?)<\/h[2-4]>/i', $this->content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $toc[] = [
                'level' => (int) $match[1],
                'text'  => strip_tags($match[2]),
                'id'    => Str::slug(strip_tags($match[2])),
            ];
        }

        $this->table_of_contents = $toc;
        return $toc;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && $this->published_at
            && $this->published_at->lte(now());
    }
}
