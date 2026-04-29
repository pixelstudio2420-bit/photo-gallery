<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\DownloadToken;
use App\Models\EventPhoto;
use App\Services\GoogleDriveService;
use App\Services\MailService;
use App\Services\StorageManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Build a ZIP of all photos in an order on the queue, then email the
 * customer a one-click download link when the ZIP is ready.
 *
 * Multi-driver aware
 * ------------------
 * Sources photos in this priority order for each item:
 *   1. R2 / S3 (using StorageManager, native read)
 *   2. Any mirror disk recorded on the EventPhoto
 *   3. Google Drive (legacy fallback)
 *
 * The final ZIP is uploaded to `storage_zip_disk` so customers download
 * from R2/S3 via a signed URL — zero application-server bandwidth.
 */
class BuildOrderZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 1800;
    public int $backoff = 60;

    public function __construct(
        public int $downloadTokenId
    ) {
        $this->onQueue('downloads');
    }

    public function handle(StorageManager $storage): void
    {
        $dl = DownloadToken::with(['order.event', 'order.items', 'user'])
            ->find($this->downloadTokenId);
        if (!$dl || !$dl->order) {
            Log::warning("BuildOrderZipJob: token {$this->downloadTokenId} not found");
            return;
        }

        @set_time_limit(0);

        $brand = Str::slug(AppSetting::get('site_name', config('app.name', 'Photo')));
        $event = Str::slug($dl->order->event->name ?? 'photos') ?: 'photos';
        $count = $dl->order->items->count();

        $zipName = "{$brand}_{$event}_{$count}-Photos.zip";
        $zipDir  = storage_path('app/zips');
        if (!is_dir($zipDir)) mkdir($zipDir, 0755, true);
        $zipPath = $zipDir . DIRECTORY_SEPARATOR . "order-{$dl->order_id}-" . Str::random(8) . '.zip';

        $tempDir = storage_path('app/temp/zip-' . Str::random(16));
        if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            Log::error("BuildOrderZipJob: could not open {$zipPath}");
            $this->cleanup($tempDir);
            return;
        }

        $driveApiKey = AppSetting::get('google_drive_api_key', '');

        $tempFiles = [];
        $added     = 0;
        $statsBySource = ['cloud' => 0, 'drive' => 0, 'fallback' => 0, 'fail' => 0];

        try {
            foreach ($dl->order->items as $idx => $item) {
                $fileId = $item->photo_id;
                if (!$fileId) continue;

                $num      = str_pad((string)($idx + 1), 3, '0', STR_PAD_LEFT);
                $tempFile = $tempDir . DIRECTORY_SEPARATOR . "img_{$num}.bin";
                $ok       = false;
                $ct       = 'image/jpeg';
                $source   = 'fail';

                // Resolve the associated EventPhoto (order_items.photo_id == drive_file_id)
                $photo = EventPhoto::where('drive_file_id', $fileId)
                    ->orWhere('id', is_numeric($fileId) ? (int) $fileId : 0)
                    ->first();

                // 1) Cloud-native read — zero Drive quota, fastest path
                if ($photo) {
                    [$ok, $ct, $source] = $this->readFromCloud($photo, $tempFile, $storage);
                }

                // 2) Drive API with bearer auth
                if (!$ok) {
                    try {
                        $drive = new GoogleDriveService();
                        $url   = $drive->downloadUrl($fileId);
                        $res = Http::timeout(120)
                            ->withHeaders(['Authorization' => "Bearer {$driveApiKey}"])
                            ->sink($tempFile)->get($url);
                        if ($res->successful()) {
                            $ct = (string) $res->header('Content-Type', 'image/jpeg');
                            $ok = true;
                            $source = 'drive';
                        }
                    } catch (\Throwable $e) {
                        Log::warning("BuildOrderZipJob drive fail {$fileId}: " . $e->getMessage());
                    }
                }

                // 3) Unauthenticated public Drive URL (last resort)
                if (!$ok) {
                    try {
                        $res = Http::timeout(120)->sink($tempFile)
                            ->get("https://drive.google.com/uc?id={$fileId}&export=download");
                        if ($res->successful()) {
                            $ct = (string) $res->header('Content-Type', 'image/jpeg');
                            $ok = true;
                            $source = 'fallback';
                        }
                    } catch (\Throwable $e) {
                        Log::warning("BuildOrderZipJob fallback fail {$fileId}: " . $e->getMessage());
                    }
                }

                if (!$ok || !is_file($tempFile) || filesize($tempFile) === 0) {
                    @unlink($tempFile);
                    $statsBySource['fail']++;
                    continue;
                }

                $statsBySource[$source]++;

                $ext = match (true) {
                    str_contains($ct, 'png')  => 'png',
                    str_contains($ct, 'webp') => 'webp',
                    default                   => 'jpg',
                };
                $zip->addFile($tempFile, "{$brand}_{$event}_IMG-{$num}.{$ext}");
                $tempFiles[] = $tempFile;
                $added++;
            }
            $zip->close();
        } finally {
            foreach ($tempFiles as $tf) @unlink($tf);
            @rmdir($tempDir);
        }

        if ($added === 0) {
            @unlink($zipPath);
            Log::warning("BuildOrderZipJob: no files added for token {$this->downloadTokenId}");
            return;
        }

        // Stage the ZIP on the configured zip disk (R2 by default → CDN-backed,
        // customer downloads direct, zero egress cost from app server).
        $zipDisk = $storage->zipDisk();
        $publicPath = 'zips/' . basename($zipPath);
        $downloadUrl = '';

        try {
            $bytes = file_get_contents($zipPath);
            if ($zipDisk === StorageManager::DRIVER_R2 || $zipDisk === StorageManager::DRIVER_S3) {
                $storage->putToDriver($zipDisk, $publicPath, $bytes, ['visibility' => 'private']);
                $ttl = (int) AppSetting::get('storage_signed_url_ttl', 3600);
                $downloadUrl = $storage->signedUrl($publicPath, $zipDisk, $ttl);
            } else {
                Storage::disk('public')->put($publicPath, $bytes);
                $downloadUrl = Storage::disk('public')->url($publicPath);
            }
            @unlink($zipPath);
        } catch (\Throwable $e) {
            Log::warning('BuildOrderZipJob: failed to stage ZIP: ' . $e->getMessage());
            // fallback to local public
            try {
                Storage::disk('public')->put($publicPath, file_get_contents($zipPath));
                $downloadUrl = Storage::disk('public')->url($publicPath);
                @unlink($zipPath);
            } catch (\Throwable $e2) {
                Log::error('BuildOrderZipJob: emergency fallback failed: ' . $e2->getMessage());
                return;
            }
        }

        Log::info('BuildOrderZipJob: ZIP built', [
            'token'   => $this->downloadTokenId,
            'added'   => $added,
            'sources' => $statsBySource,
            'disk'    => $zipDisk,
        ]);

        // Email the customer — also queued
        try {
            $user = $dl->user;
            $email = $user->email ?? null;
            if ($email) {
                MailService::queue('downloadReady', [[
                    'order_number' => $dl->order->order_number ?? $dl->order_id,
                    'email'        => $email,
                    'name'         => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'ลูกค้า',
                ], $downloadUrl, [
                    'event_name'  => $dl->order->event->name ?? null,
                    'photo_count' => $added,
                    'expires_at'  => now()->addDays(7)->format('d/m/Y H:i'),
                ]]);
            }
        } catch (\Throwable $e) {
            Log::warning('BuildOrderZipJob: mail dispatch failed: ' . $e->getMessage());
        }
    }

    /**
     * Try to read a photo directly from its cloud storage_disk (or mirrors).
     *
     * @return array{0:bool,1:string,2:string}  [ok, content-type, source]
     */
    private function readFromCloud(EventPhoto $photo, string $destPath, StorageManager $storage): array
    {
        $candidates = array_filter(array_unique([
            $photo->storage_disk,
            ...((is_array($photo->storage_mirrors) ? $photo->storage_mirrors : [])),
        ]));

        foreach ($candidates as $disk) {
            if (!in_array($disk, ['r2', 's3', 'public'], true)) continue;
            if (!$storage->driverIsEnabled($disk)) continue;
            if (empty($photo->original_path)) continue;

            try {
                if (!Storage::disk($disk)->exists($photo->original_path)) continue;
                $stream = Storage::disk($disk)->readStream($photo->original_path);
                if (!$stream) continue;

                $out = fopen($destPath, 'wb');
                stream_copy_to_stream($stream, $out);
                fclose($out);
                if (is_resource($stream)) fclose($stream);

                return [true, $photo->mime_type ?: 'image/jpeg', 'cloud'];
            } catch (\Throwable $e) {
                Log::debug("BuildOrderZipJob: cloud read fail photo={$photo->id} disk={$disk}: " . $e->getMessage());
            }
        }

        return [false, 'image/jpeg', 'fail'];
    }

    private function cleanup(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') continue;
            @unlink($dir . DIRECTORY_SEPARATOR . $f);
        }
        @rmdir($dir);
    }
}
