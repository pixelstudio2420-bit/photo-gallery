<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\EventPhoto;
use App\Models\PhotographerProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// SubscriptionService is the source of truth for plan→quota mapping.
use App\Services\SubscriptionService;

/**
 * Single source of truth for per-photographer storage accounting.
 *
 * Why a dedicated service:
 *   • Quota math (bytes/GB conversion, tier→default lookup, override override)
 *     would get duplicated across middleware + observer + admin + dashboard
 *     if each called AppSetting + PhotographerProfile directly.
 *   • Every write path (upload / delete / bulk-purge) needs to keep
 *     storage_used_bytes honest. Funnelling through this service means the
 *     +/- arithmetic lives in exactly one place.
 *   • Tier defaults and multipliers change over time. Keeping the lookup
 *     centralised means bumping the free tier from 5→10 GB is one admin click,
 *     not a code deploy.
 *
 * Derivative overhead
 * -------------------
 * Each uploaded photo produces 3 artifacts on R2 (original + thumbnail +
 * watermarked). `file_size` on EventPhoto stores ONLY the original size, so
 * effective storage used = file_size × DERIVATIVE_MULTIPLIER. The multiplier
 * is intentionally conservative (3.0) even though thumbs/watermarks are
 * smaller than the original in practice — being slightly pessimistic prevents
 * surprise R2 bills more than being slightly optimistic would.
 */
class StorageQuotaService
{
    /** Multiplier to convert EventPhoto.file_size → total bytes including derivatives. */
    public const DERIVATIVE_MULTIPLIER = 3.0;

    private const GB_TO_BYTES = 1073741824; // 1024^3

    /**
     * Is quota enforcement currently active? Admin can flip this OFF to
     * diagnose "why won't my upload go through" issues without touching
     * individual profiles.
     */
    public function enforcementEnabled(): bool
    {
        return (string) AppSetting::get('photographer_quota_enforcement_enabled', '1') === '1';
    }

    /**
     * Effective quota (bytes) for this photographer.
     *
     * **Storage quota follows the SUBSCRIPTION PLAN — period.**
     * Resolution order:
     *   1) profile.storage_quota_bytes (set by SubscriptionService::syncProfileCache
     *      whenever a sub activates / renews / changes / expires).
     *   2) Live lookup of currentPlan->storage_bytes (covers freshly-created
     *      profiles where syncProfileCache hasn't run yet).
     *   3) Tier-based legacy fallback ONLY for very old profiles that pre-date
     *      the subscription system. Those rows can be migrated by running
     *      `php artisan storage:resync-photographer-quotas`.
     *
     * The plan is the single source of truth — quotas NEVER stack with the
     * Free 2 GB. Switching from Free → Pro replaces the cap (not adds).
     * Switching back drops it to whatever the new plan grants.
     */
    public function quotaFor(PhotographerProfile $profile): int
    {
        // Step 1: cached value (set when sub activates/changes/expires)
        if ($profile->storage_quota_bytes !== null && (int) $profile->storage_quota_bytes > 0) {
            return (int) $profile->storage_quota_bytes;
        }

        // Step 2: live plan lookup — this is the single source of truth.
        // Even if the profile has no active subscription row, currentPlan()
        // falls through to the seeded Free plan automatically.
        try {
            $plan = app(SubscriptionService::class)->currentPlan($profile);
            if ($plan && (int) ($plan->storage_bytes ?? 0) > 0) {
                $bytes = (int) $plan->storage_bytes;

                // Self-heal: cache it on the profile so subsequent reads
                // skip step 2 entirely. Use forceFill+saveQuietly to avoid
                // touching `updated_at` and triggering observers.
                $profile->forceFill(['storage_quota_bytes' => $bytes])->saveQuietly();
                return $bytes;
            }
        } catch (\Throwable $e) {
            // SubscriptionService boot failed — fall through to legacy.
        }

        // Step 3: legacy tier fallback (only for very old profiles)
        return $this->tierQuotaBytes((string) ($profile->tier ?: PhotographerProfile::TIER_CREATOR));
    }

    /** Quota for a bare tier string — LEGACY. New photographers go through plan. */
    public function tierQuotaBytes(string $tier): int
    {
        $gb = match ($tier) {
            PhotographerProfile::TIER_PRO    => (int) AppSetting::get('photographer_quota_pro_gb', '500'),
            PhotographerProfile::TIER_SELLER => (int) AppSetting::get('photographer_quota_seller_gb', '50'),
            default                          => (int) AppSetting::get('photographer_quota_creator_gb', '5'),
        };
        return max(0, $gb) * self::GB_TO_BYTES;
    }

    /**
     * Percent used (0-100, clamped). Returns 0 if quota is zero to avoid div/0.
     */
    public function percentUsed(PhotographerProfile $profile): float
    {
        $quota = $this->quotaFor($profile);
        if ($quota <= 0) return 0.0;
        $pct = ((int) $profile->storage_used_bytes / $quota) * 100;
        return max(0.0, min(100.0, $pct));
    }

