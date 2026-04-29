<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\EventPhotoCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GoogleDriveService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = AppSetting::get('google_drive_api_key', '');
    }

    // =========================================================================
    //  OAuth2 Service Account Authentication
    // =========================================================================

    /**
     * Get an OAuth2 access token via Service Account JWT assertion.
     *
     * Flow: Read JSON key → build JWT → sign with RSA → exchange for token.
     * Token is cached for 55 minutes (Google tokens last 60 min).
     *
     * @return string|null Access token or null on failure
     */
    public function getAccessToken(): ?string
    {
        // 1. Check cache first
        $cached = Cache::get('google_sa_access_token');
        if ($cached) {
            return $cached;
        }

        // 2. Load Service Account credentials
        $credentials = $this->loadServiceAccountCredentials();
        if (!$credentials) {
            return null;
        }

        try {
            // 3. Build JWT
            $now    = time();
            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64UrlEncode(json_encode([
                'iss'   => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/drive.readonly',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ]));

            // 4. Sign with private key
            $signature = '';
            $success = openssl_sign(
                "{$header}.{$claims}",
                $signature,
                $credentials['private_key'],
                OPENSSL_ALGO_SHA256
            );

            if (!$success) {
                Log::error('Google SA: Failed to sign JWT — openssl_sign returned false');
                return null;
            }

            $jwt = "{$header}.{$claims}." . $this->base64UrlEncode($signature);

            // 5. Exchange JWT for access token
            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if ($response->successful()) {
                $token     = $response->json('access_token');
                $expiresIn = $response->json('expires_in', 3600);

                // Cache for 55 min (5 min safety margin)
                Cache::put('google_sa_access_token', $token, max(60, $expiresIn - 300));

                Log::info('Google SA: Access token obtained successfully');
                return $token;
            }

            Log::error('Google SA: Token exchange failed', [
                'status' => $response->status(),
                'error'  => $response->json('error_description', $response->body()),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('Google SA: Exception getting access token', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Load Service Account JSON credentials.
     * Priority: 1) JSON stored in app_settings  2) File at storage/app/google-service-account.json
     */
    private function loadServiceAccountCredentials(): ?array
    {
        // Try from app_settings (uploaded via admin)
        $json = AppSetting::get('google_service_account_json', '');

        // Try from file
        if (empty($json)) {
            $filePath = storage_path('app/google-service-account.json');
            if (file_exists($filePath)) {
                $json = file_get_contents($filePath);
            }
        }

        if (empty($json)) {
            return null;
        }

        $creds = json_decode($json, true);
        if (!$creds || empty($creds['client_email']) || empty($creds['private_key'])) {
            Log::warning('Google SA: Invalid service account JSON — missing client_email or private_key');
            return null;
        }

        return $creds;
    }

    /**
     * Check if Service Account is configured.
     */
    public function hasServiceAccount(): bool
    {
        return $this->loadServiceAccountCredentials() !== null;
    }

    /**
     * Build an authenticated HTTP client.
     * Uses Service Account token if available, falls back to API key.
     */
    private function authenticatedRequest(): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::timeout(15)->retry(3, function (int $attempt, \Throwable $e) {
            // Exponential backoff: 1s, 2s, 4s
            return (int) (pow(2, $attempt - 1) * 1000);
        }, function (\Throwable $e, \Illuminate\Http\Client\PendingRequest $request) {
            // Only retry on rate-limit (429) or server errors (5xx)
            if ($e instanceof \Illuminate\Http\Client\RequestException) {
                $status = $e->response->status();
                return $status === 429 || $status >= 500;
            }
            return $e instanceof \Illuminate\Http\Client\ConnectionException;
        });

        $token = $this->getAccessToken();
        if ($token) {
            return $client->withToken($token);
        }

        // Fallback: plain HTTP (API key will be added as query param)
        return $client;
    }

    /**
     * Build query params — adds API key only when no OAuth token is available.
     */
    private function authParams(array $params = []): array
    {
        if (!$this->getAccessToken() && !empty($this->apiKey)) {
            $params['key'] = $this->apiKey;
        }
        return $params;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * List files in a Google Drive folder
     */
    public function listFolderFiles(string $folderId, int $maxFiles = 500): array
    {
        $cacheKey = "drive_folder_{$folderId}";

        return Cache::remember($cacheKey, 300, function () use ($folderId, $maxFiles) {
            $files = [];
            $pageToken = null;

            do {
                $params = $this->authParams([
                    'q' => "'{$folderId}' in parents and trashed = false and mimeType contains 'image/'",
                    'fields' => 'nextPageToken, files(id, name, mimeType, thumbnailLink, webViewLink, size)',
                    'pageSize' => min($maxFiles, 100),
                    'orderBy' => 'name',
                ]);

                if ($pageToken) {
                    $params['pageToken'] = $pageToken;
                }

                $response = $this->authenticatedRequest()
                    ->get('https://www.googleapis.com/drive/v3/files', $params);

                if ($response->successful()) {
                    $data = $response->json();
                    $files = array_merge($files, $data['files'] ?? []);
                    $pageToken = $data['nextPageToken'] ?? null;
                } else {
                    Log::warning('Google Drive API error in listFolderFiles', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    break;
                }
            } while ($pageToken && count($files) < $maxFiles);

            return $files;
        });
    }

    /**
     * Get thumbnail URL for a file
     */
    public function thumbnailUrl(string $fileId, int $size = 400): string
    {
        return "https://drive.google.com/thumbnail?id={$fileId}&sz=w{$size}";
    }

    /**
     * Get direct view URL
     */
    public function viewUrl(string $fileId): string
    {
        return "https://drive.google.com/file/d/{$fileId}/view";
    }

    /**
     * Get download URL
     */
    public function downloadUrl(string $fileId): string
    {
        return "https://drive.google.com/uc?id={$fileId}&export=download";
    }

    /**
     * Download file content from Google Drive (authenticated).
     *
     * Uses the Drive API v3 files.get with alt=media to download
     * the actual binary content. Requires Service Account auth.
     *
     * @param  string $fileId  Google Drive file ID
     * @return string|null     Raw binary content or null on failure
     */
    public function downloadFileContent(string $fileId): ?string
    {
        try {
            $response = $this->authenticatedRequest()
                ->timeout(120)  // Allow up to 2 min for large files
                ->get("https://www.googleapis.com/drive/v3/files/{$fileId}", $this->authParams([
                    'alt' => 'media',
                ]));

            if ($response->successful()) {
                $body = $response->body();
                Log::info("Google Drive: Downloaded file {$fileId}", [
                    'size' => strlen($body),
                ]);
                return $body;
            }

            Log::error("Google Drive: Download failed for file {$fileId}", [
                'status' => $response->status(),
                'body'   => mb_substr($response->body(), 0, 200),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error("Google Drive: Exception downloading file {$fileId}", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get file metadata from Google Drive.
     *
     * @param  string $fileId  Google Drive file ID
     * @return array|null      File metadata or null on failure
     */
    public function getFileMetadata(string $fileId): ?array
    {
        try {
            $response = $this->authenticatedRequest()
                ->get("https://www.googleapis.com/drive/v3/files/{$fileId}", $this->authParams([
                    'fields' => 'id,name,mimeType,size,imageMediaMetadata,thumbnailLink',
                ]));

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Throwable $e) {
            Log::error("Google Drive: Exception getting metadata for {$fileId}", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract folder ID from a Google Drive link
     */
    public static function extractFolderId(string $url): ?string
    {
        if (preg_match('/\/folders\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        if (preg_match('/id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        // If it's already a folder ID (no URL)
        if (preg_match('/^[a-zA-Z0-9_-]{10,}$/', $url)) {
            return $url;
        }
        return null;
    }

    /**
     * List files with enhanced metadata (size, imageMediaMetadata) from Google Drive API.
     * Matches the original PHP project's field set.
     */
    public function listFolderFilesDetailed(string $folderId, int $maxFiles = 500): array
    {
        $cacheKey = "drive_folder_detailed_{$folderId}";

        return Cache::remember($cacheKey, 300, function () use ($folderId, $maxFiles) {
            $files = [];
            $pageToken = null;

            do {
                $params = $this->authParams([
                    'q' => "'{$folderId}' in parents and trashed = false and mimeType contains 'image/'",
                    'fields' => 'nextPageToken, files(id, name, mimeType, size, thumbnailLink, webViewLink, webContentLink, imageMediaMetadata)',
                    'pageSize' => min($maxFiles, 100),
                    'orderBy' => 'name',
                ]);

                if ($pageToken) {
                    $params['pageToken'] = $pageToken;
                }

                $response = $this->authenticatedRequest()
                    ->get('https://www.googleapis.com/drive/v3/files', $params);

                if ($response->successful()) {
                    $data = $response->json();
                    $files = array_merge($files, $data['files'] ?? []);
                    $pageToken = $data['nextPageToken'] ?? null;
                } else {
                    Log::warning('Google Drive API error in listFolderFilesDetailed', [
                        'folder_id' => $folderId,
                        'status'    => $response->status(),
                        'body'      => $response->body(),
                    ]);
                    break;
                }
            } while ($pageToken && count($files) < $maxFiles);

            return $files;
        });
    }

    /**
     * Sync folder files to cache table
     */
    public function syncToCache(int $eventId, string $folderId): int
    {
        $files = $this->listFolderFiles($folderId);
        $count = 0;

        foreach ($files as $file) {
            EventPhotoCache::updateOrCreate(
                ['event_id' => $eventId, 'drive_file_id' => $file['id']],
                [
                    'filename' => $file['name'] ?? null,
                    'mime_type' => $file['mimeType'] ?? null,
                    'thumbnail_link' => $file['thumbnailLink'] ?? null,
                    'synced_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    // =========================================================================
    //  Stale-While-Revalidate (SWR) Cache
    // =========================================================================

    /**
     * List folder files using a Stale-While-Revalidate strategy.
     *
     * 1. Fresh cache  -> return immediately (source: cache)
     * 2. Stale cache within grace period -> return stale + dispatch background refresh (source: cache_stale)
     * 3. No cache     -> live API call, store result, return (source: live)
     *
     * Settings used from AppSetting:
     *   queue_sync_interval_minutes  – cache freshness window (default 60)
     *   perf_cache_grace_hours       – stale grace period (default 24)
     *   perf_api_max_files           – max files to fetch (default 500)
     */
    public function listFolderFilesWithSWR(string $folderId, int $eventId): array
    {
        $intervalMin = (int) AppSetting::get('queue_sync_interval_minutes', 60);
        $graceHours  = (int) AppSetting::get('perf_cache_grace_hours', 24);

        // --- Check cache ---
        $cached = $this->getCachedPhotos($eventId);

        if (!empty($cached)) {
            $latestSync = $this->getLatestSyncTime($eventId);

            // Fresh cache
            if ($latestSync && $latestSync->greaterThan(now()->subMinutes($intervalMin))) {
                return [
                    'photos' => $this->formatCachedPhotos($cached),
                    'total'  => count($cached),
                    'source' => 'cache',
                ];
            }

            // Stale but within grace period
            if ($latestSync && $latestSync->greaterThan(now()->subHours($graceHours))) {
                // Dispatch background refresh (non-blocking)
                $this->dispatchBackgroundSync($eventId, $folderId);

                return [
                    'photos' => $this->formatCachedPhotos($cached),
                    'total'  => count($cached),
                    'source' => 'cache_stale',
                ];
            }
        }

        // --- No cache or expired beyond grace period: live fetch ---
        // Thundering herd protection
        if ($this->isSyncLocked($eventId)) {
            $waited = $this->waitForSyncUnlock($eventId);
            if ($waited) {
                $cached = $this->getCachedPhotos($eventId);
                if (!empty($cached)) {
                    return [
                        'photos' => $this->formatCachedPhotos($cached),
                        'total'  => count($cached),
                        'source' => 'cache_waited',
                    ];
                }
            }
        }

        // Fetch live
        $maxFiles = (int) AppSetting::get('perf_api_max_files', 500);
        $files = $this->listFolderFilesDetailed($folderId, $maxFiles);

        if (!empty($files)) {
            $this->syncToCacheDetailed($eventId, $folderId, $files);
            return [
                'photos' => $this->formatLivePhotos($files),
                'total'  => count($files),
                'source' => 'live',
            ];
        }

        // Live fetch returned empty — fall back to stale cache if available
        if (!empty($cached)) {
            return [
                'photos' => $this->formatCachedPhotos($cached),
                'total'  => count($cached),
                'source' => 'cache_stale',
            ];
        }

        return [
            'photos' => [],
            'total'  => 0,
            'source' => 'none',
        ];
    }

    // =========================================================================
    //  Thundering Herd Protection
    // =========================================================================

    /**
     * Check if another process is currently syncing this event.
     * Uses the sync_queue table with status='processing' as a DB-level lock.
     *
     * Settings: perf_lock_timeout (default 30 seconds)
     */
    public function isSyncLocked(int $eventId): bool
    {
        try {
            if (!Schema::hasTable('sync_queue')) {
                return false;
            }

            $lockTimeout = (int) AppSetting::get('perf_lock_timeout', 30);

            return DB::table('sync_queue')
                ->where('event_id', $eventId)
                ->where('status', 'processing')
                ->where('created_at', '>', now()->subSeconds($lockTimeout))
                ->exists();
        } catch (\Throwable $e) {
            Log::warning('isSyncLocked check failed', ['error' => $e->getMessage()]);
            return false; // fail-open
        }
    }

    /**
     * Wait with polling until the sync lock is released or timeout is reached.
     *
     * Settings:
     *   perf_lock_poll_interval – milliseconds between polls (default 500)
     *   perf_lock_max_wait      – maximum number of poll iterations (default 8)
     *
     * @return bool True if cache appeared during wait, false if timed out.
     */
    public function waitForSyncUnlock(int $eventId): bool
    {
        $pollIntervalMs = (int) AppSetting::get('perf_lock_poll_interval', 500);
        $maxWait        = (int) AppSetting::get('perf_lock_max_wait', 8);
        $pollIntervalUs = $pollIntervalMs * 1000; // convert to microseconds

        for ($i = 0; $i < $maxWait; $i++) {
            usleep($pollIntervalUs);

            $cached = $this->getCachedPhotos($eventId);
            if (!empty($cached)) {
                return true;
            }

            // Also check if lock was released
            if (!$this->isSyncLocked($eventId)) {
                return false;
            }
        }

        return false;
    }

    // =========================================================================
    //  Enhanced Cache Sync
    // =========================================================================

    /**
     * Sync folder files to cache with full metadata (individual records).
     *
     * - Stores each file as a separate row in event_photos_cache
     * - Includes file_size, width, height (from Drive API imageMediaMetadata)
     * - Removes files from cache that no longer exist in Drive
     * - Returns detailed counts of added, updated, removed files
     *
     * @param int         $eventId
     * @param string      $folderId
     * @param array|null  $files  Pre-fetched files array. If null, fetches from API.
     * @return array{added: int, updated: int, removed: int, total: int}
     */
    public function syncToCacheDetailed(int $eventId, string $folderId, ?array $files = null): array
    {
        $added   = 0;
        $updated = 0;
        $removed = 0;

        try {
            if ($files === null) {
                $maxFiles = (int) AppSetting::get('perf_api_max_files', 500);
                $files = $this->listFolderFilesDetailed($folderId, $maxFiles);
            }

            // Get existing cached file IDs for this event
            $existingFileIds = EventPhotoCache::where('event_id', $eventId)
                ->pluck('drive_file_id')
                ->toArray();

            $liveFileIds = [];

            foreach ($files as $file) {
                $fileId = $file['id'];
                $liveFileIds[] = $fileId;

                $data = [
                    'filename'       => $file['name'] ?? null,
                    'mime_type'      => $file['mimeType'] ?? null,
                    'file_size'      => $file['size'] ?? 0,
                    'width'          => $file['imageMediaMetadata']['width'] ?? 0,
                    'height'         => $file['imageMediaMetadata']['height'] ?? 0,
                    'thumbnail_link' => $file['thumbnailLink'] ?? null,
                    'synced_at'      => now(),
                ];

                $existing = EventPhotoCache::where('event_id', $eventId)
                    ->where('drive_file_id', $fileId)
                    ->first();

                if ($existing) {
                    $existing->update($data);
                    $updated++;
                } else {
                    EventPhotoCache::create(array_merge($data, [
                        'event_id'      => $eventId,
                        'drive_file_id' => $fileId,
                    ]));
                    $added++;
                }
            }

            // Remove files that are no longer in Drive
            if (!empty($liveFileIds)) {
                $removed = EventPhotoCache::where('event_id', $eventId)
                    ->whereNotIn('drive_file_id', $liveFileIds)
                    ->delete();
            } elseif (!empty($existingFileIds)) {
                // If live returned zero files but we had cache, do NOT delete
                // (Google API may have failed silently)
                $removed = 0;
            }

            Log::info("syncToCacheDetailed: event {$eventId}", [
                'added' => $added, 'updated' => $updated, 'removed' => $removed,
            ]);
        } catch (\Throwable $e) {
            Log::error("syncToCacheDetailed failed for event {$eventId}: " . $e->getMessage());
        }

        return [
            'added'   => $added,
            'updated' => $updated,
            'removed' => $removed,
            'total'   => $added + $updated,
        ];
    }

    // =========================================================================
    //  Performance-aware API Response Headers
    // =========================================================================

    /**
     * Get cache headers for API responses.
     *
     * Settings:
     *   perf_browser_cache_maxage  – max-age in seconds (default 300)
     *   perf_stale_revalidate      – stale-while-revalidate in seconds (default 600)
     *
     * @param string $source  Cache source indicator (cache|cache_stale|live)
     * @return array<string, string>
     */
    public function getCacheHeaders(string $source = 'cache'): array
    {
        $maxAge           = (int) AppSetting::get('perf_browser_cache_maxage', 300);
        $staleRevalidate  = (int) AppSetting::get('perf_stale_revalidate', 600);

        // Reduce max-age for stale/live responses
        if ($source === 'cache_stale') {
            $maxAge = max(60, (int) ($maxAge / 5));
        } elseif ($source === 'live') {
            // Live data is freshest — normal max-age
        }

        return [
            'Cache-Control'  => "public, max-age={$maxAge}, stale-while-revalidate={$staleRevalidate}",
            'X-Cache-Source'  => $source,
        ];
    }

    // =========================================================================
    //  Internal Helpers
    // =========================================================================

    /**
     * Get cached photos for an event from event_photos_cache table.
     */
    private function getCachedPhotos(int $eventId): array
    {
        try {
            if (!Schema::hasTable('event_photos_cache')) {
                return [];
            }

            return EventPhotoCache::where('event_id', $eventId)
                ->orderBy('filename')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get the latest synced_at timestamp for an event's cache.
     */
    private function getLatestSyncTime(int $eventId): ?\Illuminate\Support\Carbon
    {
        try {
            $row = EventPhotoCache::where('event_id', $eventId)
                ->orderByDesc('synced_at')
                ->first();

            return $row?->synced_at;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Dispatch a background sync job without blocking the current request.
     * Uses the sync_queue table — avoids duplicate pending jobs.
     */
    private function dispatchBackgroundSync(int $eventId, string $folderId): void
    {
        try {
            if (!Schema::hasTable('sync_queue')) {
                return;
            }

            // Don't queue if there's already a pending/processing job for this event
            $existing = DB::table('sync_queue')
                ->where('event_id', $eventId)
                ->whereIn('status', ['pending', 'processing'])
                ->exists();

            if ($existing) {
                return;
            }

            DB::table('sync_queue')->insert([
                'event_id'     => $eventId,
                'job_type'     => 'sync_photos',
                'payload'      => json_encode(['drive_folder_id' => $folderId]),
                'status'       => 'pending',
                'attempts'     => 0,
                'max_attempts' => (int) AppSetting::get('queue_max_retries', 3),
                'created_at'   => now(),
            ]);

            Log::info("Background sync queued for event {$eventId}");
        } catch (\Throwable $e) {
            Log::warning("Failed to queue background sync for event {$eventId}: " . $e->getMessage());
        }
    }

    /**
     * Format cached photo records for API response.
     */
    private function formatCachedPhotos(array $cached): array
    {
        return array_map(function ($f) {
            $fileId = $f['drive_file_id'] ?? $f['file_id'] ?? '';
            $thumbnailLink = $f['thumbnail_link'] ?? '';

            // Prefer API thumbnailLink (works with Service Account auth, no public sharing needed)
            $thumb400 = !empty($thumbnailLink) ? preg_replace('/=s\d+/', '=s400', $thumbnailLink) : $this->thumbnailUrl($fileId, 400);
            $thumb800 = !empty($thumbnailLink) ? preg_replace('/=s\d+/', '=s800', $thumbnailLink) : $this->thumbnailUrl($fileId, 800);

            return [
                'id'            => $fileId,
                'name'          => $f['filename'] ?? $f['file_name'] ?? '',
                'thumbnail'     => $thumb400,
                'medium'        => $thumb800,
                'thumbnailLink' => $thumbnailLink,
                'fallback'      => $this->thumbnailUrl($fileId, 400),
                'fallback_md'   => $this->thumbnailUrl($fileId, 800),
                'view_url'      => $this->viewUrl($fileId),
                'size'          => (int) ($f['file_size'] ?? 0),
                'width'         => (int) ($f['width'] ?? 0),
                'height'        => (int) ($f['height'] ?? 0),
            ];
        }, $cached);
    }

    /**
     * Format live API photos for API response.
     */
    private function formatLivePhotos(array $files): array
    {
        return array_map(function ($f) {
            $fileId = $f['id'] ?? '';
            $thumbnailLink = $f['thumbnailLink'] ?? '';

            // Prefer API thumbnailLink (works with Service Account auth, no public sharing needed)
            $thumb400 = !empty($thumbnailLink) ? preg_replace('/=s\d+/', '=s400', $thumbnailLink) : $this->thumbnailUrl($fileId, 400);
            $thumb800 = !empty($thumbnailLink) ? preg_replace('/=s\d+/', '=s800', $thumbnailLink) : $this->thumbnailUrl($fileId, 800);

            return [
                'id'            => $fileId,
                'name'          => $f['name'] ?? '',
                'thumbnail'     => $thumb400,
                'medium'        => $thumb800,
                'thumbnailLink' => $thumbnailLink,
                'fallback'      => $this->thumbnailUrl($fileId, 400),
                'fallback_md'   => $this->thumbnailUrl($fileId, 800),
                'view_url'      => $this->viewUrl($fileId),
                'size'          => (int) ($f['size'] ?? 0),
                'width'         => (int) ($f['imageMediaMetadata']['width'] ?? 0),
                'height'        => (int) ($f['imageMediaMetadata']['height'] ?? 0),
            ];
        }, $files);
    }

    /**
     * Test the Google Drive API connection by attempting to verify the API key.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        $hasSA  = $this->hasServiceAccount();
        $hasKey = !empty($this->apiKey);

        if (!$hasSA && !$hasKey) {
            return [
                'success' => false,
                'message' => 'ยังไม่ได้ตั้งค่า — กรุณาอัปโหลด Service Account JSON หรือกรอก API Key',
                'auth'    => 'none',
            ];
        }

        try {
            // Use Service Account if available
            if ($hasSA) {
                $token = $this->getAccessToken();
                if (!$token) {
                    return [
                        'success' => false,
                        'message' => 'Service Account: ไม่สามารถสร้าง Access Token ได้ — ตรวจสอบ JSON key file',
                        'auth'    => 'service_account_error',
                    ];
                }

                $response = Http::withToken($token)->timeout(10)
                    ->get('https://www.googleapis.com/drive/v3/about', ['fields' => 'user']);

                if ($response->successful()) {
                    $email = $response->json('user.emailAddress', 'unknown');
                    return [
                        'success' => true,
                        'message' => "เชื่อมต่อสำเร็จ (Service Account: {$email})",
                        'auth'    => 'service_account',
                    ];
                }

                $error = $response->json('error.message', $response->body());
                return [
                    'success' => false,
                    'message' => "Service Account error: {$error}",
                    'auth'    => 'service_account_error',
                ];
            }

            // Fallback: API Key (likely to fail with current Google policy)
            $response = Http::timeout(10)->get('https://www.googleapis.com/drive/v3/about', [
                'fields' => 'kind',
                'key'    => $this->apiKey,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'เชื่อมต่อสำเร็จ (API Key)',
                    'auth'    => 'api_key',
                ];
            }

            $error = $response->json('error.message', 'Unknown error');

            // Detect the specific "API keys not supported" error
            if (str_contains($error, 'API keys are not supported')) {
                return [
                    'success' => false,
                    'message' => 'API Key ใช้ไม่ได้แล้ว — Google Drive API ต้องใช้ Service Account แทน กรุณาอัปโหลด JSON key file',
                    'auth'    => 'api_key_deprecated',
                ];
            }

            return [
                'success' => false,
                'message' => "API error: {$error}",
                'auth'    => 'api_key_error',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'auth'    => 'error',
            ];
        }
    }
}
