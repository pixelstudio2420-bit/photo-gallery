<?php

namespace App\Services\Payment;

use App\Models\AppSetting;
use App\Models\PaymentSlip;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * SlipVerifier — scores an uploaded payment slip 0–100.
 *
 * Scoring philosophy
 * ──────────────────
 * We reward STRONG signals (exact amount match, SlipOK-confirmed transRef,
 * receiver-account match) heavily, and treat weak signals (image size, EXIF)
 * as small bonuses. The previous version gave too much weight to file
 * validation (which proves nothing about the transaction) and too little to
 * the fields that actually matter.
 *
 *   Maximum breakdown (clamped to 100):
 *     Amount match (tiered)          : up to +30
 *     SlipOK amount verified         :        +15
 *     SlipOK receiver = our bank     :        +15
 *     No duplicate image hash        :        +10
 *     No duplicate ref_code          :        +8
 *     No duplicate SlipOK transRef   :        +12
 *     Transfer date valid & recent   :        +5
 *     Slip time ≥ order time         :        +5
 *     File meta (size/type/dims)     :        +5
 *     EXIF data (small bonus)        :        +3
 *     Base                           :        +2
 *   ─────────────────────────────────────────
 *   Total potential                  :       110  →  capped at 100
 *
 * Fraud flags cap the score at 20 regardless. Strict mode (configurable) can
 * require SlipOK confirmation / receiver match before auto-approve is allowed.
 */
class SlipVerifier
{
    /** Tiered amount-match point values (higher = stricter match). */
    private const AMOUNT_POINTS_EXACT       = 30;  // |Δ| < 0.01 THB
    private const AMOUNT_POINTS_HALF_PCT    = 25;  // within 0.5%
    private const AMOUNT_POINTS_TOLERANCE   = 20;  // within configured tolerance
    private const AMOUNT_POINTS_TWO_PCT     = 10;  // within 2% (very loose)

    public function verify(UploadedFile $file, array $orderData): array
    {
        $path = $file->getRealPath();
        $hash = hash_file('sha256', $path);

        $slipAmount  = (float) ($orderData['transfer_amount'] ?? 0);
        $orderAmount = (float) ($orderData['order_amount'] ?? 0);
        $refCode     = (string) ($orderData['ref_code'] ?? '');
        $transferAt  = $orderData['transfer_date'] ?? null;
        $orderAt     = $orderData['order_created_at'] ?? null;

        // ── Collect raw checks ──────────────────────────────────────────────
        $tolerancePct = max(0.1, min(5.0, (float) AppSetting::get('slip_amount_tolerance_percent', '1')));
        $amountTier = $this->amountMatchTier($slipAmount, $orderAmount, $tolerancePct);

        $checks = [
            'dimensions'       => $this->validateDimensions($path),
            'file_size'        => $this->validateFileSize($file),
            'file_type'        => $this->validateFileType($file),
            'amount_tier'      => $amountTier,                           // 'exact'|'half'|'tolerance'|'two'|'none'
            'duplicate_hash'   => !$this->isDuplicateHash($hash),
            'duplicate_ref'    => !$this->isDuplicateRefCode($refCode),
            'transfer_date'    => $this->validateTransferDate($transferAt),
            'slip_after_order' => $this->validateSlipAfterOrder($transferAt, $orderAt),
        ];
        $hasExif = $this->hasExifData($path);

        // ── SlipOK external API (strongest per-transaction evidence) ────────
        $slipok         = new SlipOKService();
        $slipokResult   = null;   // raw API result
        $normalised     = null;   // flat shape
        $slipokAmount   = false;
        $slipokReceiver = false;
        $slipokDupRef   = false;

        if ($slipok->isEnabled() && $slipok->isConfigured()) {
            $slipokResult = $slipok->verify($path);
            if ($slipokResult['success']) {
                $normalised      = $slipok->normaliseResult($slipokResult['data']);
                $apiAmount       = $normalised['amount'];
                $slipokAmount    = $apiAmount > 0
                    && $orderAmount > 0
                    && abs($apiAmount - $orderAmount) <= max(0.01, $orderAmount * ($tolerancePct / 100));
                $slipokReceiver  = $slipok->matchesOurBankAccount($normalised);
                $slipokDupRef    = $slipok->isDuplicateTransRef($normalised['trans_ref'] ?? null);
            }
        }

        $checks['slipok_enabled']  = $slipok->isEnabled();
        $checks['slipok_success']  = $slipokResult['success'] ?? false;
        $checks['slipok_amount']   = $slipokAmount;
        $checks['slipok_receiver'] = $slipokReceiver;
        $checks['slipok_dup_ref']  = !$slipokDupRef; // inverted: true = OK, no duplicate

        // ── Score build ─────────────────────────────────────────────────────
        $breakdown = $this->buildBreakdown($checks, $hasExif);
        $score     = (int) min(100, array_sum(array_column($breakdown, 'points')));

        // ── Fraud detection (critical — caps score) ─────────────────────────
        $fraudFlags = $this->detectFraud($checks, $file, $hash, $slipokDupRef, $normalised, $orderAmount);
        if (!empty($fraudFlags)) {
            $score = min($score, 20);
        }

        // ── Decide auto-approve ─────────────────────────────────────────────
        $verifyMode       = AppSetting::get('slip_verify_mode', 'manual');
        $threshold        = (int) AppSetting::get('slip_auto_approve_threshold', 80);
        $requireSlipOK    = AppSetting::get('slip_require_slipok_for_auto', '0') === '1';
        $requireReceiver  = AppSetting::get('slip_require_receiver_match', '0') === '1';

        $autoApprove = $verifyMode === 'auto'
            && $score >= $threshold
            && empty($fraudFlags);

        if ($autoApprove && $requireSlipOK) {
            $autoApprove = ($slipokResult['success'] ?? false) && $slipokAmount;
        }
        if ($autoApprove && $requireReceiver) {
            $autoApprove = $slipokReceiver;
        }

        return [
            'score'        => $score,
            'passed'       => $score >= 50,
            'checks'       => $checks,
            'breakdown'    => $breakdown,
            'auto_approve' => $autoApprove,
            'hash'         => $hash,
            'has_exif'     => $hasExif,
            'fraud_flags'  => $fraudFlags,
            'slipok'       => $slipokResult,
            'slipok_data'  => $normalised,
        ];
    }

