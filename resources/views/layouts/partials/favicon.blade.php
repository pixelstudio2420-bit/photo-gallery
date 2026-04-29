{{-- ════════════════════════════════════════════════════════════════════
     Favicon link tags — resolves the URL of the user-uploaded favicon
     stored in AppSetting('seo_favicon').

     Resolution order
     ----------------
       1. R2 (if configured + key exists) — uses the bucket's public URL
          (R2.dev subdomain or custom domain) via R2MediaService.
       2. Local public disk — for non-R2 deploys, asset('storage/{key}')
          works once `php artisan storage:link` has been run.
       3. /favicon.ico — fallback to the static file in /public so the
          browser still gets *something* even when no upload exists.

     Why a partial instead of inline in each layout
     ----------------------------------------------
       Three layouts (app/admin/photographer) all need these tags. A
       partial keeps the favicon resolution logic in one place — e.g.
       if we add an Apple Touch Icon variant later, we update one file.

     Caching
     -------
       AppSetting::get() is already memoised inside the AppSetting
       model, so this lookup costs essentially nothing. The browser
       caches the favicon binary itself for a long time (default 1 day,
       can be tuned via web-server headers if needed).
═══════════════════════════════════════════════════════════════════════ --}}
@php
    // Fallback path that lives in /public — the framework default.
    $faviconUrl = asset('favicon.ico');

    $key = \App\Models\AppSetting::get('seo_favicon');
    if ($key) {
        // Prefer R2's public URL when configured. R2MediaService throws
        // a StorageNotConfiguredException if R2 creds aren't set up; in
        // that case we silently fall through to the local-disk path.
        try {
            $r2Url = app(\App\Services\Media\R2MediaService::class)->url($key);
            if ($r2Url) {
                $faviconUrl = $r2Url;
            }
        } catch (\Throwable) {
            // R2 not configured — try the local public disk next.
            try {
                $localUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($key);
                if ($localUrl) {
                    $faviconUrl = $localUrl;
                }
            } catch (\Throwable) {
                // both failed — keep the /favicon.ico default already set.
            }
        }
    }
@endphp
<link rel="icon" type="image/x-icon" href="{{ $faviconUrl }}">
<link rel="shortcut icon" href="{{ $faviconUrl }}">
<link rel="apple-touch-icon" href="{{ $faviconUrl }}">
