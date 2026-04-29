<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A single uploaded file in a user's file manager.
 *
 * `storage_path` is the object key on the configured disk (default r2).
 * `filename` is a sanitised on-disk name; `original_name` is what the
 * user uploaded.
 *
 * Soft-deleted files stay on disk until a purge runs (so users can
 * restore from trash). Quota usage is reclaimed at hard-delete time.
 *
 * `share_token` (when set) lets unauthenticated users download at a
 * public URL; `share_expires_at` and `share_password_hash` gate access.
 */
class UserFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_files';

    protected $fillable = [
        'user_id',
        'folder_id',
        'filename',
        'original_name',
        'extension',
        'mime_type',
        'size_bytes',
        'storage_path',
        'storage_disk',
        'checksum_sha256',
        'is_public',
        'share_token',
        'share_expires_at',
        'share_password_hash',
        'downloads',
        'last_accessed_at',
        'thumbnail_path',
        'preview_generated',
        'meta',
    ];

    protected $casts = [
        'size_bytes'        => 'integer',
        'is_public'         => 'boolean',
        'share_expires_at'  => 'datetime',
        'downloads'         => 'integer',
        'last_accessed_at'  => 'datetime',
        'preview_generated' => 'boolean',
        'meta'              => 'array',
    ];

    protected $hidden = ['share_password_hash'];

    // ─── Relations ───────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(UserFolder::class, 'folder_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeInFolder($q, ?int $folderId)
    {
        return $folderId === null
            ? $q->whereNull('folder_id')
            : $q->where('folder_id', $folderId);
    }

    public function scopeShared($q)
    {
        return $q->whereNotNull('share_token');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    public function getSizeKbAttribute(): float
    {
        return round($this->size_bytes / 1024, 1);
    }

    public function getSizeMbAttribute(): float
    {
        return round($this->size_bytes / (1024 ** 2), 2);
    }

    /**
     * Friendly filesize — auto-picks B/KB/MB/GB/TB scale.
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes < 1024) return $bytes.' B';

        $units = ['KB', 'MB', 'GB', 'TB'];
        $scale = min(4, (int) floor(log($bytes, 1024)));
        $value = $bytes / (1024 ** $scale);
        return number_format($value, $value < 10 ? 2 : 1).' '.$units[$scale - 1];
    }

    public function isImage(): bool
    {
        return Str::startsWith($this->mime_type ?? '', 'image/');
    }

    public function isVideo(): bool
    {
        return Str::startsWith($this->mime_type ?? '', 'video/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Icon class for the given extension — Bootstrap Icons name mapping.
     */
    public function getIconAttribute(): string
    {
        $ext = strtolower($this->extension ?? '');
        return match (true) {
            in_array($ext, ['jpg','jpeg','png','gif','webp','svg','bmp','heic','heif'])  => 'bi-file-earmark-image',
            in_array($ext, ['mp4','mov','avi','mkv','webm','m4v'])                       => 'bi-file-earmark-play',
            in_array($ext, ['mp3','wav','ogg','flac','m4a','aac'])                       => 'bi-file-earmark-music',
            in_array($ext, ['pdf'])                                                      => 'bi-file-earmark-pdf',
            in_array($ext, ['doc','docx','odt','pages'])                                 => 'bi-file-earmark-word',
            in_array($ext, ['xls','xlsx','ods','csv','numbers'])                         => 'bi-file-earmark-spreadsheet',
            in_array($ext, ['ppt','pptx','odp','keynote'])                               => 'bi-file-earmark-slides',
            in_array($ext, ['zip','rar','7z','tar','gz','bz2'])                          => 'bi-file-earmark-zip',
            in_array($ext, ['txt','md','rtf'])                                           => 'bi-file-earmark-text',
            in_array($ext, ['html','css','js','ts','php','py','rb','go','rs','json','yml','yaml']) => 'bi-file-earmark-code',
            default                                                                      => 'bi-file-earmark',
        };
    }

    /**
     * True if the share token is set and not expired.
     */
    public function isShareActive(): bool
    {
        if (!$this->share_token) return false;
        if ($this->share_expires_at && $this->share_expires_at->isPast()) return false;
        return true;
    }

    /**
     * Generate a fresh unique share token (unused; caller is responsible for save()).
     */
    public static function newShareToken(): string
    {
        do {
            $token = Str::random(40);
        } while (static::where('share_token', $token)->exists());
        return $token;
    }
}
