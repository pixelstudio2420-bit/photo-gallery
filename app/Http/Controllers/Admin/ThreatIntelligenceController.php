<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ThreatIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ThreatIntelligenceController extends Controller
{
    public function __construct(
        private ThreatIntelligenceService $threats
    ) {}

    /**
     * Show the threat-intelligence dashboard.
     */
    public function index()
    {
        $stats = $this->threats->getDashboardStats();

        // Open incidents — paginated for the table
        $incidents = DB::table('threat_incidents')
            ->orderByDesc('created_at')
            ->paginate(20);

        // Active blocks
        $blockedFingerprints = DB::table('threat_blocked_fingerprints')
            ->where('expires_at', '>', now())
            ->orderByDesc('blocked_at')
            ->limit(50)
            ->get();

        // Top threat IPs (already in $stats but add a bit of context)
        $topScores = DB::table('threat_scores')
            ->orderByDesc('score')
            ->limit(20)
            ->get();

        return view('admin.security.threat-intelligence', compact(
            'stats',
            'incidents',
            'blockedFingerprints',
            'topScores'
        ));
    }

    /**
     * Mark an incident as resolved.
     */
    public function resolve(Request $request, int $id)
    {
        $request->validate([
            'resolution' => 'nullable|string|max:500',
        ]);

        $this->threats->resolveIncident(
            $id,
            $request->input('resolution', 'Resolved by admin')
        );

        return back()->with('success', "Incident #{$id} marked as resolved.");
    }

    /**
     * Manually unblock a fingerprint.
     */
    public function unblockFingerprint(Request $request)
    {
        $request->validate([
            'fingerprint' => 'required|string|max:255',
        ]);

        DB::table('threat_blocked_fingerprints')
            ->where('fingerprint', $request->input('fingerprint'))
            ->delete();

        return back()->with('success', 'Fingerprint unblocked.');
    }

    /**
     * Run cleanup on demand.
     */
    public function cleanup(Request $request)
    {
        $days    = (int) $request->input('days', 30);
        $days    = max(7, min(365, $days));
        $deleted = $this->threats->cleanup($days);

        return back()->with('success', "ลบ threat records {$deleted} รายการ (เก่ากว่า {$days} วัน)");
    }
}
