<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\CreditTransaction;
use App\Models\Order;
use App\Models\PhotographerCreditBundle;
use App\Models\PhotographerProfile;
use App\Models\UploadCreditPackage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CreditService — single source of truth for upload credit mutations.
 *
 * Rules of engagement:
 *   • Every balance-changing operation writes (a) a bundle row change AND
 *     (b) a credit_transactions ledger entry inside the SAME DB transaction.
 *     No partial writes; read-side can trust the ledger + cache.
 *   • `credits_balance_cached` on photographer_profiles is a denormalised
 *     snapshot. Always mutate it with atomic SQL (GREATEST(0, col+N))
 *     so race conditions during parallel uploads can't break the invariant.
 *   • Consume uses FIFO by `expires_at` — the bundle closest to expiring
 *     drains first. This minimises expired-credit waste and feels fair.
 *   • The service NEVER throws on "insufficient credits" — it returns
 *     false. Callers (middleware, controllers) decide how to surface that
 *     to the user.
 *
 * Hot paths (consume):
 *   • Called once per uploaded photo via EventPhotoStorageObserver.
 *   • Uses a single DB transaction + row-level SELECT FOR UPDATE on the
 *     candidate bundle to prevent double-spend under concurrent upload.
 */
class CreditService
{
    // ────────────────────────────────────────────────────────────────────
    // Feature toggles
    // ────────────────────────────────────────────────────────────────────

    public function systemEnabled(): bool
    {
        // Default OFF — the credits/upload-credit system is opt-in. New
        // installs (and admins who haven't reviewed it) get the safer
        // "off" state until they explicitly enable it from
        // /admin/settings. This prevents a fresh deploy from accidentally
        // gating photographer uploads behind a credits paywall the admin
        // doesn't yet understand.
        return (string) AppSetting::get('credits_system_enabled', '0') === '1';
    }

    public function defaultBillingMode(): string
    {
        $mode = (string) AppSetting::get('default_billing_mode', PhotographerProfile::BILLING_CREDITS);
        return in_array($mode, [PhotographerProfile::BILLING_CREDITS, PhotographerProfile::BILLING_COMMISSION], true)
            ? $mode
            : PhotographerProfile::BILLING_CREDITS;
    }

    // ────────────────────────────────────────────────────────────────────
    // Read-side (cheap, cacheable)
    // ────────────────────────────────────────────────────────────────────

    /** Current balance = sum of non-expired bundle remaining. Prefers cache. */
    public function balance(int $photographerUserId): int
    {
        $cached = DB::table('photographer_profiles')
            ->where('user_id', $photographerUserId)
            ->value('credits_balance_cached');
        if ($cached !== null) return (int) $cached;

        return $this->recalculateBalance($photographerUserId);
    }

    /** Collection of usable bundles ordered FIFO (for UI + consume path). */
    public function usableBundles(int $photographerUserId): Collection
    {
        return PhotographerCreditBundle::query()
            ->where('photographer_id', $photographerUserId)
            ->usable()
            ->fifo()
            ->get();
    }

    /** All active packages in the catalog (for the photographer store). */
    public function catalog(): Collection
    {
        return UploadCreditPackage::query()->active()->ordered()->get();
    }

    /**
     * How much storage-quota-in-bytes do this photographer's credits
     * "represent"? Rough answer for UI hints — 1 credit ≈ 15 MB (avg event
     * JPEG + derivatives). Not enforced; storage quota is still its own lane.
     */
    public function creditsAsBytes(int $credits): int
    {
        return max(0, $credits) * 15 * 1024 * 1024;
    }

    // ────────────────────────────────────────────────────────────────────
    // Write-side: purchase & grants
    // ────────────────────────────────────────────────────────────────────

