<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Auth\SocialAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * LineConnectController
 * ─────────────────────────────────────────────────────────
 * Handles the post-signup "connect your LINE account" prompt.
 *
 *  GET  /auth/connect-line        → show()
 *  POST /auth/connect-line/skip   → skip()
 *
 * The actual OAuth dance is reused from AuthController::redirectLine
 * (users click "Connect" which hits `/auth/line?connect=1`).  The
 * callback will attach a SocialLogin row to the already-logged-in
 * user.  This controller only governs page rendering + skip.
 */
class LineConnectController extends Controller
{
    public function __construct(
        protected SocialAuthService $social
    ) {}

    /**
     * Show the "connect LINE" page.
     *
     *  • Redirect away if feature disabled by admin.
     *  • Redirect away if user already has LINE linked.
     *  • Redirect away if user previously skipped (session flag).
     */
    public function show(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Feature disabled → go home
        if (!$this->social->requiresLineConnect() || !$this->social->isProviderEnabled('line')) {
            return redirect()->route('home');
        }

        // Already linked
        if ($this->social->userHasLineLinked($user)) {
            return redirect()->route('home')
                ->with('info', 'บัญชีของคุณเชื่อมต่อกับ LINE อยู่แล้ว');
        }

        // Previously skipped
        if (session('line_connect_skipped') && $this->social->allowLineConnectSkip()) {
            return redirect()->route('home');
        }

        // Mark that we need to attach social login to current user
        session(['line_connect_attach_to_user_id' => $user->id]);

        return view('public.auth.connect-line');
    }

    /**
     * User chose to skip.  Allowed only if admin permits.
     */
    public function skip(Request $request)
    {
        if (!$this->social->allowLineConnectSkip()) {
            return back()->with('error', 'ระบบกำหนดให้ต้องเชื่อมต่อ LINE ก่อนใช้งาน');
        }

        session(['line_connect_skipped' => true]);

        return redirect()->route('home')
            ->with('info', 'คุณสามารถเชื่อมต่อ LINE ได้ภายหลังจากหน้าโปรไฟล์');
    }
}
