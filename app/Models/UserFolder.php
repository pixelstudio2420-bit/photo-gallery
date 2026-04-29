<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A folder in a user's personal file manager tree.
 *
 * Rooted at `parent_id = NULL` (root); folders can nest arbitrarily deep.
 * `path` is a denormalised breadcrumb ("/A/B/C") maintained by
 * FileManagerService for cheap breadcrumb rendering + search.
 *
 * `files_count` and `size_bytes` are aggregate caches updated whenever
 * files are created/moved/deleted so the UI can show folder sizes
 * without recursive walks.
 */
class UserFolder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_folders';

    protected $fillable = [
        'user_id',
        'parent_id',
        'name',
        'path',
        'files_count',
        'size_bytes',
    ];

    protected $casts = [
        'files_count' => 'integer',
        'size_bytes'  => 'integer',
    ];

    // ─── Relations ───────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(UserFile::class, 'folder_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeRoots($q)
    {
        return $q->whereNull('parent_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    public function getSizeMbAttribute(): float
    {
        return round($this->size_bytes / (1024 ** 2), 2);
    }
}