    /**
     * Issue a bundle from a paid Order. Called by the payment webhook once
     * an order flips to 'paid'. Idempotent: second call returns the existing
     * bundle without double-crediting.
     */
    public function issueFromPaidOrder(Order $order): ?PhotographerCreditBundle
    {
        if (!$order->isCreditPackageOrder()) return null;

        // Re-entry guard: if a bundle already exists for this order, return it.
        if ($existing = PhotographerCreditBundle::where('order_id', $order->id)->first()) {
            return $existing;
        }

        $package = $order->creditPackage;
        if (!$package) {
            Log::warning("CreditService::issueFromPaidOrder — order #{$order->id} has no credit_package_id");
            return null;
        }

        // Recipient = the photographer who placed the order (user_id on the Order).
        $photographerUserId = (int) $order->user_id;

        $expiresAt = $package->validity_days > 0
            ? now()->addDays((int) $package->validity_days)
            : null;

        return DB::transaction(function () use ($order, $package, $photographerUserId, $expiresAt) {
            $bundle = PhotographerCreditBundle::create([
                'photographer_id'   => $photographerUserId,
                'package_id'        => $package->id,
                'order_id'          => $order->id,
                'source'            => 'purchase',
                'credits_initial'   => (int) $package->credits,
                'credits_remaining' => (int) $package->credits,
                'price_paid_thb'    => (float) $package->price_thb,
                'expires_at'        => $expiresAt,
                'note'              => "Package: {$package->code}",
            ]);

            $newBalance = $this->addToCache($photographerUserId, (int) $package->credits);

            CreditTransaction::create([
                'photographer_id' => $photographerUserId,
                'bundle_id'       => $bundle->id,
                'kind'            => CreditTransaction::KIND_PURCHASE,
                'delta'           => (int) $package->credits,
                'balance_after'   => $newBalance,
                'reference_type'  => 'order',
                'reference_id'    => (string) $order->id,
                'meta'            => ['package_code' => $package->code, 'price' => (float) $package->price_thb],
                'actor_user_id'   => null,
                'created_at'      => now(),
            ]);

            Log::info("CreditService: issued {$package->credits} credits to user #{$photographerUserId} from order #{$order->id}");

            return $bundle;
        });
    }

    /**
     * Grant credits manually (admin gift, bonus, or monthly free tier).
     * `expiresDays=null` means no expiry.
     */
    public function grant(
        int $photographerUserId,
        int $credits,
        string $source = 'grant',
        ?int $expiresDays = 30,
        ?int $actorUserId = null,
        ?string $note = null
    ): PhotographerCreditBundle {
        if ($credits <= 0) {
            throw new \InvalidArgumentException('grant credits must be positive');
        }

        $expiresAt = $expiresDays !== null ? now()->addDays($expiresDays) : null;

        return DB::transaction(function () use ($photographerUserId, $credits, $source, $expiresAt, $actorUserId, $note) {
            $bundle = PhotographerCreditBundle::create([
                'photographer_id'   => $photographerUserId,
                'package_id'        => null,
                'order_id'          => null,
                'source'            => $source,
                'credits_initial'   => $credits,
                'credits_remaining' => $credits,
                'price_paid_thb'    => 0,
                'expires_at'        => $expiresAt,
                'note'              => $note,
            ]);

            $newBalance = $this->addToCache($photographerUserId, $credits);

            CreditTransaction::create([
                'photographer_id' => $photographerUserId,
                'bundle_id'       => $bundle->id,
                'kind'            => $source === 'bonus' ? CreditTransaction::KIND_BONUS : CreditTransaction::KIND_GRANT,
                'delta'           => $credits,
                'balance_after'   => $newBalance,
                'reference_type'  => $actorUserId ? 'user' : 'system',
                'reference_id'    => $actorUserId ? (string) $actorUserId : null,
                'meta'            => ['note' => $note],
                'actor_user_id'   => $actorUserId,
                'created_at'      => now(),
            ]);

            return $bundle;
        });
    }

    // ────────────────────────────────────────────────────────────────────
    // Write-side: consume (hot path)
    // ────────────────────────────────────────────────────────────────────

