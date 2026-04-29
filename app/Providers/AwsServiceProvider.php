<?php
namespace App\Providers;

use App\Services\Aws\CloudFrontService;
use App\Services\Aws\S3StorageService;
use App\Services\Aws\SesConfigService;
use Illuminate\Support\ServiceProvider;

class AwsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(S3StorageService::class);
        $this->app->singleton(CloudFrontService::class);
    }

    public function boot(): void
    {
        // Apply SES configuration from database settings if enabled
        try {
            if (app()->bound('db') && \Schema::hasTable('app_settings')) {
                SesConfigService::apply();
            }
        } catch (\Throwable $e) {
            // Silently skip if DB not available (migrations, etc.)
        }
    }
}
