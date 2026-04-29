<?php

namespace App\Console\Commands;

use App\Services\Notifications\PhotographerLifecycleNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Add-on expiry sweeper + countdown reminders.
 *
 * Photographers buy boost campaigns, AI credit packs, and priority
 * lanes via /photographer/store. These have an `expires_at` timestamp
 * but the V1 store had no scheduler to (a) warn before they end or
 * (b) flip the row's status to 'expired' so the UI stops showing
 * them as active.
 *
 * This command runs daily and does both:
 *
 *   A. Warn at T-3 day (one-shot via notifyOnce(refId.expiring.3d))
 *   B. Expire any row whose expires_at has already passed but status
 *      is still 'activated' — flip to 'expired' + fire notification
 *
 * One-time add-ons (`one_time => true` in catalog) have null
 * expires_at and are skipped from both branches.
 */
class AddonsNotifyExpiringCommand extends Command
{
    protected $signature   = 'addons:notify-expiring
                              {--quiet-if-none : Suppress output when no addons match}';
    protected $description = 'Warn + auto-expire photographer add-ons (storage, AI credits, promotions)';

    public function handle(PhotographerLifecycleNotifier $notifier): int
    {
        $now = now();
        $warned = 0;
        $expired = 0;

        // ── A. T-3 day warning ──
        $window = [$now->copy()->addDays(3)->startOfDay(),
                   $now->copy()->addDays(3)->endOfDay()];
        $rows = DB::table('photographer_addon_purchases')
            ->where('status', 'activated')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', $window)
            ->get();

        foreach ($rows as $row) {
            try {
                $snapshot = json_decode((string) $row->snapshot, true) ?: [];
                $notifier->addonExpiringSoon(
                    photographerId: (int) $row->photographer_id,
                    purchaseId:     (int) $row->id,
                    snapshot:       $snapshot,
                    expiresAt:      \Carbon\Carbon::parse($row->expires_at),
                );
                $warned++;
            } catch (\Throwable $e) {
                Log::warning('addons:notify-expiring T-3 failed', [
                    'purchase_id' => $row->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        // ── B. Auto-expire past-due rows ──
        // Find activated rows whose expires_at < now. Flip status →
        // 'expired' and fire the notification. The UPDATE is bounded by
        // status='activated' so re-runs are idempotent (already-expired
        // rows are skipped).
        $expireRows = DB::table('photographer_addon_purchases')
            ->where('status', 'activated')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->get();

        foreach ($expireRows as $row) {
            try {
                $rowsAffected = DB::table('photographer_addon_purchases')
                    ->where('id', $row->id)
                    ->where('status', 'activated')   // race guard
                    ->update([
                        'status'     => 'expired',
                        'updated_at' => $now,
                    ]);
                if ($rowsAffected === 0) continue;   // someone else got it

                $snapshot = json_decode((string) $row->snapshot, true) ?: [];
                $notifier->addonExpired(
                    photographerId: (int) $row->photographer_id,
                    purchaseId:     (int) $row->id,
                    snapshot:       $snapshot,
                );
                $expired++;
            } catch (\Throwable $e) {
                Log::warning('addons:notify-expiring expire failed', [
                    'purchase_id' => $row->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        if ($warned === 0 && $expired === 0 && $this->option('quiet-if-none')) {
            return self::SUCCESS;
        }
        $this->info("addons:notify-expiring warned={$warned} expired={$expired}");
        return self::SUCCESS;
    }
}