    /**
     * Consume N credits, FIFO across usable bundles. Returns true on success,
     * false if insufficient. Never throws — callers decide the UX.
     *
     * Safe for parallel uploads: uses lockForUpdate() on candidate bundles
     * inside a single transaction, so two concurrent consumes can't drain
     * the same unit twice.
     */
    public function consume(
        int $photographerUserId,
        int $credits = 1,
        string $referenceType = 'event_photo',
        ?string $referenceId = null
    ): bool {
        if ($credits <= 0) return true;
        if (!$this->systemEnabled()) return true; // bypass if globally disabled

        return DB::transaction(function () use ($photographerUserId, $credits, $referenceType, $referenceId) {
            $remaining = $credits;

            $bundles = PhotographerCreditBundle::query()
                ->where('photographer_id', $photographerUserId)
                ->usable()
                ->fifo()
                ->lockForUpdate()
                ->get();

            $totalAvailable = $bundles->sum('credits_remaining');
            if ($totalAvailable < $credits) {
                // Not enough — don't mutate anything.
                return false;
            }

            foreach ($bundles as $bundle) {
                if ($remaining <= 0) break;

                $take = min($remaining, (int) $bundle->credits_remaining);
                if ($take <= 0) continue;

                $bundle->credits_remaining -= $take;
                $bundle->save();
                $remaining -= $take;

                $newBalance = $this->addToCache($photographerUserId, -$take);

                CreditTransaction::create([
                    'photographer_id' => $photographerUserId,
                    'bundle_id'       => $bundle->id,
                    'kind'            => CreditTransaction::KIND_CONSUME,
                    'delta'           => -$take,
                    'balance_after'   => $newBalance,
                    'reference_type'  => $referenceType,
                    'reference_id'    => $referenceId,
                    'meta'            => null,
                    'actor_user_id'   => null,
                    'created_at'      => now(),
                ]);
            }

            return true;
        });
    }

    /**
     * Refund credits (e.g. uploaded photo was deleted within X minutes).
     * Adds a NEW bundle with short expiry rather than trying to "un-consume"
     * — avoids brittle reverse-of-reverse math.
     */
    public function refund(
        int $photographerUserId,
        int $credits,
        string $referenceType = 'event_photo',
        ?string $referenceId = null,
        ?string $note = null
    ): ?PhotographerCreditBundle {
        if ($credits <= 0) return null;

        return DB::transaction(function () use ($photographerUserId, $credits, $referenceType, $referenceId, $note) {
            // Short 90-day expiry on refunds so we don't create immortal credit.
            $bundle = PhotographerCreditBundle::create([
                'photographer_id'   => $photographerUserId,
                'package_id'        => null,
                'order_id'          => null,
                'source'            => 'refund',
                'credits_initial'   => $credits,
                'credits_remaining' => $credits,
                'price_paid_thb'    => 0,
                'expires_at'        => now()->addDays(90),
                'note'              => $note ?? 'Auto-refund from deleted upload',
            ]);

            $newBalance = $this->addToCache($photographerUserId, $credits);

            CreditTransaction::create([
                'photographer_id' => $photographerUserId,
                'bundle_id'       => $bundle->id,
                'kind'            => CreditTransaction::KIND_REFUND,
                'delta'           => $credits,
                'balance_after'   => $newBalance,
                'reference_type'  => $referenceType,
                'reference_id'    => $referenceId,
                'meta'            => ['note' => $note],
                'actor_user_id'   => null,
                'created_at'      => now(),
            ]);

            return $bundle;
        });
    }

