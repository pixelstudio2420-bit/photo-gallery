<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * StorageStatsService — analyzes storage usage across the app.
 */
class StorageStatsService
{
    /**
     * Aggregated overview of storage usage.
     */
    public function overview(): array
    {
        $publicRoot = storage_path('app/public');

        $totalSize = $this->dirSize($publicRoot);

        $byType = [
            'photos' => $this->dirSize($publicRoot . DIRECTORY_SEPARATOR . 'photos'),
            'slips'  => $this->dirSize($publicRoot . DIRECTORY_SEPARATOR . 'slips'),
            'blog'   => $this->dirSize($publicRoot . DIRECTORY_SEPARATOR . 'blog'),
            'chat'   => $this->dirSize($publicRoot . DIRECTORY_SEPARATOR . 'chat'),
        ];

        // Remaining = total - known categories (but never negative)
        $other = max(0, $totalSize - array_sum($byType));
        $byType['other'] = $other;

        $eventCacheSize = 0;
        try {
            if (Schema::hasTable('event_photos_cache') && Schema::hasColumn('event_photos_cache', 'file_size')) {
                $eventCacheSize = (int) DB::table('event_photos_cache')->sum('file_size');
            } elseif (Schema::hasTable('event_photos_cache') && Schema::hasColumn('event_photos_cache', 'cached_size')) {
                $eventCacheSize = (int) DB::table('event_photos_cache')->sum('cached_size');
            }
        } catch (\Throwable $e) {
            Log::warning('StorageStatsService: event cache size failed: ' . $e->getMessage());
        }

        $driveSyncedCount = 0;
        try {
            if (Schema::hasTable('event_events') && Schema::hasColumn('event_events', 'drive_sync_status')) {
                $driveSyncedCount = (int) DB::table('event_events')
                    ->where('drive_sync_status', 'synced')
                    ->count();
            } else {
                // Fallback: count events with a drive_folder_id set
                if (Schema::hasColumn('event_events', 'drive_folder_id')) {
                    $driveSyncedCount = (int) DB::table('event_events')
                        ->whereNotNull('drive_folder_id')
                        ->count();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('StorageStatsService: drive synced count failed: ' . $e->getMessage());
        }

        $cdnEnabled = (string) AppSetting::get('cloudfront_enabled', '0') === '1';

        return [
            'total_size'          => $totalSize,
            'by_type'             => $byType,
            'event_cache_size'    => $eventCacheSize,
            'drive_synced_count'  => $driveSyncedCount,
            'cdn_enabled'         => $cdnEnabled,
        ];
    }

    /**
     * Top photographers by total storage usage.
     * Uses event_photos.file_size aggregation per photographer_id.
     */
    public function byPhotographer(int $limit = 10): array
    {
        try {
            if (!Schema::hasTable('event_photos') || !Schema::hasTable('event_events')) {
                return [];
            }

            $rows = DB::table('event_photos as p')
                ->join('event_events as e', 'e.id', '=', 'p.event_id')
                ->select(
                    'e.photographer_id',
                    DB::raw('SUM(COALESCE(p.file_size, 0)) as total_size'),
                    DB::raw('COUNT(p.id) as photo_count')
                )
                ->groupBy('e.photographer_id')
                ->orderByDesc('total_size')
                ->limit($limit)
                ->get();

            $ids = $rows->pluck('photographer_id')->filter()->unique()->all();
            $users = User::whereIn('id', $ids)->get()->keyBy('id');

            return $rows->map(function ($r) use ($users) {
                $user = $users->get($r->photographer_id);
                return [
                    'photographer_id' => (int) $r->photographer_id,
                    'name'            => $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : 'Unknown',
                    'email'           => $user?->email,
                    'total_size'      => (int) $r->total_size,
                    'photo_count'     => (int) $r->photo_count,
                ];
            })->all();
        } catch (\Throwable $e) {
            Log::warning('StorageStatsService::byPhotographer failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Recursive directory size in bytes.
     */
    protected function dirSize(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += (int) $file->getSize();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('StorageStatsService::dirSize failed for ' . $path . ': ' . $e->getMessage());
        }

        return $size;
    }
}
