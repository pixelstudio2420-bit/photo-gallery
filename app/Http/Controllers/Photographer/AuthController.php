<?php
namespace App\Http\Controllers\Photographer;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\LoginRouter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\PhotographerProfile;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        // Smart redirect if already authenticated
        $redirect = LoginRouter::redirectIfAuthenticated('photographer');
        if ($redirect) {
            return redirect($redirect);
        }

        return view('photographer.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // ─── Check Admin account first (separate table) ───
        $admin = Admin::where('email', $request->email)->first();
        if ($admin && Hash::check($request->password, $admin->password_hash)) {
            if (!$admin->is_active) {
                return back()->withErrors(['email' => 'บัญชีแอดมินถูกระงับ กรุณาติดต่อ Super Admin'])->onlyInput('email');
            }
            Auth::guard('admin')->login($admin);
            $request->session()->regenerate();
            $admin->update(['last_login_at' => now()]);

            return redirect()->route('admin.dashboard')
                ->with('success', 'เข้าสู่ระบบสำเร็จ — ตรวจพบบัญชีแอดมิน เปลี่ยนไปแดชบอร์ดแอดมิน');
        }

        // ─── Check User account ───
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            Log::warning('auth.photographer.login_failed', [
                'email' => (string) $request->email,
                'ip'    => $request->ip(),
                'ua'    => substr((string) $request->userAgent(), 0, 200),
            ]);
            return back()->withErrors(['email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'])->onlyInput('email');
        }

        // Check if account is suspended
        if ($user->status === 'suspended') {
            return back()->withErrors(['email' => 'บัญชีถูกระงับ กรุณาติดต่อผู้ดูแลระบบ'])->onlyInput('email');
        }

        // ─── Check photographer profile status ───
        $profile = $user->photographerProfile;

        if (!$profile) {
            // Not a photographer — redirect to customer login with hint
            return redirect()->route('login')
                ->with('info', 'บัญชีนี้ไม่ใช่ช่างภาพ กรุณาเข้าสู่ระบบผ่านหน้าลูกค้า หรือสมัครเป็นช่างภาพ');
        }

        // Hard blocks — admin has actively disabled this account.
        if (in_array($profile->status, ['rejected', 'suspended', 'banned'], true)) {
            $msg = $profile->status === 'rejected'
                ? 'คำขอเป็นช่างภาพถูกปฏิเสธ กรุณาติดต่อผู้ดูแลระบบเพื่อขอข้อมูลเพิ่มเติม'
                : 'บัญชีถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';
            return back()->withErrors(['email' => $msg])->onlyInput('email');
        }

        // Note: 'pending' status is no longer a blocker — new signups are
        // auto-approved as Creator tier, and legacy pending profiles get
        // the same treatment (still bounded by tier gates for selling).

        // ─── Photographer — login ───
        Auth::login($user);
        $request->session()->regenerate();

        $user->update([
            'last_login_at' => now(),
            'login_count'   => ($user->login_count ?? 0) + 1,
        ]);

        Log::info('auth.photographer.login_success', [
            'user_id' => $user->id,
            'email'   => $user->email,
            'ip'      => $request->ip(),
        ]);

        return redirect()->route('photographer.dashboard')
            ->with('success', 'เข้าสู่ระบบสำเร็จ — ยินดีต้อนรับกลับ ช่างภาพ!');
    }
    /**
     * Show the photographer signup page.
     *
     * Three branches:
     *   1. Guest (not logged in)        — full LINE/email signup form
     *   2. Logged in WITHOUT profile    — "Become a photographer" claim form
     *      (user already has User row + at least one social login from
     *       earlier customer signup; we only need to spawn the
     *       PhotographerProfile and route them to connect-google).
     *   3. Logged in WITH profile       — bounce to dashboard (already a
     *      photographer; no point showing the signup page).
     */
    public function showRegister()
    {
        if (Auth::check()) {
            $userId = Auth::id();

            // Already a photographer? Skip the signup page entirely.
            if (PhotographerProfile::where('user_id', $userId)->exists()) {
                return redirect()->route('photographer.dashboard')
                    ->with('info', 'คุณเป็นช่างภาพอยู่แล้ว');
            }

            // What providers are already linked? We use this to skip the
            // LINE step for customers who registered via LINE earlier.
            $links = \DB::table('auth_social_logins')
                ->where('user_id', $userId)
                ->whereIn('provider', ['google', 'line'])
                ->pluck('provider')
                ->toArray();

            return view('photographer.auth.register', [
                'authedUser' => Auth::user(),
                'hasLine'    => in_array('line', $links, true),
                'hasGoogle'  => in_array('google', $links, true),
            ]);
        }

        // Guest path — original signup UI.
        return view('photographer.auth.register', [
            'authedUser' => null,
            'hasLine'    => false,
            'hasGoogle'  => false,
        ]);
    }

    /**
     * "Become a photographer" — claim flow for an existing logged-in user
     * (typically a customer who signed up with LINE earlier and now wants
     * to start selling). Creates the PhotographerProfile in-place and
     * routes them to /photographer/connect-google if Google isn't yet
     * linked, otherwise straight to the dashboard.
     *
     * Idempotent: if a profile already exists, just bounces to dashboard
     * without creating a duplicate.
     */
    public function claim(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('photographer.login')
                ->with('error', 'กรุณาเข้าสู่ระบบก่อนเปิดบัญชีช่างภาพ');
        }

        $user = Auth::user();

        // Already a photographer — no-op.
        if (PhotographerProfile::where('user_id', $user->id)->exists()) {
            return redirect()->route('photographer.dashboard')
                ->with('info', 'คุณเป็นช่างภาพอยู่แล้ว');
        }

        // Spawn the profile. Same defaults as the email-signup path
        // above — Creator tier, auto-approved, ready to upload.
        $photographer = PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH-' . strtoupper(Str::random(8)),
            'display_name'      => $request->input('display_name')
                ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
                ?: 'ช่างภาพใหม่',
            'status'            => 'approved',
            'tier'              => PhotographerProfile::TIER_CREATOR,
            'onboarding_stage'  => 'active',
            'approved_at'       => now(),
        ]);

        // Notifications — same trio as the email register() path.
        try {
            app(\App\Services\LineNotifyService::class)
                ->notifyNewRegistration([
                    'name'  => $user->first_name,
                    'email' => $user->email,
                ], 'photographer');
        } catch (\Throwable $e) {
            Log::error('Line notification error: ' . $e->getMessage());
        }

        try {
            $mail = app(\App\Services\MailService::class);
            $mail->photographerWelcome($user->email, $user->first_name);

            $adminEmail = \App\Models\AppSetting::get(
                'admin_notification_email',
                \App\Models\AppSetting::get('mail_from_email')
            );
            if ($adminEmail) {
                $mail->adminNewPhotographerAlert($adminEmail, [
                    'id'         => $photographer->id,
                    'name'       => $photographer->display_name,
                    'email'      => $user->email,
                    'phone'      => $user->phone ?? null,
                    'created_at' => now()->format('d/m/Y H:i'),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Photographer email failed: ' . $e->getMessage());
        }

        // Promo-funnel hand-off (mirrors register() above) — if the user
        // came in from /promo/checkout/{code} we honour that intent
        // first, jumping straight to the subscription checkout page.
        $intendedPlan = session()->pull('intended_plan');
        if ($intendedPlan) {
            return redirect()->to(
                route('photographer.subscription.plans') . '?plan=' . urlencode($intendedPlan) . '#plan-' . $intendedPlan
            )->with('success', 'เปิดบัญชีช่างภาพสำเร็จ — เลือกวิธีชำระเงินด้านล่างเพื่อเริ่มใช้แผน');
        }

        // Where to go next: if the admin has the require-google flag on,
        // and Google isn't yet linked, push the user through the connect
        // gate. Otherwise straight to the dashboard.
        $require   = \App\Models\AppSetting::get('photographer_require_google_link', '1') === '1';
        $hasGoogle = \DB::table('auth_social_logins')
            ->where('user_id', $user->id)
            ->where('provider', 'google')
            ->exists();

        if ($require && !$hasGoogle) {
            return redirect()->route('photographer.connect-google')
                ->with('success', 'เปิดบัญชีช่างภาพสำเร็จ — ขั้นตอนสุดท้าย: เชื่อม Google เพื่อเริ่มขาย');
        }

        return redirect()->route('photographer.dashboard')
            ->with('success', 'ยินดีต้อนรับสู่ระบบช่างภาพ! เริ่มอัปโหลดผลงานได้เลย');
    }

    /**
     * Show the "Connect Google" page — the required final step after LINE
     * (or email) signup. Shown to any authenticated photographer who hasn't
     * yet linked a Google account.
     *
     * If they already have Google linked, this just bounces them to the
     * dashboard so the URL becomes a no-op for fully-onboarded users.
     */
    public function showConnectGoogle()
    {
        if (!Auth::check()) {
            return redirect()->route('photographer.login');
        }

        // Check both LINE and Google. Photographer is "fully onboarded" if
        // EITHER is linked — we no longer require both.
        $links = \Illuminate\Support\Facades\DB::table('auth_social_logins')
            ->where('user_id', Auth::id())
            ->whereIn('provider', ['google', 'line'])
            ->pluck('provider')
            ->toArray();

        $hasGoogle = in_array('google', $links, true);
        $hasLine   = in_array('line', $links, true);

        // If at least one is linked → page is a no-op, send to dashboard.
        if ($hasGoogle || $hasLine) {
            return redirect()->route('photographer.dashboard');
        }

        return view('photographer.auth.connect-google', [
            'lineLinked' => $hasLine,    // false at this point
            'googleLinked' => $hasGoogle, // false at this point
            'userEmail'  => Auth::user()?->email,
            'userName'   => Auth::user()?->first_name,
        ]);
    }
    public function register(Request $request) {
        $request->validate(['first_name'=>'required','email'=>'required|email|unique:auth_users','password'=>'required|confirmed|min:8','display_name'=>'required']);
        $user = User::create(['first_name'=>$request->first_name,'last_name'=>$request->last_name??'','email'=>$request->email,'password_hash'=>Hash::make($request->password),'auth_provider'=>'local']);
        // Instant Creator tier — no admin approval needed to use the
        // dashboard. Selling is gated at the tier level (adds a PromptPay
        // number → seller; adds ID + contract → pro).
        $photographer = PhotographerProfile::create([
            'user_id'           => $user->id,
            'photographer_code' => 'PH-'.strtoupper(Str::random(8)),
            'display_name'      => $request->display_name,
            'status'            => 'approved',
            'tier'              => PhotographerProfile::TIER_CREATOR,
            'onboarding_stage'  => 'active',
            'approved_at'       => now(),
        ]);
        Auth::login($user);

        // 1. LINE notification
        try {
            $line = app(\App\Services\LineNotifyService::class);
            $line->notifyNewRegistration(['name' => $user->first_name, 'email' => $user->email], 'photographer');
        } catch (\Throwable $e) {
            \Log::error('Line notification error: ' . $e->getMessage());
        }

        // 2. Admin notification — fired automatically by
        // AdminNotificationObserver on PhotographerProfile::created.
        // Direct call removed to prevent duplicate bell-icon entries.

        // 3. Email: photographer welcome + admin alert
        try {
            $mail = app(\App\Services\MailService::class);
            $mail->photographerWelcome($user->email, $user->first_name);

            $adminEmail = \App\Models\AppSetting::get('admin_notification_email', \App\Models\AppSetting::get('mail_from_email'));
            if ($adminEmail) {
                $mail->adminNewPhotographerAlert($adminEmail, [
                    'id'         => $photographer->id,
                    'name'       => $request->display_name,
                    'email'      => $user->email,
                    'phone'      => $user->phone ?? null,
                    'created_at' => now()->format('d/m/Y H:i'),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Photographer email failed: ' . $e->getMessage());
        }

        // Promo-funnel hand-off: if the user came in via /promo/checkout/{code}
        // we stash the plan code in the session there. After the account is
        // live we honour that intent FIRST, before the connect-google gate
        // — paying customers shouldn't be forced through Google linking
        // before they can hand us money. They can connect Google after
        // checkout if the require-google flag is on; the connect-google
        // page is reachable from the photographer dashboard anyway.
        $intendedPlan = session()->pull('intended_plan');
        if ($intendedPlan) {
            return redirect()->to(
                route('photographer.subscription.plans') . '?plan=' . urlencode($intendedPlan) . '#plan-' . $intendedPlan
            )->with('success', 'สมัครสำเร็จ — เลือกวิธีชำระเงินด้านล่างเพื่อเริ่มใช้แผน');
        }

        // Email signup → still must connect Google (replaces email-verify).
        // RequireGoogleLinked middleware would catch this anyway, but
        // explicit redirect produces a smoother UX.
        $require = \App\Models\AppSetting::get('photographer_require_google_link', '1') === '1';
        if ($require) {
            return redirect()->route('photographer.connect-google')
                ->with('success', 'สมัครสำเร็จ — ขั้นตอนสุดท้าย: เชื่อมต่อ Google เพื่อเปิดใช้งาน');
        }
        return redirect()->route('photographer.dashboard')
            ->with('success','ยินดีต้อนรับ! เริ่มอัปโหลดผลงานได้เลย — กรอก PromptPay เมื่อพร้อมเริ่มขาย');
    }
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('photographer.login')
            ->with('logout_success', 'ออกจากระบบสำเร็จ — เซสชันถูกยกเลิกแล้ว');
    }

    public function showForgotPassword()
    {
        return view('photographer.auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if ($user && $user->photographerProfile) {
            \DB::table('password_resets')->where('email', $request->email)->where('guard', 'photographer')->delete();

            $token = Str::random(64);
            \DB::table('password_resets')->insert([
                'email' => $request->email, 'token' => Hash::make($token),
                'guard' => 'photographer', 'created_at' => now(),
            ]);

            $resetUrl = url("/photographer/reset-password?token={$token}&email=" . urlencode($request->email));
            try {
                app(\App\Services\MailService::class)->passwordReset($request->email, $user->first_name, $resetUrl);
            } catch (\Throwable $e) {
                \Log::error('Photographer password reset email failed: ' . $e->getMessage());
            }
        }

        return back()->with('success', 'หากอีเมลนี้มีอยู่ในระบบ เราได้ส่งลิงก์รีเซ็ตรหัสผ่านไปแล้ว');
    }

    public function showResetPassword(Request $request)
    {
        return view('photographer.auth.reset-password', [
            'token' => $request->query('token'),
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required', 'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $record = \DB::table('password_resets')
            ->where('email', $request->email)->where('guard', 'photographer')->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return back()->withErrors(['email' => 'ลิงก์รีเซ็ตไม่ถูกต้องหรือหมดอายุแล้ว']);
        }

        if (now()->diffInMinutes($record->created_at) > 60) {
            \DB::table('password_resets')->where('email', $request->email)->where('guard', 'photographer')->delete();
            return back()->withErrors(['email' => 'ลิงก์รีเซ็ตหมดอายุแล้ว กรุณาขอลิงก์ใหม่']);
        }

        User::where('email', $request->email)->update(['password_hash' => Hash::make($request->password)]);
        \DB::table('password_resets')->where('email', $request->email)->where('guard', 'photographer')->delete();

        return redirect()->route('photographer.login')->with('success', 'เปลี่ยนรหัสผ่านสำเร็จ กรุณาเข้าสู่ระบบ');
    }
}