    /**
     * Admin adjustment — positive or negative. Creates a free-form ledger
     * entry; for positive deltas it also creates a bundle so future consumes
     * can draw on it. For negative deltas it consumes from existing bundles.
     */
    public function adjust(
        int $photographerUserId,
        int $delta,
        int $actorUserId,
        string $note = ''
    ): bool {
        if ($delta === 0) return true;

        if ($delta > 0) {
            $this->grant($photographerUserId, $delta, 'grant', 365, $actorUserId, $note ?: 'Admin adjustment');
            return true;
        }

        // Negative adjustment = consume + special ledger kind.
        $absolute = abs($delta);
        return DB::transaction(function () use ($photographerUserId, $absolute, $actorUserId, $note) {
            $bundles = PhotographerCreditBundle::query()
                ->where('photographer_id', $photographerUserId)
                ->usable()
                ->fifo()
                ->lockForUpdate()
                ->get();

            $totalAvailable = $bundles->sum('credits_remaining');
            $toTake = min($totalAvailable, $absolute);

            if ($toTake <= 0) {
                // Nothing to deduct, but still log the attempt for audit.
                CreditTransaction::create([
                    'photographer_id' => $photographerUserId,
                    'bundle_id'       => null,
                    'kind'            => CreditTransaction::KIND_ADJUST,
                    'delta'           => 0,
                    'balance_after'   => (int) DB::table('photographer_profiles')->where('user_id', $photographerUserId)->value('credits_balance_cached'),
                    'reference_type'  => 'user',
                    'reference_id'    => (string) $actorUserId,
                    'meta'            => ['requested_delta' => -$absolute, 'applied' => 0, 'reason' => 'no_balance', 'note' => $note],
                    'actor_user_id'   => $actorUserId,
                    'created_at'      => now(),
                ]);
                return false;
            }

            $remaining = $toTake;
            foreach ($bundles as $bundle) {
                if ($remaining <= 0) break;
                $take = min($remaining, (int) $bundle->credits_remaining);
                if ($take <= 0) continue;

                $bundle->credits_remaining -= $take;
                $bundle->save();
                $remaining -= $take;

                $newBalance = $this->addToCache($photographerUserId, -$take);

                CreditTransaction::create([
                    'photographer_id' => $photographerUserId,
                    'bundle_id'       => $bundle->id,
                    'kind'            => CreditTransaction::KIND_ADJUST,
                    'delta'           => -$take,
                    'balance_after'   => $newBalance,
                    'reference_type'  => 'user',
                    'reference_id'    => (string) $actorUserId,
                    'meta'            => ['note' => $note],
                    'actor_user_id'   => $actorUserId,
                    'created_at'      => now(),
                ]);
            }

            return true;
        });
    }

    // ────────────────────────────────────────────────────────────────────
    // Maintenance: expire + recalc
    // ────────────────────────────────────────────────────────────────────

    /**
     * Zero-out any bundles whose expires_at is in the past. Runs nightly
     * via schedule. Returns [bundles_expired, credits_lost] for logging.
     */
    public function expireExpired(): array
    {
        $stale = PhotographerCreditBundle::query()
            ->where('credits_remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        if ($stale->isEmpty()) {
            return ['bundles' => 0, 'credits' => 0];
        }

        $totalCreditsExpired = 0;
        $bundleCount = 0;

        foreach ($stale as $bundle) {
            $lost = (int) $bundle->credits_remaining;
            if ($lost <= 0) continue;

            DB::transaction(function () use ($bundle, $lost) {
                $bundle->credits_remaining = 0;
                $bundle->save();

                $newBalance = $this->addToCache((int) $bundle->photographer_id, -$lost);

                CreditTransaction::create([
                    'photographer_id' => $bundle->photographer_id,
                    'bundle_id'       => $bundle->id,
                    'kind'            => CreditTransaction::KIND_EXPIRE,
                    'delta'           => -$lost,
                    'balance_after'   => $newBalance,
                    'reference_type'  => 'bundle',
                    'reference_id'    => (string) $bundle->id,
                    'meta'            => ['expires_at' => optional($bundle->expires_at)->toIso8601String()],
                    'actor_user_id'   => null,
                    'created_at'      => now(),
                ]);
            });

            $totalCreditsExpired += $lost;
            $bundleCount++;
        }

        return ['bundles' => $bundleCount, 'credits' => $totalCreditsExpired];
    }

    /**
     * Rebuild the cached balance from the bundle table. Called when the
     * denormalised snapshot drifts, or by the nightly recalc job.
     */
    public function recalculateBalance(int $photographerUserId): int
    {
        $sum = (int) PhotographerCreditBundle::query()
            ->where('photographer_id', $photographerUserId)
            ->usable()
            ->sum('credits_remaining');

        DB::table('photographer_profiles')
            ->where('user_id', $photographerUserId)
            ->update([
                'credits_balance_cached' => $sum,
                'credits_last_recalc_at' => now(),
            ]);

        return $sum;
    }

