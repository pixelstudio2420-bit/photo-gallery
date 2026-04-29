<?php

namespace App\Services\Payment;

use App\Models\PaymentSlip;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Computes + matches slip-image fingerprints to block fraud.
 *
 * Three layers of dedup:
 *   1. sha256          → byte-identical re-uploads     (always available)
 *   2. byte size + mime→ trivial re-encodes            (catches resize)
 *   3. pHash (perceptual) → crops/colorshift           (best-effort, GD-required)
 *
 * Cross-user check
 * ----------------
 * The DB-level UNIQUE INDEX on slip_hash blocks duplicate inserts at
 * the storage layer. This service is the *application-side* gate that
 * runs BEFORE the insert so we can return a friendly 422 instead of a
 * raw integrity error. Both layers must agree — never disable one
 * without the other.
 */
class SlipFingerprintService
{
    /**
     * Compute a fingerprint for an uploaded file. Never throws on
     * fingerprint-input failure (e.g. corrupt image) — pHash falls
     * back to null.
     */
    public function fingerprint(UploadedFile $file): SlipFingerprint
    {
        $path = $file->getRealPath();
        if ($path === false || !is_readable($path)) {
            throw new \RuntimeException('Slip file is not readable; cannot fingerprint.');
        }

        return new SlipFingerprint(
            sha256: hash_file('sha256', $path),
            bytes:  (int) $file->getSize(),
            mime:   $file->getMimeType() ?: 'application/octet-stream',
            pHash:  $this->safePHash($path),
        );
    }

    /**
     * Has this exact image been used by ANY other user, ever?
     *
     * Returns the FIRST conflicting slip (for logging) or null when clean.
     * Excludes rejected slips so a previously-rejected user can re-upload
     * (false positives shouldn't lock them out forever).
     *
     * @param  int       $userId          The CURRENT user uploading.
     * @param  ?int      $excludeSlipId   When updating an existing slip.
     */
    public function findCrossUserDuplicate(string $sha256, int $userId, ?int $excludeSlipId = null): ?PaymentSlip
    {
        if ($sha256 === '') return null;

        $q = PaymentSlip::query()
            ->where('slip_hash', $sha256)
            ->where('verify_status', '!=', 'rejected')
            ->whereHas('order', function ($q) use ($userId) {
                $q->where('user_id', '!=', $userId);
            });

        if ($excludeSlipId) {
            $q->where('id', '!=', $excludeSlipId);
        }
        return $q->first();
    }

    /**
     * Has this user already submitted this same image for ANOTHER order?
     * (Same user reusing a slip for two of their own orders is also fraud.)
     */
    public function findSameUserDifferentOrder(string $sha256, int $userId, int $orderId): ?PaymentSlip
    {
        if ($sha256 === '') return null;

        return PaymentSlip::query()
            ->where('slip_hash', $sha256)
            ->where('order_id',  '!=', $orderId)
            ->whereHas('order', fn ($q) => $q->where('user_id', $userId))
            ->where('verify_status', '!=', 'rejected')
            ->first();
    }

    /**
     * Has this SlipOK transaction reference already been claimed by anyone?
     * Authoritative cross-user fraud signal — the bank confirmed THIS
     * specific transfer happened, and only one customer can legitimately
     * claim it.
     */
    public function findSlipokRefDuplicate(?string $transRef, ?int $excludeSlipId = null): ?PaymentSlip
    {
        if (!$transRef) return null;

        $q = PaymentSlip::query()
            ->where('slipok_trans_ref', $transRef)
            ->where('verify_status', '!=', 'rejected');

        if ($excludeSlipId) {
            $q->where('id', '!=', $excludeSlipId);
        }
        return $q->first();
    }

    /* ─────────────────── Internals ─────────────────── */

    /**
     * Compute a 64-bit perceptual hash via 8x8 DCT on a grayscaled,
     * downsampled image. Returns null when:
     *   - GD isn't installed
     *   - the file isn't a supported image (PDFs, etc.)
     *   - any failure happens (we never block the upload over pHash)
     *
     * Algorithm:
     *   1. Load image, convert to 32x32 grayscale.
     *   2. Compute DCT of the 32x32 grid (cheap O(N²) Naive DCT — at
     *      32px the constant factors are tiny).
     *   3. Take the top-left 8x8 of DCT coefficients (lowest frequencies).
     *   4. Compute median of the 64 values; bit i = 1 if coef_i > median.
     *   5. Return as 16-char hex string.
     */
    private function safePHash(string $path): ?string
    {
        if (!extension_loaded('gd') || !function_exists('imagecreatefromstring')) {
            return null;
        }
        try {
            $bytes = @file_get_contents($path);
            if ($bytes === false) return null;
            $img = @imagecreatefromstring($bytes);
            if ($img === false) return null;

            // Downsample to 32x32 grayscale.
            $small = imagecreatetruecolor(32, 32);
            imagecopyresampled($small, $img, 0, 0, 0, 0, 32, 32, imagesx($img), imagesy($img));

            $matrix = [];
            for ($y = 0; $y < 32; $y++) {
                $row = [];
                for ($x = 0; $x < 32; $x++) {
                    $rgb = imagecolorat($small, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8)  & 0xFF;
                    $b = $rgb & 0xFF;
                    // ITU-R BT.601 luma — same coefficients as JPEG.
                    $row[] = (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
                }
                $matrix[] = $row;
            }
            imagedestroy($img);
            imagedestroy($small);

            // Compute first 8 cols of DCT for the first 8 rows (we only
            // need the 8x8 low-frequency block).
            $dct = [];
            for ($u = 0; $u < 8; $u++) {
                for ($v = 0; $v < 8; $v++) {
                    $sum = 0.0;
                    for ($x = 0; $x < 32; $x++) {
                        for ($y = 0; $y < 32; $y++) {
                            $sum += $matrix[$y][$x]
                                  * cos((2 * $x + 1) * $u * M_PI / 64)
                                  * cos((2 * $y + 1) * $v * M_PI / 64);
                        }
                    }
                    $dct[] = $sum;
                }
            }

            $sorted = $dct;
            sort($sorted);
            $median = ($sorted[31] + $sorted[32]) / 2;

            $bits = '';
            foreach ($dct as $coef) {
                $bits .= ($coef > $median) ? '1' : '0';
            }
            // Pack 64 bits into 16 hex chars
            return str_pad(dechex((int) bindec($bits)), 16, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            Log::debug('SlipFingerprint pHash failed (non-fatal)', ['err' => $e->getMessage()]);
            return null;
        }
    }
}
