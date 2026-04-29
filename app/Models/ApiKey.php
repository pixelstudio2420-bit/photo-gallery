<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $table = 'api_keys';

    protected $fillable = [
        'name', 'key_hash', 'key_prefix',
        'created_by_admin_id', 'scopes', 'allowed_ips',
        'rate_limit_per_minute', 'is_active',
        'usage_count', 'last_used_at', 'last_used_ip',
        'expires_at',
    ];

    protected $casts = [
        'scopes'       => 'array',
        'allowed_ips'  => 'array',
        'is_active'    => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    protected $hidden = ['key_hash'];

    /**
     * Generate a new API key pair. Returns [plainKey, hashedModel].
     * Plain key is ONLY shown once; afterwards only the hash is stored.
     */
    public static function generate(string $name, ?int $adminId = null, array $scopes = [], ?\Carbon\Carbon $expiresAt = null): array
    {
        $plainKey = 'pg_' . Str::random(48); // pg_ prefix + 48 random chars = 51 chars
        $prefix = substr($plainKey, 0, 8);   // "pg_ABC12"
        $hash = hash('sha256', $plainKey);

        $model = static::create([
            'name'                 => $name,
            'key_hash'             => $hash,
            'key_prefix'           => $prefix,
            'created_by_admin_id'  => $adminId,
            'scopes'               => $scopes,
            'is_active'            => true,
            'expires_at'           => $expiresAt,
        ]);

        return [$plainKey, $model];
    }

    /**
     * Find an active key by plaintext key.
     */
    public static function findByKey(string $plainKey): ?self
    {
        $hash = hash('sha256', $plainKey);

        return static::where('key_hash', $hash)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    /**
     * Record a usage.
     */
    public function recordUsage(?string $ip = null): void
    {
        $this->increment('usage_count');
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ]);
    }

    /**
     * Check if key has a specific scope.
     */
    public function hasScope(string $scope): bool
    {
        if (empty($this->scopes)) return true; // No scope restriction = all allowed
        return in_array($scope, $this->scopes) || in_array('*', $this->scopes);
    }

    /**
     * Check if a given IP is allowed.
     */
    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->allowed_ips)) return true;
        return in_array($ip, $this->allowed_ips);
    }

    /**
     * Mark inactive.
     */
    public function revoke(): void
    {
        $this->update(['is_active' => false]);
    }

    // Scopes
    public function scopeActive($q)
    {
        return $q->where('is_active', true)
            ->where(function ($sub) {
                $sub->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpiringSoon($q, int $days = 7)
    {
        return $q->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays($days));
    }
}
