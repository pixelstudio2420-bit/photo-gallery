<?php
namespace App\Http\Controllers\Photographer;
use App\Http\Controllers\Controller;
use App\Jobs\ImportDrivePhotosJob;
use App\Models\AppSetting;
use App\Models\Event;
use App\Models\EventCategory;
use App\Services\GoogleDriveService;
use App\Services\QueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Services\ImageProcessorService;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\R2MediaService;
use App\Services\StorageManager;
use App\Services\SubscriptionService;
use App\Models\PhotographerProfile;
use Illuminate\Support\Facades\Log;

class EventController extends Controller
{
    /**
     * Force non-sellable status for creator-tier photographers.
     *
     * The route itself is open (creators can save drafts all day), but a
     * creator picking 'active' or 'published' in the form would bypass the
     * money-flow gate — so we rewrite the field down to 'draft' here and
     * surface a single flash message explaining why + what to do next.
     *
     * This is intentionally a soft gate, not a 403. A hard block would turn
     * the form into a dead-end for first-time signups who haven't added
     * PromptPay yet — the whole point of the creator tier is that they
     * can start working immediately and upgrade later.
     *
     * Returns the (possibly downgraded) status and whether a nudge should
     * be flashed to the user on the redirect.
     *
     * @return array{status:string, nudge:?string}
     */
    private function enforceSellableTier(string $requestedStatus): array
    {
        $profile = Auth::user()->photographerProfile;

        // Draft/closed don't involve selling — no gate needed.
        if (in_array($requestedStatus, ['draft', 'closed'], true)) {
            return ['status' => $requestedStatus, 'nudge' => null];
        }

        // Seller+ can use whatever status they picked.
        if ($profile && $profile->canReach(PhotographerProfile::TIER_SELLER)) {
            return ['status' => $requestedStatus, 'nudge' => null];
        }

        // Creator tier trying to publish → downgrade to draft + nudge.
        return [
            'status' => 'draft',
            'nudge'  => 'บันทึกเป็นแบบร่างแล้ว — กรอกหมายเลข PromptPay ในโปรไฟล์เพื่อปลดล็อกการเผยแพร่และเริ่มขายผลงาน',
        ];
    }

    /**
     * Plan cap on concurrent live events (Free=0, Starter=2, Pro=5,
     * Business/Studio=∞). Read from SubscriptionPlan.max_concurrent_events.
     *
     * Returns one of:
     *   ['ok' => true, 'status' => '<unchanged>']
     *     — under cap, proceed.
     *   ['ok' => true, 'status' => 'draft', 'nudge' => '<thai>']
     *     — Free plan trying to publish: silently downgrade to draft.
     *   ['ok' => false, 'message' => '<thai>']
     *     — at-cap: caller should redirect back with this as a flash error.
     *
     * The currentEventId arg lets update() exclude itself from the count
     * (otherwise editing an active event back to active would look like
     * "create one more").
     */
    private function enforceConcurrentEventCap(string $requestedStatus, ?int $currentEventId = null): array
    {
        // Draft / closed never count against the cap.
        if (in_array($requestedStatus, ['draft', 'closed'], true)) {
            return ['ok' => true, 'status' => $requestedStatus];
        }

        $profile = Auth::user()->photographerProfile;
        if (!$profile) {
            return ['ok' => true, 'status' => $requestedStatus];
        }

        $subs = app(SubscriptionService::class);
        $cap  = $subs->maxConcurrentEvents($profile);

        // Unlimited (Business / Studio) → always allow.
        if ($cap === null) {
            return ['ok' => true, 'status' => $requestedStatus];
        }

        // Cap = 0 → portfolio-only plan. Don't 422; just downgrade to
        // draft + nudge so the form save still succeeds.
        if ($cap === 0) {
            return [
                'ok'     => true,
                'status' => 'draft',
                'nudge'  => 'แผน '.$subs->currentPlan($profile)->name.' รองรับเฉพาะ portfolio — บันทึกเป็นแบบร่างแล้ว กรุณาอัปเกรดแผนเพื่อเปิดขายอีเวนต์',
            ];
        }

        // Cap > 0 → count active+published, excluding self for update path.
        $countQuery = Event::where('photographer_id', $profile->user_id)
            ->whereIn('status', ['active', 'published']);
        if ($currentEventId) {
            $countQuery->where('id', '!=', $currentEventId);
        }
        $live = $countQuery->count();

        if ($live >= $cap) {
            return [
                'ok'      => false,
                'message' => "แผน {$subs->currentPlan($profile)->name} เปิดอีเวนต์พร้อมกันได้สูงสุด {$cap} งาน — ปัจจุบันคุณมี {$live} งาน กรุณาปิดอีเวนต์เก่าหรืออัปเกรดแผน",
            ];
        }

        return ['ok' => true, 'status' => $requestedStatus];
    }

