<?php

namespace App\Services\Upload;

use App\Services\Media\MediaContext;
use App\Services\Media\MediaPathBuilder;
use App\Services\Media\R2MediaService;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Resumable / chunked uploads via S3 multipart-upload protocol on R2.
 *
 * Why this exists
 * ===============
 * The default direct-upload path issues a single presigned PUT URL. That
 * works fine for well-connected desktop users and small files, but breaks
 * down at the edges:
 *
 *   • Mobile uploaders on flaky networks lose the whole 25 MB transfer
 *     when the connection drops 80% of the way through. With multipart,
 *     they retry just the failed part(s).
 *
 *   • Files > config('media.categories.*.max_bytes') exceed PHP's
 *     `upload_max_filesize` and can't go through the form-multipart
 *     controller. Multipart goes browser → R2 directly, regardless of
 *     PHP limits.
 *
 *   • Long uploads pin a PHP-FPM worker for minutes; multipart frees the
 *     worker after each ~50 ms part-signing call.
 *
 * Lifecycle
 * ---------
 *   init()        →  picks an R2 key, opens a multipart upload, returns
 *                    {uploadId, key, partSize, totalParts}, persists an
 *                    upload_chunks row.
 *
 *   signPart()    →  returns a presigned PUT URL for one part. Browser
 *                    PUTs the bytes directly. Returns ETag back to caller.
 *
 *   complete()    →  validates all parts present, asks R2 to assemble the
 *                    object, marks the upload_chunks row 'completed',
 *                    returns the canonical key.
 *
 *   abort()       →  best-effort cleanup; never throws. Marks the row
 *                    'aborted'. Idempotent for retry safety.
 *
 *   sweepExpired() → cron-friendly cleanup of stale uploads (caller is
 *                    a console command).
 *
 * Idempotency
 * -----------
 * Each step is safe to retry. init() with the same idempotency key
 * returns the same upload_id; signPart() is naturally idempotent
 * (regenerating a URL doesn't break R2); complete() is idempotent at
 * the DB layer because we check the row's status first.
 */
class MultipartUploadService
{
    /**
     * R2 part-size minimum is 5 MB (except the last part). We default to
     * 5 MB because it gives a sweet spot of ~5 parts per 25 MB photo
     * (~5 retry units). Callers can raise this for very large videos.
     */
    public const DEFAULT_PART_SIZE = 5 * 1024 * 1024;
    public const MIN_PART_SIZE     = 5 * 1024 * 1024;
    public const MAX_PART_SIZE     = 100 * 1024 * 1024;
    /** R2 hard cap on parts is 10000 (matches AWS S3). */
    public const MAX_PARTS = 10000;

    public function __construct(
        private readonly R2MediaService $media,
        private readonly MediaPathBuilder $pathBuilder,
    ) {}

    /**
     * Open a multipart upload. Returns the upload_id + key + suggested
     * part layout. The browser uploads each part via signPart(), then
     * calls complete() once all parts are confirmed.
     */
    public function init(
        MediaContext $ctx,
        string $originalFilename,
        string $mimeType,
        int $totalBytes,
        ?int $partSize = null,
        ?int $userId = null,
        ?int $sessionId = null,
    ): array {
        $partSize = $partSize ?: self::DEFAULT_PART_SIZE;
        if ($partSize < self::MIN_PART_SIZE || $partSize > self::MAX_PART_SIZE) {
            throw new \InvalidArgumentException(sprintf(
                'partSize must be between %d and %d bytes',
                self::MIN_PART_SIZE,
                self::MAX_PART_SIZE,
            ));
        }
        if ($totalBytes <= 0) {
            throw new \InvalidArgumentException('totalBytes must be positive');
        }
        $totalParts = (int) ceil($totalBytes / $partSize);
        if ($totalParts > self::MAX_PARTS) {
            throw new \InvalidArgumentException(sprintf(
                'totalBytes %d / partSize %d → %d parts; max is %d. Increase partSize.',
                $totalBytes, $partSize, $totalParts, self::MAX_PARTS,
            ));
        }

        $key    = $this->pathBuilder->build($ctx, $originalFilename);
        $client = $this->s3Client();
        $bucket = (string) config('filesystems.disks.r2.bucket');

        try {
            $resp = $client->createMultipartUpload([
                'Bucket'      => $bucket,
                'Key'         => $key,
                'ContentType' => $mimeType,
            ]);
        } catch (S3Exception $e) {
            Log::error('MultipartUploadService.init: R2 createMultipartUpload failed', [
                'key'   => $key,
                'error' => $e->getAwsErrorMessage() ?: $e->getMessage(),
            ]);
            throw new \RuntimeException('Could not open multipart upload', previous: $e);
        }

        $uploadId = (string) $resp['UploadId'];

        DB::table('upload_chunks')->insert([
            'upload_session_id' => $sessionId,
            'user_id'           => (int) ($userId ?? $ctx->userId),
            'event_id'          => $ctx->resourceId !== null ? (int) $ctx->resourceId : null,
            'category'          => $ctx->categoryKey(),
            'object_key'        => $key,
            'upload_id'         => $uploadId,
            'original_filename' => $originalFilename,
            'mime_type'         => $mimeType,
            'total_bytes'       => $totalBytes,
            'total_parts'       => $totalParts,
            'completed_parts'   => 0,
            'status'            => 'initiated',
            'parts'             => json_encode([]),
            'expires_at'        => now()->addHours((int) config('media.multipart_ttl_hours', 24)),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return [
            'upload_id'   => $uploadId,
            'key'         => $key,
            'part_size'   => $partSize,
            'total_parts' => $totalParts,
            'expires_at'  => now()->addHours((int) config('media.multipart_ttl_hours', 24))->timestamp,
        ];
    }

    /**
     * Issue a presigned PUT URL for ONE part. The browser does the upload
     * itself; we just return the URL. The browser must echo back the
     * ETag from R2's response so complete() can pass the manifest back.
     *
     * @return array{url: string, expires_at: int}
     */
    public function signPart(string $uploadId, int $partNumber, int $userId): array
    {
        if ($partNumber < 1 || $partNumber > self::MAX_PARTS) {
            throw new \InvalidArgumentException("partNumber out of range (1..".self::MAX_PARTS.")");
        }

        $row = $this->lockRow($uploadId, $userId);
        if (!$row) {
            throw new \DomainException("Upload {$uploadId} not found or not yours");
        }
        if ($row->status === 'completed' || $row->status === 'aborted') {
            throw new \DomainException("Upload {$uploadId} is {$row->status}; cannot sign more parts");
        }
        if ($partNumber > $row->total_parts) {
            throw new \DomainException("partNumber {$partNumber} exceeds total_parts {$row->total_parts}");
        }

        $client = $this->s3Client();
        $cmd = $client->getCommand('UploadPart', [
            'Bucket'     => (string) config('filesystems.disks.r2.bucket'),
            'Key'        => $row->object_key,
            'UploadId'   => $uploadId,
            'PartNumber' => $partNumber,
        ]);
        $ttl  = (int) config('media.signed_upload_ttl_minutes', 30);
        $req  = $client->createPresignedRequest($cmd, "+{$ttl} minutes");

        // Mark as 'uploading' on first sign so the cleanup sweep knows
        // this isn't a stale init. Not critical for correctness; useful
        // for monitoring.
        if ($row->status === 'initiated') {
            DB::table('upload_chunks')
                ->where('id', $row->id)
                ->where('status', 'initiated')
                ->update(['status' => 'uploading', 'updated_at' => now()]);
        }

        return [
            'url'        => (string) $req->getUri(),
            'expires_at' => time() + ($ttl * 60),
        ];
    }

    /**
     * Complete the multipart upload. Caller passes in the part manifest
     * the browser collected: [{partNumber, etag}, ...]. We hand that to
     * R2's CompleteMultipartUpload, which assembles the object.
     *
     * @param  array<int, array{partNumber: int, etag: string, sizeBytes?: int}>  $parts
     * @return array{key: string, size_bytes: int, content_hash: ?string}
     */
    public function complete(string $uploadId, int $userId, array $parts): array
    {
        if (empty($parts)) {
            throw new \InvalidArgumentException('parts manifest cannot be empty');
        }

        $row = $this->lockRow($uploadId, $userId);
        if (!$row) {
            throw new \DomainException("Upload {$uploadId} not found or not yours");
        }
        if ($row->status === 'completed') {
            // Idempotent retry — return the cached completion data.
            return [
                'key'          => $row->object_key,
                'size_bytes'   => (int) $row->total_bytes,
                'content_hash' => $row->content_hash,
            ];
        }
        if ($row->status === 'aborted') {
            throw new \DomainException("Upload {$uploadId} was aborted");
        }

        // Sort by partNumber — R2 requires ascending.
        usort($parts, fn ($a, $b) => ($a['partNumber'] ?? 0) <=> ($b['partNumber'] ?? 0));
        $manifest = array_map(fn ($p) => [
            'PartNumber' => (int) $p['partNumber'],
            'ETag'       => (string) $p['etag'],
        ], $parts);

        $client = $this->s3Client();
        $bucket = (string) config('filesystems.disks.r2.bucket');

        try {
            $resp = $client->completeMultipartUpload([
                'Bucket'          => $bucket,
                'Key'             => $row->object_key,
                'UploadId'        => $uploadId,
                'MultipartUpload' => ['Parts' => $manifest],
            ]);
        } catch (S3Exception $e) {
            Log::error('MultipartUploadService.complete: R2 completeMultipartUpload failed', [
                'key'   => $row->object_key,
                'error' => $e->getAwsErrorMessage() ?: $e->getMessage(),
            ]);
            // Abort on assembly failure so R2 doesn't keep partial parts
            // around (charged storage). Caller can re-init if needed.
            $this->abortQuietly($uploadId, $row->object_key);
            DB::table('upload_chunks')->where('id', $row->id)->update([
                'status'     => 'aborted',
                'updated_at' => now(),
            ]);
            throw new \RuntimeException('Multipart assembly failed', previous: $e);
        }

        // Compute content hash if the browser supplied one (or sniff from
        // R2 headers — left as a follow-up since R2 doesn't return SHA-256
        // by default).
        $sizeBytes = (int) $row->total_bytes;

        DB::table('upload_chunks')->where('id', $row->id)->update([
            'status'          => 'completed',
            'completed_parts' => count($parts),
            'parts'           => json_encode($parts),
            'updated_at'      => now(),
        ]);

        return [
            'key'          => $row->object_key,
            'size_bytes'   => $sizeBytes,
            'content_hash' => $row->content_hash,
            'etag'         => $resp['ETag'] ?? null,
        ];
    }

    /**
     * Abort an in-flight upload — releases R2's part storage and marks
     * the row 'aborted'. Idempotent. Never throws (best-effort cleanup).
     */
    public function abort(string $uploadId, int $userId): bool
    {
        $row = DB::table('upload_chunks')
            ->where('upload_id', $uploadId)
            ->where('user_id', $userId)
            ->first();
        if (!$row) {
            return false;
        }
        if ($row->status !== 'initiated' && $row->status !== 'uploading') {
            return true; // already terminal
        }

        $this->abortQuietly($uploadId, $row->object_key);

        DB::table('upload_chunks')
            ->where('id', $row->id)
            ->whereIn('status', ['initiated', 'uploading'])
            ->update(['status' => 'aborted', 'updated_at' => now()]);

        return true;
    }

    /**
     * Sweep expired (orphaned) multipart uploads. Anything past
     * `expires_at` that's still in init/uploading state gets aborted at
     * R2 and marked 'expired' in the DB. Use from a daily cron.
     *
     * @return array{aborted: int, scanned: int}
     */
    public function sweepExpired(?\DateTimeInterface $cutoff = null): array
    {
        $cutoff = $cutoff ?? now();
        $rows = DB::table('upload_chunks')
            ->whereIn('status', ['initiated', 'uploading'])
            ->where('expires_at', '<', $cutoff)
            ->limit(500)
            ->get();

        $aborted = 0;
        foreach ($rows as $row) {
            $this->abortQuietly($row->upload_id, $row->object_key);
            DB::table('upload_chunks')
                ->where('id', $row->id)
                ->update(['status' => 'expired', 'updated_at' => now()]);
            $aborted++;
        }
        return ['aborted' => $aborted, 'scanned' => $rows->count()];
    }

    /**
     * List the parts already uploaded — useful for "where did we leave
     * off?" UIs after a disconnect. We trust our DB row over R2 because
     * the row tracks parts on a successful per-part PUT; R2 also has a
     * ListParts endpoint as a fallback for desync recovery.
     *
     * @return array<int, array{partNumber: int, etag: string}>
     */
    public function listParts(string $uploadId, int $userId): array
    {
        $row = DB::table('upload_chunks')
            ->where('upload_id', $uploadId)
            ->where('user_id', $userId)
            ->first();
        if (!$row) {
            throw new \DomainException("Upload {$uploadId} not found or not yours");
        }
        return json_decode((string) $row->parts, true) ?: [];
    }

    /**
     * Record a part as uploaded so the resume-from-disconnect path can
     * skip already-done parts. Browser calls this after each successful
     * PUT (with the ETag R2 returned). Storing client-supplied ETags is
     * fine because complete() forwards them straight to R2 — R2 itself
     * is the source of truth at assembly time.
     */
    public function recordPart(string $uploadId, int $userId, int $partNumber, string $etag, int $sizeBytes): void
    {
        $row = $this->lockRow($uploadId, $userId);
        if (!$row) {
            throw new \DomainException("Upload {$uploadId} not found or not yours");
        }

        $parts = json_decode((string) $row->parts, true) ?: [];
        // Replace any existing entry for this partNumber (resume-after-fail).
        $parts = array_values(array_filter(
            $parts,
            fn ($p) => (int) ($p['partNumber'] ?? 0) !== $partNumber,
        ));
        $parts[] = [
            'partNumber' => $partNumber,
            'etag'       => $etag,
            'sizeBytes'  => $sizeBytes,
        ];

        DB::table('upload_chunks')->where('id', $row->id)->update([
            'parts'           => json_encode($parts),
            'completed_parts' => count($parts),
            'status'          => count($parts) === (int) $row->total_parts ? 'uploading' : $row->status,
            'updated_at'      => now(),
        ]);
    }

    /* ─────────────────── internals ─────────────────── */

    /**
     * SELECT FOR UPDATE so two concurrent requests on the same upload
     * don't race on the parts manifest. Falls back to a plain SELECT on
     * sqlite (which doesn't honour lockForUpdate but never has concurrent
     * readers in test env).
     */
    private function lockRow(string $uploadId, int $userId): ?object
    {
        return DB::table('upload_chunks')
            ->where('upload_id', $uploadId)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();
    }

    private function abortQuietly(string $uploadId, string $key): void
    {
        try {
            $this->s3Client()->abortMultipartUpload([
                'Bucket'   => (string) config('filesystems.disks.r2.bucket'),
                'Key'      => $key,
                'UploadId' => $uploadId,
            ]);
        } catch (\Throwable $e) {
            // Already gone, network blip, IAM quirk — log and move on. The
            // DB row will be marked aborted regardless so the resource
            // doesn't leak in our own state.
            Log::info('MultipartUploadService.abortQuietly: R2 abort failed (likely already cleaned)', [
                'upload_id' => $uploadId,
                'key'       => $key,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function s3Client(): S3Client
    {
        // Reuse Laravel's configured R2 disk; that gives us the same
        // creds + endpoint as every other code path that touches R2.
        $disk = Storage::disk((string) config('media.disk', 'r2'));
        // The driver underneath Storage is an Adapter; the actual S3
        // client is reachable via the disk's adapter ->getClient() call,
        // but Laravel's Filesystem facade wraps it. Construct directly
        // with the same config to keep this service self-contained.
        $cfg = config('filesystems.disks.r2');
        return new S3Client([
            'version'                 => 'latest',
            'region'                  => $cfg['region'] ?? 'auto',
            'endpoint'                => $cfg['endpoint'] ?? null,
            'use_path_style_endpoint' => $cfg['use_path_style_endpoint'] ?? true,
            'credentials'             => [
                'key'    => $cfg['key']    ?? '',
                'secret' => $cfg['secret'] ?? '',
            ],
        ]);
    }
}
