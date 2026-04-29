<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFile;
use App\Models\UserFolder;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\MediaContext;
use App\Services\Media\R2MediaService;
use App\Support\PlanResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * FileManagerService
 * â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
 * Handles every file-level operation for the consumer cloud-storage system:
 * upload, list, create folder, rename, move, (soft) delete, restore, share,
 * and generate download URLs.
 *
 * Responsibilities:
 *   â€¢ Quota gating â€” delegates to UserStorageService::canUpload() before
 *     writing bytes. Once the write succeeds, increments
 *     auth_users.storage_used_bytes.
 *   â€¢ Path discipline â€” all objects land under `user-files/{id}/â€¦` on
 *     the preferred disk (typically R2). The object key lives on the
 *     user_files row; we never reconstruct paths from filename alone.
 *   â€¢ Soft delete + reclaim â€” deleted files stay on disk until a purge
 *     runs, but their bytes are deducted from the user's used-quota
 *     immediately (so they can free space by trashing).
 *   â€¢ Folder aggregates â€” files_count / size_bytes on user_folders is
 *     updated on every create/move/delete for cheap dashboard renders.
 *
 * Disk selection: defaults to StorageManager's preferredUploadDriver
 * (respects the admin's runtime driver config). Test code can inject
 * a specific disk via the $disk arg on upload().
 */
class FileManagerService
{
    public function __construct(
        protected UserStorageService $storage,
        protected StorageManager $disks,
        protected R2MediaService $media,
    ) {}

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Listing
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Return the contents of a folder for rendering in the file manager UI.
     *
     * Returns:
     *   [
     *     'folder'      => UserFolder|null (current, null = root),
     *     'breadcrumbs' => [UserFolder, â€¦] (ancestors + current),
     *     'folders'     => Collection<UserFolder> (direct children),
     *     'files'       => Collection<UserFile>  (non-deleted files in folder),
     *   ]
     */
    public function listFolder(User $user, ?int $folderId = null): array
    {
        $folder = $folderId
            ? UserFolder::forUser($user->id)->find($folderId)
            : null;

        $breadcrumbs = [];
        if ($folder) {
            $cursor = $folder;
            while ($cursor) {
                array_unshift($breadcrumbs, $cursor);
                $cursor = $cursor->parent;
            }
        }

        $folders = UserFolder::forUser($user->id)
            ->where(function ($q) use ($folderId) {
                $folderId === null
                    ? $q->whereNull('parent_id')
                    : $q->where('parent_id', $folderId);
            })
            ->orderBy('name')
            ->get();

        $files = UserFile::forUser($user->id)
            ->inFolder($folderId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();

        return compact('folder', 'breadcrumbs', 'folders', 'files');
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Folder CRUD
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function createFolder(User $user, string $name, ?int $parentId = null): UserFolder
    {
        $name   = $this->sanitiseName($name);
        $parent = $parentId ? UserFolder::forUser($user->id)->find($parentId) : null;
        if ($parentId && !$parent) {
            throw new \RuntimeException('Parent folder not found.');
        }

        $path = $parent ? rtrim($parent->path ?? '/'.$parent->name, '/').'/'.$name : '/'.$name;

        return UserFolder::create([
            'user_id'    => $user->id,
            'parent_id'  => $parent?->id,
            'name'       => $name,
            'path'       => $path,
        ]);
    }

    public function renameFolder(UserFolder $folder, string $newName): UserFolder
    {
        $newName = $this->sanitiseName($newName);

        return DB::transaction(function () use ($folder, $newName) {
            $oldPath = $folder->path ?? '/'.$folder->name;
            $parent  = $folder->parent;
            $newPath = $parent ? rtrim($parent->path ?? '/'.$parent->name, '/').'/'.$newName : '/'.$newName;

            $folder->update(['name' => $newName, 'path' => $newPath]);

            // Cascade path rewrite into descendants. Cheap LIKE scan since
            // one user rarely has >10k folders.
            $descendants = UserFolder::where('user_id', $folder->user_id)
                ->where('path', 'ilike', $oldPath.'/%')
                ->get();
            foreach ($descendants as $d) {
                $d->update([
                    'path' => $newPath.Str::substr($d->path, Str::length($oldPath)),
                ]);
            }

            return $folder->fresh();
        });
    }

    public function deleteFolder(UserFolder $folder, bool $cascade = true): void
    {
        if (!$cascade && $folder->files()->whereNull('deleted_at')->exists()) {
            throw new \RuntimeException('Folder has files; use cascade=true to remove them.');
        }

        DB::transaction(function () use ($folder) {
            // Soft-delete all files in this folder and descendants, reclaim quota.
            $descendantIds = UserFolder::where('user_id', $folder->user_id)
                ->where('path', 'ilike', ($folder->path ?? '/'.$folder->name).'/%')
                ->pluck('id')
                ->push($folder->id)
                ->all();

            $files = UserFile::whereIn('folder_id', $descendantIds)
                ->whereNull('deleted_at')
                ->get();

            foreach ($files as $file) {
                $this->delete($file);
            }

            UserFolder::whereIn('id', $descendantIds)->delete();
        });
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // File upload
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Ingest an uploaded file.
     *
     * Validates quota via UserStorageService first. On success, writes the
     * object to the preferred disk and returns the UserFile record.
     *
     * @throws \RuntimeException with one of: system_disabled, quota_exceeded,
     *                          file_too_large, file_count_exceeded
     */
    /**
     * Per-user upload security policy.
     * - Block executable / dangerous extensions even if MIME claims something else.
     * - Block known-malicious MIME prefixes.
     * - Cap individual file size at 100 MB by default (admin can raise via
     *   AppSetting or pass through caller — keep simple here).
     */
    private const BLOCKED_EXTENSIONS = [
        // Server-side executables
        'php','phtml','php3','php4','php5','php7','phar','pl','py','rb','sh','bash','zsh','cgi','jsp','jspx','asp','aspx',
        // Windows / mac native binaries
        'exe','dll','msi','bat','cmd','com','scr','pif','vbs','vbe','js','jse','wsf','wsh','ps1','psm1','app','dmg',
        // Server / config exposure
        'env','htaccess','htpasswd','ini','conf','config',
    ];
    private const BLOCKED_MIME_PREFIXES = [
        'application/x-php',
        'application/x-httpd-php',
        'application/x-msdownload',     // exe, dll
        'application/x-msdos-program',  // .com, .exe
        'application/x-msi',
        'application/x-sh',
        'application/x-shellscript',
        'application/x-perl',
        'application/x-python',
        'application/x-bat',
        'text/x-php',
        'text/x-shellscript',
    ];
    private const MAX_FILE_BYTES = 100 * 1024 * 1024; // 100 MB hard cap

    public function upload(User $user, UploadedFile $upload, ?int $folderId = null, ?string $disk = null): UserFile
    {
        $size = $upload->getSize() ?: 0;

        // ── Hard caps ────────────────────────────────────────────────
        if ($size > self::MAX_FILE_BYTES) {
            throw new \RuntimeException('file_too_large');
        }

        // ── Extension + MIME block-list (defense in depth) ───────────
        // We check both because a hostile client may lie about either:
        //   1. The Content-Type header (-> getMimeType())
        //   2. The file extension (.jpg disguising a .php payload)
        // Blocking on either match means an attacker has to bypass both.
        $original = $upload->getClientOriginalName();
        $ext      = strtolower($upload->getClientOriginalExtension());
        $mime     = $upload->getMimeType() ?: null;

        if ($ext !== '' && in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
            throw new \RuntimeException('blocked_file_type');
        }
        // Some uploads hide a PHP payload inside a doubled extension (foo.php.jpg)
        // which $upload->getClientOriginalExtension() reports only the LAST one.
        // Catch by scanning the full filename for any blocked extension.
        $lowerName = strtolower($original);
        foreach (self::BLOCKED_EXTENSIONS as $bad) {
            if (str_contains($lowerName, '.' . $bad . '.') || str_ends_with($lowerName, '.' . $bad)) {
                throw new \RuntimeException('blocked_file_type');
            }
        }
        if ($mime !== null) {
            $lowMime = strtolower($mime);
            foreach (self::BLOCKED_MIME_PREFIXES as $blockedMime) {
                if (str_starts_with($lowMime, $blockedMime)) {
                    throw new \RuntimeException('blocked_mime_type');
                }
            }
        }

        [$ok, $reason] = $this->storage->canUpload($user, $size);
        if (!$ok) {
            throw new \RuntimeException($reason ?? 'upload_blocked');
        }

        $folder = $folderId ? UserFolder::forUser($user->id)->find($folderId) : null;
        if ($folderId && !$folder) {
            throw new \RuntimeException('Destination folder not found.');
        }

        // Compute checksum before write — gives us a stable fingerprint for
        // dedupe / integrity without a second read after upload.
        $checksum = null;
        try {
            $checksum = hash_file('sha256', $upload->getRealPath()) ?: null;
        } catch (\Throwable $e) {
            // non-fatal
        }

        // Store on R2 under the canonical schema:
        //   storage/files/user_{id}/folder_{id_or_root}/{uuid}_{name}.{ext}
        // The folder_id segment uses the user's folder primary key when the
        // file lives inside a folder; otherwise we use folder_root so the
        // path remains valid (the schema demands a resource segment).
        $folderResource = $folder ? (int) $folder->id : 0;

        try {
            $upload_result = $this->media->uploadByContext(
                MediaContext::make('storage', 'files', (int) $user->id, $folderResource),
                $upload,
            );
        } catch (InvalidMediaFileException $e) {
            // Surface the precise rejection reason; wrapping in 'upload_blocked'
            // would erase useful detail (e.g. "MIME type not allowed").
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }

        $relPath = $upload_result->key;
        $disk    = $upload_result->disk; // always 'r2' under the new media stack
        $safeName = $this->sanitiseFilename(pathinfo($original, PATHINFO_FILENAME));

        $file = DB::transaction(function () use ($user, $folder, $original, $safeName, $ext, $mime, $size, $relPath, $disk, $checksum) {
            $file = UserFile::create([
                'user_id'         => $user->id,
                'folder_id'       => $folder?->id,
                'filename'        => $safeName.($ext ? '.'.$ext : ''),
                'original_name'   => $original,
                'extension'       => $ext ?: null,
                'mime_type'       => $mime,
                'size_bytes'      => $size,
                'storage_path'    => $relPath,
                'storage_disk'    => $disk,
                'checksum_sha256' => $checksum,
            ]);

            $user->increment('storage_used_bytes', $size);

            if ($folder) {
                $folder->increment('files_count');
                $folder->increment('size_bytes', $size);
            }

            return $file;
        });

        // Record metered usage for the consumer-storage system. The plan
        // code lives on auth_users.storage_plan_code (set by
        // UserStorageService::syncUserCache()). Defaulting to 'free' is
        // safe because that's the cheapest plan and any drift towards a
        // higher tier means we under-report cost (the dashboard would
        // show conservative numbers, never optimistic ones).
        $planCode = PlanResolver::storageCode($user);
        \App\Services\Usage\UsageMeter::record(
            userId:   (int) $user->id,
            planCode: $planCode,
            resource: 'storage.bytes',
            units:    $size,
            metadata: ['file_id' => $file->id, 'folder_id' => $folder?->id, 'r2_key' => $relPath],
        );

        return $file;
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // File operations
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function rename(UserFile $file, string $newName): UserFile
    {
        $ext      = $file->extension ? '.'.$file->extension : '';
        $baseName = $this->sanitiseFilename(pathinfo($newName, PATHINFO_FILENAME));
        $file->update([
            'filename'      => $baseName.$ext,
            'original_name' => $baseName.$ext,
        ]);
        return $file->fresh();
    }

    public function move(UserFile $file, ?int $newFolderId): UserFile
    {
        return DB::transaction(function () use ($file, $newFolderId) {
            $oldFolder = $file->folder;
            $newFolder = $newFolderId ? UserFolder::forUser($file->user_id)->find($newFolderId) : null;
            if ($newFolderId && !$newFolder) {
                throw new \RuntimeException('Destination folder not found.');
            }

            $file->update(['folder_id' => $newFolder?->id]);

            if ($oldFolder) {
                $oldFolder->decrement('files_count');
                $oldFolder->decrement('size_bytes', $file->size_bytes);
            }
            if ($newFolder) {
                $newFolder->increment('files_count');
                $newFolder->increment('size_bytes', $file->size_bytes);
            }

            return $file->fresh();
        });
    }

    /**
     * Soft-delete a file. The object stays on disk (restorable from trash),
     * but quota is reclaimed immediately so users can free space right away.
     *
     * Use purge() to hard-delete (removes object from disk permanently).
     */
    public function delete(UserFile $file): void
    {
        $reclaim = (int) $file->size_bytes;
        $userId  = (int) $file->user_id;

        DB::transaction(function () use ($file) {
            $user   = User::find($file->user_id);
            $folder = $file->folder;

            $file->delete();  // soft â€” sets deleted_at

            if ($user) {
                $user->decrement('storage_used_bytes', $file->size_bytes);
            }
            if ($folder) {
                $folder->decrement('files_count');
                $folder->decrement('size_bytes', $file->size_bytes);
            }
        });

        // Reverse the metered counter so the consumer-storage quota gauge
        // updates immediately after a soft-delete. (We use reverse() not
        // a fresh negative record() so the ledger marks this row as a
        // reversal in metadata for audit.)
        if ($reclaim > 0) {
            $planCode = PlanResolver::storageCode(User::find($userId));
            \App\Services\Usage\UsageMeter::reverse(
                userId:   $userId,
                planCode: $planCode,
                resource: 'storage.bytes',
                units:    $reclaim,
                metadata: ['file_id' => $file->id, 'reason' => 'soft_delete'],
            );
        }
    }

    public function restore(UserFile $file): UserFile
    {
        return DB::transaction(function () use ($file) {
            $user = User::find($file->user_id);
            // Check quota before restoring â€” the user might have hit the cap
            // via other uploads while the file was in trash.
            if ($user) {
                [$ok, $reason] = $this->storage->canUpload($user, (int) $file->size_bytes);
                if (!$ok && $reason === 'quota_exceeded') {
                    throw new \RuntimeException('restore_exceeds_quota');
                }
            }

            $file->restore();

            if ($user) {
                $user->increment('storage_used_bytes', $file->size_bytes);
            }
            if ($file->folder) {
                $file->folder->increment('files_count');
                $file->folder->increment('size_bytes', $file->size_bytes);
            }

            return $file->fresh();
        });
    }

    /**
     * Hard-delete â€” removes the object from disk permanently. Irreversible.
     */
    public function purge(UserFile $file): void
    {
        try {
            // Route through R2MediaService so the CDN cache is purged too.
            // Falls back to direct disk delete only when the file pre-dates
            // the R2 migration (storage_disk != 'r2').
            if ($file->storage_disk === 'r2') {
                $this->media->delete($file->storage_path);
            } else {
                Storage::disk($file->storage_disk)->delete($file->storage_path);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to purge user file object', [
                'file_id' => $file->id,
                'path'    => $file->storage_path,
                'error'   => $e->getMessage(),
            ]);
        }

        // If file wasn't soft-deleted yet, reclaim quota now.
        if (!$file->trashed()) {
            $this->delete($file);
        }

        $file->forceDelete();
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Sharing
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Create or refresh a share link on a file.
     *
     * $expiresAt: Carbon or null for no expiry
     * $password:  plain string (hashed here) or null for no password
     */
    public function share(UserFile $file, $expiresAt = null, ?string $password = null): UserFile
    {
        $token = UserFile::newShareToken();
        $file->update([
            'share_token'         => $token,
            'share_expires_at'    => $expiresAt,
            'share_password_hash' => $password ? Hash::make($password) : null,
            'is_public'           => true,
        ]);
        return $file->fresh();
    }

    public function unshare(UserFile $file): UserFile
    {
        $file->update([
            'share_token'         => null,
            'share_expires_at'    => null,
            'share_password_hash' => null,
            'is_public'           => false,
        ]);
        return $file->fresh();
    }

    public function verifySharePassword(UserFile $file, ?string $password): bool
    {
        if (!$file->share_password_hash) return true;
        return $password !== null && Hash::check($password, $file->share_password_hash);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Download URL
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Signed URL for the file's object. Falls back to disk->url() for
     * public disks that don't sign. Bumps download counter + access time.
     */
    public function downloadUrl(UserFile $file, int $ttlMinutes = 60): string
    {
        $file->increment('downloads');
        $file->update(['last_accessed_at' => now()]);

        try {
            return Storage::disk($file->storage_disk)->temporaryUrl(
                $file->storage_path,
                now()->addMinutes($ttlMinutes)
            );
        } catch (\Throwable $e) {
            // Local/public disks don't support signed URLs â€” use public URL.
            try {
                return Storage::disk($file->storage_disk)->url($file->storage_path);
            } catch (\Throwable) {
                return '';
            }
        }
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Search
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function searchFiles(User $user, string $query, int $limit = 50)
    {
        $q = '%'.$query.'%';
        return UserFile::forUser($user->id)
            ->whereNull('deleted_at')
            ->where(function ($w) use ($q) {
                $w->where('original_name', 'ilike', $q)
                  ->orWhere('filename', 'ilike', $q);
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Helpers
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    protected function sanitiseName(string $name): string
    {
        $name = trim($name);
        // Strip path separators and control chars; leave unicode letters.
        $name = preg_replace('/[\/\\\\:*?"<>|\x00-\x1F]+/u', '_', $name);
        $name = Str::limit($name, 120, '');
        return $name !== '' ? $name : 'untitled';
    }

    protected function sanitiseFilename(string $name): string
    {
        $name = $this->sanitiseName($name);
        // Replace spaces with dash, collapse repeats.
        $name = preg_replace('/\s+/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);
        return trim($name, '-') ?: 'file';
    }
}
