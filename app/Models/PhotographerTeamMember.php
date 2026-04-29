<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A seat on a photographer's team.
 *
 * Roles:
 *   - admin   — full access (manage events, photos, orders, team)
 *   - editor  — manage events + photos (default for invited members)
 *   - viewer  — read-only (see analytics, no edits)
 *
 * Status flow:
 *   pending  → invite_token sent via email; waiting for acceptance
 *   active   → accepted; user_id is set; can act on the team
 *   revoked  → owner removed the seat
 *
 * The owner photographer is NOT stored here — they're implicit (they own
 * the photographer_profile that owns the seats). This avoids a self-join.
 */
class PhotographerTeamMember extends Model
{
    public const ROLE_ADMIN  = 'admin';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_VIEWER = 'viewer';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE  = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'owner_user_id',
        'user_id',
        'invite_email',
        'invite_token',
        'invited_at',
        'accepted_at',
        'role',
        'status',
    ];

    protected $casts = [
        'invited_at'  => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }
    public function isActive(): bool  { return $this->status === self::STATUS_ACTIVE; }

    public function canEdit(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_EDITOR], true);
    }

    public function canManageTeam(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function scopeActiveSeats($q)
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForOwner($q, int $ownerUserId)
    {
        return $q->where('owner_user_id', $ownerUserId);
    }
}
