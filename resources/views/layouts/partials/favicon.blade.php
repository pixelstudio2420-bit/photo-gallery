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
    // Always start with an absolute fallback so we never emit a bare
    // storage key as href. If every other branch fails the browser will
    // at least hit /favicon.ico (a real file in /public).
    $faviconUrl = asset('favicon.ico');

    $key = \App\Models\AppSetting::get('seo_favicon');
    if ($key) {
        $candidate = '';

        // Prefer R2's public URL when configured. R2MediaService throws
        // StorageNotConfiguredException if R2 creds aren't set up — that
        // bubbles into our catch and we fall through to the local disk.
        try {
            $candidate = (string) app(\App\Services\Media\R2MediaService::class)->url($key);
        } catch (\Throwable) {
            try {
                $candidate = (string) \Illuminate\Support\Facades\Storage::disk('public')->url($key);
            } catch (\Throwable) {
                $candidate = '';
            }
        }

        // Defensive sanity check.
        //
        // The S3 adapter's url() can return the bare object key (e.g.
        // "system/favicon/user_0/abc.ico") when `filesystems.disks.r2.url`
        // is unset or empty — that bare key, dropped into a <link href>,
        // gets resolved by the browser RELATIVE to the current page, so
        // a page at /events/3 would request /events/system/favicon/...
        // → 404.
        //
        // Two-tier handling:
        //   1. Absolute URL (http(s)://) or absolute path (/...) → trust it.
        //   2. Bare key (no leading slash) → STILL salvageable: prepend
        //      a leading slash + storage path so the browser at least
        //      sees an absolute path. Better than letting the fallback
        //      kick in because the file IS reachable via /storage/{key}
        //      on most public-disk deploys — and we'd rather show the
        //      operator's branded favicon than the default loadroop one.
        if ($candidate !== '') {
            if (preg_match('#^(?:https?:)?/#i', $candidate)) {
                // Already absolute — use directly.
                $faviconUrl = $candidate;
            } else {
                // Bare key. Wrap it as /storage/{key} so the browser sees
                // an absolute path. If that 404s on this deploy, the
                // browser will fall through to its own /favicon.ico
                // request which hits the static file.
                $faviconUrl = '/storage/' . ltrim($candidate, '/');
            }
        }
    }
@endphp
<link rel="icon" type="image/x-icon" href="{{ $faviconUrl }}">
<link rel="shortcut icon" href="{{ $faviconUrl }}">
<link rel="apple-touch-icon" href="{{ $faviconUrl }}">
