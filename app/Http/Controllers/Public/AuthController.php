<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use App\Models\SocialLogin;
use App\Models\PhotographerProfile;
use App\Services\LoginRouter;
use App\Services\Usage\AntiAbuseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        // Smart redirect if already authenticated
        $redirect = LoginRouter::redirectIfAuthenticated('customer');
        if ($redirect) {
            return redirect($redirect);
        }

        $seo = app(\App\Services\SeoService::class);
        $seo->title('เข้าสู่ระบบ');

        return view('public.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // ─── Check Admin account first (separate table) ───
        $admin = \App\Models\Admin::where('email', $request->email)->first();
        if ($admin && Hash::check($request->password, $admin->password_hash)) {
            if (!$admin->is_active) {
                return back()->withErrors(['email' => 'บัญชีแอดมินถูกระงับ กรุณาติดต่อ Super Admin'])->onlyInput('email');
            }
            Auth::guard('admin')->login($admin);
            $request->session()->regenerate();
            $admin->update(['last_login_at' => now()]);

            return redirect()->route('admin.dashboard')
                ->with('success', 'เข้าสู่ระบบสำเร็จ — ยินดีต้อนรับ ' . ($admin->role_info['thai'] ?? 'Admin'));
        }

        // ─── Check User account ───
        $user = User::where('email', $request->email)->first();

        if ($user && Hash::check($request->password, $user->password_hash)) {
            if ($user->status === 'suspended') {
                return back()->withErrors(['email' => 'บัญชีถูกระงับ กรุณาติดต่อผู้ดูแลระบบ']);
            }

            Auth::login($user);
            $request->session()->regenerate();

            $user->update([
                'last_login_at' => now(),
                'login_count' => $user->login_count + 1,
            ]);

            // Smart routing based on user role
            $sessionRedirect = session('redirect_after_login');
            session()->forget('redirect_after_login');
            $route = LoginRouter::resolveForUser($user, $sessionRedirect);

            return redirect($route['url'])->with('success', $route['message']);
        }

        return back()->withErrors([
            'email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง',
        ])->onlyInput('email');
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        $seo = app(\App\Services\SeoService::class);
        $seo->title('สมัครสมาชิก');

        return view('public.auth.register');
    }

    public function register(Request $request, AntiAbuseService $antiAbuse)
    {
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'email'      => 'required|email|unique:auth_users,email',
            'password'   => ['required', 'confirmed', Password::min(8)],
            'referral_code' => 'nullable|string|max:64',
            // Optional device fingerprint hash from the frontend (FingerprintJS / similar).
            // We accept it when provided to tighten anti-abuse — never required.
            'device_fingerprint' => 'nullable|string|size:64',
            // Optional province for geo-personalisation (Phase 4). Captured
            // at signup so the user lands on a province-targeted homepage
            // immediately. They can change it later in profile settings.
            'province_id' => 'nullable|integer|exists:thai_provinces,id',
        ]);

        // ── Anti-abuse gate (Free-tier multi-account protection) ──────────
        // Block disposable email providers outright; require Turnstile +
        // email verification when score crosses the flag threshold; let
        // clean signups through with no friction.
        $decision = $antiAbuse->evaluateSignup(
            email:             $request->input('email'),
            ip:                $request->ip(),
            deviceFingerprint: $request->input('device_fingerprint'),
        );
        if ($decision['decision'] === AntiAbuseService::DECISION_BLOCK) {
            Log::info('Signup blocked by anti-abuse', [
                'email_domain' => substr(strrchr((string) $request->email, '@') ?: '', 1),
                'ip'           => $request->ip(),
                'score'        => $decision['score'],
                'reasons'      => $decision['reasons'],
            ]);
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'ไม่สามารถสมัครสมาชิกด้วยอีเมลนี้ได้ — กรุณาใช้อีเมลอื่นหรือ ติดต่อทีมงาน']);
        }
        if ($decision['decision'] === AntiAbuseService::DECISION_REQUIRE_VERIFY) {
            // The 'turnstile' middleware already gates this route in production
            // (see routes/web.php). When the score is suspicious we additionally
            // mark the new account as requiring email verification before
            // any feature access — that cuts the burst-attack ROI to zero.
            // We DON'T block; we just elevate the friction.
            $request->session()->put('signup.require_verify_immediately', true);
        }

        // Preserve any captured referral context across session regenerate.
        $capturedRef = $request->input('referral_code')
            ?: $request->session()->get('referral_code')
            ?: $request->cookie('referral_code');

        $user = User::create([
            'first_name'    => $request->first_name,
            'last_name'     => $request->last_name ?? '',
            'email'         => $request->email,
            'password_hash' => Hash::make($request->password),
            'auth_provider' => 'local',
            // province_id is optional — null = nationwide messaging
            'province_id'   => $request->input('province_id') ?: null,
        ]);

        // Link the pre-signup signal row to the brand-new user_id so
        // post-hoc abuse investigation has a reliable join.
        $antiAbuse->linkSignalToUser($request->input('email'), $request->ip(), (int) $user->id);

        Auth::login($user);
        $request->session()->regenerate();

        // Restore referral_code into the new session so it propagates to the
        // user's first checkout (where ReferralService::apply() validates it).
        if ($capturedRef) {
            $request->session()->put('referral_code', strtoupper(trim((string) $capturedRef)));
        }

        // Mint the new user's personal referral code so they can share
        // immediately from the My Referrals page.
        try {
            app(\App\Services\Marketing\ReferralService::class)->getOrCreateForUser($user);
        } catch (\Throwable $e) {
            \Log::warning('referral.code_create_failed', ['user' => $user->id, 'err' => $e->getMessage()]);
        }

        try {
            // Bell-icon notification is fired by AdminNotificationObserver
            // on User::created — keep only the LINE channel here.
            $line = app(\App\Services\LineNotifyService::class);
            $line->notifyNewRegistration(['name' => $user->first_name . ' ' . $user->last_name, 'email' => $user->email], 'user');
        } catch (\Throwable $e) {
            \Log::error('Notification error: ' . $e->getMessage());
        }

        // Welcome notification in the user's bell — idempotent so SSO
        // re-registration won't duplicate. Best-effort.
        try {
            \App\Models\UserNotification::welcome($user->id, $user->first_name ?: $user->email);
        } catch (\Throwable $e) {
            \Log::warning('welcome notification failed: ' . $e->getMessage());
        }

        // Auto-send verification email
        try {
            $token = \Illuminate\Support\Str::random(64);
            \DB::table('email_verifications')->insert([
                'user_id' => $user->id, 'token' => Hash::make($token), 'created_at' => now(),
            ]);
            $verifyUrl = url("/verify-email?token={$token}&id={$user->id}");
            app(\App\Services\MailService::class)->send(
                $user->email,
                'ยืนยันอีเมลของคุณ — ' . config('app.name'),
                view('emails.verify-email', ['name' => $user->first_name, 'verifyUrl' => $verifyUrl])->render(),
                'email_verification'
            );
        } catch (\Throwable $e) {
            \Log::error('Auto verification email failed: ' . $e->getMessage());
        }

        return redirect()->route('home')
            ->with('success', 'สมัครสมาชิกสำเร็จ! กรุณาตรวจสอบอีเมลเพื่อยืนยันบัญชี');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('logout_success', 'ออกจากระบบสำเร็จ — ขอบคุณที่ใช้บริการ');
    }

    // =========================================================
    // Forgot Password / Reset Password
    // =========================================================

    public function showForgotPassword()
    {
        return view('public.auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            // Don't reveal if email exists — always show success
            return back()->with('success', 'หากอีเมลนี้มีอยู่ในระบบ เราได้ส่งลิงก์รีเซ็ตรหัสผ่านไปแล้ว');
        }

        // Delete old tokens for this email
        \DB::table('password_resets')->where('email', $request->email)->where('guard', 'web')->delete();

        $token = \Illuminate\Support\Str::random(64);
        \DB::table('password_resets')->insert([
            'email'      => $request->email,
            'token'      => Hash::make($token),
            'guard'      => 'web',
            'created_at' => now(),
        ]);

        $resetUrl = url("/reset-password?token={$token}&email=" . urlencode($request->email));

        try {
            app(\App\Services\MailService::class)->passwordReset($request->email, $user->first_name, $resetUrl);
        } catch (\Throwable $e) {
            \Log::error('Password reset email failed: ' . $e->getMessage());
            return back()->withErrors(['email' => 'ไม่สามารถส่งอีเมลได้ กรุณาลองใหม่ภายหลัง']);
        }

        return back()->with('success', 'หากอีเมลนี้มีอยู่ในระบบ เราได้ส่งลิงก์รีเซ็ตรหัสผ่านไปแล้ว');
    }

    public function showResetPassword(Request $request)
    {
        return view('public.auth.reset-password', [
            'token' => $request->query('token'),
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $record = \DB::table('password_resets')
            ->where('email', $request->email)
            ->where('guard', 'web')
            ->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return back()->withErrors(['email' => 'ลิงก์รีเซ็ตไม่ถูกต้องหรือหมดอายุแล้ว']);
        }

        // Check expiry (60 minutes)
        if (now()->diffInMinutes($record->created_at) > 60) {
            \DB::table('password_resets')->where('email', $request->email)->where('guard', 'web')->delete();
            return back()->withErrors(['email' => 'ลิงก์รีเซ็ตหมดอายุแล้ว กรุณาขอลิงก์ใหม่']);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return back()->withErrors(['email' => 'ไม่พบบัญชีนี้ในระบบ']);
        }

        $user->update(['password_hash' => Hash::make($request->password)]);

        \DB::table('password_resets')->where('email', $request->email)->where('guard', 'web')->delete();

        return redirect()->route('login')->with('success', 'เปลี่ยนรหัสผ่านสำเร็จ กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่');
    }

    // =========================================================
    // Email Verification
    // =========================================================

    public function sendVerificationEmail(Request $request)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        if ($user->email_verified) {
            return back()->with('info', 'อีเมลได้รับการยืนยันแล้ว');
        }

        // Delete old tokens
        \DB::table('email_verifications')->where('user_id', $user->id)->delete();

        $token = \Illuminate\Support\Str::random(64);
        \DB::table('email_verifications')->insert([
            'user_id'    => $user->id,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        $verifyUrl = url("/verify-email?token={$token}&id={$user->id}");

        try {
            app(\App\Services\MailService::class)->send(
                $user->email,
                'ยืนยันอีเมลของคุณ — ' . config('app.name'),
                view('emails.verify-email', ['name' => $user->first_name, 'verifyUrl' => $verifyUrl])->render(),
                'email_verification'
            );
        } catch (\Throwable $e) {
            \Log::error('Verification email failed: ' . $e->getMessage());
            return back()->withErrors(['email' => 'ไม่สามารถส่งอีเมลได้ กรุณาลองใหม่']);
        }

        return back()->with('success', 'ส่งอีเมลยืนยันแล้ว กรุณาตรวจสอบกล่องข้อความ');
    }

    public function verifyEmail(Request $request)
    {
        $record = \DB::table('email_verifications')
            ->where('user_id', $request->query('id'))
            ->first();

        if (!$record || !Hash::check($request->query('token', ''), $record->token)) {
            return redirect()->route('home')->withErrors(['email' => 'ลิงก์ยืนยันไม่ถูกต้องหรือหมดอายุ']);
        }

        // Check expiry (24 hours)
        if (now()->diffInHours($record->created_at) > 24) {
            \DB::table('email_verifications')->where('user_id', $request->query('id'))->delete();
            return redirect()->route('home')->withErrors(['email' => 'ลิงก์ยืนยันหมดอายุแล้ว กรุณาขอลิงก์ใหม่']);
        }

        User::where('id', $request->query('id'))->update([
            'email_verified'    => true,
            'email_verified_at' => now(),
        ]);

        \DB::table('email_verifications')->where('user_id', $request->query('id'))->delete();

        return redirect()->route('home')->with('success', 'ยืนยันอีเมลสำเร็จ!');
    }

    // =========================================================
    // Choose Role
    // =========================================================

    public function showChooseRole()
    {
        return view('public.auth.choose-role');
    }

    public function storeChooseRole(Request $request)
    {
        $request->validate([
            'role' => 'required|in:customer,photographer',
        ]);

        if ($request->role === 'photographer') {
            $request->validate([
                'display_name' => 'required|string|max:100',
                'phone'        => 'required|string|max:20',
            ]);

            $user = Auth::user();

            // Generate unique photographer_code
            $code = 'PH' . strtoupper(substr(md5(uniqid()), 0, 8));

            // Check if profile already exists
            $existing = PhotographerProfile::where('user_id', $user->id)->first();

            if (!$existing) {
                PhotographerProfile::create([
                    'user_id'            => $user->id,
                    'display_name'       => $request->display_name,
                    'photographer_code'  => $code,
                    'status'             => 'pending',
                ]);
            }

            // Update phone
            try {
                $user->update(['phone' => $request->phone]);
            } catch (\Throwable $e) {
                \Log::warning('Could not update phone: ' . $e->getMessage());
            }

            return redirect('/')->with('success', 'สมัครช่างภาพสำเร็จ! กรุณารอ Admin อนุมัติบัญชีก่อนใช้งาน');
        }

        // Customer role — nothing to do
        return redirect('/')->with('success', 'ยินดีต้อนรับ! เริ่มต้นซื้อรูปภาพได้เลย');
    }

    // =========================================================
    // Social Login
    // =========================================================

    // ---------- Google ----------

    /**
     * Resolve Google OAuth credentials from app_settings (single source of truth).
     * Admin manages these via /admin/settings.
     * Also pushes them into the Socialite config at runtime.
     */
    private function googleCreds(): array
    {
        $creds = [
            'client_id'     => AppSetting::get('google_client_id'),
            'client_secret' => AppSetting::get('google_client_secret'),
            'redirect'      => route('auth.google.callback'),
        ];

        // Socialite reads from config('services.google.*') — hydrate at runtime
        config(['services.google.client_id'     => $creds['client_id']]);
        config(['services.google.client_secret' => $creds['client_secret']]);
        config(['services.google.redirect'      => $creds['redirect']]);

        return $creds;
    }

    public function redirectGoogle(Request $request)
    {
        $creds = $this->googleCreds();

        if (empty($creds['client_id'])) {
            return redirect()->route('login')
                ->withErrors(['social' => 'ยังไม่ได้ตั้งค่าการเข้าสู่ระบบด้วย Google']);
        }

        $this->captureSignupRole($request);

        return \Laravel\Socialite\Facades\Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Capture optional ?role= and ?connect= query params before
     * redirecting to the social provider, so the callback knows
     * whether we're signing up a customer vs photographer, or
     * just linking a LINE account to an already-logged-in user.
     */
    private function captureSignupRole(Request $request): void
    {
        if ($request->filled('role') && in_array($request->query('role'), ['customer', 'photographer'], true)) {
            session(['signup_role' => $request->query('role')]);
        }

        if ($request->query('connect') === '1' && Auth::check()) {
            session(['line_connect_attach_to_user_id' => Auth::id()]);
        }
    }

    public function callbackGoogle()
    {
        $creds = $this->googleCreds();

        if (empty($creds['client_id'])) {
            return redirect()->route('login')
                ->withErrors(['social' => 'ยังไม่ได้ตั้งค่าการเข้าสู่ระบบด้วย Google']);
        }

        try {
            $socialUser = \Laravel\Socialite\Facades\Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')
                ->withErrors(['social' => 'การเข้าสู่ระบบด้วย Google ล้มเหลว กรุณาลองใหม่']);
        }

        return $this->handleSocialCallback('google', $socialUser->getId(), [
            'email'      => $socialUser->getEmail(),
            'first_name' => $socialUser->offsetGet('given_name') ?? $socialUser->getName(),
            'last_name'  => $socialUser->offsetGet('family_name') ?? '',
            'avatar'     => $socialUser->getAvatar(),
        ]);
    }

    // ---------- LINE ----------

    /**
     * Resolve LINE OAuth credentials from app_settings (single source of truth).
     * Admin manages these via /admin/settings/line.
     */
    private function lineCreds(): array
    {
        return [
            'channel_id'     => AppSetting::get('line_channel_id'),
            'channel_secret' => AppSetting::get('line_channel_secret'),
            'redirect'       => route('auth.line.callback'),
        ];
    }

    public function redirectLine(Request $request)
    {
        $creds = $this->lineCreds();

        if (empty($creds['channel_id'])) {
            return redirect()->route('login')
                ->withErrors(['social' => 'ยังไม่ได้ตั้งค่าการเข้าสู่ระบบด้วย LINE']);
        }

        $this->captureSignupRole($request);

        $state        = \Illuminate\Support\Str::random(40);
        $nonce        = \Illuminate\Support\Str::random(16);
        session(['line_oauth_state' => $state]);

        $params = [
            'response_type' => 'code',
            'client_id'     => $creds['channel_id'],
            'redirect_uri'  => $creds['redirect'],
            'state'         => $state,
            'scope'         => 'profile openid email',
            'nonce'         => $nonce,
        ];

        // bot_prompt=aggressive — when the LINE Login channel is linked
        // to our LINE OA, this surfaces the "Add @loadroop as friend"
        // toggle on the consent screen with default-ON. User signs in
        // once → friend-add happens silently in the same flow. This is
        // the single highest-impact change for LINE friend collection.
        // (See docs: developers.line.biz/en/docs/line-login/link-a-bot)
        // Skipped when the channel isn't linked to a bot — admin can
        // disable via setting if it ever causes friction.
        if (\App\Models\AppSetting::get('line_login_aggressive_friend_add', '1') === '1') {
            $params['bot_prompt'] = 'aggressive';
        }

        return redirect('https://access.line.me/oauth2/v2.1/authorize?' . http_build_query($params));
    }

    public function callbackLine(Request $request)
    {
        $creds = $this->lineCreds();

        if (empty($creds['channel_id']) || empty($creds['channel_secret'])) {
            return redirect()->route('login')
                ->withErrors(['social' => 'ยังไม่ได้ตั้งค่าการเข้าสู่ระบบด้วย LINE']);
        }

        // CSRF check
        if ($request->input('state') !== session('line_oauth_state')) {
            return redirect()->route('login')
                ->withErrors(['social' => 'State ไม่ตรงกัน กรุณาลองใหม่']);
        }

        if ($request->filled('error')) {
            return redirect()->route('login')
                ->withErrors(['social' => 'การเข้าสู่ระบบด้วย LINE ถูกยกเลิก']);
        }

        // Exchange code for token
        $tokenResponse = \Illuminate\Support\Facades\Http::asForm()->post(
            'https://api.line.me/oauth2/v2.1/token',
            [
                'grant_type'    => 'authorization_code',
                'code'          => $request->input('code'),
                'redirect_uri'  => $creds['redirect'],
                'client_id'     => $creds['channel_id'],
                'client_secret' => $creds['channel_secret'],
            ]
        );

        if (!$tokenResponse->successful()) {
            // LINE returns a JSON body like {"error":"invalid_request",
            // "error_description":"..."} on 400s. Without logging this we
            // can't tell whether it's a bad channel type, a redirect_uri
            // mismatch, or expired creds. Log enough to debug, but keep
            // secrets out (no client_secret, no auth code).
            $errBody = $tokenResponse->json() ?: ['raw' => substr($tokenResponse->body(), 0, 500)];
            \Illuminate\Support\Facades\Log::warning('LINE token exchange failed', [
                'status'            => $tokenResponse->status(),
                'error'             => $errBody['error']             ?? null,
                'error_description' => $errBody['error_description'] ?? null,
                'error_code'        => $errBody['error_code']        ?? null,
                'redirect_uri'      => $creds['redirect'],
                'channel_id_tail'   => substr((string) $creds['channel_id'], -4),
            ]);

            // Translate LINE's most common errors into actionable Thai messages
            // so the admin doesn't have to dig through the laravel.log.
            $msg = match ($errBody['error'] ?? '') {
                'invalid_client'  => 'Channel ID หรือ Channel Secret ไม่ถูกต้อง — ตรวจสอบใน Admin → ตั้งค่า → LINE',
                'invalid_grant'   => 'Authorization code หมดอายุ หรือ Callback URL ไม่ตรงกับที่ลงทะเบียนไว้ใน LINE Developers Console',
                'invalid_request' => 'คำขอไม่ถูกต้อง — ตรวจสอบ Callback URL ใน LINE Developers (ต้องเป็น ' . $creds['redirect'] . ' พอดี)',
                default           => 'ไม่สามารถรับ Token จาก LINE ได้ (' . ($errBody['error'] ?? 'HTTP ' . $tokenResponse->status()) . ') — ดูรายละเอียดใน laravel.log',
            };

            return redirect()->route('login')->withErrors(['social' => $msg]);
        }

        $accessToken = $tokenResponse->json('access_token');

        // Fetch profile
        $profileResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
            ->get('https://api.line.me/v2/profile');

        if (!$profileResponse->successful()) {
            return redirect()->route('login')
                ->withErrors(['social' => 'ไม่สามารถดึงข้อมูลโปรไฟล์ LINE ได้']);
        }

        $profile = $profileResponse->json();

        // LINE email requires openid scope — try to get from id_token
        $email = null;
        if ($tokenResponse->json('id_token')) {
            $parts = explode('.', $tokenResponse->json('id_token'));
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                $email   = $payload['email'] ?? null;
            }
        }

        $user = $this->handleSocialCallback('line', $profile['userId'], [
            'email'      => $email,
            'first_name' => $profile['displayName'] ?? 'LINE User',
            'last_name'  => '',
            'avatar'     => $profile['pictureUrl'] ?? null,
        ]);

        // Capture LINE userId + flip is_friend = true.
        //
        // When the channel is linked to our LINE OA and we sent
        // bot_prompt=aggressive in the redirect, the consent screen
        // surfaces "Add @loadroop as friend" with default-ON. Most users
        // don't uncheck it, so completing the OAuth flow effectively
        // means they are now a friend of the OA. We optimistically mark
        // them friend here; the LINE OA webhook (follow/unfollow) is the
        // source of truth and will correct the flag if they tap unfollow
        // later.
        //
        // handleSocialCallback returns either a redirect or sometimes the
        // newly-logged-in user — defensive guard so we don't crash if its
        // return shape changes in the future.
        if (\Illuminate\Support\Facades\Auth::check()) {
            $authedUser = \Illuminate\Support\Facades\Auth::user();
            if ($authedUser) {
                $aggressive = \App\Models\AppSetting::get('line_login_aggressive_friend_add', '1') === '1';
                $authedUser->forceFill([
                    'line_user_id' => $profile['userId'],
                    // Only flip is_friend when aggressive prompt was used —
                    // otherwise we have no evidence they actually added.
                    'line_is_friend'         => $aggressive ? true : ($authedUser->line_is_friend ?? false),
                    'line_friend_changed_at' => $aggressive ? now() : ($authedUser->line_friend_changed_at ?? null),
                ])->save();
            }
        }

        return $user;
    }

    // ---------- Facebook ----------

    /**
     * Resolve Facebook OAuth credentials from app_settings (single source of
     * truth, managed at /admin/settings/social-auth). Falls back to legacy
     * .env values so existing installations keep working after the migration
     * from .env-based config.
     *
     * Also pushes them into config('services.facebook.*') at runtime so any
     * downstream code that still reads config() picks them up.
     */
    private function facebookCreds(): array
    {
        $creds = [
            'app_id'     => AppSetting::get('facebook_client_id',     (string) env('FB_APP_ID', '')),
            'app_secret' => AppSetting::get('facebook_client_secret', (string) env('FB_APP_SECRET', '')),
            'redirect'   => AppSetting::get('facebook_redirect_uri',  (string) env('FB_REDIRECT_URI', '')),
        ];

        // If admin didn't specify a redirect URI, default to our canonical callback
        if (empty($creds['redirect'])) {
            $creds['redirect'] = route('auth.facebook.callback');
        }

        // Hydrate config in case any other code path reads from there
        config(['services.facebook.app_id'     => $creds['app_id']]);
        config(['services.facebook.app_secret' => $creds['app_secret']]);
        config(['services.facebook.redirect'   => $creds['redirect']]);

        return $creds;
    }

    public function redirectFacebook(Request $request)
    {
        $creds = $this->facebookCreds();

        if (empty($creds['app_id'])) {
            return redirect()->route('login')
                ->withErrors(['social' => 'ยังไม่ได้ตั้งค่าการเข้าสู่ระบบด้วย Facebook']);
        }

        $this->captureSignupRole($request);

        $state  = \Illuminate\Support\Str::random(40);
        session(['fb_oauth_state' => $state]);

        $params = http_build_query([
            'client_id'     => $creds['app_id'],
            'redirect_uri'  => $creds['redirect'],
            'state'         => $state,
            'scope'         => 'email,public_profile',
            'response_type' => 'code',
        ]);

        return redirect('https://www.facebook.com/v19.0/dialog/oauth?' . $params);
    }

    public function callbackFacebook(Request $request)
    {
        $creds = $this->facebookCreds();

        if (empty($creds['app_id'])) {
            return redirect()->route('login')
                ->withErrors(['social' => 'ยังไม่ได้ตั้งค่าการเข้าสู่ระบบด้วย Facebook']);
        }

        // CSRF check
        if ($request->input('state') !== session('fb_oauth_state')) {
            return redirect()->route('login')
                ->withErrors(['social' => 'State ไม่ตรงกัน กรุณาลองใหม่']);
        }

        if ($request->filled('error')) {
            return redirect()->route('login')
                ->withErrors(['social' => 'การเข้าสู่ระบบด้วย Facebook ถูกยกเลิก']);
        }

        // Exchange code for access token
        $tokenResponse = \Illuminate\Support\Facades\Http::get(
            'https://graph.facebook.com/v19.0/oauth/access_token',
            [
                'client_id'     => $creds['app_id'],
                'client_secret' => $creds['app_secret'],
                'redirect_uri'  => $creds['redirect'],
                'code'          => $request->input('code'),
            ]
        );

        if (!$tokenResponse->successful()) {
            return redirect()->route('login')
                ->withErrors(['social' => 'ไม่สามารถรับ Token จาก Facebook ได้']);
        }

        $accessToken = $tokenResponse->json('access_token');

        // Fetch user profile
        $profileResponse = \Illuminate\Support\Facades\Http::get(
            'https://graph.facebook.com/me',
            [
                'fields'       => 'id,first_name,last_name,email,picture.type(large)',
                'access_token' => $accessToken,
            ]
        );

        if (!$profileResponse->successful()) {
            return redirect()->route('login')
                ->withErrors(['social' => 'ไม่สามารถดึงข้อมูลโปรไฟล์ Facebook ได้']);
        }

        $profile = $profileResponse->json();

        return $this->handleSocialCallback('facebook', $profile['id'], [
            'email'      => $profile['email'] ?? null,
            'first_name' => $profile['first_name'] ?? 'Facebook User',
            'last_name'  => $profile['last_name'] ?? '',
            'avatar'     => $profile['picture']['data']['url'] ?? null,
        ]);
    }

    // =========================================================
    // Shared social login handler
    // =========================================================

    private function handleSocialCallback(string $provider, string $providerId, array $info)
    {
        $isNewUser  = false;

        // ── Connect-only flow: logged-in user attaching a new social ──
        // (e.g. /auth/line?connect=1 triggered by LineConnectController)
        if (Auth::check() && session('line_connect_attach_to_user_id')) {
            $existingUser = Auth::user();
            SocialLogin::updateOrCreate(
                ['provider' => $provider, 'provider_id' => $providerId],
                ['user_id' => $existingUser->id, 'avatar' => $info['avatar'] ?? null]
            );
            session()->forget('line_connect_attach_to_user_id');
            session()->forget('line_connect_skipped');

            return redirect()->route('home')
                ->with('success', 'เชื่อมต่อ' . ucfirst($provider) . 'สำเร็จ');
        }

        // Look for existing social login record
        $socialLogin = SocialLogin::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->with('user')
            ->first();

        if ($socialLogin && $socialLogin->user) {
            $user = $socialLogin->user;
        } else {
            // Find or create user by email (if we have one)
            $user = null;
            if (!empty($info['email'])) {
                $user = User::where('email', $info['email'])->first();
            }

            if (!$user) {
                // Create a new user account
                $user = User::create([
                    'first_name'    => $info['first_name'],
                    'last_name'     => $info['last_name'] ?? '',
                    'email'         => $info['email'] ?? $provider . '_' . $providerId . '@noemail.local',
                    'password_hash' => Hash::make(\Illuminate\Support\Str::random(32)),
                    'auth_provider' => $provider,
                    'status'        => 'active',
                ]);
                $isNewUser = true;
                // Admin bell notification is fired by AdminNotificationObserver
                // on User::created — no explicit call needed here anymore.
            }

            // Create or update social login record
            SocialLogin::updateOrCreate(
                ['provider' => $provider, 'provider_id' => $providerId],
                ['user_id' => $user->id, 'avatar' => $info['avatar'] ?? null]
            );
        }

        if ($user->status === 'suspended') {
            return redirect()->route('login')
                ->withErrors(['social' => 'บัญชีถูกระงับ กรุณาติดต่อผู้ดูแลระบบ']);
        }

        Auth::login($user);
        request()->session()->regenerate();

        $user->update([
            'last_login_at' => now(),
            'login_count'   => ($user->login_count ?? 0) + 1,
        ]);

        // ── Post-signup role-aware routing ──
        $social     = app(\App\Services\Auth\SocialAuthService::class);
        $intendedRole = session('signup_role'); // set by register page if present
        session()->forget('signup_role');

        if ($isNewUser) {
            // Photographer signup intent → instant Creator tier.
            //
            // Previously this created a status='pending' profile and kicked
            // the user to a multi-step onboarding wizard — an admin had to
            // manually approve before the photographer could even see their
            // dashboard. That's gone. Today a fresh OAuth signup lands
            // straight in as tier='creator' with status='approved': they
            // can upload, create draft events, view stats. Selling (seller
            // tier) kicks in automatically the moment they add a PromptPay
            // number; pro tier unlocks after ID + contract.
            //
            // The status is still 'approved' (not 'pending') because the
            // dashboard middleware checks status for hard-blocks only —
            // "pending" would gate them out of the dashboard entirely.
            if ($intendedRole === 'photographer') {
                $existing = PhotographerProfile::where('user_id', $user->id)->first();
                if (!$existing) {
                    try {
                        PhotographerProfile::create([
                            'user_id'           => $user->id,
                            'display_name'      => trim($user->first_name . ' ' . $user->last_name) ?: 'Photographer',
                            'photographer_code' => 'PH' . strtoupper(substr(md5(uniqid()), 0, 8)),
                            'status'            => 'pending',
                            'tier'              => PhotographerProfile::TIER_CREATOR,
                            // Start in 'draft' so the fast 2-step wizard runs
                            // (PromptPay + contract tick → instant active).
                            // That way a brand-new photographer captures the
                            // payout method before they ever see the dashboard.
                            'onboarding_stage'  => 'draft',
                        ]);
                    } catch (\Throwable $e) { /* ignore — admin will handle */ }
                }

                // Route straight to the 2-step onboarding wizard.
                // It's <1 min, and ensures PromptPay is on file so the
                // "start selling" CTA on the dashboard isn't a dead end.
                return redirect()->route('photographer-onboarding.index')
                    ->with('success', 'ยินดีต้อนรับ! กรอกอีก 2 ขั้นเพื่อเริ่มขายรูปได้ทันที');
            }

            // Customer signup intent (or unspecified) — go to profile after optional LINE connect.
            //
            // LINE is the "zero-friction" signup for Thai customers: no email
            // verification, no password. We stamp a session flag so downstream
            // code (welcome modal, role-picker bypass, attribution) can tell
            // a LINE-native signup apart from other paths without re-parsing
            // the provider string. Flag is read-once and auto-cleared after
            // the first page render via the profile controller.
            if ($provider === 'line') {
                session(['signup_via_line' => true]);
            }

            if ($provider !== 'line' && $social->shouldPromptConnectLine($user)) {
                return redirect()->route('auth.connect-line')
                    ->with('success', 'สมัครสำเร็จ! กรุณาเชื่อมต่อ LINE เพื่อรับแจ้งเตือน');
            }

            return redirect()->route('profile')
                ->with('success', $provider === 'line'
                    ? 'ยินดีต้อนรับ! คุณเข้าสู่ระบบด้วย LINE แล้ว — เริ่มต้นซื้อรูปภาพได้เลย'
                    : 'ยินดีต้อนรับ! เริ่มต้นซื้อรูปภาพได้เลย');
        }

        // ── Returning user ──
        // If admin now requires LINE and user hasn't linked it yet → prompt
        if ($provider !== 'line' && $social->shouldPromptConnectLine($user)) {
            return redirect()->route('auth.connect-line');
        }

        // Smart routing — same logic as regular login
        $sessionRedirect = session('redirect_after_login');
        session()->forget('redirect_after_login');
        $route = LoginRouter::resolveForUser($user, $sessionRedirect);

        return redirect($route['url'])->with('success', $route['message']);
    }
}
