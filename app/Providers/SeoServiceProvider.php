<?php

namespace App\Providers;

use App\Services\SeoService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * SeoServiceProvider
 *
 * Registers the SeoService as a singleton and shares it with all Blade views
 * under the variable name `$seo`.
 */
class SeoServiceProvider extends ServiceProvider
{
    /**
     * Register the SeoService singleton into the service container.
     */
    public function register(): void
    {
        $this->app->singleton(SeoService::class, function () {
            return new SeoService();
        });

        // Also bind a short alias so callers can resolve it as 'seo'
        $this->app->alias(SeoService::class, 'seo');
    }

    /**
     * Bootstrap the service: share the singleton with every Blade view.
     *
     * Using a View::composer with '*' ensures the singleton is only resolved
     * when a view is actually rendered (lazy), avoiding unnecessary work on
     * console commands and API responses that never render a view.
     */
    public function boot(): void
    {
        View::share('seo', $this->app->make(SeoService::class));
    }
}
