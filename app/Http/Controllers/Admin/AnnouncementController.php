<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\R2MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin CRUD for site-wide announcements.
 *
 * Routes (registered in routes/web.php under 'admin' prefix):
 *   GET    /admin/announcements                index   list (filterable)
 *   GET    /admin/announcements/create         create  empty form
 *   POST   /admin/announcements                store   create
 *   GET    /admin/announcements/{id}/edit      edit    populated form
 *   PUT    /admin/announcements/{id}           update  save changes
 *   DELETE /admin/announcements/{id}           destroy soft-delete
 *   POST   /admin/announcements/{id}/restore   restore undo soft-delete
 *   POST   /admin/announcements/{id}/publish   publish quick-publish (toggle status → published)
 *   POST   /admin/announcements/{id}/archive   archive quick-archive (status → archived)
 *   POST   /admin/announcements/{id}/pin       pin     toggle is_pinned
 *
 *   POST   /admin/announcements/{id}/attachments              storeAttachment
 *   DELETE /admin/announcements/attachments/{attachmentId}    destroyAttachment
 *
 * Image upload: cover image is stored on the announcement row; gallery
 * attachments are individual rows. R2MediaService handles the actual
 * upload + the per-image cleanup on delete.
 */
class AnnouncementController extends Controller
{
    public function __construct(private R2MediaService $media) {}

    /* ─────────────── List ─────────────── */

    public function index(Request $request)
    {
        $q = Announcement::query()->with('attachments')->withTrashed();

        if ($request->filled('audience') && $request->audience !== 'all_filter') {
            $q->where('audience', $request->audience);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $kw = '%' . $request->search . '%';
            $q->where(fn ($w) => $w->where('title', 'ilike', $kw)
                                   ->orWhere('excerpt', 'ilike', $kw));
        }
        if ($request->boolean('only_trashed')) {
            $q->onlyTrashed();
        }

        $announcements = $q->orderByDesc('is_pinned')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total'     => Announcement::count(),
            'live'      => Announcement::active()->count(),
            'draft'     => Announcement::where('status', Announcement::STATUS_DRAFT)->count(),
            'archived'  => Announcement::where('status', Announcement::STATUS_ARCHIVED)->count(),
        ];

