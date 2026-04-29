<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Cache Invalidation Observer
 * ===========================
 * Keeps the app-level cache fresh when models change. Without this,
 * edits to events / photographers / categories wouldn't appear on the
 * public site until the cache TTL expires (up to 10 minutes).
 *
 * Attached per-model in AppServiceProvider.
 *
 * Design:
 * - Cheap: we call Cache::forget on a short list of well-known keys.
 * - Safe : unknown models silently do nothing.
 * - For page-level invalidation (Cloudflare CDN), pair this with a
 *   Cloudflare API call in a queued job — see SCALING.md.
 */
class CacheInvalidationObserver
{
    public function saved(Model $model): void    { $this->invalidate($model); }
    public function deleted(Model $model): void  { $this->invalidate($model); }
    public function restored(Model $model): void { $this->invalidate($model); }

    private function invalidate(Model $model): void
    {
        try {
            $class = class_basename($model);

            $keys = match ($class) {
                'Event' => [
                    'public.home.featured_events',
                    'public.home.latest_events',
                    'public.home.categories',
                    'public.events.stats',
                    'public.events.categories_with_count',
                    'admin.dashboard.core_stats',
                    'admin.events.stats',
                    'admin.events.total_revenue',
                ],
                'PhotographerProfile' => [
                    'public.home.photographers',
                    // Per-photographer stats (can't forget all — let TTL handle)
                    'public.photographer.' . ($model->user_id ?? 0) . '.stats',
                ],
                'EventCategory' => [
                    'public.home.categories',
                    'public.events.categories_with_count',
                ],
                'User' => [
                    'admin.users.stats',
                ],
                'Order' => [
                    'admin.dashboard.core_stats',
                    'admin.users.total_revenue',
                    'admin.events.total_revenue',
                ],
                'DigitalOrder' => [
                    'admin.dashboard.core_stats',
                ],
                'AppSetting' => [
                    'app_settings_all',
                    'setting.platform_commission',
                ],
                default => [],
            };

            foreach ($keys as $k) {
                Cache::forget($k);
            }
        } catch (\Throwable $e) {
            // Cache hiccup — never let it bubble up into the save() call.
        }
    }
}
