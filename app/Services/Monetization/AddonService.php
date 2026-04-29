<?php

namespace App\Services\Monetization;

use App\Models\PhotographerProfile;
use App\Models\PhotographerPromotion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Photographer self-serve store — catalog lookup, purchase creation,
 * activation when paid.
 *
 * Lifecycle of a purchase
 * -----------------------
 *   1. UI shows catalog from config('addon_catalog')
 *   2. Photographer clicks Buy → POST → buy() creates a purchase row
 *      with status=pending and triggers checkout via PaymentService
 *   3. Webhook lands → OrderObserver detects status=paid → calls
 *      AddonService::activateForOrder($order) which dispatches to the
 *      right per-category activation handler
 *
 * Idempotency
 * -----------
 * activate() is safe to call multiple times — each handler checks the
 * purchase row's status before applying. Webhook replays are common
 * in payment flows and must NOT double-credit storage / promotions.
 */
class AddonService
{
    public function __construct(
        private readonly PromotionService $promotions,
    ) {}

    /**
     * Return the catalog grouped by category. Suitable for direct
     * iteration in Blade templates.
     *
     * Resolution order
     * ────────────────
     *   1. DB (addon_items table) — admin-editable, cached 5 min
     *   2. Config fallback (config/addon_catalog.php) — used during
     *      installs that haven't run AddonItemsSeeder yet, or as a
     *      safety net if the DB query throws.
     *
     * The DB path uses an in-memory cache keyed by AddonItem::CACHE_KEY
     * which the AddonItem model's saved/deleted hooks invalidate. So an
     * admin edit is reflected on the next page load without a deploy.
     */
    public function catalog(): array
    {
        try {
            $cached = \Illuminate\Support\Facades\Cache::remember(
                \App\Models\AddonItem::CACHE_KEY,
                \App\Models\AddonItem::CACHE_TTL,
                fn () => \App\Models\AddonItem::catalogStructure(activeOnly: true),
            );
            if (!empty($cached)) return $cached;
        } catch (\Throwable $e) {
            // DB not migrated yet, table missing, etc. — fall through to config.
            \Illuminate\Support\Facades\Log::debug('AddonService: catalog DB read failed, falling back to config', [
                'error' => $e->getMessage(),
            ]);
        }
        return config('addon_catalog', []);
    }

    /**
     * Find a single catalog entry by SKU. Returns null if the SKU has
     * been removed from config — the caller should treat this as
     * "purchase no longer available", not crash.
     */
    public function findBySku(string $sku): ?array
    {
        foreach ($this->catalog() as $categoryKey => $category) {
            foreach (($category['items'] ?? []) as $item) {
                if (($item['sku'] ?? null) === $sku) {
                    return $item + ['_category' => $categoryKey];
                }
            }
        }
        return null;
    }

