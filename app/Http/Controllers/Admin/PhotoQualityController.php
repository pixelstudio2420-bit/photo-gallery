<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\PhotoQualityScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin photo-quality dashboard.
 *
 * Aggregates quality_score from event_photos so admins can see which
 * events have the lowest avg score (likely needs more curation), which
 * have never been scored, and which photos are dragging the average down.
 */
class PhotoQualityController extends Controller
{
    public function __construct(protected PhotoQualityScoringService $svc) {}

    public function index(Request $request)
    {
        $kpis = $this->svc->kpis();

        // Per-event aggregates via subqueries — avoids N+1 while still
        // getting fresh counts. The actual table name is `event_events`
        // (legacy of the early multi-tenant prefix scheme); using the
        // Event model's qualifyColumn keeps us safe against future
        // table-name changes.
        $events = Event::query()
            ->select([
                'event_events.id',
                'event_events.name',
                'event_events.slug',
                'event_events.created_at',
            ])
            ->selectSub(
                EventPhoto::selectRaw('COUNT(*)')
                    ->whereColumn('event_id', 'event_events.id')
                    ->where('status', '!=', 'deleted'),
                'photo_count'
            )
            ->selectSub(
                EventPhoto::selectRaw('ROUND(AVG(quality_score)::numeric, 1)')
                    ->whereColumn('event_id', 'event_events.id')
                    ->whereNotNull('quality_score'),
                'avg_score'
            )
            ->selectSub(
                EventPhoto::selectRaw('MAX(quality_scored_at)')
                    ->whereColumn('event_id', 'event_events.id'),
                'last_scored_at'
            )
            ->orderByDesc('event_events.created_at')
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
