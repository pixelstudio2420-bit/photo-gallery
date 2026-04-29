<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class LoginHistory extends Model
{
    protected $table = 'login_history';
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'admin_id',
        'guard',
        'event_type',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'platform',
        'country',
        'city',
        'is_suspicious',
        'created_at',
    ];

    protected $casts = [
        'is_suspicious' => 'boolean',
        'created_at'    => 'datetime',
    ];

    /* ──────────────────────────── Relations ──────────────────────────── */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /* ──────────────────────────── Scopes ──────────────────────────── */
    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeSuspicious($q)
    {
        return $q->where('is_suspicious', true);
    }

    public function scopeRecent($q, int $days = 30)
    {
        return $q->where('created_at', '>=', now()->subDays($days));
    }

    /* ──────────────────────────── Static Recorder ──────────────────────────── */

    /**
     * Record a login-related event.
     * Automatically parses UA for device/browser/platform and flags suspicious IPs.
     */
    public static function record(array $data): self
    {
        try {
            $userAgent = $data['user_agent'] ?? null;

            // Parse user agent if not already provided
            $data['device_type'] = $data['device_type'] ?? self::parseDeviceType($userAgent);
            $data['browser']     = $data['browser']     ?? self::parseBrowser($userAgent);
            $data['platform']    = $data['platform']    ?? self::parsePlatform($userAgent);
            $data['created_at']  = $data['created_at']  ?? now();
            $data['guard']       = $data['guard']       ?? 'user';
            $data['event_type']  = $data['event_type']  ?? 'login';

            // Check suspicious pattern: >3 distinct IPs in 24h for this user
            if (!array_key_exists('is_suspicious', $data) || $data['is_suspicious'] === null) {
                $data['is_suspicious'] = self::detectSuspicious(
                    $data['user_id']  ?? null,
                    $data['admin_id'] ?? null,
                    $data['ip_address'] ?? null
                );
            }

            return static::create($data);
        } catch (\Throwable $e) {
            Log::warning('LoginHistory::record failed: ' . $e->getMessage());
            // Return an unsaved instance so callers don't get null deref
            return new static($data);
        }
    }

    /* ──────────────────────────── Accessors ──────────────────────────── */

    /**
     * Human-readable browser info: "Chrome on Windows"
     */
    public function getBrowserInfoAttribute(): string
    {
        $browser  = $this->browser  ?: 'Unknown';
        $platform = $this->platform ?: 'Unknown';
        return "{$browser} on {$platform}";
    }

    /**
     * Bootstrap Icons class name based on device type.
     */
    public function getIconAttribute(): string
    {
        return match ($this->device_type) {
            'mobile'  => 'bi-phone',
            'tablet'  => 'bi-tablet',
            'desktop' => 'bi-display',
            default   => 'bi-question-circle',
        };
    }

    /* ──────────────────────────── UA Parsers ──────────────────────────── */

    protected static function parseDeviceType(?string $ua): string
    {
        if (!$ua) return 'desktop';
        if (preg_match('/Tablet|iPad/i', $ua))   return 'tablet';
        if (preg_match('/Mobi|Android/i', $ua))  return 'mobile';
        return 'desktop';
    }

    protected static function parseBrowser(?string $ua): string
    {
        if (!$ua) return 'Unknown';
        if (preg_match('/Edg\//i', $ua))     return 'Edge';
        if (preg_match('/OPR\//i', $ua))     return 'Opera';
        if (preg_match('/Chrome/i', $ua))    return 'Chrome';
        if (preg_match('/Firefox/i', $ua))   return 'Firefox';
        if (preg_match('/Safari/i', $ua))    return 'Safari';
        if (preg_match('/MSIE|Trident/i', $ua)) return 'IE';
        return 'Unknown';
    }

    protected static function parsePlatform(?string $ua): string
    {
        if (!$ua) return 'Unknown';
        if (preg_match('/Windows/i', $ua))        return 'Windows';
        if (preg_match('/iPhone|iPad|iOS/i', $ua)) return 'iOS';
        if (preg_match('/Mac OS X|Macintosh/i', $ua)) return 'Mac';
        if (preg_match('/Android/i', $ua))        return 'Android';
        if (preg_match('/Linux/i', $ua))          return 'Linux';
        return 'Unknown';
    }

    /**
     * Detect suspicious behavior: >3 distinct IPs in the past 24h.
     */
    protected static function detectSuspicious(?int $userId, ?int $adminId, ?string $currentIp): bool
    {
        try {
            if (!$userId && !$adminId) return false;

            $q = static::query()->where('created_at', '>=', now()->subDay());

            if ($userId)  $q->where('user_id', $userId);
            if ($adminId) $q->where('admin_id', $adminId);

            $distinctIps = (clone $q)->distinct()->pluck('ip_address')->filter()->all();

            if ($currentIp && !in_array($currentIp, $distinctIps, true)) {
                $distinctIps[] = $currentIp;
            }

            return count(array_unique($distinctIps)) > 3;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
