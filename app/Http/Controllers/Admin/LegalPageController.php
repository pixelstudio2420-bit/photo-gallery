<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use App\Models\LegalPageVersion;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Admin CMS for legal pages (Privacy Policy, Terms of Service, Refund Policy, etc.).
 *
 * Every save snapshots the previous state into `legal_page_versions`, so the
 * full revision history is preserved and any prior version can be restored.
 *
 * All mutations emit ActivityLog entries for the audit trail.
 */
class LegalPageController extends Controller
{
    /* ══════════════════════════ Index ══════════════════════════ */

    public function index()
    {
        $pages = LegalPage::with('updatedBy')
            ->orderByRaw("CASE slug WHEN 'privacy-policy' THEN 3 WHEN 'terms-of-service' THEN 2 WHEN 'refund-policy' THEN 1 ELSE 0 END DESC")
            ->orderBy('title')
            ->get();

        return view('admin.legal.index', compact('pages'));
    }

    /* ══════════════════════════ Create ══════════════════════════ */

    public function create()
    {
        return view('admin.legal.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'slug'             => 'required|string|max:100|alpha_dash|unique:legal_pages,slug',
            'title'            => 'required|string|max:255',
            'content'          => 'nullable|string',
            'effective_date'   => 'nullable|date',
            'is_published'     => 'nullable|boolean',
            'meta_description' => 'nullable|string|max:500',
            'change_note'      => 'nullable|string|max:500',
        ]);

        $adminId = Auth::guard('admin')->id();

        $page = LegalPage::create([
            'slug'             => Str::slug($data['slug']),
            'title'            => $data['title'],
            'content'          => $data['content'] ?? '',
            'version'          => '1.0',
            'effective_date'   => $data['effective_date'] ?? now()->toDateString(),
            'is_published'     => (bool) ($data['is_published'] ?? true),
            'meta_description' => $data['meta_description'] ?? null,
            'last_updated_by'  => $adminId,
        ]);

        // First version row (so history is never empty)
        LegalPageVersion::create([
            'legal_page_id'    => $page->id,
            'version'          => $page->version,
            'title'            => $page->title,
            'content'          => $page->content,
            'meta_description' => $page->meta_description,
            'effective_date'   => $page->effective_date,
            'updated_by'       => $adminId,
            'change_note'      => $data['change_note'] ?? 'Initial version',
        ]);

        ActivityLogger::admin(
            action: 'legal_page.created',
            target: $page,
            description: "สร้างหน้ากฎหมาย {$page->title} ({$page->slug})",
            oldValues: null,
            newValues: [
                'slug'           => $page->slug,
                'title'          => $page->title,
                'version'        => $page->version,
                'effective_date' => optional($page->effective_date)->toDateString(),
                'is_published'   => $page->is_published,
            ],
        );