    public function index(Request $request)
    {
        $query = Auth::user()->photographerProfile->events();

        // Status filter from the index page's chip strip. The 'active'
        // chip aggregates both `active` and `published` because the
        // photographer-facing UI doesn't surface a meaningful difference
        // between the two — they're both "live and selling".
        $status = $request->query('status', 'all');
        if ($status !== 'all') {
            if ($status === 'active') {
                $query->whereIn('status', ['active', 'published']);
            } else {
                $query->where('status', $status);
            }
        }

        // Free-text search. Postgres-friendly ILIKE so case-insensitive
        // matches work without a wrapper LOWER() on every row. Falls back
        // to LIKE on MySQL (still works, just case-sensitive on case-
        // sensitive collations).
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $op = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($w) use ($like, $op) {
                $w->where('name',     $op, $like)
                  ->orWhere('location', $op, $like);
            });
        }

        // Eager-load photo count so the card meta line ("12 รูป") doesn't
        // trigger one COUNT query per row.
        $events = $query->withCount('photos')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        return view('photographer.events.index', compact('events'));
    }

    public function create()
    {
        $categories = EventCategory::active()->get();
        // Hard floor of 100 THB/photo — the admin may raise this via AppSetting but never lower it.
        $minPrice  = max(100.0, (float) AppSetting::get('min_event_price', 100));
        $allowFree = (bool) AppSetting::get('allow_free_events', true);
        // Provinces feed the cascading location picker in _extra_info_card.
        // Cached for an hour — the list never changes day-to-day.
        $provinces = \Illuminate\Support\Facades\Cache::remember(
            'photographer.locations.provinces', 3600,
            fn () => \App\Models\ThaiProvince::orderBy('name_th')->get(['id','name_th','name_en'])
        );
        return view('photographer.events.create', compact('categories', 'minPrice', 'allowFree', 'provinces'));
    }

    public function store(Request $request)
    {
        $minPrice  = max(100.0, (float) AppSetting::get('min_event_price', 100));
        $allowFree = (bool) AppSetting::get('allow_free_events', true);

        $validated = $request->validate(array_merge([
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'location'        => 'nullable|string|max:255',
            'shoot_date'      => 'nullable|date',
            'price_per_photo' => "required|numeric|min:{$minPrice}",
            'visibility'      => 'required|in:public,private,password',
            'event_password'  => 'nullable|string|max:100',
            'status'          => 'required|in:draft,active,published,closed',
            'category_id'     => 'nullable|exists:event_categories,id',
            'cover_image'     => 'nullable|image|max:5120',
            'drive_folder_url'=> 'nullable|url',
            'is_free'         => 'nullable|boolean',
            // Photographer-level AI toggles. The DB column +
            // public-facing gate already existed; this exposes the
            // switch on the photographer's own form so they can
            // opt out per-event without an admin round-trip.
            'face_search_enabled' => 'nullable|boolean',
        ], $this->enrichmentValidationRules()), [
            'price_per_photo.min' => "ราคาต่อภาพต้องไม่ต่ำกว่า {$minPrice} บาท",
        ]);

        // Guard: honour the `is_free` toggle only when the admin allows free events
        $wantsFree = $request->boolean('is_free');
        if ($wantsFree && !$allowFree) {
            return back()->withErrors(['is_free' => 'ระบบไม่อนุญาตให้สร้างอีเวนต์ฟรีในขณะนี้'])->withInput();
        }
        if ($wantsFree) {
            $validated['price_per_photo'] = 0;
        }

        // Tier gate: creators can create drafts, but can't publish sellable events
        // until they add PromptPay (seller tier). Downgrade silently + flash.
        $tierDecision = $this->enforceSellableTier($validated['status']);
        $validated['status'] = $tierDecision['status'];

        // Plan-cap gate: SubscriptionPlan.max_concurrent_events caps how
        // many live events the photographer can run at once. Free=0
        // (forced to draft), Starter=2, Pro=5, Business/Studio=∞.
        $capDecision = $this->enforceConcurrentEventCap($validated['status']);
        if (!$capDecision['ok']) {
            return back()->withInput()->with('error', $capDecision['message']);
        }
        $validated['status'] = $capDecision['status'];

        // Generate unique slug
        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;
        while (Event::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        // Cover uploaded AFTER the create call so the stored path can be
        // namespaced under events/{id}/cover/…
        $coverFile = $request->hasFile('cover_image') ? $request->file('cover_image') : null;

        // Extract drive folder ID from URL
        $driveFolderId = null;
        $driveFolderLink = $request->input('drive_folder_url');
        if ($driveFolderLink) {
            // Extract folder ID from Google Drive URL patterns
            if (preg_match('/\/folders\/([a-zA-Z0-9_-]+)/', $driveFolderLink, $matches)) {
                $driveFolderId = $matches[1];
            } elseif (preg_match('/id=([a-zA-Z0-9_-]+)/', $driveFolderLink, $matches)) {
                $driveFolderId = $matches[1];
            }
        }

        $event = Event::create(array_merge([
            'photographer_id'   => Auth::id(),
            'category_id'       => $validated['category_id'] ?? null,
            'name'              => $validated['name'],
            'slug'              => $slug,
            'description'       => $validated['description'] ?? null,
            'cover_image'       => null,
            'drive_folder_id'   => $driveFolderId,
            'drive_folder_link' => $driveFolderLink,
            'location'          => $validated['location'] ?? null,
            'price_per_photo'   => $validated['price_per_photo'],
            'is_free'           => $request->boolean('is_free'),
            'visibility'        => $validated['visibility'],
            'event_password'    => $validated['event_password'] ?? null,
            'status'            => $validated['status'],
            'shoot_date'        => $validated['shoot_date'] ?? null,
            // Default ON for create — `face_search_enabled` is opt-out
            // (database default is also true). The photographer can
            // untick the form checkbox to disable the customer-side
            // face-search button + API for this specific event.
            'face_search_enabled' => $request->boolean('face_search_enabled', true),
        ], $this->extractEnrichmentPayload($request, $validated)));

        if ($coverFile) {
            try {
                $upload = app(R2MediaService::class)
                    ->uploadEventCover((int) $event->photographer_id, (int) $event->id, $coverFile);
                $event->cover_image = $upload->key;
                $event->save();
            } catch (InvalidMediaFileException $e) {
                // Event was already created — surface a flash message rather
                // than rolling back the whole save (the photographer can
                // re-upload the cover from the edit page).
                Log::warning('Event cover upload rejected', [
                    'event_id' => $event->id,
                    'reason'   => $e->getMessage(),
                ]);
            }
        }

        try {
            $line = app(\App\Services\LineNotifyService::class);
            $line->notifyNewEvent(['name' => $event->name, 'shoot_date' => $event->shoot_date], 'photographer');
        } catch (\Throwable $e) {
            \Log::error('Notification error: ' . $e->getMessage());
        }

        $redirect = redirect()->route('photographer.events.index')->with('success', 'สร้างอีเวนต์สำเร็จ');
        if (!empty($tierDecision['nudge'])) {
            $redirect->with('warning', $tierDecision['nudge']);
        } elseif (!empty($capDecision['nudge'])) {
            $redirect->with('warning', $capDecision['nudge']);
        }
        return $redirect;
    }

    public function show(Event $event)
    {
        $this->authorizePhotographer($event);

        // Photo stats — single query with FILTER for each bucket so we
        // don't fan out one query per status. Mirrors the photos index
        // controller's stats query for consistency.
        $photoStats = $event->photos()
            ->where('status', '!=', 'deleted')
            ->selectRaw("
                COUNT(*)                                  as total,
                COUNT(*) FILTER (WHERE status = 'active')     as active,
                COUNT(*) FILTER (WHERE status = 'processing') as processing,
                COUNT(*) FILTER (WHERE status = 'failed')     as failed,
                COALESCE(SUM(file_size), 0)               as size_bytes
            ")
            ->first();

        $stats = [
            'photos'          => (int) ($photoStats->total ?? 0),
            'photos_active'   => (int) ($photoStats->active ?? 0),
            'photos_processing' => (int) ($photoStats->processing ?? 0),
            'photos_failed'   => (int) ($photoStats->failed ?? 0),
            'storage_bytes'   => (int) ($photoStats->size_bytes ?? 0),
            'view_count'      => (int) ($event->view_count ?? 0),
        ];

        // Revenue + order stats — best-effort (skip if photographer
        // doesn't have any orders yet). We pull aggregate from
        // photographer_payouts where the originating order's event_id
        // matches this event.
        try {
            $revenueRow = \DB::table('photographer_payouts as pp')
                ->join('orders as o', 'o.id', '=', 'pp.order_id')
                ->where('o.event_id', $event->id)
                ->where('pp.photographer_id', \Illuminate\Support\Facades\Auth::id())
                ->selectRaw('COUNT(DISTINCT o.id) as order_count, COALESCE(SUM(pp.payout_amount), 0) as total_revenue')
                ->first();
            $stats['orders'] = (int) ($revenueRow->order_count ?? 0);
            $stats['revenue'] = (float) ($revenueRow->total_revenue ?? 0);
        } catch (\Throwable) {
            // Table or column shape doesn't match — degrade gracefully
            $stats['orders'] = 0;
            $stats['revenue'] = 0.0;
        }

        // Recent photos for the gallery preview (12 thumbs)
        $recentPhotos = $event->photos()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        // Hydrate location names for display
        $locationParts = [];
        if ($event->subdistrict_id) {
            $sub = \DB::table('thai_subdistricts')->where('id', $event->subdistrict_id)->first();
            if ($sub) $locationParts[] = $sub->name_th;
        }
        if ($event->district_id) {
            $dist = \DB::table('thai_districts')->where('id', $event->district_id)->first();
            if ($dist) $locationParts[] = $dist->name_th;
        }
        if ($event->province_id) {
            $prov = \DB::table('thai_provinces')->where('id', $event->province_id)->first();
            if ($prov) $locationParts[] = $prov->name_th;
        }
        $locationFull = implode(' · ', $locationParts);

        return view('photographer.events.show', compact(
            'event', 'stats', 'recentPhotos', 'locationFull'
        ));
    }

    public function edit(Event $event)
    {
        $categories = EventCategory::active()->get();
        $minPrice  = max(100.0, (float) AppSetting::get('min_event_price', 100));
        $allowFree = (bool) AppSetting::get('allow_free_events', true);

        // Same cascading picker data as create() — plus the prefill rows
        // for whichever province/district the event already has saved
        // so the dropdowns render their selected values without an
        // initial AJAX round-trip.
        $provinces = \Illuminate\Support\Facades\Cache::remember(
            'photographer.locations.provinces', 3600,
            fn () => \App\Models\ThaiProvince::orderBy('name_th')->get(['id','name_th','name_en'])
        );
        $districts = $event->province_id
            ? \App\Models\ThaiDistrict::where('province_id', $event->province_id)
                ->orderBy('name_th')->get(['id','name_th','name_en'])
            : collect();
        $subdistricts = $event->district_id
            ? \App\Models\ThaiSubdistrict::where('district_id', $event->district_id)
                ->orderBy('name_th')->get(['id','name_th','name_en','zip_code'])
            : collect();

        return view('photographer.events.edit', compact(
            'event', 'categories', 'minPrice', 'allowFree',
            'provinces', 'districts', 'subdistricts'
        ));
    }

    public function update(Request $request, Event $event)
    {
        $minPrice  = max(100.0, (float) AppSetting::get('min_event_price', 100));
        $allowFree = (bool) AppSetting::get('allow_free_events', true);

        $validated = $request->validate(array_merge([
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'location'        => 'nullable|string|max:255',
            'shoot_date'      => 'nullable|date',
            'price_per_photo' => "required|numeric|min:{$minPrice}",
            'visibility'      => 'required|in:public,private,password',
            'event_password'  => 'nullable|string|max:100',
            'status'          => 'required|in:draft,active,published,closed',
            'category_id'     => 'nullable|exists:event_categories,id',
            'cover_image'     => 'nullable|image|max:5120',
            'drive_folder_url'=> 'nullable|url',
            'is_free'         => 'nullable|boolean',
            // Photographer-level AI toggles. The DB column +
            // public-facing gate already existed; this exposes the
            // switch on the photographer's own form so they can
            // opt out per-event without an admin round-trip.
            'face_search_enabled' => 'nullable|boolean',
        ], $this->enrichmentValidationRules()), [
            'price_per_photo.min' => "ราคาต่อภาพต้องไม่ต่ำกว่า {$minPrice} บาท",
        ]);

        // Guard: honour the `is_free` toggle only when the admin allows free events
        $wantsFree = $request->boolean('is_free');
        if ($wantsFree && !$allowFree) {
            return back()->withErrors(['is_free' => 'ระบบไม่อนุญาตให้สร้างอีเวนต์ฟรีในขณะนี้'])->withInput();
        }
        if ($wantsFree) {
            $validated['price_per_photo'] = 0;
        }

        // Tier gate: creator-tier photographers editing an event cannot flip
        // it from draft to a sellable status. Downgrade silently + flash.
        $tierDecision = $this->enforceSellableTier($validated['status']);
        $validated['status'] = $tierDecision['status'];

        // Plan-cap gate: same rule as create, but exclude this event from
        // the count so editing an already-active event back to active
        // doesn't trip the limit.
        $capDecision = $this->enforceConcurrentEventCap($validated['status'], $event->id);
        if (!$capDecision['ok']) {
            return back()->withInput()->with('error', $capDecision['message']);
        }
        $validated['status'] = $capDecision['status'];

        // Regenerate slug if name changed
        if ($event->name !== $validated['name']) {
            $slug = Str::slug($validated['name']);
            $originalSlug = $slug;
            $counter = 1;
            while (Event::where('slug', $slug)->where('id', '!=', $event->id)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }
            $event->slug = $slug;
        }

        // Cover image replacement.
        // Old cover is deleted on R2 (and CDN cache purged async by
        // R2MediaService) BEFORE we upload the new one — that way a
        // crashed upload can never leave two images charged against
        // the photographer's storage quota.
        if ($request->hasFile('cover_image')) {
            $media = app(R2MediaService::class);
            $oldKey = $event->getRawOriginal('cover_image');
            if ($oldKey) {
                try { $media->delete($oldKey); } catch (\Throwable) {}
            }
            try {
                $upload = $media->uploadEventCover(
                    (int) $event->photographer_id,
                    (int) $event->id,
                    $request->file('cover_image'),
                );
                $event->cover_image = $upload->key;
            } catch (InvalidMediaFileException $e) {
                return back()->withErrors(['cover_image' => $e->getMessage()]);
            }
        }

        // Extract drive folder ID from URL
        $driveFolderLink = $request->input('drive_folder_url');
        $driveFolderId = $event->drive_folder_id;
        if ($driveFolderLink) {
            if (preg_match('/\/folders\/([a-zA-Z0-9_-]+)/', $driveFolderLink, $matches)) {
                $driveFolderId = $matches[1];
            } elseif (preg_match('/id=([a-zA-Z0-9_-]+)/', $driveFolderLink, $matches)) {
                $driveFolderId = $matches[1];
            }
        }

        $event->update(array_merge([
            'category_id'       => $validated['category_id'] ?? null,
            'name'              => $validated['name'],
            'description'       => $validated['description'] ?? null,
            'drive_folder_id'   => $driveFolderId,
            'drive_folder_link' => $driveFolderLink ?? $event->drive_folder_link,
            'location'          => $validated['location'] ?? null,
            'price_per_photo'   => $validated['price_per_photo'],
            'is_free'           => $request->boolean('is_free'),
            'visibility'        => $validated['visibility'],
            'event_password'    => $validated['event_password'] ?? null,
            'status'            => $validated['status'],
            'shoot_date'        => $validated['shoot_date'] ?? null,
            // Honour the form checkbox if it was on the page; otherwise
            // preserve the existing value so `boolean()` defaulting
            // doesn't accidentally flip an admin-set value back to
            // false on every save.
            'face_search_enabled' => $request->has('face_search_enabled')
                ? $request->boolean('face_search_enabled')
                : $event->face_search_enabled,
        ], $this->extractEnrichmentPayload($request, $validated)));

        $redirect = redirect()->route('photographer.events.index')->with('success', 'อัพเดทสำเร็จ');
        if (!empty($tierDecision['nudge'])) {
            $redirect->with('warning', $tierDecision['nudge']);
        } elseif (!empty($capDecision['nudge'])) {
            $redirect->with('warning', $capDecision['nudge']);
        }
        return $redirect;
    }

    public function destroy(Event $event)
    {
        // Ownership check — without this any logged-in photographer could
        // DELETE another photographer's event by guessing the ID. The
        // existing route only requires `photographer` middleware, not a
        // per-row authorization, so we enforce it here.
        if ((int) $event->photographer_id !== (int) Auth::id()) {
            abort(403, 'ไม่มีสิทธิ์ลบอีเวนต์นี้');
        }

        $profile = Auth::user()->photographerProfile;

        // Pre-compute the displayable byte total BEFORE the delete so we
        // can show the user a precise "freed X MB" number. Cheap query
        // — single SUM over the indexed file_size column.
        $freedBytes = 0;
        try {
            $freedBytes = (int) DB::table('event_photos')
                ->where('event_id', $event->id)
                ->sum('file_size');
            // Apply the same derivative multiplier the quota service uses
            // (originals + thumbnails + watermarked previews) so the
            // refunded total matches what shows on the dashboard.
            $freedBytes = (int) round($freedBytes * \App\Services\StorageQuotaService::DERIVATIVE_MULTIPLIER);
        } catch (\Throwable) { /* counter is best-effort, never block the delete */ }

        // Cascade delete — Event::booted() static `deleting` hook fires:
        //   1. Sweeps cover_image from R2/S3/local across every enabled driver
        //   2. Purges events/{id}/* directory tree across every driver
        // Eloquent's per-row `deleted` observer on EventPhoto does NOT fire
        // for FK cascades, which is why we re-sum below instead of trusting
        // the running counter to have been decremented row-by-row.
        $event->delete();

        // Resync storage_used_bytes from the database of record. Without
        // this, the photographer's dashboard would still show the bytes
        // from the deleted photos as "in use" until the nightly recalc
        // catches up — making it look like the delete didn't return any
        // space. recalculate() does one SUM JOIN and writes the corrected
        // total back via saveQuietly so we don't trigger model events.
        if ($profile) {
            try {
                $quota = app(\App\Services\StorageQuotaService::class);
                $remainingBytes = $quota->recalculate($profile);

                $freedHuman    = $quota->humanBytes($freedBytes);
                $remainingHuman = $quota->humanBytes($remainingBytes);

                return redirect()->route('photographer.events.index')->with(
                    'success',
                    $freedBytes > 0
                        ? "ลบอีเวนต์สำเร็จ — คืนพื้นที่จัดเก็บ {$freedHuman} (ใช้อยู่ตอนนี้: {$remainingHuman})"
                        : 'ลบอีเวนต์สำเร็จ'
                );
            } catch (\Throwable $e) {
                Log::warning('Storage recalc after event delete failed', [
                    'event_id'   => $event->id,
                    'profile_id' => $profile->id ?? null,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('photographer.events.index')
            ->with('success', 'ลบอีเวนต์สำเร็จ');
    }

    public function qrcode(Event $event)
    {
        return view('photographer.events.qrcode', compact('event'));
    }

    /**
     * Trigger import of photos from Google Drive folder.
     * POST /photographer/events/{event}/import-drive
     */
    public function importDrive(Request $request, Event $event)
    {
        $this->authorizePhotographer($event);

        // Accept drive_folder_url from request and update event if provided
        $driveUrl = $request->input('drive_folder_url');
        if ($driveUrl) {
            $folderId = GoogleDriveService::extractFolderId($driveUrl);
            if ($folderId) {
                $event->update([
                    'drive_folder_id'   => $folderId,
                    'drive_folder_link' => $driveUrl,
                ]);
            }
        }

        if (empty($event->drive_folder_id)) {
            return response()->json([
                'success' => false,
                'message' => 'กรุณาระบุ Google Drive folder link',
            ], 422);
        }

        // Check if there's already an active import
        $queueService = app(QueueService::class);
        $activeImport = $queueService->getEventImportProgress($event->id);

        if ($activeImport) {
            // If stuck as pending or failed → cancel it and allow re-import
            if (in_array($activeImport->status, ['pending', 'failed'])) {
                DB::table('sync_queue')->where('id', $activeImport->id)->delete();
            } else {
                // Truly processing → don't interrupt
                return response()->json([
                    'success' => false,
                    'message' => 'กำลังนำเข้ารูปภาพอยู่แล้ว กรุณารอสักครู่',
                    'progress' => [
                        'id'              => $activeImport->id,
                        'status'          => $activeImport->status,
                        'total_files'     => $activeImport->total_files,
                        'processed_files' => $activeImport->processed_files,
                    ],
                ], 409);
            }
        }

        // Create sync_queue record for tracking
        $queueId = $queueService->dispatch('import_drive_photos', $event->id, [
            'drive_folder_id' => $event->drive_folder_id,
        ]);

        // Dispatch the coordinator job
        ImportDrivePhotosJob::dispatch(
            $event->id,
            $event->drive_folder_id,
            $queueId
        )->onQueue('photos');

        return response()->json([
            'success'  => true,
            'message'  => 'เริ่มนำเข้ารูปภาพจาก Google Drive แล้ว',
            'queue_id' => $queueId,
        ]);
    }

    /**
     * Get import progress for an event.
     * GET /photographer/events/{event}/import-progress
     */
    public function importProgress(Event $event)
    {
        $this->authorizePhotographer($event);

        $queueService = app(QueueService::class);
        $progress = $queueService->getEventImportProgress($event->id);

        if (!$progress) {
            // Check last completed import
            try {
                $lastImport = Schema::hasTable('sync_queue')
                    ? DB::table('sync_queue')
                        ->where('event_id', $event->id)
                        ->whereIn('job_type', ['import_drive_photos'])
                        ->orderByDesc('created_at')
                        ->first()
                    : null;
            } catch (\Throwable) {
                $lastImport = null;
            }

            return response()->json([
                'active'      => false,
                'last_import' => $lastImport ? [
                    'status'          => $lastImport->status,
                    'total_files'     => $lastImport->total_files,
                    'processed_files' => $lastImport->processed_files,
                    'completed_at'    => $lastImport->completed_at,
                ] : null,
            ]);
        }

        $percent = $progress->total_files > 0
            ? round(($progress->processed_files / $progress->total_files) * 100)
            : 0;

        return response()->json([
            'active'          => true,
            'queue_id'        => $progress->id,
            'status'          => $progress->status,
            'total_files'     => $progress->total_files,
            'processed_files' => $progress->processed_files,
            'percent'         => $percent,
            'error'           => $progress->error_message,
        ]);
    }

    /**
     * Get photo processing status (for polling after upload).
     * GET /photographer/events/{event}/photo-status
     */
    public function photoStatus(Request $request, Event $event)
    {
        $this->authorizePhotographer($event);

        $photoIds = $request->input('ids', []);

        if (empty($photoIds)) {
            return response()->json(['photos' => []]);
        }

        $photos = \App\Models\EventPhoto::where('event_id', $event->id)
            ->whereIn('id', $photoIds)
            ->get(['id', 'status', 'thumbnail_path', 'storage_disk']);

        $result = [];
        foreach ($photos as $photo) {
            $result[] = [
                'id'            => $photo->id,
                'status'        => $photo->status,
                'thumbnail_url' => $photo->status === 'active' ? $photo->thumbnail_url : null,
            ];
        }

        return response()->json(['photos' => $result]);
    }

    /**
     * Manually archive this event to the portfolio. Wipes ORIGINALS only —
     * cover, thumbnail and watermarked previews are kept so the event can
     * still shine on the photographer's profile page. Orders remain valid.
     *
     * This uses the same job the nightly retention command dispatches so
     * behaviour stays consistent between manual and automatic flows.
     */
    public function archiveToPortfolio(Request $request, Event $event)
    {
        $this->authorizePhotographer($event);

        if ($event->isPortfolioOnly()) {
            return back()->with('info', 'อีเวนต์นี้ถูกเก็บเป็นผลงานแล้ว');
        }

        $purgeDrive = (bool) AppSetting::get('event_auto_delete_purge_drive', 0);
        \App\Jobs\PurgeEventOriginalsJob::dispatch($event->id, $purgeDrive, false, Auth::id());

        return back()->with('success',
            'กำลังเก็บอีเวนต์นี้ไว้ในผลงาน — ระบบจะลบไฟล์ต้นฉบับและเก็บภาพตัวอย่างกับภาพหน้าปกไว้'
        );
    }

    /**
     * Toggle the `is_portfolio` flag — pins the event so it's always
     * displayed on the portfolio page, and makes it immune to the
     * full-delete half of the retention command.
     */
    public function togglePortfolio(Request $request, Event $event)
    {
        $this->authorizePhotographer($event);

        $event->is_portfolio = !$event->is_portfolio;
        $event->save();

        return back()->with(
            'success',
            $event->is_portfolio
                ? 'ปักหมุดอีเวนต์นี้เป็นผลงานถาวรแล้ว'
                : 'ยกเลิกการปักหมุดผลงานแล้ว'
        );
    }

    /**
     * Ensure the photographer owns this event.
     */
    private function authorizePhotographer(Event $event): void
    {
        if ((int) $event->photographer_id !== (int) Auth::id()) {
            abort(403, 'คุณไม่มีสิทธิ์จัดการอีเวนต์นี้');
        }
    }

    /* ────────────────── Cascading location picker (AJAX) ──────────────────
     * Mirrors the admin endpoints (Admin\EventController::getDistricts /
     * getSubdistricts) but mounted under the photographer prefix so the
     * create/edit form's <select> can populate without needing admin
     * privileges. The data is public reference (Thai government
     * geographical codes) — read-only, cacheable, no PII.
     * ────────────────────────────────────────────────────────────────── */

    /** GET /photographer/api/locations/districts?province_id={id} */
    public function getDistricts(Request $request)
    {
        $provinceId = (int) $request->input('province_id', 0);
        if ($provinceId <= 0) {
            return response()->json([]);
        }
        $districts = \App\Models\ThaiDistrict::where('province_id', $provinceId)
            ->orderBy('name_th')
            ->get(['id', 'name_th', 'name_en']);
        return response()->json($districts);
    }

    /** GET /photographer/api/locations/subdistricts?district_id={id} */
    public function getSubdistricts(Request $request)
    {
        $districtId = (int) $request->input('district_id', 0);
        if ($districtId <= 0) {
            return response()->json([]);
        }
        $subdistricts = \App\Models\ThaiSubdistrict::where('district_id', $districtId)
            ->orderBy('name_th')
            ->get(['id', 'name_th', 'name_en', 'zip_code']);
        return response()->json($subdistricts);
    }

    /* ─────────────────── Enriched fields (2026-05-01) ───────────────────
     * The Extra-Info card on the create/edit forms posts a dozen
     * optional fields driving Schema.org Event JSON-LD + the public
     * event page sections. Centralising the rules + the array→DB
     * shaping here keeps store() / update() readable and ensures the
     * two never diverge.
     * ─────────────────────────────────────────────────────────────── */

    /**
     * Validation rules for the enrichment fields. Returned as a plain
     * array so callers `array_merge` it into their main rule set.
     *
     * Two text fields (highlights_text / tags_text) are accepted as
     * raw form input — the array shape stored in DB is computed by
     * extractEnrichmentPayload() since arrays from textarea/csv don't
     * fit cleanly into a single validation rule.
     */
    private function enrichmentValidationRules(): array
    {
        return [
            'start_time'         => 'nullable|date_format:H:i',
            'end_time'           => 'nullable|date_format:H:i|after_or_equal:start_time',
            'venue_name'         => 'nullable|string|max:200',
            'organizer'          => 'nullable|string|max:200',
            'event_type'         => 'nullable|string|max:50|in:'
                . implode(',', array_keys(\App\Models\Event::eventTypeOptions())),
            'expected_attendees' => 'nullable|integer|min:0|max:1000000',
            'highlights_text'    => 'nullable|string|max:2000',
            'tags_text'          => 'nullable|string|max:1000',
            'contact_phone'      => 'nullable|string|max:30',
            'contact_email'      => 'nullable|email|max:150',
            'website_url'        => 'nullable|url|max:500',
            'facebook_url'       => 'nullable|url|max:500',
            'dress_code'         => 'nullable|string|max:200',
            'parking_info'       => 'nullable|string|max:500',
            // Cascading location picker — `exists` checks confirm the
            // posted IDs match real Thai gov reference rows so we can't
            // be tricked into storing arbitrary integers.
            'province_id'        => 'nullable|integer|exists:thai_provinces,id',
            'district_id'        => 'nullable|integer|exists:thai_districts,id',
            'subdistrict_id'     => 'nullable|integer|exists:thai_subdistricts,id',
            'location_detail'    => 'nullable|string|max:500',
        ];
    }

    /**
     * Shape the enriched fields into the array passed to
     * Event::create/$event->update.
     *
     *   - text fields → trimmed strings (or null when blank)
     *   - highlights_text → array of non-empty trimmed lines
     *   - tags_text → array of non-empty trimmed comma-separated
     *     values, lowercased + de-duplicated for consistent storage
     *
     * Both array fields are returned as PHP arrays — the model casts
     * them to JSON on the way to Postgres.
     */
    private function extractEnrichmentPayload(Request $request, array $validated): array
    {
        $clean = function (?string $v): ?string {
            $v = trim((string) $v);
            return $v === '' ? null : $v;
        };

        // Highlights — split textarea on newlines, drop blanks.
        $highlightsRaw = (string) ($validated['highlights_text'] ?? '');
        $highlights = collect(preg_split('/\r?\n/', $highlightsRaw))
            ->map(fn ($s) => trim((string) $s))
            ->filter(fn ($s) => $s !== '')
            ->values()
            ->all();

        // Tags — split CSV, normalize lower-case, dedupe, drop blanks.
        $tagsRaw = (string) ($validated['tags_text'] ?? '');
        $tags = collect(explode(',', $tagsRaw))
            ->map(fn ($s) => mb_strtolower(trim((string) $s)))
            ->filter(fn ($s) => $s !== '')
            ->unique()
            ->values()
            ->all();

        return [
            'start_time'         => $clean($validated['start_time'] ?? null),
            'end_time'           => $clean($validated['end_time'] ?? null),
            'venue_name'         => $clean($validated['venue_name'] ?? null),
            'organizer'          => $clean($validated['organizer'] ?? null),
            'event_type'         => $clean($validated['event_type'] ?? null),
            'expected_attendees' => $validated['expected_attendees'] ?? null,
            'highlights'         => empty($highlights) ? null : $highlights,
            'tags'               => empty($tags) ? null : $tags,
            'contact_phone'      => $clean($validated['contact_phone'] ?? null),
            'contact_email'      => $clean($validated['contact_email'] ?? null),
            'website_url'        => $clean($validated['website_url'] ?? null),
            'facebook_url'       => $clean($validated['facebook_url'] ?? null),
            'dress_code'         => $clean($validated['dress_code'] ?? null),
            'parking_info'       => $clean($validated['parking_info'] ?? null),
            // Cascading picker — store nulls when the photographer
            // didn't pick a value so the row stays valid (the columns
            // are nullable in the schema).
            'province_id'        => !empty($validated['province_id'])    ? (int) $validated['province_id']    : null,
            'district_id'        => !empty($validated['district_id'])    ? (int) $validated['district_id']    : null,
            'subdistrict_id'     => !empty($validated['subdistrict_id']) ? (int) $validated['subdistrict_id'] : null,
            'location_detail'    => $clean($validated['location_detail'] ?? null),
        ];
    }
}
