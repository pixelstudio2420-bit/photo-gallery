<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventPhotoCache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class EventService
{
    private GoogleDriveService $driveService;

    public function __construct(GoogleDriveService $driveService)
    {
        $this->driveService = $driveService;
    }

    /**
     * Get paginated events with filters
     */
    public function getEvents(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = Event::with('category')
            ->whereIn('status', ['active', 'published']);

        if (!empty($filters['category'])) {
            $query->where('category_id', $filters['category']);
        }

        if (!empty($filters['q'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'ilike', "%{$filters['q']}%")
                  ->orWhere('description', 'ilike', "%{$filters['q']}%");
            });
        }

        if (!empty($filters['photographer_id'])) {
            $query->where('photographer_id', $filters['photographer_id']);
        }

        $sort = $filters['sort'] ?? 'latest';
        match ($sort) {
            'name'    => $query->orderBy('name'),
            'popular' => $query->orderByDesc('view_count'),
            'oldest'  => $query->orderBy('shoot_date'),
            default   => $query->orderByDesc('shoot_date'),
        };

        return $query->paginate($perPage);
    }

    /**
     * Get featured events
     */
    public function getFeaturedEvents(int $limit = 8): \Illuminate\Database\Eloquent\Collection
    {
        return Event::with('category')
            ->whereIn('status', ['active', 'published'])
            ->where('visibility', 'public')
            ->orderByDesc('shoot_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Get latest events
     */
    public function getLatestEvents(int $limit = 8): \Illuminate\Database\Eloquent\Collection
    {
        return Event::with('category')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get event photos from cache or Google Drive
     */
    public function getEventPhotos(Event $event): array
    {
        if (!$event->drive_folder_id) {
            return [];
        }

        // Try cache first
        $cached = EventPhotoCache::where('event_id', $event->id)
            ->orderBy('file_name')
            ->get();

        if ($cached->isNotEmpty()) {
            return $cached->toArray();
        }

        // Fetch from Drive and cache
        $files = $this->driveService->listFolderFiles($event->drive_folder_id);

        foreach ($files as $file) {
            EventPhotoCache::updateOrCreate(
                ['event_id' => $event->id, 'file_id' => $file['id']],
                [
                    'file_name'      => $file['name'] ?? null,
                    'mime_type'      => $file['mimeType'] ?? null,
                    'thumbnail_link' => $file['thumbnailLink'] ?? null,
                    'web_view_link'  => $file['webViewLink'] ?? null,
                    'synced_at'      => now(),
                ]
            );
        }

        return $files;
    }

    /**
     * Sync event photos from Google Drive
     */
    public function syncPhotos(Event $event): int
    {
        if (!$event->drive_folder_id) {
            return 0;
        }

        return $this->driveService->syncToCache($event->id, $event->drive_folder_id);
    }

    /**
     * Get categories with event counts
     */
    public function getCategoriesWithCounts(): \Illuminate\Database\Eloquent\Collection
    {
        return EventCategory::withCount(['events' => function ($q) {
            $q->where('status', 'active');
        }])->orderBy('name')->get();
    }

    /**
     * Increment event view count
     */
    public function incrementViewCount(Event $event): void
    {
        $event->increment('view_count');
    }
}