    /**
     * Create a pending purchase row for a photographer. Caller is
     * responsible for redirecting to the checkout flow afterwards.
     *
     * @return array{purchase_id:int, sku:string, price_thb:float}
     */
    public function buy(int $photographerUserId, string $sku): array
    {
        $item = $this->findBySku($sku);
        if (!$item) {
            throw new \InvalidArgumentException("addon sku not in catalog: {$sku}");
        }

        $purchaseId = DB::table('photographer_addon_purchases')->insertGetId([
            'photographer_id' => $photographerUserId,
            'sku'             => $sku,
            'category'        => $item['_category'],
            'price_thb'       => (float) $item['price_thb'],
            'snapshot'        => json_encode($item, JSON_UNESCAPED_UNICODE),
            'status'          => 'pending',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return [
            'purchase_id' => (int) $purchaseId,
            'sku'         => $sku,
            'price_thb'   => (float) $item['price_thb'],
        ];
    }

    /**
     * Activate a paid purchase. Dispatches to per-category handlers.
     * Returns true on success, false when nothing was activated (already
     * active, missing config, etc).
     *
     * Called from OrderObserver when an order with metadata pointing to
     * a purchase row flips to 'paid'.
     */
    public function activate(int $purchaseId): bool
    {
        $row = DB::table('photographer_addon_purchases')->where('id', $purchaseId)->first();
        if (!$row) return false;

        // Idempotency — if we already activated, succeed silently.
        if ($row->status === 'activated') return true;

        $snapshot = json_decode((string) $row->snapshot, true) ?: [];
        if (empty($snapshot)) {
            Log::warning('addon.activate.no_snapshot', ['purchase_id' => $purchaseId]);
            return false;
        }

        try {
            $promotionId = match ($row->category) {
                'promotion'  => $this->activatePromotion((int) $row->photographer_id, $snapshot),
                'storage'    => $this->activateStorage((int) $row->photographer_id, $snapshot),
                'ai_credits' => $this->activateAiCredits((int) $row->photographer_id, $snapshot),
                'branding',
                'priority'   => $this->activateFlag((int) $row->photographer_id, $snapshot),
                default      => null,
            };

            $expiresAt = $this->computeExpiry($snapshot);
            DB::table('photographer_addon_purchases')->where('id', $purchaseId)->update([
                'status'        => 'activated',
                'promotion_id'  => $promotionId,
                'activated_at'  => now(),
                'expires_at'    => $expiresAt,
                'updated_at'    => now(),
            ]);

            // Lifecycle notification — fires after the row flips to
            // 'activated'. Idempotency relies on UserNotification::notifyOnce
            // dedup'ing on refId (addon.{purchaseId}.activated), so even if
            // the OrderObserver retries via webhook replay we don't spam.
            try {
                app(\App\Services\Notifications\PhotographerLifecycleNotifier::class)
                    ->addonActivated(
                        photographerId: (int) $row->photographer_id,
                        purchaseId:     $purchaseId,
                        snapshot:       $snapshot,
                        expiresAt:      $expiresAt,
                    );
            } catch (\Throwable $e) {
                Log::debug('AddonService: activated notify skipped', [
                    'purchase_id' => $purchaseId,
                    'error'       => $e->getMessage(),
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('addon.activate.failed', [
                'purchase_id' => $purchaseId,
                'category'    => $row->category,
                'err'         => $e->getMessage(),
            ]);
            DB::table('photographer_addon_purchases')->where('id', $purchaseId)->update([
                'status'     => 'failed',
                'updated_at' => now(),
            ]);
            return false;
        }
    }

    /* ────────────────── per-category activation ────────────────── */

    /**
     * Promotion category — delegates to PromotionService::create().
     * Returns the new promotion's id so we can link it back on the
     * purchase row for refund/audit visibility.
     */
    private function activatePromotion(int $photographerId, array $item): int
    {
        $promo = $this->promotions->create([
            'photographer_id' => $photographerId,
            'kind'            => $item['kind']  ?? 'boost',
            'billing_cycle'   => $item['cycle'] ?? 'monthly',
            'amount_thb'      => $item['price_thb'],
            'placement'       => 'global',
            'status'          => PhotographerPromotion::STATUS_ACTIVE,
            'meta'            => ['source' => 'self_serve', 'sku' => $item['sku']],
        ]);
        return $promo->id;
    }

    /**
     * Storage top-up — bumps storage_quota_bytes by the configured
     * amount. Top-ups are additive: a 200GB pack adds 200GB on top of
     * whatever the photographer already had.
     */
    private function activateStorage(int $photographerId, array $item): ?int
    {
        $deltaBytes = (int) ($item['storage_gb'] ?? 0) * 1024 * 1024 * 1024;
        if ($deltaBytes <= 0) return null;

        $profile = PhotographerProfile::where('user_id', $photographerId)->first();
        if (!$profile) return null;

        $current = (int) ($profile->storage_quota_bytes ?? 0);
        // Treat "null quota" as "default plan quota" for the purposes of
        // top-up addition. Without this, a photographer on the default
        // 100GB Pro plan would see "100GB" become exactly "+200GB" not
        // "300GB" — confusing.
        if ($current === 0) {
            $planQuota = (int) ($profile->subscription_plan_code
                ? optional(\App\Models\SubscriptionPlan::where('code', $profile->subscription_plan_code)->first())->storage_bytes
                : 0);
            $current = max($current, $planQuota);
        }
        $profile->update(['storage_quota_bytes' => $current + $deltaBytes]);
        return null;
    }

    /**
     * AI credits top-up — adds to current month bucket. The existing
     * SubscriptionService already has aiCreditsRemaining() which reads
     * a cached counter, so we just bump the counter directly.
     */
    private function activateAiCredits(int $photographerId, array $item): ?int
    {
        $delta = (int) ($item['credits'] ?? 0);
        if ($delta <= 0) return null;

        // ai_credits_used is the consumed counter; we DECREMENT it to add
        // headroom (rather than maintaining a separate "topup" column,
        // which would need joining everywhere). Floor at 0 if the
        // photographer hasn't used any credits yet.
        //
        // Use CASE instead of GREATEST() — Postgres supports both, but
        // SQLite (used in tests + small installs) only has GREATEST in
        // very recent versions. CASE works everywhere.
        DB::table('photographer_profiles')
            ->where('user_id', $photographerId)
            ->update([
                'ai_credits_used' => DB::raw(
                    "CASE WHEN ai_credits_used > {$delta}
                          THEN ai_credits_used - {$delta}
                          ELSE 0 END"
                ),
                'updated_at'      => now(),
            ]);
        return null;
    }

    /**
     * Branding/Priority flags — flip a setting on the profile. The
     * existing custom_branding / priority_upload subscription gates
     * are read-through, so once the flag is on the feature is live.
     */
    private function activateFlag(int $photographerId, array $item): ?int
    {
        // Stored on app_settings keyed by photographer id — these are
        // photographer-scoped overrides, not subscription features.
        $sku = (string) ($item['sku'] ?? '');
        $key = "addon_flag:{$photographerId}:{$sku}";
        \App\Models\AppSetting::set($key, '1');
        return null;
    }

    private function computeExpiry(array $item): ?\Carbon\CarbonInterface
    {
        if (!empty($item['one_time'])) return null;   // lifetime

        $cycle = $item['cycle'] ?? null;
        return match ($cycle) {
            'daily'   => now()->addDay(),
            'monthly' => now()->addMonth(),
            'yearly'  => now()->addYear(),
            default   => null,
        };
    }
}
