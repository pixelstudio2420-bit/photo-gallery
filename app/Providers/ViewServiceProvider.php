<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Share global data with all views.
        //
        // $siteName is the single source of truth for the site's display name
        // across admin, photographer, and public sections. Resolution order:
        //   1. AppSetting('site_name')   — DB-overridable (admin can change without touching .env)
        //   2. config('app.name')        — bootstrap config from .env APP_NAME
        //   3. 'Photo Gallery'           — last-resort literal so the page never renders empty
        View::composer('*', function ($view) {
            $siteName = AppSetting::get('site_name', '');
            if ($siteName === '' || $siteName === null) {
                $siteName = config('app.name', 'Photo Gallery');
            }
            $view->with('siteName', $siteName);
            $view->with('siteLogo', AppSetting::get('site_logo', ''));
        });

        // Share cart count with public layouts
        View::composer('layouts.app', function ($view) {
            $cartService = app(CartService::class);
            $view->with('cartCount', $cartService->count());
        });

        // Share admin data with admin layouts
        View::composer('layouts.admin', function ($view) {
            $view->with('adminUser', Auth::guard('admin')->user());
        });

        // Share photographer data
        View::composer('layouts.photographer', function ($view) {
            $view->with('photographerUser', Auth::guard('web')->user());
        });
    }
}
