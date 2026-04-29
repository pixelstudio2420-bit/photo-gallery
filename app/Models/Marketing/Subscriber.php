<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Newsletter subscriber (opt-in list member).
 *
 * @property int    $id
 * @property string $email
 * @property ?string $name
 * @property string $status  pending|confirmed|unsubscribed|bounced
 */
class Subscriber extends Model
{
    use HasFactory;

    protected $table = 'marketing_subscribers';

    protected $fillable = [
        'email', 'name', 'locale', 'source', 'status',
        'confirm_token', 'confirmed_at', 'unsubscribed_at',
        'user_id', 'tags', 'meta',
    ];

    protected $casts = [
        'tags' => 'array',
        'meta' => 'array',
        'confirmed_at'     => 'datetime',
        'unsubscribed_at'  => 'datetime',
    ];

    // ── Scopes ────────────────────────────────────────────────
    public function scopeConfirmed($q)    { return $q->where('status', 'confirmed'); }
    public function scopePending($q)      { return $q->where('status', 'pending'); }
    public function scopeUnsubscribed($q) { return $q->where('status', 'unsubscribed'); }

    public function scopeHasTag($q, string $tag)
    {
        return $q->whereJsonContains('tags', $tag);
    }

    // ── Helpers ───────────────────────────────────────────────
    public static function newConfirmToken(): string
    {
        return Str::random(48);
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function markConfirmed(): void
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'confirm_token' => null,
        ]);
    }

    public function markUnsubscribed(): void
    {
        $this->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);
    }
}
