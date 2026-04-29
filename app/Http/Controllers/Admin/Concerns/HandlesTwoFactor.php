<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Http\Middleware\RequireTwoFactorSetup;
use App\Models\AppSetting;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * TWO-FACTOR AUTHENTICATION (Admin TOTP)
 *
 * Extracted from SettingsController as part of the modularisation pass.
 * Route names and method signatures are unchanged so this is a drop-in
 * trait — the parent controller just `use`s it.
 *
 * Routes touched:
 *   • admin.settings.2fa                 — twoFactor()
 *   • admin.settings.2fa.enable          — enable2fa()
 *   • admin.settings.2fa.verify          — verify2fa()
 *   • admin.settings.2fa.disable         — disable2fa()
 *   • admin.settings.2fa.enforcement     — updateEnforcement()
 */
trait HandlesTwoFactor
{
    /**
     * Show the 2FA setup/management page.
     */
    public function twoFactor()
    {
        $admin = Auth::guard('admin')->user();
        $twoFa = app(TwoFactorAuthService::class);

        $enabled    = $twoFa->isEnabled($admin->id);
        $secret     = null;
        $qrImageUrl = null;

        if (!$enabled) {
            // Check if we already have a pending secret in the session
            $secret = session('2fa_pending_secret');
            if ($secret) {
                $otpauthUrl = $twoFa->getQRCodeUrl($admin->email, $secret);
                $qrImageUrl = $twoFa->getQRCodeImageUrl($otpauthUrl);
            }
        }

        // Expose the current enforcement state + whether the env is pinning
        // it (in which case the UI toggle is locked). Keeps the view free of
        // env() / AppSetting lookups.
        $enforcementActive = RequireTwoFactorSetup::enforcementActive();
        $enforcementLocked = RequireTwoFactorSetup::enforcementLockedByEnv();

        return view('admin.settings.2fa', compact(
            'enabled', 'secret', 'qrImageUrl', 'admin',
            'enforcementActive', 'enforcementLocked'
        ));
    }

    /**
     * Generate a new secret and store it in the session, then show the QR code.
     */
    public function enable2fa()
    {
        $admin = Auth::guard('admin')->user();
        $twoFa = app(TwoFactorAuthService::class);

        $secret = $twoFa->generateSecret();
        session(['2fa_pending_secret' => $secret]);

        $otpauthUrl = $twoFa->getQRCodeUrl($admin->email, $secret);
        $qrImageUrl = $twoFa->getQRCodeImageUrl($otpauthUrl);
        $enabled    = false;

        // Mirror twoFactor() — blade expects these two flags in both renders.
        $enforcementActive = RequireTwoFactorSetup::enforcementActive();
        $enforcementLocked = RequireTwoFactorSetup::enforcementLockedByEnv();

        return view('admin.settings.2fa', compact(
            'enabled', 'secret', 'qrImageUrl', 'admin',
            'enforcementActive', 'enforcementLocked'
        ));
    }

    /**
     * Verify the TOTP code from the session secret and, if valid, enable 2FA.
     */
    public function verify2fa(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);

        $admin  = Auth::guard('admin')->user();
        $twoFa  = app(TwoFactorAuthService::class);
        $secret = session('2fa_pending_secret');

        if (!$secret) {
            return back()->with('error', 'No pending 2FA setup found. Please start again.');
        }

        if (!$twoFa->verifyCode($secret, $request->input('code'))) {
            return back()->with('error', 'Invalid code. Please try again.');
        }

        $twoFa->enable($admin->id, $secret);
        session()->forget('2fa_pending_secret');

        // Generate backup codes
        $backupCodes = $twoFa->generateBackupCodes();
        $twoFa->saveBackupCodes($admin->id, $backupCodes);
        session(['2fa_backup_codes' => $backupCodes]);

        return redirect()->route('admin.settings.2fa')
            ->with('success', '2FA has been enabled successfully.')
            ->with('backup_codes', $backupCodes);
    }

    /**
     * Disable 2FA after verifying the admin password.
     */
    public function disable2fa(Request $request)
    {
        $request->validate(['password' => 'required|string']);

        $admin = Auth::guard('admin')->user();

        // Block disabling while enrolment is enforced — the middleware would
        // just bounce the admin straight back to setup, so short-circuit here
        // with a clear message. Uses the same resolution as the middleware:
        // env override wins, then AppSetting, default OFF.
        if (RequireTwoFactorSetup::enforcementActive()) {
            return back()->with('error',
                '2FA ถูกกำหนดให้เปิดใช้งานบังคับในระบบ — ปิดการบังคับในหน้าตั้งค่า 2FA ก่อนจึงจะปิด 2FA ได้'
            );
        }

        // Use the model's own password column accessor (works whether the
        // schema uses `password` or `password_hash`). `getAuthPassword()` is
        // the canonical way to read it — matches how the login guard checks.
        if (!Hash::check($request->input('password'), $admin->getAuthPassword())) {
            return back()->with('error', 'Incorrect password. 2FA was not disabled.');
        }

        $twoFa = app(TwoFactorAuthService::class);
        $twoFa->disable($admin->id);

        return redirect()->route('admin.settings.2fa')
            ->with('success', '2FA has been disabled.');
    }

    /**
     * Toggle the global "force every admin to enrol in 2FA" switch.
     *
     * Persisted as AppSetting key `enforce_admin_2fa` ("1" = on, "0" = off).
     * Default on fresh installs is OFF — the first admin gets a chance to
     * configure the system before being pushed into 2FA enrolment.
     *
     * Refuses the write when `ENFORCE_ADMIN_2FA` is set in .env — ops have
     * pinned the value and the AppSetting would be silently ignored anyway.
     */
    public function updateEnforcement(Request $request)
    {
        // Accept the raw checkbox ("1" when ticked, absent when not).
        $request->validate(['enforce' => 'nullable|in:0,1']);

        if (RequireTwoFactorSetup::enforcementLockedByEnv()) {
            return back()->with('error',
                'ค่า ENFORCE_ADMIN_2FA ถูกตั้งไว้ใน .env — แก้ไขผ่านหน้าเว็บไม่ได้'
            );
        }

        $old = AppSetting::get('enforce_admin_2fa', '0');
        $new = $request->input('enforce') === '1' ? '1' : '0';

        AppSetting::set('enforce_admin_2fa', $new);

        // Only log an activity entry when the value actually changed — saves
        // a log row per accidental save. The parent controller owns the
        // ActivityLogger call so we skip it here; AppSetting::set already
        // flushed the cache.
        if ($old !== $new) {
            try {
                \App\Services\ActivityLogger::admin(
                    action: 'settings.2fa_enforcement_updated',
                    target: null,
                    description: $new === '1'
                        ? 'เปิดบังคับใช้ 2FA สำหรับแอดมินทุกบัญชี'
                        : 'ปิดบังคับใช้ 2FA — แอดมินเลือกเปิด/ปิดเองได้',
                    oldValues: ['enforce_admin_2fa' => $old],
                    newValues: ['enforce_admin_2fa' => $new],
                );
            } catch (\Throwable $e) {
                // Activity log is best-effort — don't block the toggle.
            }
        }

        return redirect()->route('admin.settings.2fa')->with('success',
            $new === '1'
                ? 'เปิดการบังคับใช้ 2FA แล้ว — แอดมินที่ยังไม่ได้ตั้งค่าจะถูกบังคับให้ตั้งก่อนใช้งาน'
                : 'ปิดการบังคับใช้ 2FA แล้ว — แอดมินสามารถเลือกเปิด/ปิดเองได้'
        );
    }
}
