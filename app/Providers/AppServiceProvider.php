<?php

namespace App\Providers;

use App\Observers\CacheInvalidationObserver;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\LineNotifyService::class);
        $this->app->singleton(\App\Services\MailService::class);
        $this->app->singleton(\App\Services\HoneypotService::class);
        $this->app->singleton(\App\Services\GeoAccessService::class);
        $this->app->singleton(\App\Services\ThreatIntelligenceService::class);
        $this->app->singleton(\App\Services\ProxyShieldService::class);
        $this->app->singleton(\App\Services\WatermarkService::class);
        $this->app->singleton(\App\Services\ImageProcessorService::class);
        $this->app->singleton(\App\Services\Menu\MenuRegistry::class);

        // Load config/menus/*.php into the `menus.*` config namespace.
        // Laravel auto-discovers config/*.php at boot, but does NOT
        // recurse into sub-directories. Doing it here means a future
        // dev can drop new files (config/menus/customer.php etc.)
        // without touching boot code.
        foreach (glob(config_path('menus' . DIRECTORY_SEPARATOR . '*.php')) as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $this->app['config']->set("menus.{$name}", require $file);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Storage config override — so R2/S3 credentials stored in the
        //    admin panel (AppSetting) flow into the filesystem disks BEFORE
        //    anything else asks for them.
        //
        //    Previously these values only landed in config when the
        //    R2/S3-Service was instantiated (e.g. via app()->make()). That
        //    worked for HTTP controllers that touched the service first —
        //    but the queue worker boots and calls Storage::disk('r2')->get()
        //    directly inside jobs (see ProcessUploadedPhotoJob::downloadOriginal).
        //    When the .env values are empty (which is the common setup now
        //    that creds live in the DB), Storage::disk('r2') ends up with
        //    empty bucket/key/endpoint and every read throws
        //    "Cannot read original file: r2:events/…".
        //
        //    Running the override here guarantees every request + every
        //    queued job sees the DB-backed config, identical to how .env
        //    would behave. Wrapped in try/catch because AppSetting reads
        //    during migration-phase boots would otherwise blow up.
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('app_settings')) {
                $this->applyDynamicStorageConfig();
                $this->applyDynamicOAuthConfig();
            }
        } catch (\Throwable) {
            // First-run / migration boot — settings table doesn't exist yet.
        }

        // Use Bootstrap 5 pagination globally (app uses Bootstrap, not Tailwind)
        Paginator::useBootstrapFive();

        // ── Collection::paginate() macro — allows paginating in-memory
        // Collections (e.g. grouped aggregates, merged results from multiple
        // sources). Eloquent Builders have this method natively, but
        // Collections don't — calling paginate() on a Collection previously
        // threw BadMethodCallException. This closes that gap for admin
        // operations that paginate merged/computed data.
        if (!Collection::hasMacro('paginate')) {
            Collection::macro('paginate', function (int $perPage = 15, ?int $page = null, array $options = []) {
                /** @var \Illuminate\Support\Collection $this */
                $page    = $page ?: (Paginator::resolveCurrentPage() ?: 1);
                $options = array_merge(['path' => Paginator::resolveCurrentPath()], $options);

                return new LengthAwarePaginator(
                    $this->forPage($page, $perPage)->values(),
                    $this->count(),
                    $perPage,
                    $page,
                    $options
                );
            });
        }

        // ── Cache invalidation — keep cached stats/pages fresh on model change.
        $observable = [
            \App\Models\Event::class,
            \App\Models\EventCategory::class,
            \App\Models\PhotographerProfile::class,
            \App\Models\User::class,
            \App\Models\Order::class,
            \App\Models\DigitalOrder::class,
            \App\Models\AppSetting::class,
        ];
        foreach ($observable as $class) {
            if (class_exists($class)) {
                $class::observe(CacheInvalidationObserver::class);
            }
        }

        // ── Storage-quota accounting — increment/decrement used_bytes as
        //    photos come and go. Registered here (not in a dedicated
        //    EventServiceProvider) to stay next to the cache observer
        //    registration above.
        if (class_exists(\App\Models\EventPhoto::class)) {
            \App\Models\EventPhoto::observe(\App\Observers\EventPhotoStorageObserver::class);
        }

        // ── Admin notification generator — single observer attached to
        //    every model that should ping the admin bell. The observer
        //    routes by class_basename internally; failures are swallowed
        //    so a bad notification can't break checkout/registration.
        $notifyTargets = [
            \App\Models\Order::class,
            \App\Models\User::class,
            \App\Models\PhotographerProfile::class,
            \App\Models\ContactMessage::class,
            \App\Models\Review::class,
            \App\Models\PaymentSlip::class,
        ];
        foreach ($notifyTargets as $class) {
            if (class_exists($class)) {
                $class::observe(\App\Observers\AdminNotificationObserver::class);
            }
        }

        // ── Order lifecycle hooks (referral rewards on paid/refunded).
        //    Kept separate from AdminNotificationObserver so the marketing
        //    side-effects don't share blast radius with admin notifications.
        if (class_exists(\App\Models\Order::class)) {
            \App\Models\Order::observe(\App\Observers\OrderObserver::class);
        }

        // SEO management — auto-history, auto-key, auto-cache-bust on every
        // save/delete of a per-page SEO override.
        if (class_exists(\App\Models\SeoPage::class)) {
            \App\Models\SeoPage::observe(\App\Observers\SeoPageObserver::class);
        }
    }

    /**
     * Merge AppSetting-stored storage credentials into the live config repo.
     *
     * Mirrors the per-service applyDynamicConfig() methods in
     * R2StorageService and S3StorageService so those runtime overrides are
     * guaranteed to be in place even when the Storage facade is touched
     * before either service is constructed (e.g. inside queued jobs).
     */
    private function applyDynamicStorageConfig(): void
    {
        $r2Map = [
            'r2_access_key_id'     => 'filesystems.disks.r2.key',
            'r2_secret_access_key' => 'filesystems.disks.r2.secret',
            'r2_bucket'            => 'filesystems.disks.r2.bucket',
            'r2_endpoint'          => 'filesystems.disks.r2.endpoint',
            'r2_public_url'        => 'filesystems.disks.r2.url',
        ];
        $s3Map = [
            's3_access_key_id'     => 'filesystems.disks.s3.key',
            's3_secret_access_key' => 'filesystems.disks.s3.secret',
            's3_bucket'            => 'filesystems.disks.s3.bucket',
            's3_region'            => 'filesystems.disks.s3.region',
            's3_url'               => 'filesystems.disks.s3.url',
        ];

        foreach (array_merge($r2Map, $s3Map) as $settingKey => $configPath) {
            try {
                $value = \App\Models\AppSetting::get($settingKey, '');
                if ($value !== '' && $value !== null) {
                    config([$configPath => $value]);
                }
            } catch (\Throwable) {
                // Individual setting read failure — skip, don't abort boot.
            }
        }
    }

    /**
     * Bridge OAuth credentials from admin AppSettings into Laravel's
     * `services.*` config namespace so Socialite + the hand-rolled LINE
     * flow can read them at runtime.
     *
     * Why this is necessary
     * ---------------------
     * The original config in config/services.php only sources values
     * from env(). Once the project moved to admin-managed credentials
     * (so an operator can paste a new Google Client ID in the UI without
     * editing .env and redeploying), the SocialAuthController had to
     * call its own `hydrateConfig()` before each redirect/callback.
     * That worked for the photographer-side OAuth flow, but anything
     * else touching `config('services.google.client_id')` directly —
     * the public-side AuthController, future API consumers, queue
     * jobs that resend a verification email — would still see the
     * empty env value and fail.
     *
     * Hydrating once at boot covers every code path uniformly.
     *
     * Settings keys (managed via /admin/settings/social-auth)
     *   - google_client_id          → services.google.client_id
     *   - google_client_secret      → services.google.client_secret
     *   - line_login_channel_id     → services.line.client_id
     *   - line_login_channel_secret → services.line.client_secret
     *
     * Redirect URIs are NOT mirrored here — they're route-derived
     * (route('photographer.auth.callback', ['provider' => …])) and
     * therefore belong to the controller that builds them.
     *
     * Empty values pass through unchanged, so an unset admin field
     * leaves the env-based default intact (good for local dev where
     * .env still works fine).
     */
    private function applyDynamicOAuthConfig(): void
    {
        $map = [
            'google_client_id'          => 'services.google.client_id',
            'google_client_secret'      => 'services.google.client_secret',
            'line_login_channel_id'     => 'services.line.client_id',
            'line_login_channel_secret' => 'services.line.client_secret',
        ];

        foreach ($map as $settingKey => $configPath) {
            try {
                $value = \App\Models\AppSetting::get($settingKey, '');
                if ($value !== '' && $value !== null) {
                    config([$configPath => $value]);
                }
            } catch (\Throwable) {
                // Schema not ready / row missing — fall back to env.
            }
        }
    }
}
