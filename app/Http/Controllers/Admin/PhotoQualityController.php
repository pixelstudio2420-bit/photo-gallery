<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\PhotoQualityScoringService;
use Illuminate\Http\Request;

class PhotoQualityController extends Controller
{
    public function __construct(protected PhotoQualityScoringService $svc) {}

    public function index(Request $request)
    {
        $kpis = $this->svc->kpis();

        // Events sorted by average score desc, with photo counts
        $events = Event::query()
            ->select('events.id', 'events.name', 'events.event_code', 'events.created_at',
                \DB::raw('(SELECT COUNT(*) FROM event_photos WHERE event_id = events.id AND status != "deleted") as photo_count'),
                \DB::raw('(SELECT ROUND(AVG(quality_score),1) FROM event_photos WHERE event_id = events.id AND quality_score IS NOT NULL) as avg_score'),
                \DB::raw('(SELECT MAX(quality_scored_at) FROM event_photos WHERE event_id = events.id) as last_scored_at')
            )
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.photo-quality.index', compact('kpis', 'events'));
    }

    public function show(Event $event)
    {
        $top    = $this->svc->topByEvent($event->id, 24);
        $bottom = $this->svc->lowQualityCandidates($event->id, 40.0)->take(24);

        return view('admin.photo-quality.show', compact('event', 'top', 'bottom'));
    }

    public function rescoreEvent(Event $event)
    {
        $n = $this->svc->scoreEvent($event);
        return back()->with('success', "คำนวณใหม่แล้ว: {$n} รูป");
    }

    public function rescoreAll()
    {
        $result = $this->svc->scoreAllEvents();
        return back()->with('success', "คำนวณใหม่ครบทั้งหมด: {$result['events_scored']} งาน, {$result['photos_scored']} รูป");
    }
}
