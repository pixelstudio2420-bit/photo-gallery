<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

/**
 * Photographer-side feed of announcements (audience IN ('photographer','all')).
 *
 * Routes:
 *   GET /photographer/announcements         index — paginated list
 *   GET /photographer/announcements/{slug}  show  — detail with attachments
 *
 * The list query encodes visibility (status='published' AND in window AND
 * audience matches) in the model scope, so this controller is thin.
 */
class AnnouncementFeedController extends Controller
{
    public function index(Request $request)
    {
        $announcements = Announcement::query()
            ->visibleTo(Announcement::AUDIENCE_PHOTOGRAPHER)
            ->forFeed()
            ->with(['attachments'])
            ->paginate(12);

        return view('photographer.announcements.index', compact('announcements'));
    }

    public function show(string $slug)
    {
        $announcement = Announcement::query()
            ->visibleTo(Announcement::AUDIENCE_PHOTOGRAPHER)
            ->where('slug', $slug)
            ->with('attachments')
            ->firstOrFail();

        // Lazy view-count bump — counts unique-ish opens but doesn't try
        // to be cookie-perfect. View count is for editorial signal, not
        // analytics-grade.
        $announcement->bumpViewCount();

        return view('photographer.announcements.show', compact('announcement'));
    }
}
