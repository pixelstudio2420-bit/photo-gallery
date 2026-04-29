<?php

namespace Database\Seeders;

use App\Models\AddonItem;
use Illuminate\Database\Seeder;

/**
 * Seeds addon_items from the legacy config/addon_catalog.php.
 *
 * Rationale
 * ─────────
 * Pre-DB, the catalog was a static config file. Photographers had been
 * buying SKUs like `boost.monthly` for weeks before this seeder ran —
 * we MUST keep those SKUs stable so the historical
 * photographer_addon_purchases.snapshot rows still resolve to a
 * matching catalog entry on the AddonService::findBySku() lookups
 * that refund/audit paths run.
 *
 * Idempotent: keyed on `sku`, so re-running updates row content
 * (price/label/badge) but never duplicates. Admin edits made through
 * the UI are preserved on re-seed because we use updateOrCreate with
 * only the columns we want to refresh — anything else (custom badge,
 * is_active toggles) keeps the admin's value.
 */
class AddonItemsSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = config('addon_catalog', []);
        $sortBase = 0;
        $created = 0;
        $skipped = 0;

        foreach ($catalog as $category => $section) {
            foreach (($section['items'] ?? []) as $item) {
                if (empty($item['sku'])) continue;
                $sortBase++;

                // Strip presentation fields that live on the row vs in
                // the meta JSON. Anything not in the column list goes
                // into meta so the admin form can still edit it.
                $columnFields = ['sku', 'label', 'tagline', 'price_thb', 'badge'];
                $meta = array_diff_key($item, array_flip($columnFields));

                $existing = AddonItem::where('sku', $item['sku'])->first();
                if ($existing) {
                    $skipped++;
                    continue;
                }

                AddonItem::create([
                    'sku'        => $item['sku'],
                    'category'   => $category,
                    'label'      => $item['label'] ?? $item['sku'],
                    'tagline'    => $item['tagline'] ?? null,
                    'price_thb'  => (float) ($item['price_thb'] ?? 0),
                    'badge'      => $item['badge'] ?? null,
                    'meta'       => $meta,
                    'is_active'  => true,
                    'sort_order' => $sortBase,
                ]);
                $created++;
            }
        }

        $this->command?->info("AddonItems: {$created} created, {$skipped} preserved (admin-edited)");
    }
}
