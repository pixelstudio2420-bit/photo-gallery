<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\AppSetting;

class SourceProtection
{
    /**
     * Paths that require extra no-cache headers.
     */
    private array $sensitivePrefixes = ['/admin/', '/api/'];

    public function handle(Request $request, Closure $next): mixed
    {
        $enabled = AppSetting::get('source_protection_enabled', '0') === '1';

        if ($enabled) {
            $config = [
                'enabled'              => true,
                'sp_disable_rightclick' => AppSetting::get('sp_disable_rightclick', '0') === '1',
                'sp_disable_devtools'  => AppSetting::get('sp_disable_devtools', '0') === '1',
                'sp_disable_viewsource' => AppSetting::get('sp_disable_viewsource', '0') === '1',
                'sp_disable_copy'      => AppSetting::get('sp_disable_copy', '0') === '1',
                'sp_disable_drag'      => AppSetting::get('sp_disable_drag', '0') === '1',
                'sp_console_warning'   => AppSetting::get('sp_console_warning', '0') === '1',
            ];

            view()->share('sourceProtection', $config);
        } else {
            view()->share('sourceProtection', ['enabled' => false]);
        }

        $response = $next($request);

        // Add no-cache headers for sensitive paths
        if ($enabled && $this->isSensitivePath($request)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    /**
     * Determine if the current request path is considered sensitive.
     */
    private function isSensitivePath(Request $request): bool
    {
        $uri = '/' . ltrim($request->getPathInfo(), '/');

        foreach ($this->sensitivePrefixes as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