    /**
     * Does this photographer have room for one more file of N bytes?
     * Conservative: the full derivative multiplier is applied.
     */
    public function canUpload(PhotographerProfile $profile, int $fileBytes): bool
    {
        if (!$this->enforcementEnabled()) {
            return true;
        }
        $quota = $this->quotaFor($profile);
        if ($quota <= 0) {
            return false;
        }
        $needed = (int) round($fileBytes * self::DERIVATIVE_MULTIPLIER);
        return ((int) $profile->storage_used_bytes + $needed) <= $quota;
    }

    /**
     * Thai message explaining why an upload was refused — what to show the
     * photographer. Generated here so middleware and controllers share wording.
     */
    public function refusalMessage(PhotographerProfile $profile, int $fileBytes): string
    {
        $quota   = $this->quotaFor($profile);
        $used    = (int) $profile->storage_used_bytes;
        $need    = (int) round($fileBytes * self::DERIVATIVE_MULTIPLIER);
        $after   = $used + $need;
        $overBy  = max(0, $after - $quota);

        return sprintf(
            'พื้นที่เต็มแล้ว — ใช้ไป %s / %s. ไฟล์นี้ต้องการเพิ่ม %s (เกินโควต้า %s). '
          . 'กรุณาลบรูปเก่าหรืออัปเกรดแพ็คเกจ.',
            $this->humanBytes($used),
            $this->humanBytes($quota),
            $this->humanBytes($need),
            $this->humanBytes($overBy)
        );
    }

    // ────────────────────────────────────────────────────────────────────
    // Write paths — called by Observer / Jobs / PurgeEventJob
    // ────────────────────────────────────────────────────────────────────

    /**
     * Increment a photographer's used-bytes by (original × multiplier).
     * Idempotent-safe: an atomic DB increment so parallel uploads can't
     * clobber each other.
     */
    public function recordUpload(int $photographerUserId, int $originalBytes): void
    {
        if ($originalBytes <= 0 || $photographerUserId <= 0) return;
        $delta = (int) round($originalBytes * self::DERIVATIVE_MULTIPLIER);
        $this->adjust($photographerUserId, $delta);
    }

    /**
     * Decrement when a single photo is deleted.
     * Clamped to zero — never lets the counter go negative (drift is fixed
     * by the nightly recalc job).
     */
    public function recordDelete(int $photographerUserId, int $originalBytes): void
    {
        if ($originalBytes <= 0 || $photographerUserId <= 0) return;
        $delta = -(int) round($originalBytes * self::DERIVATIVE_MULTIPLIER);
        $this->adjust($photographerUserId, $delta);
    }

    /**
     * Full recalculation from source of truth (event_photos rows).
     * Used by the nightly job and by the admin "fix drift" button.
     *
     * Returns the new byte total.
     */
    public function recalculate(PhotographerProfile $profile): int
    {
        // We count ALL photos the photographer has uploaded across all
        // their events, regardless of status. Processing/failed photos
        // still occupy R2 space and should count against quota until they
        // are purged by their owning event's cleanup.
        $originalBytes = (int) DB::table('event_photos')
            ->join('event_events', 'event_events.id', '=', 'event_photos.event_id')
            ->where('event_events.photographer_id', $profile->user_id)
            ->sum('event_photos.file_size');

        $totalWithDerivatives = (int) round($originalBytes * self::DERIVATIVE_MULTIPLIER);

        $profile->forceFill([
            'storage_used_bytes'       => $totalWithDerivatives,
            'storage_recalculated_at'  => now(),
        ])->saveQuietly();

        return $totalWithDerivatives;
    }

    // ────────────────────────────────────────────────────────────────────
    // Display helpers
    // ────────────────────────────────────────────────────────────────────

