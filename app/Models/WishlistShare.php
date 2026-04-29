<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WishlistShare extends Model
{
    protected $table = 'wishlist_shares';

    protected $fillable = [
        'user_id',
        'token',
        'title',
        'description',
        'is_public',
        'view_count',
        'expires_at',
    ];

    protected $casts = [
        'is_public'  => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* ──────────────────────────── Relations ──────────────────────────── */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Returns Wishlist models for this share's user.
     */
    public function items()
    {
        return $this->hasMany(Wishlist::class, 'user_id', 'user_id');
    }

    /* ──────────────────────────── Scopes ──────────────────────────── */
    public function scopeActive($q)
    {
        return $q->where('is_public', true)
                 ->where(function ($q2) {
                     $q2->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                 });
    }

    public function scopeExpired($q)
    {
        return $q->whereNotNull('expires_at')
                 ->where('expires_at', '<=', now());
    }

    /* ──────────────────────────── Static Factory ──────────────────────────── */

    /**
     * Create a new shareable wishlist for a user with a unique token.
     */
    public static function createForUser(int $userId, array $data = []): self
    {
        // Generate a unique 40-char token
        do {
            $token = Str::random(40);
        } while (static::where('token', $token)->exists());

        return static::create(array_merge([
            'user_id'     => $userId,
            'token'       => $token,
            'title'       => $data['title']       ?? null,
            'description' => $data['description'] ?? null,
            'is_public'   => $data['is_public']   ?? true,
            'view_count'  => 0,
            'expires_at'  => $data['expires_at']  ?? null,
        ], array_intersect_key($data, array_flip([
            'title', 'description', 'is_public', 'expires_at',
        ]))));
    }

    /* ──────────────────────────── Methods ──────────────────────────── */

    public function getUrl(): string
    {
        return url("/wishlist/shared/{$this->token}");
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function incrementViews(): void
    {
        try {
            $this->increment('view_count');
        } catch (\Throwable $e) {
            \Log::warning('WishlistShare::incrementViews failed: ' . $e->getMessage());
        }
    }

    /**
     * Returns Wishlist records belonging to this share's owner,
     * with their related Event / Product eagerly loaded.
     */
    public function getWishlistItems()
    {
        return Wishlist::where('user_id', $this->user_id)
            ->with(['event.photographerProfile', 'event.category', 'product'])
            ->get();
    }
}