        return view('admin.announcements.index', compact('announcements', 'stats'));
    }

    /* ─────────────── Create / Store ─────────────── */

    public function create()
    {
        $announcement = new Announcement([
            'audience' => Announcement::AUDIENCE_ALL,
            'priority' => Announcement::PRIORITY_NORMAL,
            'status'   => Announcement::STATUS_DRAFT,
        ]);
        return view('admin.announcements.form', compact('announcement'));
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        $announcement = DB::transaction(function () use ($data, $request) {
            $a = Announcement::create(array_merge($data, [
                'created_by_admin_id' => Auth::guard('admin')->id(),
                'updated_by_admin_id' => Auth::guard('admin')->id(),
            ]));

            // Cover image — must be uploaded AFTER create so we have the
            // announcement id for the R2 key path. The temp path uploaded
            // here is the canonical key on R2; admins can replace it via
            // the edit form.
            if ($request->hasFile('cover_image')) {
                $a->cover_image_path = $this->uploadCover($a, $request->file('cover_image'));
                $a->save();
            }

            return $a;
        });

        return redirect()->route('admin.announcements.edit', $announcement->id)
            ->with('success', 'สร้างประกาศเรียบร้อยแล้ว');
    }

    /* ─────────────── Edit / Update ─────────────── */

    public function edit($id)
    {
        $announcement = Announcement::with('attachments')->findOrFail($id);
        return view('admin.announcements.form', compact('announcement'));
    }

    public function update(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);
        $data = $this->validatePayload($request, $announcement);

        DB::transaction(function () use ($announcement, $data, $request) {
            // Replace cover image — drop the old R2 object first so we
            // don't accumulate orphaned bytes. Failure to drop the old
            // image is non-fatal; new image still saves.
            if ($request->hasFile('cover_image')) {
                $oldKey = $announcement->cover_image_path;
                $newKey = $this->uploadCover($announcement, $request->file('cover_image'));
                $data['cover_image_path'] = $newKey;
                if ($oldKey && $oldKey !== $newKey) {
                    try { $this->media->forget($oldKey); } catch (\Throwable) {}
                }
            }
            // Explicit "remove cover" checkbox — overrides upload. If both
            // came in (unlikely), the upload still wins because $data was
            // mutated above.
            if ($request->boolean('remove_cover') && !$request->hasFile('cover_image')) {
                if ($announcement->cover_image_path) {
                    try { $this->media->forget($announcement->cover_image_path); } catch (\Throwable) {}
                }
                $data['cover_image_path'] = null;
            }

            $data['updated_by_admin_id'] = Auth::guard('admin')->id();
            $announcement->update($data);
        });

        return redirect()->route('admin.announcements.edit', $announcement->id)
            ->with('success', 'บันทึกประกาศเรียบร้อยแล้ว');
    }

    /* ─────────────── Destroy / Restore ─────────────── */

    public function destroy($id)
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->delete();
        return redirect()->route('admin.announcements.index')
            ->with('success', 'ลบประกาศแล้ว (สามารถกู้คืนได้จากตัวกรอง “ที่ลบแล้ว”)');
    }

    public function restore($id)
    {
        $announcement = Announcement::onlyTrashed()->findOrFail($id);
        $announcement->restore();
        return back()->with('success', 'กู้คืนประกาศเรียบร้อยแล้ว');
    }

    /* ─────────────── Quick actions ─────────────── */

    public function publish($id)
    {
        $a = Announcement::findOrFail($id);
        $a->update([
            'status'              => Announcement::STATUS_PUBLISHED,
            'updated_by_admin_id' => Auth::guard('admin')->id(),
            // If admin clicks publish on a never-scheduled draft, set
            // starts_at=now so the visibility window opens immediately.
            'starts_at'           => $a->starts_at ?: now(),
        ]);
        return back()->with('success', 'เผยแพร่ประกาศแล้ว');
    }

    public function archive($id)
    {
        $a = Announcement::findOrFail($id);
        $a->update([
            'status'              => Announcement::STATUS_ARCHIVED,
            'updated_by_admin_id' => Auth::guard('admin')->id(),
        ]);
        return back()->with('success', 'ซ่อนประกาศแล้ว (archived)');
    }

    public function pin($id): JsonResponse
    {
        $a = Announcement::findOrFail($id);
        $a->update([
            'is_pinned'           => !$a->is_pinned,
            'updated_by_admin_id' => Auth::guard('admin')->id(),
        ]);
        return response()->json([
            'ok'        => true,
            'is_pinned' => $a->is_pinned,
        ]);
    }

    /* ─────────────── Attachments ─────────────── */

    public function storeAttachment(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);
        $request->validate([
            'image'   => 'required|image|max:5120',   // 5MB
            'caption' => 'nullable|string|max:200',
        ]);

        try {
            $upload = $this->media->uploadAnnouncementImage(
                (int) Auth::guard('admin')->id(),
                $announcement->id,
                $request->file('image'),
            );
        } catch (InvalidMediaFileException $e) {
            return back()->withErrors(['image' => $e->getMessage()]);
        }

        $maxSort = (int) $announcement->attachments()->max('sort_order');
        AnnouncementAttachment::create([
            'announcement_id' => $announcement->id,
            'image_path'      => $upload->key,
            'caption'         => $request->caption,
            'sort_order'      => $maxSort + 1,
        ]);

        return back()->with('success', 'อัปโหลดรูปแล้ว');
    }

    public function destroyAttachment($attachmentId)
    {
        $attachment = AnnouncementAttachment::findOrFail($attachmentId);
        $key = $attachment->image_path;
        $attachment->delete();
        if ($key) {
            try { $this->media->forget($key); } catch (\Throwable) {}
        }
        return back()->with('success', 'ลบรูปแล้ว');
    }

    /* ─────────────── Helpers ─────────────── */

    /**
     * Validate + normalise the form payload for both create + update.
     */
    private function validatePayload(Request $request, ?Announcement $existing = null): array
    {
        $rules = [
            'title'      => 'required|string|max:200',
            'slug'       => 'nullable|string|max:220|regex:/^[a-z0-9\-]+$/',
            'excerpt'    => 'nullable|string|max:300',
            'body'       => 'nullable|string|max:50000',
            'audience'   => 'required|in:photographer,customer,all',
            'priority'   => 'required|in:low,normal,high',
            'status'     => 'required|in:draft,published,archived',
            'starts_at'  => 'nullable|date',
            'ends_at'    => 'nullable|date|after_or_equal:starts_at',
            'is_pinned'  => 'nullable|boolean',
            'cta_label'  => 'nullable|string|max:60',
            'cta_url'    => 'nullable|url|max:500',
            'cover_image'  => 'nullable|image|max:5120',   // 5MB
        ];

        // Slug uniqueness — exclude self on edit so admin can save without
        // changing the slug.
        if ($existing) {
            $rules['slug'] .= '|unique:announcements,slug,' . $existing->id;
        } else {
            $rules['slug'] .= '|unique:announcements,slug';
        }

        $validated = $request->validate($rules);

        // Normalise: empty string → null on optional fields so DB stays clean
        foreach (['excerpt', 'body', 'cta_label', 'cta_url', 'starts_at', 'ends_at', 'slug'] as $f) {
            if (array_key_exists($f, $validated) && $validated[$f] === '') {
                $validated[$f] = null;
            }
        }
        $validated['is_pinned'] = $request->boolean('is_pinned');

        // Cover/upload + remove flags are handled in store/update directly
        unset($validated['cover_image']);

        return $validated;
    }

    private function uploadCover(Announcement $announcement, $file): string
    {
        $upload = $this->media->uploadAnnouncementImage(
            (int) Auth::guard('admin')->id(),
            $announcement->id,
            $file,
        );
        return $upload->key;
    }
}
