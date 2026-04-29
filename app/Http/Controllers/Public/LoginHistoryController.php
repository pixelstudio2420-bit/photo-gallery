<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use Illuminate\Support\Facades\Auth;

class LoginHistoryController extends Controller
{
    /**
     * Show the authenticated user's own recent login history.
     */
    public function index()
    {
        $userId = Auth::id();

        $logs = LoginHistory::forUser($userId)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        // Flag suspicious activity in last 7d
        $hasSuspicious = LoginHistory::forUser($userId)
            ->suspicious()
            ->where('created_at', '>=', now()->subDays(7))
            ->exists();

        $currentIp = request()->ip();

        return view('public.profile.login-history', compact('logs', 'hasSuspicious', 'currentIp'));
    }
}
