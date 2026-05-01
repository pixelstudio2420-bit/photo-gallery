<?php

namespace App\Observers;

use App\Models\Event;
use App\Services\Pricing\BundleService;
use Illuminate\Support\Facades\Log;

/**
 * Seeds default photo bundles when a new event is created.
 *
 * Why this is its own observer instead of being inlined into the Event
 * model:
 *   - Keeps the Event model focused on event lifecycle (visibility,
 *     password, soft-delete, etc.) without dragging pricing concerns in.
 *   - Easier to disable in tests / migrations that bulk-create events.
 *   - Lets BundleService stay the single owner of bundle creation logic.
 *
 * The observer is registered in AppServiceProvider::boot(). It runs on
 * the `created` hook (after the event is persisted) so we have the event
 * id available to set as the foreign key on the new bundle rows.
 *
 * Idempotent by virtue of BundleService::seedDefaultsForEvent() — that
 * method bails early if any bundles already exist for the event, so a
 * double-fire (or a manual recreate of a deleted event) won't double-up.
 */
class EventBundleSeederObserver
{
    public function __construct(private readonly BundleService $bundles) {}

    public function created(Event $event): void
    {
        // Guard against partial-state events. A free event or an event
        // without per-photo pricing has no sensible bundle math, so we
        // skip them silently — the photographer can manually add bundles
        // (or apply a template) once they finalize pricing.
        if (!$event->id) return;
        if ((float) ($event->price_per_photo ?? 0) <= 0) return;
        if ((bool) ($event->is_free ?? false)) return;

        try {
            $created = $this->bundles->seedDefaultsForEvent($event);
            if ($created > 0) {
                Log::info("EventBundleSeederObserver: seeded {$created} bundles for event #{$event->id}");
            }
        } catch (\Throwable $e) {
            // Don't let a bundle-seed failure block the event creation
            // itself. The photographer can always apply a template
            // manually from the event packages page.
            Log::warning("EventBundleSeederObserver failed for event #{$event->id}: " . $e->getMessage());
        }
    }
}
