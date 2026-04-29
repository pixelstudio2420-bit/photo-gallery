<?php

use App\Models\AppSetting;

if (!function_exists('setting')) {
    /**
     * Get or set an app setting
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return AppSetting::get($key, $default);
    }
}

if (!function_exists('format_thb')) {
    /**
     * Format number as Thai Baht
     */
    function format_thb(float $amount): string
    {
        return number_format($amount, 2) . ' THB';
    }
}

if (!function_exists('drive_thumbnail')) {
    /**
     * Get Google Drive thumbnail URL
     */
    function drive_thumbnail(string $fileId, int $size = 400): string
    {
        return "https://drive.google.com/thumbnail?id={$fileId}&sz=w{$size}";
    }
}

if (!function_exists('drive_download')) {
    /**
     * Get Google Drive download URL
     */
    function drive_download(string $fileId): string
    {
        return "https://drive.google.com/uc?id={$fileId}&export=download";
    }
}

if (!function_exists('cdn_asset')) {
    /**
     * Rewrite an asset URL to point at the CDN when CDN_BASE_URL is set.
     *
     * Why: in a 50k+ concurrent setup the PHP box cannot serve CSS/JS/images.
     * Point a CloudFront (or Cloudflare) distro at `APP_URL/build/*` and
     * `APP_URL/images/*`, set CDN_BASE_URL to the distro hostname, and every
     * asset() call in your Blade templates swings over to the edge for free.
     *
     * Usage in Blade:
     *     <img src="{{ cdn_asset('images/logo.png') }}">
     *     <link href="{{ cdn_asset(mix('css/app.css')) }}" rel="stylesheet">
     *
     * Returns the original asset() URL when CDN_BASE_URL is empty (dev).
     */
    function cdn_asset(string $path, ?bool $secure = null): string
    {
        $cdn = rtrim((string) config('app.cdn_base_url', env('CDN_BASE_URL', '')), '/');

        if ($cdn === '') {
            return asset($path, $secure);
        }

        // If the caller already passed a full URL (mix() / Vite output), swap host.
        if (preg_match('#^https?://#i', $path)) {
            $parsed = parse_url($path);
            $rest   = ($parsed['path'] ?? '')
                . (isset($parsed['query'])    ? '?' . $parsed['query']    : '')
                . (isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '');
            return $cdn . $rest;
        }

        return $cdn . '/' . ltrim($path, '/');
    }
}

if (!function_exists('status_badge')) {
    /**
     * Return Bootstrap badge class for status
     */
    function status_badge(string $status): string
    {
        return match ($status) {
            'active', 'paid', 'completed', 'successful' => 'success',
            'pending', 'processing' => 'warning',
            'failed', 'cancelled', 'blocked' => 'danger',
            'draft' => 'secondary',
            'refunded' => 'info',
            default => 'secondary',
        };
    }
}