    /*----------------------------------------------------------------------
    | Scoring
    |----------------------------------------------------------------------*/

    /**
     * Amount match tier — returns how close the slip amount is to the order.
     * Only the highest tier that passes counts (scoring picks by name).
     */
    private function amountMatchTier(float $slipAmount, float $orderAmount, float $tolerancePct): string
    {
        if ($orderAmount <= 0 || $slipAmount <= 0) return 'none';

        $diff = abs($slipAmount - $orderAmount);
        if ($diff < 0.01) return 'exact';
        if ($diff <= $orderAmount * 0.005) return 'half';               // 0.5%
        if ($diff <= $orderAmount * ($tolerancePct / 100)) return 'tolerance';
        if ($diff <= $orderAmount * 0.02) return 'two';                 // legacy 2% band
        return 'none';
    }

    /**
     * Per-check breakdown. Each row records whether the check passed and how
     * many points it contributed. Persisted to DB so admins can audit scoring.
     *
     * @return array<string,array{passed:bool, points:int, label:string}>
     */
    private function buildBreakdown(array $checks, bool $hasExif): array
    {
        $amountPoints = match ($checks['amount_tier']) {
            'exact'     => self::AMOUNT_POINTS_EXACT,
            'half'      => self::AMOUNT_POINTS_HALF_PCT,
            'tolerance' => self::AMOUNT_POINTS_TOLERANCE,
            'two'       => self::AMOUNT_POINTS_TWO_PCT,
            default     => 0,
        };
        $amountLabels = [
            'exact'     => 'ยอดเงินตรงกันเป๊ะ',
            'half'      => 'ยอดเงินต่างกัน ≤ 0.5%',
            'tolerance' => 'ยอดเงินในช่วงที่อนุญาต',
            'two'       => 'ยอดเงินต่างกัน ≤ 2%',
            'none'      => 'ยอดเงินไม่ตรง',
        ];

        // File meta (condensed: size+type+dims collapsed to +5 total so they
        // can't dominate the score like before).
        $filePassed = $checks['file_size'] && $checks['file_type'] && $checks['dimensions'];

        $rows = [
            'base'            => ['passed' => true, 'points' => 2, 'label' => 'คะแนนพื้นฐาน'],
            'amount'          => ['passed' => $amountPoints > 0, 'points' => $amountPoints, 'label' => $amountLabels[$checks['amount_tier']] ?? 'ยอดเงิน'],
            'file_meta'       => ['passed' => $filePassed, 'points' => $filePassed ? 5 : 0, 'label' => 'ไฟล์ถูกต้อง (ขนาด/ชนิด/ความละเอียด)'],
            'exif'            => ['passed' => $hasExif, 'points' => $hasExif ? 3 : 0, 'label' => 'มี EXIF data'],
            'duplicate_hash'  => ['passed' => $checks['duplicate_hash'], 'points' => $checks['duplicate_hash'] ? 10 : 0, 'label' => 'ไม่พบสลิปรูปซ้ำ'],
            'duplicate_ref'   => ['passed' => $checks['duplicate_ref'], 'points' => $checks['duplicate_ref'] ? 8 : 0, 'label' => 'ไม่พบรหัสอ้างอิงซ้ำ'],
            'transfer_date'   => ['passed' => $checks['transfer_date'], 'points' => $checks['transfer_date'] ? 5 : 0, 'label' => 'วันที่โอนถูกต้อง'],
            'slip_after_order'=> ['passed' => $checks['slip_after_order'], 'points' => $checks['slip_after_order'] ? 5 : 0, 'label' => 'โอนหลังสร้างออเดอร์'],
        ];

        // SlipOK rows only appear if enabled (avoids misleading zero rows for
        // installs that don't use the external API).
        if ($checks['slipok_enabled']) {
            $rows['slipok_amount'] = [
                'passed' => (bool) $checks['slipok_amount'],
                'points' => $checks['slipok_amount'] ? 15 : 0,
                'label'  => 'SlipOK ยืนยันยอดเงิน',
            ];
            $rows['slipok_receiver'] = [
                'passed' => (bool) $checks['slipok_receiver'],
                'points' => $checks['slipok_receiver'] ? 15 : 0,
                'label'  => 'SlipOK ยืนยันบัญชีรับเงิน',
            ];
            $rows['slipok_dup_ref'] = [
                'passed' => (bool) $checks['slipok_dup_ref'],
                'points' => $checks['slipok_dup_ref'] ? 12 : 0,
                'label'  => 'SlipOK transRef ไม่ซ้ำ',
            ];
        }

        return $rows;
    }

