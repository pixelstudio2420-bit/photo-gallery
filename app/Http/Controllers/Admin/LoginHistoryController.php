<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use Illuminate\Http\Request;

class LoginHistoryController extends Controller
{
    /**
     * Paginated list of login history with filters.
     */
    public function index(Request $request)
    {
        // â”€â”€ Stats â”€â”€
        $stats = [
            'total_logins' => LoginHistory::where('event_type', 'login')->count(),
            'failed_24h'   => LoginHistory::where('event_type', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'suspicious_7d' => LoginHistory::where('is_suspicious', true)
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'unique_ips_24h' => LoginHistory::where('created_at', '>=', now()->subDay())
                ->distinct('ip_address')
                ->count('ip_address'),
        ];

        // â”€â”€ Query with filters â”€â”€
        $logs = LoginHistory::with(['user', 'admin'])
            ->when($request->filled('date_from'), function ($q) use ($request) {
                $q->where('created_at', '>=', $request->input('date_from'));
            })
            ->when($request->filled('date_to'), function ($q) use ($request) {
                $q->where('created_at', '<=', $request->input('date_to') . ' 23:59:59');
            })
            ->when($request->filled('guard'), function ($q) use ($request) {
                $q->where('guard', $request->input('guard'));
            })
            ->when($request->filled('event_type'), function ($q) use ($request) {
                $q->where('event_type', $request->input('event_type'));
            })
            ->when($request->boolean('suspicious'), function ($q) {
                $q->where('is_suspicious', true);
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $s = $request->input('q');
                $q->where(function ($q2) use ($s) {
                    $q2->whereHas('user', function ($u) use ($s) {
                           $u->where('first_name', 'ilike', "%{$s}%")
                             ->orWhere('last_name', 'ilike', "%{$s}%")
                             ->orWhere('email', 'ilike', "%{$s}%");
                       })
                       ->orWhereHas('admin', function ($a) use ($s) {
                           $a->where('first_name', 'ilike', "%{$s}%")
                             ->orWhere('last_name', 'ilike', "%{$s}%")
                             ->orWhere('email', 'ilike', "%{$s}%");
                       })
                       ->orWhere('ip_address', 'ilike', "%{$s}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.login-history.index', compact('logs', 'stats'));
    }
}
