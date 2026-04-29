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

    public function index()
    {
        $events = Auth::user()->photographerProfile->events()->orderByDesc('created_at')->paginate(20);
        return view('photographer.events.index', compact('events'));
    }

    public function create()
    {
        $categories = EventCategory::active()->get();
        // Hard floor of 100 THB/photo — the admin may raise this via AppSetting but never lower it.
        $minPrice  = max(100.0, (float) AppSetting::get('min_event_price', 100));
        $allowFree = (bool) AppSetting::get('allow_free_events', true);
        return view('photographer.events.create', compact('categories', 'minPrice', 'allowFree'));
    }

    public function store(Request $request)
    {
        $minPrice  = max(100.0, (float) AppSetting::get('min_event_price', 100));
        $allowFree = (bool) AppSetting::get('allow_free_events', true);

        $validated = $request->validate([
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
        ], [
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

        $event = Event::create([
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
        ]);

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
        return view('photographer.events.show', compact('event'));
    }

    public function edit(Event $event)
    {
        $categories = EventCategory::active()->get();
        $minPrice  = max(100.0, (float) AppSetting::get('min_event_price', 100));
        $allowFree = (bool) AppSetting::get('allow_free_events', true);
        return view('photographer.events.edit', compact('event', 'categories', 'minPrice', 'allowFree'));
    }

    public function update(Request $request, Event $event)
    {
        $minPrice  = max(100.0, (float) AppSetting::get('min_event_price', 100));
        $allowFree = (bool) AppSetting::get('allow_free_events', true);

        $validated = $request->validate([
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
        ], [
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

        $event->update([
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
        ]);

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
        $event->delete();
        return redirect()->route('photographer.events.index');
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
}
