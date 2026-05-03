<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Event extends Model
{
    protected $table = 'event_events';
    protected $fillable = [
        'photographer_id','category_id','name','slug','description','cover_image',
        'drive_folder_id','drive_folder_link','location','province_id','district_id',
        'subdistrict_id','location_detail','price_per_photo','is_free','visibility',
        'event_password','status','shoot_date','created_by_admin','view_count',
        // Retention policy
        'retention_days_override','auto_delete_at','auto_delete_exempt','auto_delete_warned_at',
        // Portfolio retention (after originals are purged the event can live on
        // as a "previously sold" entry on the photographer's portfolio page).
        'originals_purged_at','is_portfolio',
        // Face-search toggle (PDPA — admin can disable per event)
        'face_search_enabled',
        // Time window
        'start_time','end_time',
        // Venue / organizer / categorization
        'venue_name','organizer','event_type','expected_attendees',
        // Marketing JSON arrays
        'highlights','tags',
        // Contact
        'contact_phone','contact_email',
        // Links
        'website_url','facebook_url',
        // Logistics
        'dress_code','parking_info',
    ];
    protected $hidden = ['event_password'];

    /**
     * Auto-hash event password when it's assigned.
     *
     * Event passwords are the gate on password-protected galleries (visibility
     * = 'password'). They used to be stored plaintext, which meant a DB leak
     * would expose every gallery's password in one shot. We now always store
     * a bcrypt hash — the mutator detects "already-hashed" input via the
     * `$2y$` prefix so migrating existing rows doesn't double-hash.
     *
     * NULL / empty string clears the password (used when visibility switches
     * back to 'public').
     */
    public function setEventPasswordAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['event_password'] = null;
            return;
        }
        // Already a bcrypt/argon hash — keep as-is (prevents double-hashing
        // when rows are re-saved or imported from another environment).
        if (is_string($value) && preg_match('/^\$(2[aby]|argon2[id]?)\$/', $value)) {
            $this->attributes['event_password'] = $value;
            return;
        }
        $this->attributes['event_password'] = Hash::make((string) $value);
    }

    /**
     * Verify a plaintext password against the stored hash in constant time.
     * Falls back to a legacy plaintext compare using `hash_equals` for any
     * row that hasn't been re-hashed yet (should be 0 after the migration).
     */
    public function checkPassword(?string $plaintext): bool
    {
        if (!$this->event_password || $plaintext === null || $plaintext === '') {
            return false;
        }
        // Hash format → bcrypt verify (constant time)
        if (preg_match('/^\$(2[aby]|argon2[id]?)\$/', $this->event_password)) {
            return Hash::check($plaintext, $this->event_password);
        }
        // Legacy plaintext row — constant-time compare, then opportunistically
        // upgrade to a hash so next login is on the fast path.
        $ok = hash_equals((string) $this->event_password, $plaintext);
        if ($ok) {
            $this->event_password = $plaintext; // mutator will hash + save
            $this->save();
        }
        return $ok;
    }

    protected $casts = [
        'price_per_photo'       => 'decimal:2',
        'is_free'               => 'boolean',
        'created_by_admin'      => 'boolean',
        'shoot_date'            => 'date',
        'auto_delete_at'        => 'datetime',
        'auto_delete_warned_at' => 'datetime',
        'auto_delete_exempt'    => 'boolean',
        'originals_purged_at'   => 'datetime',
        'is_portfolio'          => 'boolean',
        'face_search_enabled'   => 'boolean',
        // Enriched fields (2026-05-01)
        'expected_attendees'    => 'integer',
        'highlights'            => 'array',
        'tags'                  => 'array',
        // start_time / end_time intentionally NOT cast — Postgres
        // returns "HH:MM:SS" strings which Blade `{{ }}` renders
        // fine; casting to datetime would invent a date and break
        // schema.org Event.startDate ISO output we build later.
    ];

    /**
     * Compose Schema.org Event.startDate / endDate (ISO 8601) from
     * the date + time columns. Returns null if shoot_date is missing.
     * Used by both AutoSeoGenerator and PSeoSchemaBuilder so the two
     * can never drift on date formatting.
     */
    public function startDateIso(): ?string
    {
        if (!$this->shoot_date) return null;
        $date = $this->shoot_date->toDateString(); // YYYY-MM-DD
        $time = $this->start_time ? substr((string) $this->start_time, 0, 8) : '00:00:00';
        // Tag with +07:00 (Asia/Bangkok) — Google's Event rich result
        // requires a timezone offset to show the local time correctly.
        return "{$date}T{$time}+07:00";
    }

    public function endDateIso(): ?string
    {
        if (!$this->shoot_date || !$this->end_time) return null;
        $date = $this->shoot_date->toDateString();
        $time = substr((string) $this->end_time, 0, 8);
        return "{$date}T{$time}+07:00";
    }

    /**
     * Canonical event-type list used by the create/edit datalist and
     * surfaced as Schema.org Event.@type indirectly (via name/keywords).
     * Adding a value here automatically lights up admin autocomplete +
     * pSEO event_archive landings without further code changes.
     */
    public static function eventTypeOptions(): array
    {
        return [
            'wedding'    => 'งานแต่งงาน',
            'graduation' => 'รับปริญญา',
            'running'    => 'งานวิ่ง / มาราธอน',
            'concert'    => 'คอนเสิร์ต',
            'corporate'  => 'งานบริษัท / สัมมนา',
            'prewedding' => 'Pre-wedding',
            'portrait'   => 'พอร์ตเทรต',
            'festival'   => 'เทศกาล / งานวัฒนธรรม',
            'birthday'   => 'วันเกิด / ปาร์ตี้',
            'sport'      => 'งานกีฬาอื่นๆ',
            'other'      => 'อื่นๆ',
        ];
    }

    /**
     * Cascade-delete every file an event owns the moment the row goes away.
     *
     * Runs in two passes so we always free as much storage as possible, even
     * when a single driver throws:
     *
     *   1. Delete the cover_image file (kept in `events/{id}/cover/…` for new
     *      uploads, or the legacy `events/covers/…` path for pre-migration
     *      rows — both are stored verbatim in the column so a raw delete on
     *      the `public` disk removes whichever layout applies).
     *   2. Purge the entire `events/{id}` directory tree across every enabled
     *      driver so photo originals, thumbnails, watermarked previews and
     *      any stray files get wiped in one sweep. EventPhoto rows
     *      themselves have their own `deleting` hook for Rekognition cleanup
     *      — we rely on FK cascades or manual photo deletes to fire those.
     */
    protected static function booted(): void
    {
        // ── Event publish → auto-broadcast to users in same area ──
        //
        // When an event transitions to status='published' (or 'active'
        // — the codebase uses both as "publicly visible") we create
        // a popup announcement targeting users in the same province +
        // push a LINE OA message to friends in the same area.
        //
        // Wrapped in try so a notification-side failure doesn't roll
        // back the event save. Idempotent: skipBroadcast flag prevents
        // re-broadcasting on every save (e.g. cover image upload after
        // publish would otherwise fire a duplicate notification).
        static::saved(function (self $event) {
            try {
                // "Became public" = status is publishable AND status
                // CHANGED in this save (or the row was just created
                // already-public).
                //
                // Use wasChanged() not getOriginal() because the
                // `saved` hook fires AFTER syncChanges(), so
                // getOriginal returns the post-save value — useless
                // for detecting transitions. wasChanged() retains
                // the pre-save snapshot precisely for this case.
                $isPublic = in_array($event->status, ['published', 'active'], true);
                $justTransitioned = $event->wasChanged('status') || $event->wasRecentlyCreated;

                if ($isPublic && $justTransitioned && empty($event->_skipBroadcast)) {
                    app(\App\Services\GeoEventBroadcastService::class)
                        ->broadcastNewEvent($event);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    "Event#{$event->id} broadcast failed: " . $e->getMessage()
                );
            }
        });

        static::deleting(function (self $event) {
            $storage = app(\App\Services\StorageManager::class);

            // 1) Cover image. cover_image may store a raw key (on whatever
            //    driver was primary at upload time) or a full http(s) URL
            //    (newer setCover path). deleteAsset handles both — URLs
            //    early-return as no-op, raw keys get swept across every
            //    enabled driver so R2 copies go too.
            if ($event->cover_image) {
                try {
                    $storage->deleteAsset($event->cover_image);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        "Event#{$event->id} cover delete failed: " . $e->getMessage()
                    );
                }
            }

            // 2) Purge the whole events/{id} tree across every enabled driver.
            try {
                $storage->purgeDirectory("events/{$event->id}");
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    "Event#{$event->id} directory purge failed: " . $e->getMessage()
                );
            }
        });
    }

    // Accessor: full URL for cover image
    //
    // `cover_image` can hold three shapes depending on when/how it was set:
    //   1) A full URL (http/https) — newer setCover stores this directly so
    //      the correct disk is already baked in.
    //   2) A bare storage key (e.g. "events/3/photos/thumbnails/abc.jpg") —
    //      written by older setCover or bulk imports, disk unknown.
    //   3) Empty / null.
    //
    // Case 2 is what broke the page when uploads moved to R2: the old
    // implementation blindly prefixed `asset('storage/')` which only works
    // for files on the local `public` disk. For R2/S3 it yields a dead
    // `/storage/...` URL that 404s.
    //
    // We now route bare keys through StorageManager which picks the live
    // primary driver (R2 > S3 > public), so legacy rows heal themselves
    // without a migration.
    public function getCoverImageUrlAttribute()
    {
        if (!$this->cover_image) return null;

        // Already fully qualified → pass through unchanged.
        if (str_starts_with($this->cover_image, 'http://')
            || str_starts_with($this->cover_image, 'https://')
            || str_starts_with($this->cover_image, '//')) {
            return $this->cover_image;
        }

        // Bare storage key → ask StorageManager which disk owns it.
        try {
            $manager = app(\App\Services\StorageManager::class);
            $url = $manager->url($this->cover_image, $manager->primaryDriver());
            if (!empty($url)) return $url;
        } catch (\Throwable) {
            // Fall through to local URL below.
        }

        return asset('storage/' . $this->cover_image);
    }

    // Accessor: formatted location string
    public function getFullLocationAttribute(): string
    {
        $parts = [];
        if ($this->subdistrict) $parts[] = $this->subdistrict->name_th;
        if ($this->district) $parts[] = $this->district->name_th;
        if ($this->province) $parts[] = $this->province->name_th;
        return implode(', ', $parts) ?: ($this->location ?? '-');
    }

    public function photographer() { return $this->belongsTo(User::class,'photographer_id'); }
    public function photographerProfile() { return $this->hasOne(PhotographerProfile::class,'user_id','photographer_id'); }
    public function category() { return $this->belongsTo(EventCategory::class,'category_id'); }
    public function province() { return $this->belongsTo(\App\Models\ThaiProvince::class,'province_id'); }
    public function district() { return $this->belongsTo(\App\Models\ThaiDistrict::class,'district_id'); }
    public function subdistrict() { return $this->belongsTo(\App\Models\ThaiSubdistrict::class,'subdistrict_id'); }
    public function orders() { return $this->hasMany(Order::class,'event_id'); }
    public function reviews() { return $this->hasMany(Review::class,'event_id'); }
    public function photosCache() { return $this->hasMany(EventPhotoCache::class,'event_id'); }
    public function photos() { return $this->hasMany(EventPhoto::class,'event_id'); }
    public function activePhotos() { return $this->hasMany(EventPhoto::class,'event_id')->where('status','active')->orderBy('sort_order'); }
    public function packages() { return $this->hasMany(\App\Models\PricingPackage::class); }
    public function scopeActive($q) { return $q->where('status','active'); }
    public function scopePublished($q) { return $q->whereIn('status',['published','active']); }

    // ─────────────────────────────────────────────────────────────
    //  Retention policy
    // ─────────────────────────────────────────────────────────────

    /**
     * When should this event be auto-deleted?
     *
     * Priority:
     *   1. `auto_delete_exempt` → never (returns null)
     *   2. `auto_delete_at`     → explicit date set by admin
     *   3. `retention_days_override` added to the configured base timestamp
     *   4. Per-tier default `retention_days_<tier>` (creator/seller/pro)
     *      added to the base timestamp
     *   5. Global default `event_default_retention_days` as last-resort fallback
     *
     * Base timestamp is controlled by app setting `event_auto_delete_from_field`
     * — defaults to `shoot_date`, falls back to `created_at` if shoot_date is null.
     */
    public function effectiveDeleteAt(): ?\Illuminate\Support\Carbon
    {
        if ($this->auto_delete_exempt) {
            return null;
        }
        if ($this->auto_delete_at) {
            return $this->auto_delete_at;
        }

        $fromField = (string) \App\Models\AppSetting::get('event_auto_delete_from_field', 'shoot_date');
        $base = null;
        if ($fromField === 'shoot_date' && $this->shoot_date) {
            $base = \Illuminate\Support\Carbon::parse($this->shoot_date);
        }
        $base = $base ?: $this->created_at;
        if (!$base) {
            return null;
        }

        $days = $this->retention_days_override !== null
            ? (int) $this->retention_days_override
            : $this->tierRetentionDays();

        if ($days <= 0) {
            return null;
        }

        return $base->copy()->addDays($days);
    }

    /**
     * Resolve the retention days for this event's photographer tier.
     *
     * Looked up once per model instance because this method gets called
     * inside loops (PurgeExpiredEventsCommand walks all events, admin
     * preview page shows one row per event). Misses fall back to the
     * global default so legacy events without a known tier still work.
     */
    public function tierRetentionDays(): int
    {
        // Memoise per-instance — avoids a profile query for every call in
        // a loop. Cleared on model refresh.
        if (isset($this->__tierRetentionDaysCache)) {
            return $this->__tierRetentionDaysCache;
        }

        $tier = null;
        if ($this->photographer_id) {
            $tier = \App\Models\PhotographerProfile::where('user_id', $this->photographer_id)
                ->value('tier');
        }

        $key = match ((string) $tier) {
            \App\Models\PhotographerProfile::TIER_PRO    => 'retention_days_pro',
            \App\Models\PhotographerProfile::TIER_SELLER => 'retention_days_seller',
            \App\Models\PhotographerProfile::TIER_CREATOR => 'retention_days_creator',
            default => null,
        };

        $days = $key
            ? (int) \App\Models\AppSetting::get($key, '0')
            : 0;

        // Fall back to the legacy single-number setting if no tier key matches
        // or the tier key is set to 0 ("use global default").
        if ($days <= 0) {
            $days = (int) \App\Models\AppSetting::get('event_default_retention_days', 90);
        }

        return $this->__tierRetentionDaysCache = $days;
    }

    /** @var int|null Memoised tier retention. Not persisted. */
    private ?int $__tierRetentionDaysCache = null;

    /** Is this event overdue for auto-deletion? */
    public function shouldAutoDelete(): bool
    {
        $eta = $this->effectiveDeleteAt();
        return $eta !== null && $eta->isPast();
    }

    /** Events eligible for auto-deletion — cheap indexed scan. */
    public function scopePendingAutoDelete($q)
    {
        return $q->where('auto_delete_exempt', false)
            ->where(function ($inner) {
                $inner->whereNotNull('auto_delete_at')
                    ->where('auto_delete_at', '<=', now());
                // (Events without explicit auto_delete_at are filtered in-PHP by the
                // command because their effective date depends on AppSetting values
                // that would make this WHERE unwieldy.)
            });
    }

    /**
     * Does this event have revenue-bearing orders?
     * Used as a safety guard: events with paid orders are NOT auto-deleted
     * unless admin explicitly passes --include-with-orders to the command.
     */
    public function hasBlockingOrders(): bool
    {
        return $this->orders()
            ->whereIn('status', ['paid', 'completed', 'processing'])
            ->exists();
    }

    // ─────────────────────────────────────────────────────────────
    //  Portfolio retention helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * True once the full-resolution originals have been wiped. The event
     * row, cover image, thumbnails and watermarked previews are still
     * around — the work just can't be purchased or re-downloaded anymore.
     */
    public function isPortfolioOnly(): bool
    {
        return $this->originals_purged_at !== null;
    }

    /**
     * Events that should appear on the photographer's portfolio page —
     * either because the originals were purged (historical work) or
     * because the photographer explicitly pinned them (`is_portfolio=1`).
     *
     * We keep this deliberately permissive on status so archived AND
     * published events both surface — the portfolio view is a career
     * showcase, not a live sale list.
     */
    public function scopePortfolio($q)
    {
        return $q->where(function ($inner) {
            $inner->whereNotNull('originals_purged_at')
                  ->orWhere('is_portfolio', true);
        })->whereNotIn('status', ['draft', 'hidden']);
    }

    /** True when a user could still buy / download photos from this event. */
    public function isSellable(): bool
    {
        if ($this->isPortfolioOnly()) return false;
        if (in_array($this->status, ['draft', 'hidden', 'archived'], true)) return false;
        return true;
    }
}
