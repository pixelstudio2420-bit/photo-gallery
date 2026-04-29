<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\PhotographerProfile;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\R2MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Fast-track photographer sign-up.
 *
 * Collapsed UX (2 steps):
 *   1. Basic           display_name*, phone, PromptPay*, optional bio/specialties
 *   2. Contract+Submit tick terms → instantly active (seller tier)
 *
 * Everything else (bank details, ID card, portfolio uploads) moved to the
 * profile screen so the photographer can keep selling while they finish the
 * optional paperwork. Uploading ID card later upgrades them to the "Pro"
 * tier, which unlocks admin-featured placements.
 */
class PhotographerOnboardingController extends Controller
{
    const STEPS = ['basic', 'contract'];

    public function index()
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        // Seed with the admin-configured default photographer share (not
        // the platform share). Previous hard-coded 20.00 was the platform
        // cut, which meant new applicants saw the wrong number on their
        // onboarding screen until an admin adjusted it at approval time.
        $defaultPhotographerRate = (float) AppSetting::get(
            'photographer_commission_rate',
            100.0 - (float) AppSetting::get('platform_commission', 20.0)
        );

        $profile = PhotographerProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'display_name'     => trim($user->first_name . ' ' . $user->last_name) ?: $user->username,
                'photographer_code'=> 'PG-' . str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'commission_rate'  => $defaultPhotographerRate,
                'status'           => 'pending',
                'onboarding_stage' => 'draft',
            ]
        );

        $step = $this->resolveStep($profile);
        return view('public.photographer-onboarding.wizard', [
            'profile' => $profile,
            'step'    => $step,
            'steps'   => self::STEPS,
            'specialties' => PhotographerProfile::specialtyOptions(),
        ]);
    }

    /**
     * Single-page "Become Photographer" form for logged-in customers.
     *
     * Replaces the 2-step wizard for the common case: customer who just
     * wants to switch to photographer mode without filling 8 fields.
     *
     * Auto-prefills:
     *   - display_name = first_name + last_name (or username)
     *   - phone = user.phone (if set)
     *
     * Required field: just the agreement checkbox.
     * PromptPay is OPTIONAL — empty = creator tier (cannot sell yet),
     * filled = seller tier (can sell immediately). Photographer can
     * add PromptPay later from their profile to unlock selling.
     */
    public function showQuick()
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        // If already an active photographer, jump to the dashboard.
        $existing = PhotographerProfile::where('user_id', $user->id)->first();
        if ($existing && $existing->onboarding_stage === 'active') {
            return redirect()->route('photographer.dashboard')
                ->with('info', 'คุณเป็นช่างภาพอยู่แล้ว');
        }

        $defaultDisplayName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        if ($defaultDisplayName === '') $defaultDisplayName = (string) ($user->username ?? '');

        return view('public.photographer-onboarding.quick', [
            'user'               => $user,
            'profile'            => $existing,
            'defaultDisplayName' => $defaultDisplayName,
            'defaultPhone'       => (string) ($user->phone ?? ''),
        ]);
    }

    /**
     * One-shot activation. Creates the photographer_profile (or reuses
     * an existing draft) and flips it active in a single POST.
     */
    public function saveQuick(Request $request)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        $data = $request->validate([
            'display_name'     => ['required', 'string', 'max:200'],
            'phone'            => ['nullable', 'string', 'max:30'],
            'promptpay_number' => ['nullable', 'string', 'max:20'],
            'agree'            => ['accepted'],
        ], [
            'display_name.required' => 'กรุณากรอกชื่อที่แสดง',
            'agree.accepted'        => 'กรุณาติ๊กยอมรับเงื่อนไขเพื่อเริ่มต้นเป็นช่างภาพ',
        ]);

        $defaultPhotographerRate = (float) AppSetting::get(
            'photographer_commission_rate',
            100.0 - (float) AppSetting::get('platform_commission', 20.0)
        );

        $profile = PhotographerProfile::firstOrNew(['user_id' => $user->id]);

        // Only set static fields on first creation.
        if (!$profile->exists) {
            $profile->user_id           = $user->id;
            $profile->photographer_code = 'PG-' . str_pad((string) $user->id, 5, '0', STR_PAD_LEFT);
            $profile->commission_rate   = $defaultPhotographerRate;
        }

        $profile->display_name      = $data['display_name'];
        $profile->phone             = ($data['phone'] ?? null) ?: ($profile->phone ?? null);
        $profile->promptpay_number  = ($data['promptpay_number'] ?? null) ?: ($profile->promptpay_number ?? null);
        $profile->contract_signed_at = now();
        $profile->contract_signer_ip = $request->ip();
        $profile->onboarding_stage  = 'active';
        $profile->status            = 'approved';
        $profile->save();

        // Compute tier (seller if PromptPay filled, else creator)
        try {
            if (method_exists($profile, 'syncTier')) {
                $profile->syncTier();
            }
        } catch (\Throwable $e) {
            Log::warning('quick syncTier failed', ['err' => $e->getMessage()]);
        }

        // Provision Free subscription + sync profile cache so storage quota
        // and feature flags follow the plan from day one. Without this,
        // the photographer would see the legacy tier-based quota (5 GB)
        // instead of the Free plan's 2 GB, and feature gates would fall
        // through to the synthetic free fallback.
        try {
            $subs = app(\App\Services\SubscriptionService::class);
            $sub  = $subs->ensureFreeSubscription($profile->fresh());
            $subs->syncProfileCache($profile->fresh(), $sub->fresh('plan'));
        } catch (\Throwable $e) {
            Log::warning('Quick upgrade: free sub provisioning failed', [
                'user_id' => $user->id, 'err' => $e->getMessage(),
            ]);
        }

        // Inform admins (FYI only — no approval gate)
        try {
            \App\Models\AdminNotification::create([
                'type'    => 'photographer.activated',
                'title'   => 'ช่างภาพใหม่เข้าระบบ',
                'message' => $profile->display_name . ' เริ่มเป็นช่างภาพแล้ว (ผ่าน quick upgrade)',
                'link'    => 'admin/photographers',
                'ref_id'  => (string) $profile->id,
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Quick photographer activation notify failed', ['err' => $e->getMessage()]);
        }

        $msg = $profile->promptpay_number
            ? 'ยินดีต้อนรับสู่โหมดช่างภาพ — เริ่มสร้างอีเวนต์และขายรูปได้เลย!'
            : 'ยินดีต้อนรับสู่โหมดช่างภาพ — เพิ่ม PromptPay ในโปรไฟล์เมื่อพร้อมเปิดขาย';

        return redirect()->route('photographer.dashboard')->with('success', $msg);
    }

    public function save(Request $request, string $step)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        $profile = PhotographerProfile::where('user_id', $user->id)->firstOrFail();

        match ($step) {
            'basic'     => $this->saveBasic($request, $profile),
            'contract'  => $this->signContractAndActivate($request, $profile),
            // Legacy step names still accepted so existing bookmarks / half-
            // filled forms don't 404. They all fall through to a sensible
            // handler. (`identity` was the old ID-card upload step — now a
            // no-op redirect since ID Card was removed from the system.)
            'portfolio' => $this->savePortfolio($request, $profile),
            'bank'      => $this->saveBasic($request, $profile),
            'identity'  => null,  // ID Card upload removed
            'submit'    => $this->signContractAndActivate($request, $profile),
            default     => abort(404),
        };

        return redirect()->route('photographer-onboarding.index')
            ->with('success', 'บันทึกเรียบร้อย');
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Step 1 — Basic (identity + payout combined)
    // ═══════════════════════════════════════════════════════════════════

    protected function saveBasic(Request $request, PhotographerProfile $profile): void
    {
        $data = $request->validate([
            'display_name'      => ['required', 'string', 'max:200'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'bio'               => ['nullable', 'string', 'max:2000'],
            'specialties'       => ['nullable', 'array'],
            'specialties.*'     => ['string', 'in:' . implode(',', array_keys(PhotographerProfile::specialtyOptions()))],
            'years_experience'  => ['nullable', 'integer', 'min:0', 'max:80'],
            'promptpay_number'  => ['required', 'string', 'max:20'],
            'bank_account_name' => ['nullable', 'string', 'max:200'],
        ], [
            'display_name.required'     => 'กรุณากรอกชื่อที่แสดง',
            'promptpay_number.required' => 'กรุณากรอก PromptPay (ใช้เป็นช่องทางรับเงินหลัก)',
        ]);

        $profile->fill($data);
        $profile->save();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Step 2 — Contract & Instant Activation
    //  Auto-approves to "seller" tier with no admin gate. The Pro tier
    //  (admin-featured) is still gated by ID card + admin review, handled
    //  from the profile page.
    // ═══════════════════════════════════════════════════════════════════

    protected function signContractAndActivate(Request $request, PhotographerProfile $profile): void
    {
        // Make sure step 1 was filled
        $missing = [];
        if (!$profile->display_name)     $missing[] = 'ชื่อที่แสดง';
        if (!$profile->promptpay_number) $missing[] = 'PromptPay';
        if ($missing) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'submit' => 'กรอกขั้นตอนที่ 1 ยังไม่ครบ: ' . implode(', ', $missing),
            ]);
        }

        $request->validate([
            'agree' => ['accepted'],
        ], [
            'agree.accepted' => 'กรุณาติ๊กยอมรับเงื่อนไขเพื่อเริ่มขายรูป',
        ]);

        $profile->contract_signed_at  = now();
        $profile->contract_signer_ip  = $request->ip();
        $profile->onboarding_stage    = 'active';
        $profile->status              = 'approved';
        $profile->save();

        // Auto-sync seller tier if the model supports it; safe no-op otherwise
        try {
            if (method_exists($profile, 'syncTier')) {
                $profile->syncTier();
            }
        } catch (\Throwable $e) {
            Log::warning('Photographer syncTier failed', ['err' => $e->getMessage()]);
        }

        // Notify admins (informational only — no approval needed)
        try {
            \App\Models\AdminNotification::create([
                'type'    => 'photographer.activated',
                'title'   => 'ช่างภาพเริ่มขายแล้ว',
                'message' => $profile->display_name . ' เริ่มขายรูปแล้ว — ตรวจสอบโปรไฟล์ได้ที่หน้าช่างภาพ',
                'link'    => 'admin/photographers',
                'ref_id'  => (string) $profile->id,
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Photographer activation notify failed', ['err' => $e->getMessage()]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Legacy helpers — kept so back-compat paths (Pro tier upgrade flow,
    //  old bookmarks) still resolve without hitting abort(404).
    // ═══════════════════════════════════════════════════════════════════

    protected function savePortfolio(Request $request, PhotographerProfile $profile): void
    {
        $data = $request->validate([
            'portfolio_url'        => ['nullable', 'url', 'max:500'],
            'portfolio_samples'    => ['nullable', 'array', 'max:5'],
            'portfolio_samples.*'  => ['image', 'max:8192'], // 8 MB/image
        ]);

        $samples = $profile->portfolio_samples ?? [];
        if (!empty($data['portfolio_samples'])) {
            // Each sample lands at:
            //   photographer/portfolio/user_{id}/{uuid}_{name}.{ext}
            // Cascade delete uses R2MediaService::deleteResource() with the
            // same MediaContext — one prefix wipe handles every sample.
            $media  = app(R2MediaService::class);
            $userId = (int) $profile->user_id;

            foreach ($data['portfolio_samples'] as $file) {
                try {
                    $upload    = $media->uploadPortfolioImage($userId, $file);
                    $samples[] = $upload->key;
                } catch (InvalidMediaFileException $e) {
                    // Skip the offending file but keep the others — the user
                    // gets a partial-success message rather than losing every
                    // valid upload because one was malformed.
                    Log::warning('Onboarding portfolio sample rejected', [
                        'user_id' => $userId,
                        'file'    => $file->getClientOriginalName(),
                        'reason'  => $e->getMessage(),
                    ]);
                }
            }
        }
        $profile->portfolio_url = $data['portfolio_url'] ?? $profile->portfolio_url;
        $profile->portfolio_samples = array_slice($samples, -5); // keep last 5
        $profile->save();
    }

    // Helpers
    protected function resolveStep(PhotographerProfile $profile): string
    {
        // Already active? Show the done screen.
        if (in_array($profile->onboarding_stage, ['active', 'contract_signed'], true)) {
            return 'done';
        }
        if ($profile->onboarding_stage === 'rejected') {
            return 'rejected';
        }

        // Allow ?step= for direct nav back to a specific step while still in draft
        $requested = (string) request('step', '');
        if ($requested && in_array($requested, self::STEPS, true)) {
            return $requested;
        }

        // Auto-advance to contract once basics are filled
        if ($profile->display_name && $profile->promptpay_number) {
            return 'contract';
        }
        return 'basic';
    }
}
