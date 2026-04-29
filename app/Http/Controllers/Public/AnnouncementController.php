<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

/**
 * Customer-facing announcements feed (audience IN ('customer','all')).
 *
 * Routes:
 *   GET /announcements         index — paginated list (also surfaces in homepage banner)
 *   GET /announcements/{slug}  show  — detail page
 */
class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $announcements = Announcement::query()
            ->visibleTo(Announcement::AUDIENCE_CUSTOMER)
            ->forFeed()
            ->with('attachments')
            ->paginate(12);

        return view('public.announcements.index', compact('announcements'));
    }

    public function show(string $slug)
    {
        $announcement = Announcement::query()
            ->visibleTo(Announcement::AUDIENCE_CUSTOMER)
            ->where('slug', $slug)
            ->with('attachments')
            ->firstOrFail();

        $announcement->bumpViewCount();

        return view('public.announcements.show', compact('announcement'));
    }
}
