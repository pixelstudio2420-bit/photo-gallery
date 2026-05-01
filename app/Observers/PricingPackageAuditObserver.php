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
     * Persist one audit row.
     *
     * The write is wrapped in a defensive try-block — losing an audit
     * write must never block a legitimate business action. But during
     * the rollout (May 2026) we surface failures into the standard
     * Laravel log channel at ERROR level (not warning) so they appear
     * in Cloud's log stream and we don't lose them while the system
     * is still under verification. The try/catch can be tightened to
     * `warning` later, once the path is proven reliable.
     */
    private function log(PricingPackage $package, string $action, ?array $old, ?array $new): void
    {
        // DEBUG MODE: re-throw failures so they surface during the
        // 2026-05-01 verification window. The previous swallow-into-Log
        // approach hid an exception that wasn't visible from tinker
        // (Laravel Cloud sends Log::error to CloudWatch, not the local
        // file system). Once the audit pipeline is verified end-to-end
        // we'll restore the safe try/catch fallback.
        $reason   = $package->auditReason ?? null;
        $roleHint = $package->auditRole ?? $this->guessRole();

        $oldClean = $old !== null ? $this->safeJsonEncode($old) : null;
        $newClean = $new !== null ? $this->safeJsonEncode($new) : null;

        PricingPackageLog::create([
            'package_id'      => $package->id,
            'event_id'        => $package->event_id,
            'action'          => $action,
            'old_values'      => $oldClean,
            'new_values'      => $newClean,
            'changed_by'      => Auth::id(),
            'changed_by_role' => $roleHint,
            'reason'          => $reason,
            'ip_address'      => $this->ip(),
            'created_at'      => now(),
        ]);
    }

    /**
     * Round-trip an array through json_encode/json_decode so any
     * Carbon / object instances become plain string/array primitives.
     * Without this, the JSON cast on the log column can throw at
     * INSERT time when it encounters an instance it doesn't know
     * how to serialize.
     */
    private function safeJsonEncode(array $values): array
    {
        $json = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            // json_encode failed (probably circular ref or non-UTF8).
            // Fall back to per-key string coercion so we still write
            // SOMETHING useful.
            $out = [];
            foreach ($values as $k => $v) {
                $out[$k] = is_scalar($v) || $v === null ? $v : (string) (is_object($v) ? get_class($v) : gettype($v));
            }
            return $out;
        }
        return json_decode($json, true) ?: [];
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
