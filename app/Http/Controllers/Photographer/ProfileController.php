<?php
namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\PhotographerProfile;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\R2MediaService;
use App\Services\PromptPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function index()
    {
        return view('photographer.profile.index', ['photographer' => Auth::user()->photographerProfile]);
    }

    /**
     * Profile update — handles basic fields and avatar upload/removal.
     *
     * Historically this method also owned the seller → pro jump via ID card
     * upload + contract signing. That path was retired on 2026-04-25; Pro is
     * now an admin-only promotion based on `onboarding_stage = 'active'`
     * plus a PromptPay number. The creator → seller jump still happens on
     * the bank page (gated by PromptPay).
     */
    public function update(Request $request, R2MediaService $media)
    {
        $profile = Auth::user()->photographerProfile;
        if (!$profile) {
            return back()->with('error', 'ไม่พบโปรไฟล์ช่างภาพ');
        }

        $validated = $request->validate([
            'display_name'     => ['nullable', 'string', 'max:200'],
            'bio'              => ['nullable', 'string', 'max:2000'],
            'portfolio_url'    => ['nullable', 'url', 'max:500'],
            'province_id'      => ['nullable', 'integer', 'exists:thai_provinces,id'],
            // R2MediaService.auth.avatar enforces 2MB max + jpeg/png/webp.
            // Allow 4MB through the form so the friendly client-side UX
            // doesn't reject before the server-side resize step that the
            // ImageProcessorService runs would have shrunk it. The R2 service
            // re-validates regardless.
            'avatar'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_avatar'    => ['nullable', 'boolean'],
        ]);

        $profile->fill([
            'display_name'  => $validated['display_name']  ?? $profile->display_name,
            'bio'           => $validated['bio']           ?? $profile->bio,
            'portfolio_url' => $validated['portfolio_url'] ?? $profile->portfolio_url,
            // province_id is exists-validated above, so any non-null value
            // is guaranteed to reference a real thai_provinces row. Nulling
            // out is allowed (photographer wants to remove their location).
            'province_id'   => array_key_exists('province_id', $validated)
                ? $validated['province_id']
                : $profile->province_id,
        ]);

        // ── Avatar upload / removal ───────────────────────────────────────
        // Photographer avatars use auth.avatar — the SAME slot as customer
        // avatars (one user = one avatar across the whole platform). The
        // photographer profile stores the canonical R2 key on its own row.
        $avatarChanged = false;

        if ($request->hasFile('avatar')) {
            // Replace flow: delete old avatar BEFORE the new upload so a
            // crashed upload cannot leave two avatars charged against the
            // photographer's storage quota.
            $oldKey = $profile->getRawOriginal('avatar');
            if ($oldKey && !str_starts_with($oldKey, 'http')) {
                try { $media->delete($oldKey); } catch (\Throwable) {}
            }

            try {
                $upload = $media->uploadAvatar((int) $profile->user_id, $request->file('avatar'));
            } catch (InvalidMediaFileException $e) {
                return back()->withErrors(['avatar' => $e->getMessage()]);
            } catch (\Throwable $e) {
                Log::error('Photographer avatar upload failed', [
                    'user_id' => $profile->user_id,
                    'error'   => $e->getMessage(),
                ]);
                return back()->withErrors(['avatar' => 'อัปโหลดรูปไม่สำเร็จ กรุณาลองใหม่']);
            }

            Log::info('Photographer avatar uploaded', [
                'user_id' => $profile->user_id,
                'key'     => $upload->key,
            ]);

            $profile->avatar = $upload->key;
            $avatarChanged = true;
        } elseif ($request->boolean('remove_avatar')) {
            $oldKey = $profile->getRawOriginal('avatar');
            if ($oldKey && !str_starts_with($oldKey, 'http')) {
                try { $media->delete($oldKey); } catch (\Throwable) {}
            }
            $profile->avatar = null;
            $avatarChanged = true;
        }

        $profile->save();
        $profile->syncTier();

        // Craft a message that tells the photographer exactly what just happened.
        if ($avatarChanged && $request->hasFile('avatar')) {
            $msg = 'อัปโหลดรูปโปรไฟล์สำเร็จ';
        } elseif ($avatarChanged) {
            $msg = 'ลบรูปโปรไฟล์แล้ว';
        } else {
            $msg = 'อัพเดทสำเร็จ';
        }

        return back()->with('success', $msg);
    }

    public function setupBank()
    {
        return view('photographer.profile.setup-bank', ['photographer' => Auth::user()->photographerProfile]);
    }

    /**
     * Save PromptPay + bank-account-name (user-typed).
     *
     * PromptPay is the primary payout rail — it's the single field that
     * matters for the creator→seller tier jump. The `bank_account_name`
     * is required alongside the PromptPay number: we no longer pretend to
     * look names up locally (that was a fake/mock; see PromptPayService
     * header for why). The typed name is what Omise submits to ITMX on
     * the first transfer, and ITMX returns the authoritative name for
     * us to cache as `promptpay_verified_name`.
     *
     * When the PromptPay number changes, any prior ITMX-verified name is
     * wiped — the photographer has to earn verification again via the
     * next successful transfer.
     *
     * `syncTier()` runs after save so creator→seller happens in the same
     * request — the redirect lands on a dashboard that already reflects
     * the new tier.
     */
    public function updateBank(Request $request, PromptPayService $promptpay)
    {
        $validated = $request->validate([
            'bank_name'           => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:30',
            // Required when PromptPay is set. Enforced below after we
            // know whether a PromptPay number was supplied.
            'bank_account_name'   => 'nullable|string|max:200',
            'promptpay_number'    => 'nullable|string|max:30',
        ]);

        $profile = Auth::user()->photographerProfile;
        $previousPromptPay = $profile->promptpay_number;

        // Normalise + format-validate the PromptPay ID before touching the
        // DB. Reject clearly malformed input instead of silently saving
        // junk that would fail at payout time.
        $newPromptPay = null;
        if (!empty($validated['promptpay_number'])) {
            $newPromptPay = $promptpay->normalise($validated['promptpay_number']);
            if (!$promptpay->classify($newPromptPay)) {
                return back()->withErrors([
                    'promptpay_number' => 'หมายเลข PromptPay ต้องเป็นเบอร์โทร 10 หลัก หรือเลขบัตรประชาชน 13 หลัก',
                ])->withInput();
            }

            // Require the bank-account name when a PromptPay is saved — we
            // need SOMETHING to submit to ITMX on the first transfer. The
            // photographer copies this from their bank app / book. No name,
            // no payout.
            if (empty($validated['bank_account_name'])) {
                return back()->withErrors([
                    'bank_account_name' => 'กรุณากรอกชื่อบัญชีตามที่ปรากฏในแอพธนาคาร (จำเป็น)',
                ])->withInput();
            }
        }

        // If the number changed (including being cleared), wipe the
        // ITMX-verified data — the cached name belonged to the old number
        // and can't be trusted for the new one.
        $verificationChanged = ($previousPromptPay !== $newPromptPay);

        $profile->update([
            'bank_name'           => $validated['bank_name'] ?? null,
            'bank_account_number' => $validated['bank_account_number'] ?? null,
            'bank_account_name'   => $validated['bank_account_name'] ?? null,
            'promptpay_number'    => $newPromptPay,
            // Clear both ITMX fields when the number changes. When the
            // number stays the same (same edit, different bank details)
            // we keep prior verification to avoid re-requiring a transfer.
            'promptpay_verified_name' => $verificationChanged ? null : $profile->promptpay_verified_name,
            'promptpay_verified_at'   => $verificationChanged ? null : $profile->promptpay_verified_at,
            // Clear cached Omise recipient_id too — name change means
            // Omise's recipient is stale and may carry the wrong
            // bank_account.name on file, so let the provider rebuild it
            // on the next transfer with the new typed name.
            'omise_recipient_id'      => $verificationChanged ? null : $profile->omise_recipient_id,
        ]);

        // Tier may have just changed (creator → seller the moment
        // PromptPay was added). Persist it immediately so downstream
        // checks this request already see the new entitlement.
        $tierChanged = $profile->syncTier();

        $msg = 'บันทึกข้อมูลรับเงินสำเร็จ';
        if ($tierChanged && $profile->isSeller()) {
            $msg .= ' — ยินดีด้วย คุณอัปเกรดเป็น Seller แล้ว สามารถเผยแพร่และเริ่มขายได้ทันที';
        } elseif (!empty($newPromptPay) && !$profile->isPromptPayVerified()) {
            $msg .= ' — ระบบจะยืนยันชื่อบัญชีกับธนาคารโดยอัตโนมัติตอนโอนเงินครั้งแรก';
        }

        return back()->with('success', $msg);
    }

    /**
     * AJAX: Format-validate a PromptPay ID.
     *
     * Called from the setup-bank page as the user types, so they see an
     * instant "รูปแบบถูกต้อง" / "รูปแบบไม่ถูกต้อง" hint before hitting Save.
     * It does NOT (and cannot) look up the account holder's name — that
     * information is only returned by ITMX on a real transfer. See the
     * PromptPayService header for the full rationale.
     *
     * This endpoint intentionally does not persist anything or bump the
     * verified-at timestamp. Persistence is still the job of updateBank().
     */
    public function verifyPromptPay(Request $request, PromptPayService $promptpay)
    {
        $request->validate([
            'promptpay_number' => 'required|string|max:30',
        ]);

        $profile = Auth::user()->photographerProfile;
        if (!$profile) {
            return response()->json([
                'ok'      => false,
                'error'   => 'no_profile',
                'message' => 'ไม่พบโปรไฟล์ช่างภาพ',
            ], 404);
        }

        $result = $promptpay->validateFormat($request->input('promptpay_number'));

        if (!$result['ok']) {
            return response()->json($result, 422);
        }

        return response()->json([
            'ok'      => true,
            'type'    => $result['type'],
            'masked'  => $result['masked'],
            'message' => 'รูปแบบถูกต้อง — กดบันทึกเพื่อจัดเก็บ ระบบจะยืนยันชื่อกับธนาคารตอนโอนเงินครั้งแรก',
        ]);
    }
}
