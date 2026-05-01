<?php

namespace App\Observers;

use App\Models\Event;
use App\Services\Pricing\BundleService;
use App\Services\EventPriceResolver;
use Illuminate\Support\Facades\Log;

/**
 * Auto-recalculate bundle prices whenever Event.price_per_photo changes.
 *
 * Without this observer, the photographer's experience was:
 *   1. Create event at ฿100/photo → seeder builds 3รูป=฿269, 6รูป=฿879, …
 *   2. Realize they undersold → bump per_photo to ฿200
 *   3. Bundles still show old ฿269 / ฿879 prices → buyers grab a 6-photo
 *      bundle for ฿879 = ฿146/photo (a hidden 27% discount instead of
 *      the intended 22%). Photographer loses revenue silently.
 *
 * This observer closes that loop: any time per_photo changes, we
 * re-derive every count + event_all bundle through SmartPricingService
 * with the new price. The photographer's manually-set is_featured /
 * sort_order / badges are preserved — only the price math is refreshed.
 *
 * Triggers on UPDATE only (creating events come through
 * EventBundleSeederObserver instead, which builds the initial bundles
 * from scratch).
 *
 * Idempotent: if per_photo didn't actually change in this update, the
 * wasChanged() guard skips the work entirely. Safe to call inside any
 * Event::save() chain without amplifying writes.
 *
 * Also busts the per-event price cache so every read after the save
 * sees the fresh number — the EventPriceResolver caches for 60s and
 * that cache is what powered the SEED in the first place; if we don't
 * forget it here, recalc would still see the stale price.
 */
class EventPriceChangeObserver
{
    public function __construct(
        private readonly BundleService $bundles,
        private readonly EventPriceResolver $priceResolver,
    ) {}

    /**
     * Hook into the UPDATE lifecycle. The created hook is owned by
     * EventBundleSeederObserver, which seeds an initial bundle set
     * from the freshly-attached per_photo — running this method on
     * 'created' would double-fire.
     */
    public function updated(Event $event): void
    {
        // Skip if per_photo didn't change this save.
        if (!$event->wasChanged('price_per_photo')) {
            return;
        }

        // Skip free events — bundles don't make sense at ฿0/photo and
        // the seeder also no-ops in that case, so we'd just be running
        // an empty loop.
        if ((float) ($event->price_per_photo ?? 0) <= 0) {
            return;
        }

        try {
            // Bust the resolver cache first so recalc reads the new value.
            $this->priceResolver->forget($event->id);

            [$updated, $skipped] = $this->bundles->recalculatePrices($event);

            if ($updated > 0) {
                Log::info('EventPriceChangeObserver: auto-recalc bundles', [
                    'event_id' => $event->id,
                    'old_price'  => $event->getOriginal('price_per_photo'),
                    'new_price'  => $event->price_per_photo,
                    'updated'  => $updated,
                    'skipped'  => $skipped,
                ]);
            }
        } catch (\Throwable $e) {
            // Bundle recalc is a nice-to-have — never block the
            // photographer's price update because of it. They can
            // always click "ปรับราคาทั้งหมด" manually from the
            // packages page.
            Log::warning('EventPriceChangeObserver failed', [
                'event_id' => $event->id,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
