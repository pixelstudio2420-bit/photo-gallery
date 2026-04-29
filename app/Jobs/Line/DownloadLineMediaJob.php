<?php

namespace App\Jobs\Line;

use App\Models\AppSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Downloads an image / video / file the LINE user sent to our OA, then
 * stores it on R2 under the chat-attachments path.
 *
 * Why this is a job
 * -----------------
 * The LINE Messaging API requires us to ACK the webhook within 1 second
 * — but media payloads can be 10 MB photos that take 5-30s to download.
 * Doing the GET inline would cause LINE to retry the webhook (we'd ACK
 * after the timeout) and create duplicate processing.
 *
 * The job runs on the 'downloads' queue (same lane as photo mirrors)
 * so a burst of inbound media doesn't starve the web pool.
 *
 * Retry semantics
 * ---------------
 * 3 attempts with exponential backoff (60s / 300s / 1500s) covers
 * transient LINE 5xx + DNS hiccups. After that the job lands in
 * failed_jobs; an admin can re-run it from there. The line_inbound_events
 * row stays in `processing_status='processed'` because the webhook itself
 * processed the event correctly — only the deferred download failed.
 *
 * Idempotency
 * -----------
 * A second run with the same message_id is a no-op: we check for an
 * existing chat_attachments row at the top. The unique LINE message
 * id is the natural dedup key.
 */
class DownloadLineMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;
    public int $backoff = 60;

    public function __construct(
        public readonly string $messageId,
        public readonly string $lineUserId,
        public readonly string $contentType, // 'image' | 'video' | 'audio' | 'file'
    ) {
        $this->onQueue('downloads');
    }

    public function backoff(): array
    {
        // 1m, 5m, 25m — covers LINE's "give us a minute" + a longer
        // window for accidental rate-limit lockouts.
        return [60, 300, 1500];
    }

    public function handle(): void
    {
        // ── Idempotency: same message_id was already downloaded? ──────
        $existing = DB::table('line_inbound_media')
            ->where('message_id', $this->messageId)
            ->first();
        if ($existing && $existing->status === 'completed') {
            Log::info('DownloadLineMediaJob: already downloaded, skipping', [
                'message_id' => $this->messageId,
            ]);
            return;
        }

        $token = (string) AppSetting::get('line_channel_access_token', '');
        if ($token === '') {
            throw new \RuntimeException('line_channel_access_token not configured');
        }

        $url = "https://api-data.line.me/v2/bot/message/{$this->messageId}/content";

        // We download to a temp file first, then stream to R2. Holding
        // a 10 MB body in PHP memory works but doesn't scale to 50 MB
        // videos; the temp-file path stays flat regardless of size.
        $tempPath = tempnam(sys_get_temp_dir(), 'line-media-');
        $sink = fopen($tempPath, 'wb');

        try {
            $response = Http::withToken($token)
                ->withOptions(['sink' => $sink])
                ->timeout(60)
                ->get($url);

            if (!$response->successful()) {
                throw new \RuntimeException(sprintf(
                    'LINE content GET failed: HTTP %d %s',
                    $response->status(),
                    substr($response->body(), 0, 200),
                ));
            }

            // The sink stream may still be open; close it before we
            // read filesize / hash / upload.
            if (is_resource($sink)) {
                fclose($sink);
                $sink = null;
            }

            $sizeBytes = filesize($tempPath) ?: 0;
            if ($sizeBytes <= 0) {
                throw new \RuntimeException('Downloaded media is empty');
            }
            // Sanity ceiling — anything bigger than this is almost
            // certainly a misuse / fork bomb. Adjust as needed.
            $maxBytes = (int) AppSetting::get('line_media_max_bytes', 50 * 1024 * 1024);
            if ($sizeBytes > $maxBytes) {
                throw new \RuntimeException(sprintf(
                    'Downloaded media too large: %d > %d',
                    $sizeBytes, $maxBytes,
                ));
            }

            $contentTypeHeader = $response->header('Content-Type') ?: '';
            $extension         = $this->extensionFor($contentTypeHeader, $this->contentType);

            // R2 path: chat/attachments/{user}/line-{messageId}.{ext}.
            // Using the LINE message id as part of the filename gives us
            // a natural unique key + makes it trivial to grep for "where
            // did message X go?".
            $key = sprintf(
                'chat/attachments/line/%s/%s.%s',
                substr($this->lineUserId, 0, 8),
                $this->messageId,
                $extension,
            );

            $disk = Storage::disk((string) config('media.disk', 'r2'));
            $stream = fopen($tempPath, 'rb');
            try {
                $disk->put($key, $stream, ['visibility' => 'private']);
            } finally {
                if (is_resource($stream)) fclose($stream);
            }

            $sha256 = hash_file('sha256', $tempPath) ?: null;

            // Persist the metadata. Upsert by message_id so a retry
            // doesn't insert a duplicate row.
            DB::table('line_inbound_media')->updateOrInsert(
                ['message_id' => $this->messageId],
                [
                    'line_user_id' => $this->lineUserId,
                    'content_type' => $this->contentType,
                    'mime_type'    => $contentTypeHeader,
                    'object_key'   => $key,
                    'size_bytes'   => $sizeBytes,
                    'content_hash' => $sha256,
                    'status'       => 'completed',
                    'downloaded_at'=> now(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]
            );
        } finally {
            if (is_resource($sink)) fclose($sink);
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        // Persist a failure row so the admin UI can show "13 inbound
        // media failed in the last 24h" without grepping logs.
        DB::table('line_inbound_media')->updateOrInsert(
            ['message_id' => $this->messageId],
            [
                'line_user_id' => $this->lineUserId,
                'content_type' => $this->contentType,
                'status'       => 'failed',
                'error'        => substr((string) $e->getMessage(), 0, 500),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );
    }

    private function extensionFor(string $mimeHeader, string $contentType): string
    {
        // Prefer the actual Content-Type LINE returns; fall back to the
        // event's high-level type if the header is missing.
        return match (true) {
            str_contains($mimeHeader, 'jpeg')                       => 'jpg',
            str_contains($mimeHeader, 'png')                        => 'png',
            str_contains($mimeHeader, 'webp')                       => 'webp',
            str_contains($mimeHeader, 'gif')                        => 'gif',
            str_contains($mimeHeader, 'mp4'),
                str_contains($mimeHeader, 'video/mp4')              => 'mp4',
            str_contains($mimeHeader, 'mpeg')                       => 'mp3',
            str_contains($mimeHeader, 'aac')                        => 'aac',
            str_contains($mimeHeader, 'wav')                        => 'wav',
            $contentType === 'image'                                => 'jpg',
            $contentType === 'video'                                => 'mp4',
            $contentType === 'audio'                                => 'm4a',
            default                                                 => 'bin',
        };
    }
}
