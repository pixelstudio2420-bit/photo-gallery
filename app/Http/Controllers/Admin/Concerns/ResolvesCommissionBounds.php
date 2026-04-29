<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\AppSetting;

/**
 * ResolvesCommissionBounds — honour the admin-configured commission floor
 * and ceiling when validating `commission_rate` on photographer profiles
 * and commission tiers.
 *
 * Why this trait exists
 * ---------------------
 * The settings screen at /admin/commission/settings lets an admin set
 * `min_commission_rate` and `max_commission_rate` (defaults 50/95). But
 * every other form that touches `commission_rate` was validating against
 * a hard-coded `min:0|max:100` — so an admin could open a photographer's
 * edit page and set them to 99% even though the configured ceiling was 95.
 *
 * This trait centralises the rule so adding another route that takes a
 * commission value doesn't require remembering to re-copy the bounds.
 */
trait ResolvesCommissionBounds
{
    /**
     * Laravel validation rule for commission_rate, clamped to the
     * admin-configured floor/ceiling. Falls back to the full 0-100
     * range if the settings aren't present or have been corrupted.
     */
    protected function commissionRateRule(): string
    {
        [$min, $max] = $this->commissionBounds();
        return "required|numeric|min:{$min}|max:{$max}";
    }

    /**
     * Thai-language messages for min/max violations — the admin who
     * hits this needs to know *why* it failed and where to adjust it.
     */
    protected function commissionRateMessages(): array
    {
        [$min, $max] = $this->commissionBounds();
        return [
            'commission_rate.min' => "ค่าคอมมิชชั่นต้องไม่น้อยกว่า {$min}% (แก้ได้ที่ Commission Settings)",
            'commission_rate.max' => "ค่าคอมมิชชั่นต้องไม่เกิน {$max}% (แก้ได้ที่ Commission Settings)",
        ];
    }

    /**
     * Resolve [min, max] from AppSetting, coerced into sane absolute
     * bounds. A bad DB row (e.g. max < min) can't slip through and
     * brick every photographer update form.
     *
     * @return array{0: float, 1: float}
     */
    private function commissionBounds(): array
    {
        $min = (float) AppSetting::get('min_commission_rate', 0);
        $max = (float) AppSetting::get('max_commission_rate', 100);

        $min = max(0.0, min(100.0, $min));
        $max = max($min, min(100.0, $max));

        return [$min, $max];
    }
}
