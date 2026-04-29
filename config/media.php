<?php

/**
 * ===========================================================================
 *  Media Storage Configuration
 * ===========================================================================
 *
 *  Single source of truth for the path layout enforced by R2MediaService.
 *
 *  PATH SCHEMA (immutable contract — do not deviate):
 *
 *      {system}/{entity_type}/user_{user_id}/{resource_prefix}_{resource_id}/{filename}
 *
 *  The MediaPathBuilder rejects any combination not declared below — this is
 *  intentional. New use-cases must be added here in code review, not improvised
 *  by individual controllers. That keeps `/uploads/` and `/images/` style
 *  generic dumping grounds out of the bucket forever.
 *
 *  Field semantics
 *  ---------------
 *    system          short namespace, snake_case, ≤ 16 chars  (e.g. "auth")
 *    entity_type     leaf bucket inside the system            (e.g. "avatar")
 *    user_id         OWNER of the upload (NOT necessarily the actor)
 *    resource_id     foreign key to the resource the file belongs to
 *
 *  When `requires_resource` is false, files land at:
 *      {system}/{entity_type}/user_{user_id}/{filename}
 *  Useful for unique-per-user assets such as avatar/cover.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Disk Enforcement
    |--------------------------------------------------------------------------
    |
    | When true, R2MediaService refuses to operate against any disk other than
    | 'r2' — every upload, every read, every delete. Production MUST set this
    | true so we never accidentally write a customer's payment slip to local
    | disk where backups would miss it.
    |
    | Setting the env var STORAGE_R2_ONLY=false is allowed only for local
    | development against MinIO or against a stub disk in tests.
    */
    'r2_only' => env('STORAGE_R2_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Disk Name
    |--------------------------------------------------------------------------
    | The Laravel filesystem disk that R2MediaService talks to. Always 'r2'
    | in production; tests swap to 'r2-test' (in-memory) via test setup.
    */
    'disk' => env('MEDIA_DISK', 'r2'),

    /*
    |--------------------------------------------------------------------------
    | Default Visibility
    |--------------------------------------------------------------------------
    | 'private' = require signed URLs to view (default for paid content,
    |             payment slips, KYC docs)
    | 'public'  = readable via the bucket's public URL (avatar, cover, blog)
    |
    | Per-entity overrides live in `categories` below.
    */
    'default_visibility' => 'private',

    /*
    |--------------------------------------------------------------------------
    | Signed URL TTL
    |--------------------------------------------------------------------------
    | How long a presigned read URL stays valid (minutes). Customer downloads
    | after purchase use a longer window; everything else uses default.
    */
    'signed_url_ttl_minutes'          => (int) env('MEDIA_SIGNED_URL_TTL', 60),
    'signed_upload_ttl_minutes'       => (int) env('MEDIA_PRESIGNED_UPLOAD_TTL', 30),
    'signed_download_ttl_minutes'     => (int) env('MEDIA_DOWNLOAD_TTL', 240),
    // Long-lived URLs for images sent INTO LINE chats. Default 30 days
    // so a customer who saves a chat and opens it a month later still
    // sees the image. R2 caps single-URL TTLs at 7 days for AWS-style
    // presigning, so 30 d here means we may need to refresh on access
    // — but the URL remains a stable lookup key.
    'line_image_ttl_minutes'          => (int) env('MEDIA_LINE_IMAGE_TTL', 30 * 24 * 60),
    // Multipart-upload session window. Stale uploads past this are
    // aborted by the SweepStaleUploadsCommand cron.
    'multipart_ttl_hours'             => (int) env('MEDIA_MULTIPART_TTL', 24),
    'upload_session_ttl_hours'        => (int) env('MEDIA_UPLOAD_SESSION_TTL', 24),

    /*
    |--------------------------------------------------------------------------
    | Upload Categories — the WHITELIST
    |--------------------------------------------------------------------------
    | EVERY allowed upload destination is listed here. Each category is keyed
    | by "{system}.{entity_type}". Adding a new upload point means adding a
    | row here AND a factory helper on R2MediaService.
    |
    | Fields:
    |   resource_prefix     `event_`, `order_`, etc.  Empty when requires_resource=false
    |   requires_resource   true → path includes resource_{id}; false → user-scoped only
    |   visibility          override of default_visibility
    |   max_bytes           hard cap (after PHP upload_max_filesize check)
    |   allowed_mime        server-checked MIME allowlist (NOT extension-only)
    |   allowed_extensions  client-friendly extension list (validated separately)
    */
    'categories' => [

        // ── Auth / profile assets ─────────────────────────────────────────
        'auth.avatar' => [
            'resource_prefix'    => '',
            'requires_resource'  => false,
            'visibility'         => 'public',
            'max_bytes'          => 2 * 1024 * 1024, // 2 MB
            'allowed_mime'       => ['image/jpeg', 'image/png', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
        'auth.cover' => [
            'resource_prefix'    => '',
            'requires_resource'  => false,
            'visibility'         => 'public',
            'max_bytes'          => 5 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/png', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        ],

        // ── Event photo gallery (the core product) ────────────────────────
        'events.photos' => [
            'resource_prefix'    => 'event_',
            'requires_resource'  => true,
            'visibility'         => 'private', // signed URLs for paid galleries
            'max_bytes'          => 25 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'],
        ],
        'events.thumbnails' => [
            'resource_prefix'    => 'event_',
            'requires_resource'  => true,
            'visibility'         => 'public', // thumbs are tiny + public for grid view perf
            'max_bytes'          => 1 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'webp'],
        ],
        'events.watermarked' => [
            'resource_prefix'    => 'event_',
            'requires_resource'  => true,
            'visibility'         => 'public', // preview-quality with watermark = public OK
            'max_bytes'          => 5 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'webp'],
        ],
        'events.covers' => [
            'resource_prefix'    => 'event_',
            'requires_resource'  => true,
            'visibility'         => 'public', // hero image on the event listing page
            'max_bytes'          => 5 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/png', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        ],

        // ── Payments ──────────────────────────────────────────────────────
        'payments.slips' => [
            'resource_prefix'    => 'order_',
            'requires_resource'  => true,
            'visibility'         => 'private', // PII / receipt — admins only via signed URL
            'max_bytes'          => 5 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
        ],

        // ── Digital products (presets, e-books) ──────────────────────────
        'digital.products' => [
            'resource_prefix'    => 'product_',
            'requires_resource'  => true,
            'visibility'         => 'private',
            'max_bytes'          => 200 * 1024 * 1024,
            'allowed_mime'       => [
                'application/zip', 'application/x-zip-compressed',
                'application/pdf',
                'application/octet-stream', // .xmp/.dng/.cube come through as octet-stream
            ],
            'allowed_extensions' => ['zip', 'pdf', 'xmp', 'dng', 'cube', '3dl'],
        ],
        'digital.product_covers' => [
            'resource_prefix'    => 'product_',
            'requires_resource'  => true,
            'visibility'         => 'public',
            'max_bytes'          => 3 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/png', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        ],

        // ── Blog / marketing ──────────────────────────────────────────────
        'blog.posts' => [
            'resource_prefix'    => 'post_',
            'requires_resource'  => true,
            'visibility'         => 'public',
            'max_bytes'          => 10 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        ],

        // ── Photographer brand & portfolio ───────────────────────────────
        // Portfolio is a JSON array on the photographer profile (max 5 samples),
        // not a separate entity. User-scoped path keeps the schema flat and
        // matches the actual data model.
        //   photographer/portfolio/user_45/{uuid}_sample.jpg
        'photographer.portfolio' => [
            'resource_prefix'    => '',
            'requires_resource'  => false,
            'visibility'         => 'public',
            'max_bytes'          => 15 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/png', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
        // Branding has one row per photographer (logo, watermark text, etc.)
        // — no need for a resource_id segment. Path becomes:
        //   photographer/branding/user_45/{uuid}_logo.png
        'photographer.branding' => [
            'resource_prefix'    => '',
            'requires_resource'  => false,
            'visibility'         => 'public',
            'max_bytes'          => 5 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
        ],
        'photographer.presets' => [
            'resource_prefix'    => 'preset_',
            'requires_resource'  => true,
            'visibility'         => 'private',
            'max_bytes'          => 5 * 1024 * 1024,
            'allowed_mime'       => ['application/octet-stream', 'text/xml', 'application/xml', 'application/zip'],
            'allowed_extensions' => ['xmp', 'cube', '3dl', 'zip'],
        ],

        // ── User cloud storage (separate product) ────────────────────────
        'storage.files' => [
            'resource_prefix'    => 'folder_',
            'requires_resource'  => true,
            'visibility'         => 'private',
            'max_bytes'          => 500 * 1024 * 1024,
            'allowed_mime'       => null, // user cloud storage → any type allowed
            'allowed_extensions' => null,
        ],

        // ── Chat / messaging ─────────────────────────────────────────────
        'chat.attachments' => [
            'resource_prefix'    => 'conversation_',
            'requires_resource'  => true,
            'visibility'         => 'private',
            'max_bytes'          => 25 * 1024 * 1024,
            'allowed_mime'       => [
                'image/jpeg', 'image/png', 'image/webp', 'image/gif',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip', 'application/x-zip-compressed',
                'text/plain',
            ],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt'],
        ],

        // ── Face search selfies ──────────────────────────────────────────
        'face_search.queries' => [
            'resource_prefix'    => 'query_',
            'requires_resource'  => true,
            'visibility'         => 'private',
            'max_bytes'          => 5 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/png', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        ],

        // ── System-owned (platform-wide) assets ──────────────────────────
        // These are NOT owned by an end-user — they belong to the platform.
        // We keep the schema regular by stamping them with the reserved
        // user_id=0. The deleteUser() sweep is keyed on real user IDs so
        // user_0/ is naturally exempt from GDPR account-deletion sweeps.
        //
        // R2MediaService::SYSTEM_USER_ID is the canonical constant.
        'system.branding' => [
            'resource_prefix'    => '',
            'requires_resource'  => false,
            'visibility'         => 'public',
            'max_bytes'          => 5 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
        ],
        'system.watermark' => [
            'resource_prefix'    => '',
            'requires_resource'  => false,
            // Public so the watermark renderer can fetch from CDN; the
            // logo isn't sensitive — only the protected photos that
            // overlay it are.
            'visibility'         => 'public',
            'max_bytes'          => 5 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/png', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
        'system.seo_og' => [
            'resource_prefix'    => '',
            'requires_resource'  => false,
            'visibility'         => 'public',
            'max_bytes'          => 5 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/png', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
        'system.favicon' => [
            'resource_prefix'    => '',
            'requires_resource'  => false,
            'visibility'         => 'public',
            'max_bytes'          => 1 * 1024 * 1024,
            'allowed_mime'       => ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/svg+xml'],
            'allowed_extensions' => ['ico', 'png', 'svg'],
        ],
        // Brand-ad creative banner images. Owned by SYSTEM_USER_ID; resource_id
        // is the campaign id so deletes cascade cleanly when a campaign is
        // removed. 8MB cap is generous for retina banners (1200×600 PNG).
        'system.ad_creative' => [
            'resource_prefix'    => 'campaign_',
            'requires_resource'  => true,
            'visibility'         => 'public',
            'max_bytes'          => 8 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/png', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        ],

        // ── Blog affiliate banners ───────────────────────────────────────
        'blog.affiliate_banners' => [
            'resource_prefix'    => 'link_',
            'requires_resource'  => true,
            'visibility'         => 'public',
            'max_bytes'          => 3 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        ],

        // ── LINE rich-menu images ────────────────────────────────────────
        // The LINE Messaging API requires PNG/JPG ≤1MB at exactly
        // 2500x1686 or 2500x843 pixels. Validation of dimensions is
        // enforced at the controller level (Laravel image rule) — the R2
        // category just bounds the file size.
        'integrations.line_richmenu' => [
            'resource_prefix'    => 'menu_',
            'requires_resource'  => true,
            'visibility'         => 'private',
            'max_bytes'          => 1 * 1024 * 1024,
            'allowed_mime'       => ['image/jpeg', 'image/png'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png'],
        ],

    ],

];
