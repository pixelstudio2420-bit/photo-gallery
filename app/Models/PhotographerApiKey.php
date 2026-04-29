<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * Bearer API token scoped to one photographer.
 *
 * The plaintext token is shown EXACTLY ONCE — at creation. We store
 * bcrypt(token) plus a non-secret 6-char prefix so admins/owners can
 * recognise which key is which without ever recovering the secret
 * (mirrors the GitHub PAT UX).
 */
class PhotographerApiKey extends Model
{
    protected $table = 'photographer_api_keys';

    protected $fillable = [
        'photographer_id',
        'label',
        'token_prefix',
        'token_hash',
        'scopes',
        'last_used_at',
        'last_used_ip',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    /**
     * Generate a new key. Returns [model, plainToken].
     * Caller MUST display the plainToken to the user immediately
     * — it cannot be recovered after this call returns.
     */
    public static function generate(int $photographerId, string $label, string $scopes = 'events:read,photos:read'): array
    {
        $plain  = 'pgk_'.bin2hex(random_bytes(24)); // pgk_ = "photographer key"
        $prefix = substr($plain, 0, 8);             // "pgk_xxxx"

        $key = static::create([
            'photographer_id' => $photographerId,
            'label'           => $label,
            'token_prefix'    => $prefix,
            'token_hash'      => Hash::make($plain),
            'scopes'          => $scopes,
        ]);

        return [$key, $plain];
    }

    public function isRevoked(): bool { return !is_null($this->revoked_at); }
    public function isExpired(): bool { return $this->expires_at && $this->expires_at->isPast(); }
    public function isUsable(): bool  { return !$this->isRevoked() && !$this->isExpired(); }

    public function scopeList(): array
    {
        return array_map('trim', explode(',', (string) $this->scopes));
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopeList(), true);
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }
}