        return redirect()->route('admin.legal.edit', $page)->with('success', 'สร้างหน้ากฎหมายเรียบร้อย');
    }

    /* ══════════════════════════ Edit / Update ══════════════════════════ */

    public function edit(LegalPage $legal)
    {
        $recentVersions = $legal->versions()->with('admin')->limit(5)->get();
        return view('admin.legal.edit', [
            'page'           => $legal,
            'recentVersions' => $recentVersions,
        ]);
    }

    public function update(Request $request, LegalPage $legal)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'content'          => 'nullable|string',
            'effective_date'   => 'nullable|date',
            'is_published'     => 'nullable|boolean',
            'meta_description' => 'nullable|string|max:500',
            'bump_version'     => 'nullable|boolean',
            'change_note'      => 'nullable|string|max:500',
        ]);

        $adminId = Auth::guard('admin')->id();

        // Old snapshot (for ActivityLog diff)
        $old = [
            'title'            => $legal->title,
            'version'          => $legal->version,
            'effective_date'   => optional($legal->effective_date)->toDateString(),
            'is_published'     => $legal->is_published,
            'meta_description' => $legal->meta_description,
            'content_length'   => strlen((string) $legal->content),
        ];

        // Snapshot the *previous* state into version history before overwriting
        $legal->snapshotCurrent($adminId, $data['change_note'] ?? null);

        // If admin opted to bump version, increment; otherwise keep
        $newVersion = (bool) ($data['bump_version'] ?? false)
            ? LegalPage::bumpVersion($legal->version)
            : $legal->version;

        $legal->update([
            'title'            => $data['title'],
            'content'          => $data['content'] ?? '',
            'version'          => $newVersion,
            'effective_date'   => $data['effective_date'] ?? $legal->effective_date,
            'is_published'     => (bool) ($data['is_published'] ?? false),
            'meta_description' => $data['meta_description'] ?? null,
            'last_updated_by'  => $adminId,
        ]);

        $new = [
            'title'            => $legal->title,
            'version'          => $legal->version,
            'effective_date'   => optional($legal->effective_date)->toDateString(),
            'is_published'     => $legal->is_published,
            'meta_description' => $legal->meta_description,
            'content_length'   => strlen((string) $legal->content),
        ];

        ActivityLogger::admin(
            action: 'legal_page.updated',
            target: $legal,
            description: "แก้ไขหน้ากฎหมาย {$legal->title} (v{$legal->version})",
            oldValues: $old,
            newValues: $new,
        );

        return redirect()->route('admin.legal.edit', $legal)->with('success', 'บันทึกการแก้ไขเรียบร้อย');
    }

    /* ══════════════════════════ Publish toggle ══════════════════════════ */

    public function togglePublish(LegalPage $legal)
    {
        $wasPublished = (bool) $legal->is_published;
        $legal->update([
            'is_published'    => !$wasPublished,
            'last_updated_by' => Auth::guard('admin')->id(),
        ]);

        ActivityLogger::admin(
            action: 'legal_page.publish_toggled',
            target: $legal,
            description: ($wasPublished ? 'ยกเลิกเผยแพร่' : 'เผยแพร่') . " หน้า {$legal->title}",
            oldValues: ['is_published' => $wasPublished],
            newValues: ['is_published' => !$wasPublished],
        );

        return back()->with('success', $legal->fresh()->is_published ? 'เผยแพร่แล้ว' : 'ยกเลิกการเผยแพร่แล้ว');
    }

    /* ══════════════════════════ Destroy ══════════════════════════ */

    public function destroy(LegalPage $legal)
    {
        // Canonical pages are kept — admin should edit/unpublish them, not delete.
        if ($legal->isCanonical()) {
            return back()->with('error', 'ไม่สามารถลบหน้ามาตรฐาน (' . $legal->slug . ') ได้ กรุณายกเลิกการเผยแพร่แทน');
        }

        $snapshot = [
            'id'      => $legal->id,
            'slug'    => $legal->slug,
            'title'   => $legal->title,
            'version' => $legal->version,
        ];

        $legal->delete();

        ActivityLogger::admin(
            action: 'legal_page.deleted',
            target: ['LegalPage', (int) $snapshot['id']],
            description: "ลบหน้ากฎหมาย {$snapshot['title']} ({$snapshot['slug']})",
            oldValues: $snapshot,
            newValues: null,
        );

        return redirect()->route('admin.legal.index')->with('success', 'ลบหน้ากฎหมายเรียบร้อย');
    }

    /* ══════════════════════════ Version History ══════════════════════════ */

    public function history(LegalPage $legal)
    {
        $versions = $legal->versions()->with('admin')->paginate(20);
        return view('admin.legal.history', [
            'page'     => $legal,
            'versions' => $versions,
        ]);
    }

    public function showVersion(LegalPage $legal, LegalPageVersion $version)
    {
        abort_unless($version->legal_page_id === $legal->id, 404);
        $version->load('admin');
        return view('admin.legal.version', [
            'page'    => $legal,
            'version' => $version,
        ]);
    }

    public function restoreVersion(Request $request, LegalPage $legal, LegalPageVersion $version)
    {
        abort_unless($version->legal_page_id === $legal->id, 404);

        $adminId = Auth::guard('admin')->id();

        // Snapshot the current state first, so "restore" itself is undoable
        $legal->snapshotCurrent($adminId, 'ก่อนการ restore เป็น v' . $version->version);

        $old = [
            'version' => $legal->version,
            'title'   => $legal->title,
        ];

        $legal->update([
            'title'            => $version->title,
            'content'          => $version->content,
            'meta_description' => $version->meta_description,
            'effective_date'   => $version->effective_date,
            'version'          => LegalPage::bumpVersion($legal->version),
            'last_updated_by'  => $adminId,
        ]);

        ActivityLogger::admin(
            action: 'legal_page.restored',
            target: $legal,
            description: "คืนค่าหน้ากฎหมาย {$legal->title} จาก v{$version->version} (ตั้งเป็น v{$legal->version})",
            oldValues: $old,
            newValues: [
                'version'            => $legal->version,
                'restored_from_id'   => $version->id,
                'restored_from_ver'  => $version->version,
            ],
        );

        return redirect()->route('admin.legal.edit', $legal)->with('success', "คืนค่าเป็น v{$version->version} เรียบร้อย (ตั้งเป็น v{$legal->version})");
    }
}
