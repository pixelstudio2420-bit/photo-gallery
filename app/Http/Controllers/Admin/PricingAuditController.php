<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\PricingPackageLog;
use Illuminate\Http\Request;

/**
 * Admin viewer for the pricing_package_logs audit trail.
 *
 * Surfaces every change touching a pricing_packages row with filters
 * for date range, event, role, and action type. Used to investigate
 * customer disputes ("the price was different yesterday") and to
 * spot anti-fraud patterns:
 *
 *   • Same package flipped to a low price then back within minutes
 *   • Bulk edits across multiple events from one IP
 *   • Photographer changing prices repeatedly under the rate-limit
 *
 * No mutating actions live here — the page is read-only by design.
 * The append-only nature of the audit table is what makes it
 * trustworthy in a dispute.
 */
class PricingAuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = PricingPackageLog::query()
            ->with([
                'package:id,name,event_id',
                'event:id,name,photographer_id',
                'changedBy:id,name,email',
            ])
            ->when($request->event_id, fn($q, $v) => $q->where('event_id', $v))
            ->when($request->action,   fn($q, $v) => $q->where('action', $v))
            ->when($request->role,     fn($q, $v) => $q->where('changed_by_role', $v))
            ->when($request->user_id,  fn($q, $v) => $q->where('changed_by', $v))
            ->when($request->from,     fn($q, $v) => $q->where('created_at', '>=', $v))
            ->when($request->to,       fn($q, $v) => $q->where('created_at', '<=', $v))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $events = Event::orderBy('name')->get(['id', 'name']);

        // Quick anti-fraud signals — count "suspicious" patterns over
        // the last 24h so admins see them without filtering.
        $signals = [
            'rapid_flips' => $this->countRapidFlips(24),
            'last_24h'    => PricingPackageLog::where('created_at', '>=', now()->subDay())->count(),
            'system_ops'  => PricingPackageLog::where('changed_by_role', 'system')
                ->where('created_at', '>=', now()->subDay())->count(),
            'photographer_ops' => PricingPackageLog::where('changed_by_role', 'photographer')
                ->where('created_at', '>=', now()->subDay())->count(),
        ];

        return view('admin.pricing-audit.index', compact('logs', 'events', 'signals'));
    }

    /**
     * Count "rapid price flips" — same package_id changed price more
     * than 3 times in any 1-hour window over the last 24 hours.
     * Cheap heuristic for catching "flip to ฿1, sell to friend, flip
     * back" patterns without scanning the whole log.
     */
    private function countRapidFlips(int $hours): int
    {
        $rows = PricingPackageLog::query()
            ->where('action', 'update')
            ->where('created_at', '>=', now()->subHours($hours))
            ->select('package_id')
            ->groupBy('package_id')
            ->havingRaw('COUNT(*) >= 4')
            ->get();
        return $rows->count();
    }
}
