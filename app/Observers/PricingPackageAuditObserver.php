<?php

namespace App\Observers;

use App\Models\PricingPackage;
use App\Models\PricingPackageLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Audit every mutation on a pricing_packages row.
 *
 * Every create / update / delete writes one PricingPackageLog row
 * with the actor's user_id, IP, role guess (photographer / admin /
 * system), and a JSON snapshot of the old + new state. Failures here
 * are intentionally swallowed — losing an audit row is bad, but
 * losing the underlying business action because we couldn't write
 * the log is worse.
 *
 * Why an observer (not inline calls): every controller, seeder, and
 * service that mutates packages would otherwise need to remember
 * to log. An observer hooks the model lifecycle so logging happens
 * automatically — the "every change is logged" guarantee survives
 * future code that adds new pathways.
 *
 * Reason / role hints:
 *   - controllers set them via PricingPackage::auditReason(...) before
 *     the save() call (see EventPackageController for examples)
 *   - if not set, defaults to "system" / null reason — clearly
 *     identifies background changes (auto-recalc, observer-seeded)
 */
class PricingPackageAuditObserver
{
    public function created(PricingPackage $package): void
    {
        $this->log($package, 'create', null, $package->toArray());
    }

    public function updated(PricingPackage $package): void
    {
        $changes = $package->getChanges();

        // Skip purely-mechanical updates (e.g. purchase_count++ from
        // OrderObserver) — those aren't human edits and the audit
        // log would drown in noise.
        $relevant = array_diff_key($changes, array_flip([
            'updated_at',
            'purchase_count',
        ]));
        if (empty($relevant)) return;

        $old = array_intersect_key(
            $package->getOriginal(),
            $changes
        );

        $this->log($package, 'update', $old, $changes);
    }

    public function deleted(PricingPackage $package): void
    {
        $this->log($package, 'delete', $package->toArray(), null);
    }

    /**
     * Persist one audit row. All errors are caught and logged at
     * warning level — the write must NEVER prevent the underlying
     * mutation from completing.
     */
    private function log(PricingPackage $package, string $action, ?array $old, ?array $new): void
    {
        try {
            $reason = $package->auditReason ?? null;
            $roleHint = $package->auditRole ?? $this->guessRole();

            PricingPackageLog::create([
                'package_id'      => $package->id,
                'event_id'        => $package->event_id,
                'action'          => $action,
                'old_values'      => $old,
                'new_values'      => $new,
                'changed_by'      => Auth::id(),
                'changed_by_role' => $roleHint,
                'reason'          => $reason,
                'ip_address'      => $this->ip(),
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('PricingPackageAuditObserver: log write failed', [
                'package_id' => $package->id,
                'action'     => $action,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Best-effort role inference. Authoritative cases (admin / photographer
     * controller) override this by setting $package->auditRole on the
     * model instance before save().
     */
    private function guessRole(): string
    {
        if (Auth::guard('admin')->check())        return 'admin';
        if (Auth::guard('photographer')->check()) return 'photographer';
        if (Auth::check())                        return 'photographer'; // default web guard
        return 'system';
    }

    private function ip(): ?string
    {
        try {
            return Request::ip();
        } catch (\Throwable) {
            return null;
        }
    }
}