    /*----------------------------------------------------------------------
    | Fraud detection
    |----------------------------------------------------------------------*/

    /**
     * Detect fraud indicators. A non-empty return caps the score at 20.
     */
    private function detectFraud(array $checks, UploadedFile $file, string $hash, bool $slipokDupRef, ?array $normalised, float $orderAmount): array
    {
        $flags = [];

        // 1. Duplicate image hash (same image reused)
        if (!$checks['duplicate_hash']) {
            $previous = PaymentSlip::where('slip_hash', $hash)
                ->where('verify_status', '!=', 'rejected')
                ->exists();
            if ($previous) {
                $flags[] = 'duplicate_slip_reuse';
            }
        }

        // 2. Duplicate reference code on a non-rejected slip (ref theft)
        if (!$checks['duplicate_ref']) {
            $flags[] = 'duplicate_ref_code';
        }

        // 3. Duplicate SlipOK transRef (strongest signal — absolute proof of reuse)
        if ($slipokDupRef) {
            $flags[] = 'duplicate_slipok_ref';
        }

        // 4. Suspiciously small file (probably a text screenshot, not a real slip)
        if ($file->getSize() < 8 * 1024) {
            $flags[] = 'file_too_small';
        }

        // 5. MIME ≠ extension (basic tamper indicator)
        $mime = $file->getMimeType();
        $ext  = strtolower($file->getClientOriginalExtension());
        $validMimes = [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'webp' => ['image/webp'],
            'heic' => ['image/heic', 'image/heif'],
        ];
        if (isset($validMimes[$ext]) && !in_array($mime, $validMimes[$ext], true)) {
            $flags[] = 'mime_extension_mismatch';
        }

        // 6. Grossly underpaid (< 50% of order). Even if tolerance is loose,
        //    a 50% shortfall is never legitimate — flag it.
        if ($orderAmount > 0 && $checks['amount_tier'] === 'none') {
            $claimed = (float) ($normalised['amount'] ?? 0);
            if ($claimed > 0 && $claimed < $orderAmount * 0.5) {
                $flags[] = 'amount_too_low';
            }
        }

        // 7. SlipOK says it's valid but receiver isn't one of our accounts
        //    → user sent to the wrong recipient (or a different merchant's slip)
        if (($checks['slipok_enabled']) && ($checks['slipok_success']) && !$checks['slipok_receiver']) {
            $flags[] = 'receiver_mismatch';
        }

        return $flags;
    }

    /*----------------------------------------------------------------------
    | Individual validations
    |----------------------------------------------------------------------*/

    private function isDuplicateHash(string $hash): bool
    {
        if (empty($hash)) return false;
        return PaymentSlip::where('slip_hash', $hash)->exists();
    }

    private function isDuplicateRefCode(string $refCode): bool
    {
        if (empty($refCode)) return false;
        return PaymentSlip::where('reference_code', $refCode)
            ->where('verify_status', '!=', 'rejected')
            ->exists();
    }

    private function validateDimensions(string $path): bool
    {
        try {
            $size = @getimagesize($path);
            if (!$size) return false;
            [$width, $height] = $size;
            return $width >= 200 && $height >= 300;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function validateFileSize(UploadedFile $file): bool
    {
        return $file->getSize() >= 15 * 1024;
    }

    private function validateFileType(UploadedFile $file): bool
    {
        return in_array($file->getMimeType(), [
            'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif',
        ], true);
    }

    private function validateTransferDate(?string $date): bool
    {
        if (empty($date)) return false;
        try {
            $transferDate = Carbon::parse($date);
            $now = now();
            return $transferDate->lte($now->copy()->addMinutes(5))   // allow tiny clock skew
                && $transferDate->gte($now->copy()->subDays(7));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Transfer time should be at or after the order creation time. A slip
     * dated BEFORE the order existed is almost certainly reused or fabricated.
     */
    private function validateSlipAfterOrder(?string $transferAt, $orderAt): bool
    {
        if (empty($transferAt) || empty($orderAt)) return false;
        try {
            $transfer = Carbon::parse($transferAt);
            $order    = $orderAt instanceof Carbon ? $orderAt : Carbon::parse($orderAt);
            // Allow up to 60s skew in case the user's device clock is ahead.
            return $transfer->gte($order->copy()->subMinute());
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function hasExifData(string $path): bool
    {
        if (!function_exists('exif_read_data')) return false;
        try {
            $exif = @exif_read_data($path);
            return !empty($exif);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
