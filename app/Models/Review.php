<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $table = 'reviews';

    protected $fillable = [
        'user_id', 'photographer_id', 'order_id', 'event_id',
        'rating', 'comment',
        'is_visible', 'is_flagged', 'flag_reason',
        'helpful_count', 'report_count',
        'is_verified_purchase', 'images', 'status',
        'admin_reply', 'admin_reply_at',
        'photographer_reply', 'photographer_reply_at',
    ];

    protected $casts = [
        'is_visible'            => 'boolean',
        'is_flagged'            => 'boolean',
        'is_verified_purchase'  => 'boolean',
        'images'                => 'array',
        'admin_reply_at'        => 'datetime',
        'photographer_reply_at' => 'datetime',
    ];

    // ─── Relations ───
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function photographer()
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    public function photographerProfile()
    {
        return $this->hasOne(PhotographerProfile::class, 'user_id', 'photographer_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function helpfulVotes()
    {
        return $this->hasMany(ReviewHelpfulVote::class, 'review_id');
    }

    public function reports()
    {
        return $this->hasMany(ReviewReport::class, 'review_id');
    }

    // ─── Scopes ───
    public function scopeVisible($q)
    {
        return $q->where('is_visible', true)->where('status', 'approved');
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeFlagged($q)
    {
        return $q->where('is_flagged', true);
    }

    public function scopeReported($q)
    {
        return $q->where('report_count', '>', 0);
    }

    public function scopeForEvent($q, int $eventId)
    {
        return $q->where('event_id', $eventId);
    }

    public function scopeForPhotographer($q, int $photographerId)
    {
        return $q->where('photographer_id', $photographerId);
    }

    public function scopeByRating($q, int $rating)
    {
        return $q->where('rating', $rating);
    }

    // ─── Methods ───

    /**
     * Check if a user has voted this review as helpful.
     */
    public function isHelpfulBy(?int $userId): bool
    {
        if (!$userId) return false;
        return $this->helpfulVotes()->where('user_id', $userId)->exists();
    }

    /**
     * Check if a user has reported this review.
     */
    public function isReportedBy(?int $userId): bool
    {
        if (!$userId) return false;
        return $this->reports()->where('user_id', $userId)->exists();
    }

    /**
     * Toggle helpful vote for a user.
     */
    public function toggleHelpful(int $userId): bool
    {
        $existing = $this->helpfulVotes()->where('user_id', $userId)->first();

        if ($existing) {
            $existing->delete();
            $this->decrement('helpful_count');
            return false; // Removed
        }

        $this->helpfulVotes()->create(['user_id' => $userId]);
        $this->increment('helpful_count');
        return true; // Added
    }

    /**
     * Get rating distribution for a set of reviews.
     */
    public static function ratingDistribution($query = null): array
    {
        $query = $query ?: static::query();

        $counts = (clone $query)->select('rating', \DB::raw('COUNT(*) as count'))
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        $distribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $distribution[$i] = $counts[$i] ?? 0;
        }
        return $distribution;
    }

    /**
     * Calculate average rating for a query.
     */
    public static function averageRating($query = null): float
    {
        $query = $query ?: static::query();
        return round((float) (clone $query)->avg('rating'), 2);
    }

    /**
     * Get rating stats summary (total, average, distribution, percentage by star).
     */
    public static function statsFor($query): array
    {
        $total = (clone $query)->count();
        $avg = $total > 0 ? round((float) (clone $query)->avg('rating'), 2) : 0;
        $distribution = static::ratingDistribution(clone $query);

        $percentages = [];
        foreach ($distribution as $star => $count) {
            $percentages[$star] = $total > 0 ? round(($count / $total) * 100, 1) : 0;
        }

        return [
            'total'        => $total,
            'average'      => $avg,
            'distribution' => $distribution,
            'percentages'  => $percentages,
        ];
    }
}
