<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\HandlesIntegrations;
use App\Http\Controllers\Admin\Concerns\HandlesMedia;
use App\Http\Controllers\Admin\Concerns\HandlesQueueManagement;
use App\Http\Controllers\Admin\Concerns\HandlesStorage;
use App\Http\Controllers\Admin\Concerns\HandlesTwoFactor;
use App\Models\AppSetting;
use App\Models\EmailLog;
use App\Services\ActivityLogger;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\R2MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Domain-grouped method sets
    |--------------------------------------------------------------------------
    | Large method groups live in trait files under Admin\Concerns\*.
    | Everything routed to `Admin\SettingsController@*` still resolves —
    | PHP composes trait methods into the parent class transparently.
    |
    | Traits in play:
    |   HandlesTwoFactor          — 2FA setup / enable / verify / disable
    |   HandlesQueueManagement    — queue(), updateQueue(), processQueue(),
    |                               retryJob(), clearQueue()
    |   HandlesStorage            — storage(), updateStorage(), probeStorage(),
    |                               backup(), backupDatabase()
    |   HandlesIntegrations       — aws/cloudflare/analytics/googleDrive/
    |                               paymentGateways (and their update*)
    |   HandlesMedia              — seo/watermark/image/language/line/webhooks
    |
    | Remaining in this file: index/general/security/performance/retention/
    | sourceProtection/proxyShield/emailLogs/guide/version/reset helpers.
    */
    use HandlesTwoFactor;
    use HandlesQueueManagement;
    use HandlesStorage;
    use HandlesIntegrations;
    use HandlesMedia;

    public function index(Request $request)
    {
        if ($request->isMethod('post')) {
            return $this->saveSettings($request);
        }

        // Load all settings (cached, single DB hit per request)
        $settings = AppSetting::getAll();

        return view('admin.settings.index', compact('settings'));
    }

    private function saveSettings(Request $request): \Illuminate\Http\RedirectResponse
    {
        $items = $request->input('settings', []);

        // ── Site Logo: file upload + optional removal ──
        // Uploaded via R2MediaService under the canonical
        // `system/branding/user_0/{uuid}_{name}.{ext}` schema. The reserved
        // user_id 0 marks platform-owned assets (see R2MediaService::SYSTEM_USER_ID)
        // — they are exempt from the GDPR delete-by-user sweep.
        $media = app(R2MediaService::class);

        if ($request->hasFile('site_logo_file')) {
            $request->validate([
                'site_logo_file' => ['image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            ]);

            // Delete the previous logo from R2 first (CDN cache purged
            // async by the delete pipeline) so we don't orphan objects
            // every time marketing swaps the logo.
            $oldLogo = (string) AppSetting::get('site_logo', '');
            if ($oldLogo !== '') {
                try { $media->delete($oldLogo); } catch (\Throwable) {}
            }

            try {
                $upload = $media->uploadSystemBranding($request->file('site_logo_file'));
                $items['site_logo'] = $upload->key;
            } catch (InvalidMediaFileException $e) {
                return back()->withInput()->withErrors(['site_logo_file' => $e->getMessage()]);
            }
        } elseif ($request->boolean('remove_site_logo')) {
            $oldLogo = (string) AppSetting::get('site_logo', '');
            if ($oldLogo !== '') {
                try { $media->delete($oldLogo); } catch (\Throwable) {}
            }
            $items['site_logo'] = '';
        }

        $keys = array_keys($items);
        $oldSnapshot = $this->snapshotSettings($keys);

        // Bulk-write with a single cache flush instead of one flush per key.
        AppSetting::setMany($items);

        $this->logSettingsChange('settings.updated', $keys, $oldSnapshot);

        return back()->with('success', 'Settings saved successfully');
    }

    /**
     * Snapshot current values for a list of setting keys.
     * Used to capture the "before" state for audit logging.
     */
    protected function snapshotSettings(array $keys): array
    {
        $snapshot = [];
        foreach ($keys as $key) {
            $snapshot[$key] = AppSetting::get($key);
        }
        return $snapshot;
    }

    /**
     * Write an activity_logs entry for a settings change, logging only the
     * keys that actually changed. Values for keys matching /secret|token|key|password/i
     * are replaced with '***' so tokens/API keys never hit the audit table.
     */
    protected function logSettingsChange(string $action, array $keys, array $oldSnapshot, ?string $description = null): void
    {
        $changed = [];
        foreach ($keys as $key) {
            $old = $oldSnapshot[$key] ?? null;
            $new = AppSetting::get($key);
            if ((string) $old !== (string) $new) {
                $changed[$key] = ['old' => $old, 'new' => $new];
            }
        }
        if (empty($changed)) {
            return;
        }

        $sensitive = '/secret|token|key|password/i';
        $oldLog = [];
        $newLog = [];
        foreach ($changed as $key => $pair) {
            if (preg_match($sensitive, $key)) {
                $oldLog[$key] = '***';
                $newLog[$key] = '***';
            } else {
                $oldLog[$key] = $pair['old'];
                $newLog[$key] = $pair['new'];
            }
        }

        ActivityLogger::admin(
            action: $action,
            target: null,
            description: $description ?? ('แก้ไขตั้งค่า: ' . implode(', ', array_keys($changed))),
            oldValues: $oldLog,
            newValues: $newLog,
        );
    }

    public function general()
    {
        $settings = AppSetting::getAll();
        return view('admin.settings.index', compact('settings'));
    }

    public function updateGeneral(Request $request)
    {
        $items = $request->except(['_token', '_method']);
        $keys = array_keys($items);
        $oldSnapshot = $this->snapshotSettings($keys);

        // Bulk-write all posted keys with a single cache flush at the end.
        AppSetting::setMany($items);

        $this->logSettingsChange('settings.general_updated', $keys, $oldSnapshot);

        return back()->with('success', 'Settings saved');
    }

    public function security()
    {
        // Load security-related app settings (cached)
        $settings = AppSetting::getAll();

        // 2FA settings for the currently authenticated admin
        $admin = Auth::guard('admin')->user();
        $twoFa = null;
        if ($admin) {
            $twoFa = DB::table('admin_2fa')->where('admin_id', $admin->id)->first();
        }

        // Recent login attempts (last 100)
        $loginAttempts = DB::table('security_login_attempts')
            ->orderByDesc('attempted_at')
            ->limit(100)
            ->get();

        // Recent security log entries (last 100)
        $securityLogs = DB::table('security_logs')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('admin.settings.security', compact('settings', 'twoFa', 'loginAttempts', 'securityLogs'));
    }

    /**
     * Update idle auto-logout timeout settings.
     */
    public function updateIdleTimeout(Request $request)
    {
        $request->validate([
            'idle_timeout_admin'        => 'required|integer|min:0|max:480',
            'idle_timeout_photographer' => 'required|integer|min:0|max:480',
            'idle_warning_seconds'      => 'required|integer|min:10|max:300',
        ]);

        $keys = ['idle_timeout_admin', 'idle_timeout_photographer', 'idle_warning_seconds'];
        $oldSnapshot = $this->snapshotSettings($keys);

        // Use cached setMany() — single cache flush, model-level consistency.
        AppSetting::setMany([
            'idle_timeout_admin'        => (string) $request->input('idle_timeout_admin'),
            'idle_timeout_photographer' => (string) $request->input('idle_timeout_photographer'),
            'idle_warning_seconds'      => (string) $request->input('idle_warning_seconds'),
        ]);

        $this->logSettingsChange('settings.idle_timeout_updated', $keys, $oldSnapshot);

        return redirect()->route('admin.settings.security')
            ->with('success', 'บันทึกการตั้งค่าออกจากระบบอัตโนมัติสำเร็จ');
    }

    public function performance()
    {
        // Cache driver and basic stats
        $cacheDriver = config('cache.default');
        $cacheStats = ['driver' => $cacheDriver];

        // Try to get cache hit stats if Redis is available
        if ($cacheDriver === 'redis') {
            try {
                $info = Cache::getRedis()->info('stats');
                $cacheStats['hits']   = $info['keyspace_hits']   ?? 'N/A';
                $cacheStats['misses'] = $info['keyspace_misses'] ?? 'N/A';
            } catch (\Throwable $e) {
                $cacheStats['error'] = $e->getMessage();
            }
        }

        // Session stats
        $sessionDriver = config('session.driver');
        $sessionStats  = ['driver' => $sessionDriver];

        // Count active DB sessions if using database driver
        if ($sessionDriver === 'database') {
            try {
                $sessionStats['active'] = DB::table('sessions')
                    ->where('last_activity', '>=', now()->subMinutes(config('session.lifetime', 120))->timestamp())
                    ->count();
            } catch (\Throwable $e) {
                $sessionStats['active'] = 'N/A';
            }
        }

        // Performance-related app settings (cached all-keys; filtered in PHP)
        $settings = collect(AppSetting::getAll())
            ->filter(fn($_, $key) => str_contains($key, 'cache') || str_contains($key, 'performance') || str_contains($key, 'cdn'))
            ->all();

        // Sync queue stats
        $syncQueue = [
            'pending'   => DB::table('sync_queue')->where('status', 'pending')->count(),
            'running'   => DB::table('sync_queue')->where('status', 'running')->count(),
            'completed' => DB::table('sync_queue')->where('status', 'completed')->count(),
            'failed'    => DB::table('sync_queue')->where('status', 'failed')->count(),
        ];

        // Performance tuning settings with defaults
        $perfSettings = [
            'perf_lazy_loading'       => AppSetting::get('perf_lazy_loading', '1'),
            'perf_image_quality'      => AppSetting::get('perf_image_quality', '80'),
            'perf_cache_ttl_minutes'  => AppSetting::get('perf_cache_ttl_minutes', '60'),
            'perf_cache_grace_hours'  => AppSetting::get('perf_cache_grace_hours', '24'),
            'perf_gallery_page_size'  => AppSetting::get('perf_gallery_page_size', '50'),
            'perf_minify_html'        => AppSetting::get('perf_minify_html', '0'),
        ];

        return view('admin.settings.performance', compact('settings', 'cacheStats', 'sessionStats', 'syncQueue', 'perfSettings'));
    }

    /**
     * Clear application cache.
     */
    public function clearCache(Request $request)
    {
        $type = $request->input('type', 'all');

        try {
            switch ($type) {
                case 'app':
                    AppSetting::flushCache();
                    $this->clearFileCacheSafe();
                    $msg = 'ล้าง Application Cache สำเร็จ';
                    break;
                case 'views':
                    \Illuminate\Support\Facades\Artisan::call('view:clear');
                    $msg = 'ล้าง View Cache สำเร็จ';
                    break;
                case 'routes':
                    \Illuminate\Support\Facades\Artisan::call('route:clear');
                    $msg = 'ล้าง Route Cache สำเร็จ';
                    break;
                case 'drive':
                    // Clear Drive photo cache keys
                    $cleared = 0;
                    try {
                        $keys = DB::table('cache')->where('key', 'ilike', '%drive%')->orWhere('key', 'ilike', '%folder_files%');
                        $cleared = $keys->count();
                        $keys->delete();
                    } catch (\Throwable $e) {}
                    // Also clear file cache for drive
                    $this->clearFileCacheByPattern('drive');
                    $msg = "ล้าง Google Drive Cache สำเร็จ ({$cleared} รายการ)";
                    break;
                default: // all
                    AppSetting::flushCache();
                    $this->clearFileCacheSafe();
                    \Illuminate\Support\Facades\Artisan::call('view:clear');
                    $msg = 'ล้าง Cache ทั้งหมดสำเร็จ';
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        ActivityLogger::admin(
            action: 'settings.cache_cleared',
            target: null,
            description: "ล้าง cache ประเภท: {$type}",
            oldValues: null,
            newValues: ['cache_type' => $type],
        );

        return back()->with('success', $msg);
    }

    /**
     * Save performance settings.
     */
    public function updatePerformance(Request $request)
    {
        $keys = [
            'perf_lazy_loading',
            'perf_image_quality',
            'perf_cache_ttl_minutes',
            'perf_cache_grace_hours',
            'perf_gallery_page_size',
            'perf_minify_html',
        ];

        // Collect only keys actually present in the request, then bulk-write.
        $items = [];
        foreach ($keys as $key) {
            if ($request->has($key)) {
                $items[$key] = $request->input($key);
            }
        }

        if ($items) {
            $presentKeys = array_keys($items);
            $oldSnapshot = $this->snapshotSettings($presentKeys);
            AppSetting::setMany($items);
            $this->logSettingsChange('settings.performance_updated', $presentKeys, $oldSnapshot);
        }

        return back()->with('success', 'บันทึกการตั้งค่าประสิทธิภาพสำเร็จ');
    }

    /**
     * Retention Policy — auto-delete old events to reclaim storage.
     *
     * Paired command: `php artisan events:purge-expired` (scheduled daily 02:30).
     * Safe by default: `event_auto_delete_enabled` ships as "0" (OFF).
     */
    public function retention()
    {
        $settings = [
            'event_auto_delete_enabled'        => AppSetting::get('event_auto_delete_enabled', '0'),
            'event_default_retention_days'     => AppSetting::get('event_default_retention_days', '90'),
            'event_auto_delete_warn_days'      => AppSetting::get('event_auto_delete_warn_days', '7'),
            'event_auto_delete_skip_if_orders' => AppSetting::get('event_auto_delete_skip_if_orders', '1'),
            'event_auto_delete_from_field'     => AppSetting::get('event_auto_delete_from_field', 'shoot_date'),
            'event_auto_delete_purge_drive'    => AppSetting::get('event_auto_delete_purge_drive', '0'),
            'event_auto_delete_batch_limit'    => AppSetting::get('event_auto_delete_batch_limit', '50'),

            // ── Per-tier retention (layered over the above fallback) ──
            'retention_days_creator'           => AppSetting::get('retention_days_creator', '7'),
            'retention_days_seller'            => AppSetting::get('retention_days_seller', '30'),
            'retention_days_pro'               => AppSetting::get('retention_days_pro', '90'),
            'retention_warning_enabled'        => AppSetting::get('retention_warning_enabled', '1'),
            'retention_warning_days_ahead'     => AppSetting::get('retention_warning_days_ahead', '1'),
        ];

        // Stats: what does the current policy actually affect?
        $stats = [
            'total_events'  => \App\Models\Event::count(),
            'exempt'        => \App\Models\Event::where('auto_delete_exempt', true)->count(),
            'explicit_date' => \App\Models\Event::whereNotNull('auto_delete_at')->count(),
            'per_event_ttl' => \App\Models\Event::whereNotNull('retention_days_override')->count(),
        ];

        return view('admin.settings.retention', compact('settings', 'stats'));
    }

    public function updateRetention(Request $request)
    {
        $validated = $request->validate([
            'event_auto_delete_enabled'        => 'nullable|in:0,1',
            'event_default_retention_days'     => 'required|integer|min:1|max:3650',
            'event_auto_delete_warn_days'      => 'required|integer|min:0|max:90',
            'event_auto_delete_skip_if_orders' => 'nullable|in:0,1',
            'event_auto_delete_from_field'     => 'required|in:shoot_date,created_at',
            'event_auto_delete_purge_drive'    => 'nullable|in:0,1',
            'event_auto_delete_batch_limit'    => 'required|integer|min:1|max:10000',

            // Per-tier retention (days). 0 is allowed = "use global default".
            'retention_days_creator'           => 'required|integer|min:0|max:3650',
            'retention_days_seller'            => 'required|integer|min:0|max:3650',
            'retention_days_pro'               => 'required|integer|min:0|max:3650',
            'retention_warning_enabled'        => 'nullable|in:0,1',
            'retention_warning_days_ahead'     => 'required|integer|min:0|max:30',
        ]);

        $items = [
            'event_auto_delete_enabled'        => $request->input('event_auto_delete_enabled', '0'),
            'event_auto_delete_skip_if_orders' => $request->input('event_auto_delete_skip_if_orders', '0'),
            'event_auto_delete_purge_drive'    => $request->input('event_auto_delete_purge_drive', '0'),
            'event_default_retention_days'     => $validated['event_default_retention_days'],
            'event_auto_delete_warn_days'      => $validated['event_auto_delete_warn_days'],
            'event_auto_delete_from_field'     => $validated['event_auto_delete_from_field'],
            'event_auto_delete_batch_limit'    => $validated['event_auto_delete_batch_limit'],

            'retention_days_creator'           => $validated['retention_days_creator'],
            'retention_days_seller'            => $validated['retention_days_seller'],
            'retention_days_pro'               => $validated['retention_days_pro'],
            'retention_warning_enabled'        => $request->input('retention_warning_enabled', '0'),
            'retention_warning_days_ahead'     => $validated['retention_warning_days_ahead'],
        ];
        $keys = array_keys($items);
        $oldSnapshot = $this->snapshotSettings($keys);

        // Checkboxes: normalise missing → "0"; other keys from validated.
        AppSetting::setMany($items);

        $this->logSettingsChange('settings.retention_updated', $keys, $oldSnapshot);

        return back()->with('success', 'บันทึกการตั้งค่า retention policy สำเร็จ');
    }

    // ════════════════════════════════════════════════════════════════════
    // Photographer storage quotas (Part A — dedicated admin page)
    // ════════════════════════════════════════════════════════════════════

    public function photographerStorage()
    {
        $settings = [
            'photographer_quota_creator_gb'          => AppSetting::get('photographer_quota_creator_gb', '5'),
            'photographer_quota_seller_gb'           => AppSetting::get('photographer_quota_seller_gb', '50'),
            'photographer_quota_pro_gb'              => AppSetting::get('photographer_quota_pro_gb', '500'),
            'photographer_quota_enforcement_enabled' => AppSetting::get('photographer_quota_enforcement_enabled', '1'),
            'photographer_quota_warn_threshold_pct'  => AppSetting::get('photographer_quota_warn_threshold_pct', '80'),

            // ROI calculator values — displayed here for the admin to see &
            // tune alongside quotas (same page so the tier story stays coherent).
            'commission_pct_creator'         => AppSetting::get('commission_pct_creator', '30'),
            'commission_pct_seller'          => AppSetting::get('commission_pct_seller', '15'),
            'commission_pct_pro'             => AppSetting::get('commission_pct_pro', '8'),
            'platform_fee_per_photo_creator' => AppSetting::get('platform_fee_per_photo_creator', '10'),
            'platform_fee_per_photo_seller'  => AppSetting::get('platform_fee_per_photo_seller', '7'),
            'platform_fee_per_photo_pro'     => AppSetting::get('platform_fee_per_photo_pro', '5'),
            'subscription_price_seller'      => AppSetting::get('subscription_price_seller', '299'),
            'subscription_price_pro'         => AppSetting::get('subscription_price_pro', '999'),
        ];

        $snapshot = app(\App\Services\StorageQuotaService::class)->adminSnapshot();

        return view('admin.settings.photographer-storage', compact('settings', 'snapshot'));
    }

    public function updatePhotographerStorage(Request $request)
    {
        $validated = $request->validate([
            'photographer_quota_creator_gb'          => 'required|integer|min:0|max:100000',
            'photographer_quota_seller_gb'           => 'required|integer|min:0|max:1000000',
            'photographer_quota_pro_gb'              => 'required|integer|min:0|max:10000000',
            'photographer_quota_enforcement_enabled' => 'nullable|in:0,1',
            'photographer_quota_warn_threshold_pct'  => 'required|integer|min:0|max:100',

            'commission_pct_creator' => 'required|numeric|min:0|max:100',
            'commission_pct_seller'  => 'required|numeric|min:0|max:100',
            'commission_pct_pro'     => 'required|numeric|min:0|max:100',
            'platform_fee_per_photo_creator' => 'required|integer|min:0|max:1000',
            'platform_fee_per_photo_seller'  => 'required|integer|min:0|max:1000',
            'platform_fee_per_photo_pro'     => 'required|integer|min:0|max:1000',
            'subscription_price_seller'      => 'required|integer|min:0|max:1000000',
            'subscription_price_pro'         => 'required|integer|min:0|max:1000000',
        ]);

        $items = [
            'photographer_quota_creator_gb'          => $validated['photographer_quota_creator_gb'],
            'photographer_quota_seller_gb'           => $validated['photographer_quota_seller_gb'],
            'photographer_quota_pro_gb'              => $validated['photographer_quota_pro_gb'],
            'photographer_quota_enforcement_enabled' => $request->input('photographer_quota_enforcement_enabled', '0'),
            'photographer_quota_warn_threshold_pct'  => $validated['photographer_quota_warn_threshold_pct'],

            'commission_pct_creator' => $validated['commission_pct_creator'],
            'commission_pct_seller'  => $validated['commission_pct_seller'],
            'commission_pct_pro'     => $validated['commission_pct_pro'],
            'platform_fee_per_photo_creator' => $validated['platform_fee_per_photo_creator'],
            'platform_fee_per_photo_seller'  => $validated['platform_fee_per_photo_seller'],
            'platform_fee_per_photo_pro'     => $validated['platform_fee_per_photo_pro'],
            'subscription_price_seller'      => $validated['subscription_price_seller'],
            'subscription_price_pro'         => $validated['subscription_price_pro'],
        ];

        $keys = array_keys($items);
        $oldSnapshot = $this->snapshotSettings($keys);

        AppSetting::setMany($items);
        app(\App\Services\StorageQuotaService::class)->flushAdminCache();

        $this->logSettingsChange('settings.photographer_storage_updated', $keys, $oldSnapshot);

        return back()->with('success', 'บันทึกการตั้งค่าโควต้าช่างภาพสำเร็จ');
    }

    /** Fire the nightly recalc manually from the admin page. */
    public function recalcPhotographerStorage(Request $request)
    {
        \Illuminate\Support\Facades\Artisan::call('photographers:recalc-storage', ['--sync' => true]);
        app(\App\Services\StorageQuotaService::class)->flushAdminCache();

        return back()->with('success', 'คำนวณพื้นที่ใช้งานของช่างภาพใหม่เรียบร้อยแล้ว');
    }

    /**
     * Dry-run preview: returns a list of events that would be deleted if the
     * purge command ran right now with the current settings.
     */
    public function previewRetention(Request $request)
    {
        $fromField = (string) AppSetting::get('event_auto_delete_from_field', 'shoot_date');
        $defaultDays = (int) AppSetting::get('event_default_retention_days', 90);
        $skipOrders  = (bool) AppSetting::get('event_auto_delete_skip_if_orders', 1);

        $events = \App\Models\Event::where('auto_delete_exempt', false)
            ->orderBy('id')
            ->limit(500)
            ->get();

        $dueNow = [];
        foreach ($events as $event) {
            $eta = $event->effectiveDeleteAt();
            if (!$eta || $eta->isFuture()) continue;

            $hasOrders = $event->hasBlockingOrders();
            $dueNow[] = [
                'id'         => $event->id,
                'name'       => $event->name,
                'shoot_date' => optional($event->shoot_date)->format('Y-m-d'),
                'created_at' => optional($event->created_at)->format('Y-m-d'),
                'eta'        => $eta->format('Y-m-d'),
                'days_overdue' => (int) now()->diffInDays($eta, false) * -1,
                'has_orders' => $hasOrders,
                'would_skip' => $skipOrders && $hasOrders,
            ];
            if (count($dueNow) >= 100) break;
        }

        return response()->json([
            'from_field'   => $fromField,
            'default_days' => $defaultDays,
            'skip_orders'  => $skipOrders,
            'due_count'    => count($dueNow),
            'events'       => $dueNow,
        ]);
    }

    // Storage orchestration + backup → moved to Concerns\HandlesStorage trait.
    //   storage(), updateStorage(), probeStorage(), backup(), backupDatabase() — see trait.

    /**
     * Clear file-based cache without destroying sessions.
     */
    private function clearFileCacheSafe(): void
    {
        // Use Artisan cache:clear — safest method, only clears cache store
        try {
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
        } catch (\Throwable $e) {}
    }

    /**
     * Clear file cache entries matching a pattern.
     */
    private function clearFileCacheByPattern(string $pattern): void
    {
        $cachePath = storage_path('framework/cache/data');
        if (is_dir($cachePath)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cachePath, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $content = @file_get_contents($file->getRealPath());
                    if ($content && str_contains($content, $pattern)) {
                        @unlink($file->getRealPath());
                    }
                }
            }
        }
    }

    public function emailLogs(Request $request)
    {
        // Default stats in case table does not exist yet
        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        $logs  = collect();

        if (Schema::hasTable('email_logs')) {
            $query = EmailLog::query()->orderByDesc('created_at');

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('to_email', 'ilike', "%{$search}%")
                      ->orWhere('subject', 'ilike', "%{$search}%");
                });
            }

            $logs = $query->paginate(30)->withQueryString();

            $stats['sent']    = EmailLog::where('status', 'sent')->count();
            $stats['failed']  = EmailLog::where('status', 'failed')->count();
            $stats['skipped'] = EmailLog::where('status', 'skipped')->count();
        }

        $types = Schema::hasTable('email_logs')
            ? EmailLog::distinct()->pluck('type')->sort()->values()
            : collect();

        return view('admin.settings.email-logs', compact('logs', 'stats', 'types'));
    }

    // Watermark + Image + Language + LINE + Webhooks monitor
    //   → moved to Concerns\HandlesMedia trait.

    // =========================================================
    // Source Protection
    // =========================================================

    public function sourceProtection()
    {
        $keys = [
            'source_protection_enabled',
            'source_protection_level',
            'sp_disable_rightclick',
            'sp_disable_devtools',
            'sp_disable_viewsource',
            'sp_disable_drag',
            'sp_disable_copy',
            'sp_obfuscate_html',
            'sp_console_warning',
            'sp_apply_admin',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = AppSetting::get($key, '');
        }

        return view('admin.settings.source-protection', compact('settings'));
    }

    public function updateSourceProtection(Request $request)
    {
        $toggleKeys = [
            'source_protection_enabled',
            'sp_disable_rightclick',
            'sp_disable_devtools',
            'sp_disable_viewsource',
            'sp_disable_drag',
            'sp_disable_copy',
            'sp_obfuscate_html',
            'sp_console_warning',
            'sp_apply_admin',
        ];

        $allKeys = array_merge($toggleKeys, ['source_protection_level']);
        $oldSnapshot = $this->snapshotSettings($allKeys);

        foreach ($toggleKeys as $key) {
            AppSetting::set($key, $request->has($key) ? '1' : '0');
        }

        AppSetting::set('source_protection_level', $request->input('source_protection_level', 'standard'));

        $this->logSettingsChange('settings.source_protection_updated', $allKeys, $oldSnapshot);

        return back()->with('success', 'Source Protection settings saved.');
    }

    // =========================================================
    // Proxy Shield
    // =========================================================

    public function proxyShield()
    {
        $keys = [
            'proxy_shield_enabled',
            'proxy_detect_headers',
            'proxy_detect_tor',
            'proxy_detect_vpn',
            'proxy_detect_datacenter',
            'proxy_detect_anomalies',
            'proxy_client_detection',
            'proxy_action',
            'proxy_block_tor',
            'proxy_block_datacenter',
            'proxy_cache_ttl',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = AppSetting::get($key, '');
        }

        // Detection log (table may not exist yet)
        $detectionLogs = collect();
        $stats = ['total' => 0, 'blocked' => 0, 'monitored' => 0];
        try {
            if (Schema::hasTable('proxy_shield_log')) {
                $detectionLogs = DB::table('proxy_shield_log')
                    ->orderByDesc('created_at')
                    ->limit(50)
                    ->get();
                $stats['total']     = DB::table('proxy_shield_log')->count();
                $stats['blocked']   = DB::table('proxy_shield_log')->where('action', 'block')->count();
                $stats['monitored'] = DB::table('proxy_shield_log')->where('action', 'monitor')->count();
            }
        } catch (\Throwable $e) {
            // Table doesn't exist — leave defaults
        }

        // Whitelist (table may not exist yet)
        $whitelist = collect();
        try {
            if (Schema::hasTable('proxy_shield_whitelist')) {
                $whitelist = DB::table('proxy_shield_whitelist')->get();
            }
        } catch (\Throwable $e) {
            // Table doesn't exist — leave defaults
        }

        return view('admin.settings.proxy-shield', compact('settings', 'detectionLogs', 'stats', 'whitelist'));
    }

    public function updateProxyShield(Request $request)
    {
        $toggleKeys = [
            'proxy_shield_enabled',
            'proxy_detect_headers',
            'proxy_detect_tor',
            'proxy_detect_vpn',
            'proxy_detect_datacenter',
            'proxy_detect_anomalies',
            'proxy_client_detection',
            'proxy_block_tor',
            'proxy_block_datacenter',
        ];

        $allKeys = array_merge($toggleKeys, ['proxy_action', 'proxy_cache_ttl']);
        $oldSnapshot = $this->snapshotSettings($allKeys);

        foreach ($toggleKeys as $key) {
            AppSetting::set($key, $request->has($key) ? '1' : '0');
        }

        AppSetting::set('proxy_action', $request->input('proxy_action', 'monitor'));
        AppSetting::set('proxy_cache_ttl', $request->input('proxy_cache_ttl', '60'));

        $this->logSettingsChange('settings.proxy_shield_updated', $allKeys, $oldSnapshot);

        return back()->with('success', 'Proxy Shield settings saved.');
    }

    // Cloudflare + R2 → moved to Concerns\HandlesIntegrations trait.
    //   cloudflare(), updateCloudflare() — see trait.

    // Queue Management → moved to Concerns\HandlesQueueManagement trait.
    //   queue(), updateQueue() — see trait.

    // =========================================================
    // Version Info
    // =========================================================

    public function guide()
    {
        return view('admin.settings.guide');
    }

    public function version()
    {
        $systemInfo = [];

        try {
            $gdInfo    = function_exists('gd_info') ? gd_info() : [];
            $curlInfo  = function_exists('curl_version') ? curl_version() : [];
            $dbVersion = 'N/A';
            try {
                $dbVersion = DB::select('SELECT VERSION() as version')[0]->version ?? 'N/A';
            } catch (\Throwable $e) {}

            $systemInfo = [
                'app_name'            => config('app.name'),
                'app_version'         => config('app.version', '1.0.0'),
                'app_env'             => config('app.env'),
                'laravel_version'     => app()->version(),
                'php_version'         => PHP_VERSION,
                'server_software'     => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
                'os'                  => PHP_OS_FAMILY . ' (' . php_uname('r') . ')',
                'db_version'          => $dbVersion,
                'gd_version'          => $gdInfo['GD Version'] ?? 'N/A',
                'curl_version'        => $curlInfo['version'] ?? 'N/A',
                'memory_limit'        => ini_get('memory_limit'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size'       => ini_get('post_max_size'),
                'max_execution_time'  => ini_get('max_execution_time'),
            ];
        } catch (\Throwable $e) {
            // Continue with empty info
        }

        $versionHistory = collect();

        try {
            if (Schema::hasTable('version_history')) {
                $versionHistory = DB::table('version_history')
                    ->orderByDesc('created_at')
                    ->limit(50)
                    ->get();
            }
        } catch (\Throwable $e) {}

        return view('admin.settings.version', compact('systemInfo', 'versionHistory'));
    }

    public function recordVersion(Request $request)
    {
        $request->validate([
            'version'     => 'required|string|max:20',
            'title'       => 'required|string|max:255',
            'type'        => 'required|in:major,minor,patch,hotfix',
            'description' => 'nullable|string',
        ]);

        try {
            DB::table('version_history')->insert([
                'version'     => $request->input('version'),
                'title'       => $request->input('title'),
                'type'        => $request->input('type'),
                'description' => $request->input('description'),
                'released_by' => Auth::guard('admin')->id(),
                'created_at'  => now(),
            ]);

            return back()->with('success', 'บันทึก version ' . $request->input('version') . ' แล้ว');
        } catch (\Throwable $e) {
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    // =========================================================
    // System Reset
    // =========================================================

    public function reset()
    {
        return view('admin.settings.reset');
    }

    public function performReset(Request $request)
    {
        $action       = $request->input('action');
        $confirmation = $request->input('confirmation');

        $requiredConfirm = ($action === 'factory_reset') ? 'FACTORY_RESET' : 'RESET';

        if ($confirmation !== $requiredConfirm) {
            return back()->with('error', 'กรุณาพิมพ์ข้อความยืนยันให้ถูกต้อง');
        }

        // Hard guard: factory_reset is destructive + rotates admin credentials.
        // Block it in production unless an operator explicitly opts in via
        // env flag ALLOW_FACTORY_RESET=true — prevents an attacker who got
        // one admin session from nuking the whole tenant in one click.
        if ($action === 'factory_reset'
            && app()->environment('production')
            && !filter_var(env('ALLOW_FACTORY_RESET', false), FILTER_VALIDATE_BOOLEAN)) {
            ActivityLogger::admin(
                action: 'settings.factory_reset_blocked',
                target: null,
                description: 'Factory Reset ถูกบล็อกใน production (ALLOW_FACTORY_RESET ไม่ได้เปิด)',
            );
            return back()->with('error',
                'Factory Reset ถูกปิดใช้งานใน production เพื่อความปลอดภัย — ' .
                'หากจำเป็นจริง ๆ กรุณาตั้ง ALLOW_FACTORY_RESET=true ใน .env แล้วลองใหม่'
            );
        }

        // Audit BEFORE running reset — if the reset nukes activity_logs itself,
        // we still have the intent recorded on the row ahead of truncation.
        ActivityLogger::admin(
            action: 'settings.reset_performed',
            target: null,
            description: "สั่งรีเซ็ตระบบ: {$action}",
            oldValues: null,
            newValues: [
                'reset_action'    => $action,
                'confirmation_ok' => true,
            ],
        );

        // Driver-aware FK toggle — MySQL uses SET FOREIGN_KEY_CHECKS,
        // Postgres has no per-session toggle but TRUNCATE ... CASCADE handles
        // dependencies, and `session_replication_role = replica` defers FK
        // checks for the rest of the session.
        $driver = DB::connection()->getDriverName();

        $disableFk = function () use ($driver) {
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            } elseif ($driver === 'pgsql') {
                // Best-effort — requires superuser on some PG hosts; ignore failures
                try { DB::statement("SET session_replication_role = 'replica'"); } catch (\Throwable $e) {}
            }
        };

        $enableFk = function () use ($driver) {
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'pgsql') {
                try { DB::statement("SET session_replication_role = 'origin'"); } catch (\Throwable $e) {}
            }
        };

        // Truncate only if table exists — prevents 1146 errors on partial schemas.
        // On Postgres we explicitly use TRUNCATE ... CASCADE so dependent rows
        // (FK children) are wiped in one statement. MySQL relies on the FK
        // toggle above.
        $safeTruncate = function (array $tables) use (&$skipped, $driver) {
            foreach ($tables as $t) {
                try {
                    if (\Schema::hasTable($t)) {
                        if ($driver === 'pgsql') {
                            $quoted = '"' . str_replace('"', '""', $t) . '"';
                            DB::statement("TRUNCATE TABLE {$quoted} RESTART IDENTITY CASCADE");
                        } else {
                            DB::table($t)->truncate();
                        }
                    } else {
                        $skipped[] = $t;
                    }
                } catch (\Throwable $e) {
                    \Log::warning("Reset truncate failed for {$t}: " . $e->getMessage());
                    $skipped[] = $t;
                }
            }
        };
        $skipped = [];

        try {
            $disableFk();

            switch ($action) {
                case 'reset_orders':
                    $safeTruncate([
                        'order_items','download_tokens','payment_slips','payment_transactions',
                        'payment_logs','payment_refunds','payment_audit_log',
                        'photographer_payouts','orders',
                    ]);
                    $message = 'รีเซ็ตข้อมูล Orders & Revenue ทั้งหมดแล้ว';
                    break;

                case 'reset_photo_cache':
                    $safeTruncate(['event_photos_cache']);
                    $message = 'ล้าง Photo Cache ทั้งหมดแล้ว';
                    break;

                case 'reset_event_views':
                    if (\Schema::hasTable('event_events')) {
                        DB::table('event_events')->update(['view_count' => 0]);
                    }
                    $message = 'รีเซ็ต Event View Counts ทั้งหมดแล้ว';
                    break;

                case 'reset_notifications':
                    $safeTruncate(['admin_notifications','user_notifications']);
                    $message = 'ล้าง Notifications ทั้งหมดแล้ว';
                    break;

                case 'reset_security_logs':
                    $safeTruncate(['security_logs','security_login_attempts','security_rate_limits']);
                    $message = 'ล้าง Security Logs ทั้งหมดแล้ว';
                    break;

                case 'reset_all_stats':
                    if (\Schema::hasTable('event_events')) {
                        DB::table('event_events')->update(['view_count' => 0]);
                    }
                    $safeTruncate([
                        'admin_notifications','user_notifications',
                        'security_logs','security_login_attempts','security_rate_limits',
                        'payment_logs','payment_audit_log',
                    ]);
                    $message = 'รีเซ็ต Stats ทั้งหมดแล้ว (views, notifications, security, payment logs)';
                    break;

                case 'factory_reset':
                    // Truncate transactional data (everything a dashboard counts)
                    $safeTruncate([
                        // Photo orders
                        'order_items','download_tokens','payment_slips','payment_transactions',
                        'payment_logs','payment_refunds','payment_audit_log',
                        'photographer_payouts','orders',
                        // Digital orders
                        'digital_orders','digital_download_tokens',
                        // Coupon / commission / chat / cache
                        'coupon_usage','chat_messages','chat_conversations',
                        'event_photos_cache',
                        // Notifications & security
                        'admin_notifications','user_notifications',
                        'security_logs','security_login_attempts','security_rate_limits',
                    ]);

                    // Reset aggregate counters on reference tables (don't truncate these — they hold config)
                    if (\Schema::hasTable('event_events')) {
                        DB::table('event_events')->update(['view_count' => 0]);
                    }
                    if (\Schema::hasTable('digital_products')) {
                        DB::table('digital_products')->update(['total_sales' => 0, 'total_revenue' => 0]);
                    }
                    if (\Schema::hasTable('photographer_profiles')) {
                        $cols = \Schema::getColumnListing('photographer_profiles');
                        $reset = [];
                        foreach (['total_sales','total_revenue','total_orders','total_earned','rating_avg','rating_count'] as $c) {
                            if (in_array($c, $cols, true)) $reset[$c] = 0;
                        }
                        if ($reset) DB::table('photographer_profiles')->update($reset);
                    }

                    // Rotate the CURRENT admin's password to a freshly-generated
                    // random string and require change-on-next-login. Previously
                    // this reset EVERY admin's password to the same hardcoded
                    // 'Admin@1234' — effectively a backdoor. Now:
                    //   • Only the admin who triggered the reset is rotated
                    //   • Password is 20 chars, cryptographically random
                    //   • Shown once in the success message (no storage, no log)
                    //   • `must_change_password=1` is flagged if the column exists
                    //     so the login flow can force a rotation next visit
                    $newAdminPassword = null;
                    $adminTable = \Schema::hasTable('auth_admins') ? 'auth_admins' : (\Schema::hasTable('admins') ? 'admins' : null);
                    if ($adminTable) {
                        $currentAdmin = Auth::guard('admin')->user();
                        if ($currentAdmin) {
                            $newAdminPassword = Str::random(20);
                            $cols = \Schema::getColumnListing($adminTable);
                            // Support both schemas: some deployments use `password_hash`
                            // (matches getAuthPassword() override), others use `password`.
                            $pwCol = in_array('password_hash', $cols, true) ? 'password_hash'
                                   : (in_array('password', $cols, true) ? 'password' : null);
                            if ($pwCol) {
                                $update = [$pwCol => Hash::make($newAdminPassword)];
                                if (in_array('must_change_password', $cols, true)) {
                                    $update['must_change_password'] = 1;
                                }
                                if (in_array('password_changed_at', $cols, true)) {
                                    $update['password_changed_at'] = now();
                                }
                                DB::table($adminTable)
                                    ->where('id', $currentAdmin->id)
                                    ->update($update);
                            } else {
                                $newAdminPassword = null; // schema mismatch — bail
                            }
                        }
                    }

                    // Clear any cached dashboard stats
                    try { \Cache::flush(); } catch (\Throwable $e) {}

                    $message = 'Factory Reset เสร็จสิ้น — ข้อมูล Dashboard ทั้งหมดถูกรีเซ็ตเป็น 0 แล้ว';
                    if ($newAdminPassword) {
                        $message .= ' | รหัสผ่านใหม่ของคุณ (บันทึกไว้ทันที — จะไม่แสดงอีก): ' . $newAdminPassword;
                    }
                    break;

                default:
                    $enableFk();
                    return back()->with('error', 'ไม่พบ action ที่ระบุ');
            }

            if (!empty($skipped)) {
                $message .= ' (ข้าม table ที่ไม่มีอยู่: ' . implode(', ', $skipped) . ')';
            }

            $enableFk();
            return back()->with('success', $message);

        } catch (\Throwable $e) {
            try { $enableFk(); } catch (\Throwable $ex) {}
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    // Backup + database dump → moved to Concerns\HandlesStorage trait.
    //   backup(), backupDatabase() — see trait.

    // 2FA Management → moved to Concerns\HandlesTwoFactor trait.
    //   twoFactor(), enable2fa(), verify2fa(), disable2fa() — see trait.

    // Queue processing → moved to Concerns\HandlesQueueManagement trait.
    //   processQueue(), retryJob(), clearQueue() — see trait.

    // AWS / Analytics / Google Drive / Payment Gateways
    //   → moved to Concerns\HandlesIntegrations trait.

    // ═════════════════════════════════════════════════════════════════
    //  Image Moderation Settings
    // ═════════════════════════════════════════════════════════════════

    public function moderation()
    {
        $keys = [
            'moderation_enabled',
            'moderation_auto_reject_threshold',
            'moderation_flag_threshold',
            'moderation_min_confidence',
            'moderation_categories',
            'moderation_skip_verified_photographers',
            'moderation_notify_uploader',
            'moderation_client_prefilter',
        ];

        $settings = [];
        foreach ($keys as $k) {
            $settings[$k] = \App\Models\AppSetting::get($k, null);
        }

        // Provide the canonical category list so the view can render checkboxes.
        $allCategories    = \App\Services\ImageModerationService::DEFAULT_CATEGORIES;
        $enabledCategories = app(\App\Services\ImageModerationService::class)->enabledCategories();

        // Quick KPIs to show on the page: how the rules have actually been
        // applying lately. Pulled cheaply with a single aggregated query.
        $stats = [
            'pending'  => \App\Models\EventPhoto::where('moderation_status', 'pending')->count(),
            'flagged'  => \App\Models\EventPhoto::where('moderation_status', 'flagged')->count(),
            'rejected' => \App\Models\EventPhoto::where('moderation_status', 'rejected')->count(),
        ];

        return view('admin.settings.moderation', compact('settings', 'allCategories', 'enabledCategories', 'stats'));
    }

    public function updateModeration(Request $request)
    {
        $validated = $request->validate([
            'moderation_enabled'                      => 'nullable|in:0,1',
            'moderation_auto_reject_threshold'        => 'required|numeric|min:50|max:100',
            'moderation_flag_threshold'               => 'required|numeric|min:10|max:99',
            'moderation_min_confidence'               => 'nullable|numeric|min:0|max:99',
            'moderation_categories'                   => 'nullable|array',
            'moderation_categories.*'                 => 'string',
            'moderation_skip_verified_photographers'  => 'nullable|in:0,1',
            'moderation_notify_uploader'              => 'nullable|in:0,1',
            'moderation_client_prefilter'             => 'nullable|in:0,1',
        ]);

        // Sanity: flag threshold MUST be lower than reject threshold, else the
        // flagged bucket becomes impossible and everything auto-rejects.
        if ((float) $validated['moderation_flag_threshold'] >= (float) $validated['moderation_auto_reject_threshold']) {
            return back()->withInput()->withErrors([
                'moderation_flag_threshold' => 'คะแนนติดธงต้องต่ำกว่าคะแนนปฏิเสธอัตโนมัติ',
            ]);
        }

        \App\Models\AppSetting::set('moderation_enabled',                     $request->input('moderation_enabled') === '1' ? '1' : '0');
        \App\Models\AppSetting::set('moderation_auto_reject_threshold',       (string) $validated['moderation_auto_reject_threshold']);
        \App\Models\AppSetting::set('moderation_flag_threshold',              (string) $validated['moderation_flag_threshold']);
        \App\Models\AppSetting::set('moderation_min_confidence',              (string) ($validated['moderation_min_confidence'] ?? '40'));
        \App\Models\AppSetting::set('moderation_skip_verified_photographers', $request->input('moderation_skip_verified_photographers') === '1' ? '1' : '0');
        \App\Models\AppSetting::set('moderation_notify_uploader',             $request->input('moderation_notify_uploader') === '1' ? '1' : '0');
        \App\Models\AppSetting::set('moderation_client_prefilter',            $request->input('moderation_client_prefilter') === '1' ? '1' : '0');

        $categories = $validated['moderation_categories'] ?? \App\Services\ImageModerationService::DEFAULT_CATEGORIES;
        \App\Models\AppSetting::set('moderation_categories', json_encode(array_values($categories), JSON_UNESCAPED_UNICODE));

        return back()->with('success', 'บันทึกการตั้งค่าการตรวจสอบภาพเรียบร้อย');
    }

    // ═════════════════════════════════════════════════════════════════
    //  Face Search — Cost / Abuse Controls
    //
    //  Public face-search burns real AWS Rekognition $. Without caps a
    //  single attacker can drive the monthly bill into thousands (fallback
    //  path is 1 API call per event photo). These routes expose:
    //    - budget toggles (kill switch, per-event/user/IP daily caps,
    //      monthly global ceiling, fallback photo cap, cache TTL)
    //    - a live dashboard (usage counts, denied attempts, top events/IPs,
    //      rough USD cost estimate) — see faceSearchUsage().
    // ═════════════════════════════════════════════════════════════════

    public function faceSearch()
    {
        $keys = [
            'face_search_enabled_globally',
            'face_search_daily_cap_per_event',
            'face_search_daily_cap_per_user',
            'face_search_daily_cap_per_ip',
            'face_search_monthly_global_cap',
            'face_search_fallback_max_photos',
            'face_search_cache_ttl_minutes',
        ];

        $settings = [];
        foreach ($keys as $k) {
            $settings[$k] = AppSetting::get($k, null);
        }

        return view('admin.settings.face-search', compact('settings'));
    }

    public function updateFaceSearch(Request $request)
    {
        $validated = $request->validate([
            'face_search_enabled_globally'    => 'nullable|in:0,1',
            // 0 = disabled; otherwise a positive count.
            'face_search_daily_cap_per_event' => 'required|integer|min:0|max:100000',
            'face_search_daily_cap_per_user'  => 'required|integer|min:0|max:100000',
            'face_search_daily_cap_per_ip'    => 'required|integer|min:0|max:100000',
            'face_search_monthly_global_cap'  => 'required|integer|min:0|max:10000000',
            // Guardrail: even with admin's blessing, cap the fallback photo
            // count. 500 would still be ~$0.50/request which we'd rather not.
            'face_search_fallback_max_photos' => 'required|integer|min:0|max:500',
            'face_search_cache_ttl_minutes'   => 'required|integer|min:0|max:1440',
        ]);

        $items = [
            'face_search_enabled_globally'    => $request->input('face_search_enabled_globally') === '1' ? '1' : '0',
            'face_search_daily_cap_per_event' => (string) $validated['face_search_daily_cap_per_event'],
            'face_search_daily_cap_per_user'  => (string) $validated['face_search_daily_cap_per_user'],
            'face_search_daily_cap_per_ip'    => (string) $validated['face_search_daily_cap_per_ip'],
            'face_search_monthly_global_cap'  => (string) $validated['face_search_monthly_global_cap'],
            'face_search_fallback_max_photos' => (string) $validated['face_search_fallback_max_photos'],
            'face_search_cache_ttl_minutes'   => (string) $validated['face_search_cache_ttl_minutes'],
        ];

        $keys = array_keys($items);
        $oldSnapshot = $this->snapshotSettings($keys);
        AppSetting::setMany($items);
        $this->logSettingsChange('settings.face_search_updated', $keys, $oldSnapshot);

        // Also invalidate the 60s snapshot cache so the dashboard reflects
        // the new caps immediately — otherwise the admin would see stale
        // numbers for up to a minute after saving.
        Cache::forget('face_search_budget_snapshot');

        return back()->with('success', 'บันทึกการตั้งค่าระบบค้นหาด้วยใบหน้าสำเร็จ');
    }

    public function faceSearchUsage(Request $request)
    {
        $budget = app(\App\Services\FaceSearchBudget::class);
        $snapshot = $budget->snapshot();

        // Hydrate event_id → event name/slug for the top-events table.
        $eventIds = collect($snapshot['top_events'] ?? [])->pluck('event_id')->filter()->unique();
        $events = $eventIds->isNotEmpty()
            ? \App\Models\Event::whereIn('id', $eventIds)->get()->keyBy('id')
            : collect();

        // Pull the 50 most-recent rows — small enough to render inline
        // without pagination. The activity log pattern elsewhere uses
        // limit(100) so 50 here keeps the admin page snappy.
        $recent = \App\Models\FaceSearchLog::orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.settings.face-search-usage', compact('snapshot', 'events', 'recent'));
    }
}