    /**
     * Atomic cache mutation. `delta` is signed — positive for credits in,
     * negative for credits out. Floors at zero so drift can't push it below
     * zero even under concurrent writes.
     */
    private function addToCache(int $photographerUserId, int $delta): int
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::table('photographer_profiles')
                ->where('user_id', $photographerUserId)
                ->update([
                    'credits_balance_cached' => DB::raw(
                        "CASE WHEN credits_balance_cached + ({$delta}) < 0 THEN 0 ELSE credits_balance_cached + ({$delta}) END"
                    ),
                    'credits_last_recalc_at' => now(),
                ]);
        } else {
            DB::table('photographer_profiles')
                ->where('user_id', $photographerUserId)
                ->update([
                    'credits_balance_cached' => DB::raw("GREATEST(0, credits_balance_cached + ({$delta}))"),
                    'credits_last_recalc_at' => now(),
                ]);
        }

        return (int) DB::table('photographer_profiles')
            ->where('user_id', $photographerUserId)
            ->value('credits_balance_cached');
    }

    // ────────────────────────────────────────────────────────────────────
    // Decision helpers (used by middleware + UI)
    // ────────────────────────────────────────────────────────────────────

    /**
     * Can this photographer upload `creditsNeeded` more photos right now?
     *
     * Returns true when:
     *   • billing_mode != credits (legacy photographers pass through)
     *   • OR balance + overdraft grace ≥ creditsNeeded
     */
    public function canUpload(PhotographerProfile $profile, int $creditsNeeded = 1): bool
    {
        if (!$this->systemEnabled()) return true;
        if (!$profile->isCreditsMode()) return true;

        $balance = $this->balance((int) $profile->user_id);
        $grace   = (int) AppSetting::get('credits_overdraft_grace', '3');

        return ($balance + $grace) >= $creditsNeeded;
    }

    /** Human-readable refusal message shown when canUpload() returns false. */
    public function refusalMessage(PhotographerProfile $profile): string
    {
        $balance = $this->balance((int) $profile->user_id);
        return "ไม่สามารถอัปโหลดได้ — เครดิตเหลือ {$balance} ภาพ กรุณาซื้อแพ็คเก็จเพิ่มที่หน้า เครดิตของฉัน";
    }

    /**
     * Summary for the photographer dashboard widget. Cached 30s to protect
     * against N+1 on the dashboard partial.
     */
    public function dashboardSummary(PhotographerProfile $profile): array
    {
        $userId = (int) $profile->user_id;
        $balance = $this->balance($userId);

        // Bundle breakdown — what's closest to expiring, what source is it.
        $nextExpiring = PhotographerCreditBundle::query()
            ->where('photographer_id', $userId)
            ->usable()
            ->whereNotNull('expires_at')
            ->orderBy('expires_at')
            ->first();

        $warnDays = (int) AppSetting::get('credits_expiry_warn_days_ahead', '7');
        $warnSoon = $nextExpiring
            && $nextExpiring->expires_at
            && $nextExpiring->expires_at->lte(now()->addDays($warnDays));

        return [
            'billing_mode'    => $profile->billing_mode ?? self::defaultBillingModeStatic(),
            'balance'         => $balance,
            'next_expiring'   => $nextExpiring ? [
                'credits'    => (int) $nextExpiring->credits_remaining,
                'expires_at' => $nextExpiring->expires_at->toDateString(),
                'days_left'  => max(0, now()->diffInDays($nextExpiring->expires_at, false)),
                'warn_soon'  => $warnSoon,
            ] : null,
            'last_purchase'   => PhotographerCreditBundle::query()
                ->where('photographer_id', $userId)
                ->where('source', 'purchase')
                ->orderByDesc('id')
                ->value('created_at'),
            'enabled'         => $this->systemEnabled(),
        ];
    }

    private static function defaultBillingModeStatic(): string
    {
        $mode = (string) AppSetting::get('default_billing_mode', PhotographerProfile::BILLING_CREDITS);
        return in_array($mode, [PhotographerProfile::BILLING_CREDITS, PhotographerProfile::BILLING_COMMISSION], true)
            ? $mode
            : PhotographerProfile::BILLING_CREDITS;
    }
}
