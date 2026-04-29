<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventHealthService;
use Illuminate\Http\Request;

class EventHealthController extends Controller
{
    public function __construct(private EventHealthService $svc) {}

    public function index(Request $request)
    {
        $status = $request->string('status')->toString() ?: null;
        $rows = $this->svc->scoreboard(80, $status);

        $byGrade = [
            'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0,
        ];
        foreach ($rows as $r) {
            $byGrade[$r['grade']] = ($byGrade[$r['grade']] ?? 0) + 1;
        }

        return view('admin.event-health.index', compact('rows', 'status', 'byGrade'));
    }

    public function show(Event $event)
    {
        $score = $this->svc->score($event);
        return view('admin.event-health.show', compact('event', 'score'));
    }
}