    public function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $i = 0;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }
        return number_format($value, $value >= 100 ? 0 : 1) . ' ' . $units[$i];
    }

    public function warnThresholdPct(): int
    {
        return max(0, min(100, (int) AppSetting::get('photographer_quota_warn_threshold_pct', '80')));
    }

    /**
     * Admin snapshot — top-level usage figures across all photographers.
     * Cached 60s because this scans a potentially large table.
     */
    public function adminSnapshot(): array
    {
        return Cache::remember('photographer_quota_admin_snapshot', 60, function () {
            $total    = PhotographerProfile::count();
            $used     = (int) PhotographerProfile::sum('storage_used_bytes');

            // By tier — so admin can see where usage concentrates
            $byTier = PhotographerProfile::selectRaw('tier, COUNT(*) as c, SUM(storage_used_bytes) as bytes')
                ->groupBy('tier')
                ->get()
                ->keyBy('tier')
                ->map(fn ($r) => [
                    'count' => (int) $r->c,
                    'bytes' => (int) $r->bytes,
                ])
                ->toArray();

            // Photographers getting close to their cap (>= warn threshold)
            $warnPct = $this->warnThresholdPct();

            // Top 20 by usage — for the admin table
            $topUsers = PhotographerProfile::select(['id', 'user_id', 'display_name', 'tier', 'storage_used_bytes', 'storage_quota_bytes'])
                ->with(['user:id,email,first_name,last_name'])
                ->orderByDesc('storage_used_bytes')
                ->limit(20)
                ->get();

            // Rough R2 cost — $0.015/GB/month for standard class.
            $gb = $used / self::GB_TO_BYTES;
            $estCostUsd = round($gb * 0.015, 2);

            return [
                'photographers_total' => $total,
                'bytes_used_total'    => $used,
                'by_tier'             => $byTier,
                'warn_threshold_pct'  => $warnPct,
                'top_users'           => $topUsers,
                'est_cost_usd_month'  => $estCostUsd,
                'computed_at'         => now()->toIso8601String(),
            ];
        });
    }

    public function flushAdminCache(): void
    {
        Cache::forget('photographer_quota_admin_snapshot');
    }

    /**
     * Compute potential savings if this photographer upgraded one tier.
     * Used by the photographer-facing "you'd save ฿X" widget.
     *
     * Returns an array keyed by target tier, each with:
     *   commission_saved_per_1000_baht, fee_saved_per_photo, sub_cost, breakeven_photos
     */
    public function upgradeSavings(string $currentTier): array
    {
        $tiers = [
            PhotographerProfile::TIER_CREATOR,
            PhotographerProfile::TIER_SELLER,
            PhotographerProfile::TIER_PRO,
        ];
        $rank = array_flip($tiers);
        $current = $rank[$currentTier] ?? 0;

        $out = [];
        foreach ($tiers as $t => $tier) {
            if ($t <= $current) continue;
            $curCommissionPct = (float) AppSetting::get('commission_pct_' . $currentTier, '30');
            $newCommissionPct = (float) AppSetting::get('commission_pct_' . $tier, '15');
            $curFee           = (int)   AppSetting::get('platform_fee_per_photo_' . $currentTier, '10');
            $newFee           = (int)   AppSetting::get('platform_fee_per_photo_' . $tier, '7');
            $subCost          = (int)   AppSetting::get('subscription_price_' . $tier, $tier === PhotographerProfile::TIER_PRO ? '999' : '299');

            // Savings on a ฿1,000 photo (commission delta)
            $commSavedPer1000 = (int) round((($curCommissionPct - $newCommissionPct) / 100.0) * 1000);
            // Savings per photo (fee delta)
            $feeSavedPerPhoto = max(0, $curFee - $newFee);
            // How many ฿500 photos do you need to sell per month to break even on the sub?
            $breakevenPhotos  = $commSavedPer1000 > 0
                ? (int) ceil($subCost / (($commSavedPer1000 / 2) + $feeSavedPerPhoto))
                : 0;

            $out[$tier] = [
                'sub_cost'                     => $subCost,
                'commission_saved_per_1000b'   => $commSavedPer1000,
                'fee_saved_per_photo'          => $feeSavedPerPhoto,
                'breakeven_photos_at_500baht'  => $breakevenPhotos,
                'current_commission_pct'       => $curCommissionPct,
                'new_commission_pct'           => $newCommissionPct,
            ];
        }

        return $out;
    }

    // ────────────────────────────────────────────────────────────────────
    // Internals
    // ────────────────────────────────────────────────────────────────────

    /**
     * Atomic counter adjustment by photographer user_id.
     * We use raw SQL with GREATEST() to floor at zero — so even a buggy
     * -Infinity delta can't send the counter negative and confuse the
     * admin dashboard.
     */
    private function adjust(int $photographerUserId, int $delta): void
    {
        if ($delta === 0) return;

        try {
            $driver = DB::getDriverName();
            if ($driver === 'sqlite') {
                // SQLite lacks GREATEST(); MAX(col, 0) is the cross-DB safe idiom
                // but MAX takes a single expr in SQLite — so use CASE instead.
                DB::table('photographer_profiles')
                    ->where('user_id', $photographerUserId)
                    ->update([
                        'storage_used_bytes' => DB::raw(
                            "CASE WHEN storage_used_bytes + ({$delta}) < 0 THEN 0 ELSE storage_used_bytes + ({$delta}) END"
                        ),
                    ]);
            } else {
                DB::table('photographer_profiles')
                    ->where('user_id', $photographerUserId)
                    ->update([
                        'storage_used_bytes' => DB::raw("GREATEST(0, storage_used_bytes + ({$delta}))"),
                    ]);
            }
        } catch (\Throwable $e) {
            // Never bubble up — quota accounting must never block the
            // write path. Log + move on; the nightly recalc will fix it.
            Log::warning('StorageQuotaService::adjust failed', [
                'user_id' => $photographerUserId,
                'delta'   => $delta,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
