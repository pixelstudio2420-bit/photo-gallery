<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Order;
use App\Models\AppSetting;
use App\Models\ThaiProvince;
use App\Models\ThaiDistrict;
use App\Models\ThaiSubdistrict;
use App\Services\ActivityLogger;
use App\Services\GoogleDriveService;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\R2MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    public function index(Request $request)
    {
        // ── Stats (aggregate query + 60s cache) ──
        $stats = Cache::remember('admin.events.stats', 60, function () {
            $defaults = ['total' => 0, 'active' => 0, 'draft' => 0, 'archived' => 0];
            try {
                $r = DB::selectOne(
                    "SELECT COUNT(*) AS total,
                            SUM(status IN ('active','published')) AS active,
                            SUM(status='draft')    AS draft,
                            SUM(status='archived') AS archived
                     FROM event_events"
                );
                if ($r) foreach ($defaults as $k => $_) $defaults[$k] = (int) ($r->{$k} ?? 0);
            } catch (\Throwable $e) {}
            return $defaults;
        });
        $stats['total_revenue'] = Cache::remember('admin.events.total_revenue', 60, function () {
            try {
                return (float) Order::whereIn('status', ['completed', 'paid'])->sum('total');
            } catch (\Throwable $e) {
                return 0.0;
            }
        });

        // ── Query with filters ──
        // Every admin search used to trigger four LIKE '%…%' scans plus a
        // correlated subquery on photographers — full table scans each time.
        // Now: use the FULLTEXT index on (name, description, location) for
        // searches ≥ 4 chars, and fall back to LIKE only for short / special
        // queries. The photographer-name branch still runs but it's gated on
        // the search term actually being a word (avoids the JOIN for empty
        // pagination requests).
        $events = Event::with(['category', 'photographer', 'province'])
            ->withCount(['photos', 'orders', 'reviews'])
            ->when($request->q, function ($q, $s) {
                $s = trim((string) $s);
                if ($s === '') return;

                // Postgres: use ILIKE on indexed text columns. (MATCH/AGAINST is MySQL-only.)
                $q->where(function ($q2) use ($s) {
                    $like = "%{$s}%";
                    $q2->where('name', 'ilike', $like)
                       ->orWhere('description', 'ilike', $like)
                       ->orWhere('location', 'ilike', $like)
                       ->orWhere('location_detail', 'ilike', $like);
                    // Photographer-name branch — runs as a single EXISTS
                    // subquery regardless of result count (not N+1).
                    $q2->orWhereHas('photographer', fn($u) => $u
                        ->where('first_name', 'ilike', "%{$s}%")
                        ->orWhere('last_name', 'ilike', "%{$s}%"));
                });
            })
            ->when($request->category, fn($q, $c) => $q->where('category_id', $c))
            // Status filter — when admin picks a specific status (incl. 'archived'),
            // honour it. When NO status filter is set, hide archived events
            // by default. Without this hide, clicking the row's delete button
            // (which flips status → 'archived') leaves the row visually
            // unchanged and the admin reports "ลบแล้วไม่หาย". Archived
            // events are still reachable via the Status dropdown ("Archived")
            // for recovery / audit.
            ->when(
                $request->status,
                fn($q, $s) => $q->where('status', $s),
                fn($q)     => $q->where('status', '!=', 'archived'),
            )
            ->when($request->province, fn($q, $p) => $q->where('province_id', $p))
            ->when($request->photographer, fn($q, $p) => $q->where('photographer_id', $p))
            ->when($request->sort, function ($q, $sort) {
                return match ($sort) {
                    'name'     => $q->orderBy('name'),
                    'date'     => $q->orderByDesc('shoot_date'),
                    'photos'   => $q->orderByDesc('photos_count'),
                    'orders'   => $q->orderByDesc('orders_count'),
                    'price'    => $q->orderByDesc('price_per_photo'),
                    'oldest'   => $q->orderBy('created_at'),
                    default    => $q->orderByDesc('created_at'),
                };
            }, fn($q) => $q->orderByDesc('created_at'))
            ->paginate(20)
            ->withQueryString();

        $categories = EventCategory::orderBy('name')->get();
        $provinces = ThaiProvince::orderBy('name_th')->get();

        return view('admin.events.index', compact('events', 'stats', 'categories', 'provinces'));
    }

    public function create()
    {
        $categories = EventCategory::orderBy('name')->get();
        $provinces = ThaiProvince::orderBy('name_th')->get();
        // Hard floor of 100 THB/photo — admin may raise this via settings, never lower it.
        $minPrice = max(100.0, (float) AppSetting::get('min_event_price', 100));
        $allowFree = (bool) AppSetting::get('allow_free_events', true);

        return view('admin.events.form', compact('categories', 'provinces', 'minPrice', 'allowFree'));
    }

    public function store(Request $request)
    {
        $minPrice = max(100.0, (float) AppSetting::get('min_event_price', 100));
        $allowFree = (bool) AppSetting::get('allow_free_events', true);

        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'category_id'      => 'required|exists:event_categories,id',
            'event_date'       => 'required|date',
            'province_id'      => 'nullable|exists:thai_provinces,id',
            'district_id'      => 'nullable|exists:thai_districts,id',
            'subdistrict_id'   => 'nullable|exists:thai_subdistricts,id',
            'location_detail'  => 'nullable|string|max:500',
            'price_per_photo'  => "nullable|numeric|min:{$minPrice}",
            'is_free'          => 'nullable|boolean',
            'visibility'       => 'required|in:public,private,password',
            'event_password'   => 'nullable|required_if:visibility,password|string|max:100',
            'status'           => 'required|in:draft,active,published,inactive,archived',
            'photographer_id'  => 'nullable|exists:auth_users,id',
            'cover_image'      => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:10240',
            // Retention policy — optional per-event overrides
            'auto_delete_exempt'       => 'nullable|boolean',
            'retention_days_override'  => 'nullable|integer|min:1|max:3650',
            'auto_delete_at'           => 'nullable|date',
            // Face-search — admin can disable for events that need biometric-sensitive controls
            'face_search_enabled'      => 'nullable|boolean',
        ]);

        // Map form fields → DB columns
        $validated['name'] = $validated['title'];
        $validated['shoot_date'] = $validated['event_date'];
        unset($validated['title'], $validated['event_date'], $validated['cover_image']);
        $validated['slug'] = \Str::slug($validated['name']) . '-' . strtolower(\Str::random(5));
        $validated['is_free'] = $allowFree ? $request->boolean('is_free') : false;
        $validated['created_by_admin'] = true;

        // If free, price is 0; if not free, enforce min price
        if ($validated['is_free']) {
            $validated['price_per_photo'] = 0;
        }

        // Build legacy location text from structured data
        $validated['location'] = $this->buildLocationText($validated);

        // Handle Drive folder URL
        if ($request->filled('drive_folder_url')) {
            $validated['drive_folder_id'] = GoogleDriveService::extractFolderId($request->drive_folder_url);
        }

        // Cover image upload is deferred until AFTER Event::create() so the
        // stored path is scoped to the new event id: events/{id}/cover/...
        $coverFile = $request->hasFile('cover_image') ? $request->file('cover_image') : null;
        unset($validated['cover_image']);

        // Retention policy — normalise booleans + nullable-but-empty fields
        $validated['auto_delete_exempt'] = $request->boolean('auto_delete_exempt');
        if (empty($validated['retention_days_override'])) { unset($validated['retention_days_override']); }
        if (empty($validated['auto_delete_at']))          { unset($validated['auto_delete_at']); }

        // Face-search default ON for new events (matches migration default)
        $validated['face_search_enabled'] = $request->has('face_search_enabled')
            ? $request->boolean('face_search_enabled')
            : true;

        $event = Event::create($validated);

        if ($coverFile) {
            try {
                $upload = app(R2MediaService::class)
                    ->uploadEventCover((int) $event->photographer_id, (int) $event->id, $coverFile);
                $event->cover_image = $upload->key;
                $event->save();
            } catch (InvalidMediaFileException $e) {
                // Event row already created — log + continue. The admin
                // can re-upload the cover from the edit screen rather
                // than losing the entire form submission.
                Log::warning('Admin event cover upload rejected', [
                    'event_id' => $event->id,
                    'reason'   => $e->getMessage(),
                ]);
            }
        }

        ActivityLogger::admin(
            action: 'event.created',
            target: $event,
            description: "สร้างอีเวนต์ \"{$event->name}\" (status: {$event->status}, price/ภาพ: {$event->price_per_photo})",
            oldValues: null,
            newValues: [
                'id'              => (int) $event->id,
                'name'            => $event->name,
                'slug'            => $event->slug,
                'category_id'     => $event->category_id,
                'status'          => $event->status,
                'visibility'      => $event->visibility,
                'price_per_photo' => (float) $event->price_per_photo,
                'is_free'         => (bool) $event->is_free,
                'photographer_id' => $event->photographer_id,
                'drive_folder_id' => $event->drive_folder_id,
            ],
        );

        // Sync Drive photos if folder provided
        if ($event->drive_folder_id) {
            app(GoogleDriveService::class)->syncToCache($event->id, $event->drive_folder_id);
            try {
                $queueService = app(\App\Services\QueueService::class);
                $queueId = $queueService->dispatch('import_drive_photos', $event->id, [
                    'drive_folder_id' => $event->drive_folder_id,
                ]);
                \App\Jobs\ImportDrivePhotosJob::dispatch($event->id, $event->drive_folder_id, $queueId)->onQueue('photos');
            } catch (\Throwable $e) {
                \Log::warning("Failed to dispatch Drive import for event {$event->id}: " . $e->getMessage());
            }
        }

        return redirect()->route('admin.events.show', $event)
            ->with('success', "สร้างอีเวนต์ \"{$event->name}\" สำเร็จ");
    }

    public function show(Event $event)
    {
        $event->load([
            'category', 'photographer', 'province', 'district', 'subdistrict',
            'photos', 'orders.user', 'reviews.user',
        ]);

        $stats = [
            'photos_count'  => $event->photos->count(),
            'orders_count'  => $event->orders->count(),
            'reviews_count' => $event->reviews->count(),
            'avg_rating'    => $event->reviews->avg('rating') ?? 0,
            'total_revenue' => $event->orders->whereIn('status', ['completed', 'paid'])->sum('total'),
            'view_count'    => $event->view_count,
        ];

        $recentOrders = $event->orders()
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentReviews = $event->reviews()
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.events.show', compact('event', 'stats', 'recentOrders', 'recentReviews'));
    }

    public function edit(Event $event)
    {
        $event->load(['category', 'province', 'district', 'subdistrict']);
        $categories = EventCategory::orderBy('name')->get();
        $provinces = ThaiProvince::orderBy('name_th')->get();
        $minPrice = max(100.0, (float) AppSetting::get('min_event_price', 100));
        $allowFree = (bool) AppSetting::get('allow_free_events', true);

        // Preload districts and subdistricts for current selection
        $districts = $event->province_id
            ? ThaiDistrict::where('province_id', $event->province_id)->orderBy('name_th')->get()
            : collect();
        $subdistricts = $event->district_id
            ? ThaiSubdistrict::where('district_id', $event->district_id)->orderBy('name_th')->get()
            : collect();

        return view('admin.events.form', compact('event', 'categories', 'provinces', 'districts', 'subdistricts', 'minPrice', 'allowFree'));
    }

    public function update(Request $request, Event $event)
    {
        $minPrice = max(100.0, (float) AppSetting::get('min_event_price', 100));
        $allowFree = (bool) AppSetting::get('allow_free_events', true);

        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'category_id'      => 'required|exists:event_categories,id',
            'event_date'       => 'required|date',
            'province_id'      => 'nullable|exists:thai_provinces,id',
            'district_id'      => 'nullable|exists:thai_districts,id',
            'subdistrict_id'   => 'nullable|exists:thai_subdistricts,id',
            'location_detail'  => 'nullable|string|max:500',
            'price_per_photo'  => "nullable|numeric|min:{$minPrice}",
            'is_free'          => 'nullable|boolean',
            'visibility'       => 'required|in:public,private,password',
            'event_password'   => 'nullable|required_if:visibility,password|string|max:100',
            'status'           => 'required|in:draft,active,published,inactive,archived',
            'photographer_id'  => 'nullable|exists:auth_users,id',
            'cover_image'      => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:10240',
            // Retention policy — optional per-event overrides
            'auto_delete_exempt'       => 'nullable|boolean',
            'retention_days_override'  => 'nullable|integer|min:1|max:3650',
            'auto_delete_at'           => 'nullable|date',
            // Face-search — admin can disable for events that need biometric-sensitive controls
            'face_search_enabled'      => 'nullable|boolean',
        ]);

        $validated['name'] = $validated['title'];
        $validated['shoot_date'] = $validated['event_date'];
        unset($validated['title'], $validated['event_date'], $validated['cover_image']);
        $validated['slug'] = \Str::slug($validated['name']) . '-' . strtolower(\Str::random(5));
        $validated['is_free'] = $allowFree ? $request->boolean('is_free') : false;

        if ($validated['is_free']) {
            $validated['price_per_photo'] = 0;
        }

        $validated['location'] = $this->buildLocationText($validated);

        if ($request->filled('drive_folder_url')) {
            $validated['drive_folder_id'] = GoogleDriveService::extractFolderId($request->drive_folder_url);
        }

        if ($request->hasFile('cover_image')) {
            $media = app(R2MediaService::class);
            // Wipe old cover off R2 first (CDN cache purged async by the
            // R2MediaService delete pipeline) so the bucket doesn't
            // accumulate orphans across cover swaps.
            if ($event->cover_image) {
                try { $media->delete($event->cover_image); } catch (\Throwable) {}
            }
            try {
                $upload = $media->uploadEventCover(
                    (int) $event->photographer_id,
                    (int) $event->id,
                    $request->file('cover_image'),
                );
                $validated['cover_image'] = $upload->key;
            } catch (InvalidMediaFileException $e) {
                return back()->withInput()->withErrors(['cover_image' => $e->getMessage()]);
            }
        } else {
            // Don't accidentally null out the existing cover when the form
            // is submitted without a new file.
            unset($validated['cover_image']);
        }

        // Retention policy — normalise booleans + blank → null
        $validated['auto_delete_exempt'] = $request->boolean('auto_delete_exempt');
        $validated['retention_days_override'] = $request->filled('retention_days_override')
            ? (int) $request->input('retention_days_override') : null;
        $validated['auto_delete_at'] = $request->filled('auto_delete_at')
            ? $request->input('auto_delete_at') : null;

        // Face-search toggle — default ON (opt-out). Missing checkbox → false.
        $validated['face_search_enabled'] = $request->boolean('face_search_enabled');

        // Audit snapshot BEFORE update
        $oldSnapshot = [
            'name'            => $event->name,
            'status'          => $event->status,
            'visibility'      => $event->visibility,
            'price_per_photo' => (float) $event->price_per_photo,
            'is_free'         => (bool) $event->is_free,
            'photographer_id' => $event->photographer_id,
            'category_id'     => $event->category_id,
        ];

        $event->update($validated);

        ActivityLogger::admin(
            action: 'event.updated',
            target: $event,
            description: "แก้ไขอีเวนต์ \"{$event->name}\"",
            oldValues: $oldSnapshot,
            newValues: [
                'name'            => $event->name,
                'status'          => $event->status,
                'visibility'      => $event->visibility,
                'price_per_photo' => (float) $event->price_per_photo,
                'is_free'         => (bool) $event->is_free,
                'photographer_id' => $event->photographer_id,
                'category_id'     => $event->category_id,
            ],
        );

        return redirect()->route('admin.events.show', $event)
            ->with('success', 'อัปเดตอีเวนต์สำเร็จ');
    }

    public function destroy(Event $event)
    {
        $name = $event->name;
        $oldStatus = $event->status;
        $event->update(['status' => 'archived']);

        ActivityLogger::admin(
            action: 'event.archived',
            target: $event,
            description: "ย้ายอีเวนต์ \"{$name}\" ไปที่เก็บถาวร",
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'archived', 'event_id' => (int) $event->id],
        );

        // Tell the admin WHERE the event went so they don't think the
        // delete failed when the row vanishes from the default list.
        // The default index now hides archived rows; the Status dropdown
        // → Archived option exposes them again for recovery.
        return redirect()->route('admin.events.index')
            ->with('success', "ลบอีเวนต์ \"{$name}\" สำเร็จ — เลือก \"Archived\" จากตัวกรองสถานะเพื่อดู/กู้คืน");
    }

    public function toggleStatus(Event $event)
    {
        $oldStatus = $event->status;

        if (in_array($event->status, ['active', 'published'])) {
            $event->update(['status' => 'draft']);
            $msg = "ปิดการใช้งานอีเวนต์ \"{$event->name}\" สำเร็จ";
        } else {
            $event->update(['status' => 'active']);
            $msg = "เปิดใช้งานอีเวนต์ \"{$event->name}\" สำเร็จ";
        }

        ActivityLogger::admin(
            action: 'event.status_toggled',
            target: $event,
            description: "สลับสถานะอีเวนต์ \"{$event->name}\" ({$oldStatus} → {$event->status})",
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $event->status],
        );

        return back()->with('success', $msg);
    }

    public function qrcode(Event $event)
    {
        return view('admin.events.qrcode', compact('event'));
    }

    // ── Location API endpoints ──

    public function getDistricts(Request $request)
    {
        $districts = ThaiDistrict::where('province_id', $request->province_id)
            ->orderBy('name_th')
            ->get(['id', 'name_th', 'name_en']);

        return response()->json($districts);
    }

    public function getSubdistricts(Request $request)
    {
        $subdistricts = ThaiSubdistrict::where('district_id', $request->district_id)
            ->orderBy('name_th')
            ->get(['id', 'name_th', 'name_en', 'zip_code']);

        return response()->json($subdistricts);
    }

    // ── Helpers ──

    protected function buildLocationText(array $data): string
    {
        $parts = [];
        if (!empty($data['subdistrict_id'])) {
            $sub = ThaiSubdistrict::find($data['subdistrict_id']);
            if ($sub) $parts[] = $sub->name_th;
        }
        if (!empty($data['district_id'])) {
            $dist = ThaiDistrict::find($data['district_id']);
            if ($dist) $parts[] = $dist->name_th;
        }
        if (!empty($data['province_id'])) {
            $prov = ThaiProvince::find($data['province_id']);
            if ($prov) $parts[] = $prov->name_th;
        }
        return implode(', ', $parts);
    }
}
