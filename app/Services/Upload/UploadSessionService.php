<?php

namespace App\Services\Upload;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Tracks the state of a batch upload (10s–1000s of files in one go).
 *
 * The browser uploader works in batches: the photographer drag-drops 200
 * photos onto the page, the JS uploader splits them into parallel
 * channels, and each file goes through its own multipart pipeline. This
 * service is the database-backed source-of-truth for the BATCH:
 *
 *   • how many files were expected,
 *   • how many succeeded / failed,
 *   • can the user resume after a refresh / disconnect?
 *
 * Why a separate table from upload_chunks
 * ---------------------------------------
 * upload_chunks is per-file (one row per multipart upload). One drag-drop
 * may produce 200 of those. The session table aggregates the parent and
 * gives the UI a single thing to poll. Without it, the dashboard would
 * have to JOIN+aggregate 200 rows on every refresh.
 *
 * Why server-side instead of localStorage
 * ---------------------------------------
 * If the user closes the tab, opens it on their phone, or switches
 * browsers, the session continues from where it left off — that's only
 * possible because the truth lives on the server.
 */
class UploadSessionService
{
    /**
     * Open a new batch session. Returns the session token the client
     * uses on subsequent calls.
     *
     * @param  array  $meta  free-form, surfaced to the UI ("dragged 47 photos")
     */
    public function open(
        int $userId,
        ?int $eventId,
        string $category,
        int $expectedFiles = 0,
        array $meta = [],
        ?int $expiresInHours = null,
    ): array {
        $token = (string) Str::uuid();
        $expires = now()->addHours($expiresInHours ?? (int) config('media.upload_session_ttl_hours', 24));

        $id = DB::table('upload_sessions')->insertGetId([
            'session_token'   => $token,
            'user_id'         => $userId,
            'event_id'        => $eventId,
            'category'        => $category,
            'status'          => 'open',
            'expected_files'  => $expectedFiles,
            'completed_files' => 0,
            'failed_files'    => 0,
            'total_bytes'     => 0,
            'meta'            => json_encode($meta),
            'expires_at'      => $expires,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return [
            'id'             => $id,
            'session_token'  => $token,
            'expected_files' => $expectedFiles,
            'expires_at'     => $expires->timestamp,
        ];
    }

    /**
     * Mark one file as successfully uploaded inside the session. Counter
     * updates use atomic SQL increments — safe under concurrent updates
     * from many parallel upload channels.
     */
    public function recordSuccess(string $sessionToken, int $userId, int $bytes = 0): void
    {
        $this->mutateCounters($sessionToken, $userId, success: 1, fail: 0, bytes: $bytes);
    }

    public function recordFailure(string $sessionToken, int $userId): void
    {
        $this->mutateCounters($sessionToken, $userId, success: 0, fail: 1, bytes: 0);
    }

    /**
     * Adjust expected_files mid-session — used when the photographer
     * drags more files in after the session opened.
     */
    public function bumpExpected(string $sessionToken, int $userId, int $delta): void
    {
        DB::table('upload_sessions')
            ->where('session_token', $sessionToken)
            ->where('user_id', $userId)
            ->whereIn('status', ['open', 'finalising'])
            ->update([
                'expected_files' => DB::raw("expected_files + " . max(0, (int) $delta)),
                'updated_at'     => now(),
            ]);
    }

    /**
     * Move the session to 'completed'. Caller usually invokes this once
     * completed_files + failed_files = expected_files. We don't enforce
     * that — the UI can decide when to call this (e.g. after the user
     * clicks 'Finish' even if some files failed).
     */
    public function complete(string $sessionToken, int $userId): bool
    {
        $changed = DB::table('upload_sessions')
            ->where('session_token', $sessionToken)
            ->where('user_id', $userId)
            ->whereIn('status', ['open', 'finalising'])
            ->update([
                'status'     => 'completed',
                'updated_at' => now(),
            ]);
        return $changed > 0;
    }

    public function abort(string $sessionToken, int $userId): bool
    {
        $changed = DB::table('upload_sessions')
            ->where('session_token', $sessionToken)
            ->where('user_id', $userId)
            ->whereIn('status', ['open', 'finalising'])
            ->update([
                'status'     => 'aborted',
                'updated_at' => now(),
            ]);
        return $changed > 0;
    }

    public function find(string $sessionToken, int $userId): ?object
    {
        return DB::table('upload_sessions')
            ->where('session_token', $sessionToken)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Sweep stale (expired) sessions. Called from a console command. Any
     * upload_chunks rows tied to the swept session keep their own expiry
     * and are cleaned up by MultipartUploadService::sweepExpired().
     */
    public function sweepExpired(?\DateTimeInterface $cutoff = null): int
    {
        $cutoff = $cutoff ?? now();
        return DB::table('upload_sessions')
            ->whereIn('status', ['open', 'finalising'])
            ->where('expires_at', '<', $cutoff)
            ->update(['status' => 'expired', 'updated_at' => now()]);
    }

    /* ─────────────────── internals ─────────────────── */

    private function mutateCounters(string $token, int $userId, int $success, int $fail, int $bytes): void
    {
        // Single UPDATE with SQL-level arithmetic = no read-modify-write
        // races even under heavy parallel uploads.
        DB::table('upload_sessions')
            ->where('session_token', $token)
            ->where('user_id', $userId)
            ->whereIn('status', ['open', 'finalising'])
            ->update([
                'completed_files' => DB::raw('completed_files + ' . (int) $success),
                'failed_files'    => DB::raw('failed_files + ' . (int) $fail),
                'total_bytes'     => DB::raw('total_bytes + ' . (int) $bytes),
                'updated_at'      => now(),
            ]);
    }
}
