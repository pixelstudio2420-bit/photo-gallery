<?php

namespace App\Services;

use App\Http\Controllers\Admin\FeatureFlagController;
use App\Models\PhotographerProfile;
use App\Models\PhotographerSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;

/**
 * Pre-flight checker for plan changes — answers "should the user be
 * allowed to switch to plan X?" and returns a diff/warnings packet
 * the UI can render in a confirmation modal.
 *
 * Three kinds of result:
 *
 *   • allowed=true, blockers=[], warnings=[]
 *     → safe upgrade; UI can submit immediately
 *
 *   • allowed=true, warnings=[...]
 *     → e.g. downgrade where files still fit but events exceed cap.
 *       UI should show a confirmation modal that highlights what the
 *       photographer is giving up before they commit.
 *
 *   • allowed=false, blockers=[...]
 *     → e.g. downgrade to Free with 95 GB of files. Submit must be
 *       prevented and the user nudged to delete data or pick a
 *       different tier first.
 *
 * Industry-standard model (Dropbox / Notion / Google Workspace):
 *   - Files / events / data NEVER deleted automatically. Photographer
 *     keeps everything they ever uploaded.
 *   - HARD blockers only fire on downgrades where the new plan can't
 *     legally hold the photographer's existing usage (e.g. shrinking
 *     storage below current consumption).
 *   - SOFT warnings fire when something will become read-only or
 *     gated post-change (e.g. losing AI face-search means existing
 *     events keep their indexed faces but no new searches happen).
 */
class PlanChangePreflightService
{
    public function __construct(private SubscriptionService $subs) {}

    /**
     * Run every check and return a packet the controller can return
     * verbatim as JSON for the UI's confirmation modal.
     *
     * @return array{
     *   allowed: bool,
     *   is_upgrade: bool,
     *   is_downgrade: bool,
     *   is_same_plan: bool,
     *   blockers: array<int, array{code:string, label:string, detail:string}>,
     *   warnings: array<int, array{code:string, label:string, detail:string}>,
     *   diff: array<string, mixed>,
     * }
     */
    public function check(PhotographerProfile $profile, SubscriptionPlan $newPlan): array
    {
        $currentSub  = $this->subs->currentSubscription($profile);
        $currentPlan = $currentSub?->plan ?? SubscriptionPlan::defaultFree();

        $isSamePlan  = $currentPlan && $currentPlan->id === $newPlan->id;
        $oldPrice    = (float) ($currentPlan?->price_thb ?? 0);
        $newPrice    = (float) ($newPlan->price_thb ?? 0);
        $isUpgrade   = !$isSamePlan && $newPrice > $oldPrice;
        $isDowngrade = !$isSamePlan && $newPrice < $oldPrice;

        $blockers = [];
        $warnings = [];

        if ($isSamePlan) {
            return [
                'allowed'      => false,
                'is_upgrade'   => false,
                'is_downgrade' => false,
                'is_same_plan' => true,
                'blockers'     => [[
                    'code'   => 'same_plan',
                    'label'  => 'คุณอยู่ในแผนนี้อยู่แล้ว',
                    'detail' => "ไม่ต้องเปลี่ยน — แผน {$currentPlan->name} ใช้งานได้ปกติ",
                ]],
                'warnings'     => [],
                'diff'         => $this->diff($currentPlan, $newPlan, $profile),
            ];
        }

        // ── Hard blockers (downgrade with data that won't fit) ────────
        if ($isDowngrade) {
            $usedBytes  = (int) ($profile->storage_used_bytes ?? 0);
            $newQuotaBytes = (int) ($newPlan->storage_bytes ?? 0);
            if ($newQuotaBytes > 0 && $usedBytes > $newQuotaBytes) {
                $blockers[] = [
                    'code'   => 'storage_overflow',
                    'label'  => 'พื้นที่ที่ใช้อยู่เกินโควต้าใหม่',
                    'detail' => sprintf(
                        'คุณใช้พื้นที่ %s GB อยู่ — แผน %s รองรับแค่ %s GB · ต้องลบไฟล์ออกประมาณ %s GB หรือเลือกแผนที่ใหญ่กว่า',
                        $this->gb($usedBytes),
                        $newPlan->name,
                        $this->gb($newQuotaBytes),
                        $this->gb($usedBytes - $newQuotaBytes),
                    ),
                ];
            }

            // Active event count vs new plan's max_concurrent_events.
            // We DON'T block on this (events can stay open, just no
            // NEW events accepted past cap) but we surface it as a
            // warning so the photographer knows.
            $maxEvents = $newPlan->max_concurrent_events;
            if ($maxEvents !== null) {
                $activeEvents = (int) DB::table('event_events')
                    ->where('photographer_id', $profile->user_id)
                    ->whereIn('status', ['active', 'published'])
                    ->count();
                if ($activeEvents > (int) $maxEvents) {
                    $warnings[] = [
                        'code'   => 'events_over_cap',
                        'label'  => 'อีเวนต์ที่เปิดอยู่เกินขีดจำกัดใหม่',
                        'detail' => sprintf(
                            'คุณมี %d อีเวนต์เปิดอยู่ · แผน %s เปิดได้พร้อมกันสูงสุด %d อีเวนต์ · งานเดิมยังคงเปิดอยู่ แต่จะเปิดใหม่ได้ก็ต่อเมื่อปิดงานเก่าให้น้อยกว่า cap',
                            $activeEvents,
                            $newPlan->name,
                            (int) $maxEvents,
                        ),
                    ];
                }
            }

            // AI features that exist on current plan but missing on
            // new plan = will lock post-change. Soft warning only —
            // photographer's already-indexed event data stays
            // searchable for buyers; the photographer just can't run
            // NEW AI operations.
            $currentFeatures = (array) ($currentPlan?->ai_features ?? []);
            $newFeatures     = (array) ($newPlan->ai_features ?? []);
            $lostFeatures    = array_values(array_diff($currentFeatures, $newFeatures));
            if (!empty($lostFeatures)) {
                $featureLabels = collect(FeatureFlagController::FEATURES)
                    ->mapWithKeys(fn($v, $k) => [$k => $v[0] ?? $k]);
                $lostLabels = array_map(
                    fn($code) => $featureLabels[$code] ?? $code,
                    $lostFeatures
                );
                $warnings[] = [
                    'code'   => 'features_lost',
                    'label'  => 'ฟีเจอร์ที่จะถูกปิดหลังเปลี่ยนแผน',
                    'detail' => 'จะใช้ไม่ได้: ' . implode(', ', $lostLabels)
                              . ' · ข้อมูลเดิมยังอยู่ครบ แต่ฟีเจอร์เหล่านี้จะถูกล็อกในแผนใหม่',
                ];
            }

            // Commission going UP (e.g. Pro 0% → Free 20%) reduces
            // the photographer's keep-rate on every future sale.
            $oldComm = (float) ($currentPlan?->commission_pct ?? 0);
            $newComm = (float) ($newPlan->commission_pct ?? 0);
            if ($newComm > $oldComm + 0.01) {
                $warnings[] = [
                    'code'   => 'commission_up',
                    'label'  => 'ค่าคอมมิชชั่นจะเพิ่มขึ้น',
                    'detail' => sprintf(
                        'จาก %s%% → %s%% · platform จะหักจากยอดขายในอัตราใหม่ทันที',
                        $this->pct($oldComm),
                        $this->pct($newComm),
                    ),
                ];
            }
        }

        return [
            'allowed'      => empty($blockers),
            'is_upgrade'   => $isUpgrade,
            'is_downgrade' => $isDowngrade,
            'is_same_plan' => false,
            'blockers'     => $blockers,
            'warnings'     => $warnings,
            'diff'         => $this->diff($currentPlan, $newPlan, $profile),
        ];
    }

    /**
     * Build the diff packet — what's GAINED and what's LOST in the
     * change. UI renders these as +/- chips in the confirmation modal.
     */
    private function diff(?SubscriptionPlan $cur, SubscriptionPlan $new, PhotographerProfile $profile): array
    {
        $curStorage = (int) ($cur?->storage_bytes ?? 0);
        $newStorage = (int) ($new->storage_bytes ?? 0);
        $curAi      = (int) ($cur?->monthly_ai_credits ?? 0);
        $newAi      = (int) ($new->monthly_ai_credits ?? 0);
        $curComm    = (float) ($cur?->commission_pct ?? 0);
        $newComm    = (float) ($new->commission_pct ?? 0);
        $curEvents  = $cur?->max_concurrent_events;
        $newEvents  = $new->max_concurrent_events;
        $curSeats   = (int) ($cur?->max_team_seats ?? 1);
        $newSeats   = (int) ($new->max_team_seats ?? 1);

        $curFeatures = (array) ($cur?->ai_features ?? []);
        $newFeatures = (array) ($new->ai_features ?? []);
        $featureLabels = collect(FeatureFlagController::FEATURES)
            ->mapWithKeys(fn($v, $k) => [$k => $v[0] ?? $k]);

        $gainedCodes = array_values(array_diff($newFeatures, $curFeatures));
        $lostCodes   = array_values(array_diff($curFeatures, $newFeatures));

        return [
            'current_plan' => [
                'code' => $cur?->code,
                'name' => $cur?->name,
                'price_thb' => (float) ($cur?->price_thb ?? 0),
            ],
            'new_plan' => [
                'code' => $new->code,
                'name' => $new->name,
                'price_thb' => (float) $new->price_thb,
                'price_annual_thb' => $new->price_annual_thb !== null ? (float) $new->price_annual_thb : null,
            ],
            'storage' => [
                'current_gb'  => $this->gb($curStorage),
                'new_gb'      => $this->gb($newStorage),
                'delta_gb'    => $this->gb($newStorage - $curStorage),
                'used_gb'     => $this->gb((int) ($profile->storage_used_bytes ?? 0)),
                'direction'   => $newStorage > $curStorage ? 'up' : ($newStorage < $curStorage ? 'down' : 'same'),
            ],
            'ai_credits' => [
                'current'   => $curAi,
                'new'       => $newAi,
                'delta'     => $newAi - $curAi,
                'direction' => $newAi > $curAi ? 'up' : ($newAi < $curAi ? 'down' : 'same'),
            ],
            'commission_pct' => [
                'current' => $curComm,
                'new'     => $newComm,
                'delta_pp' => round($newComm - $curComm, 2),
                'direction' => $newComm < $curComm ? 'up' : ($newComm > $curComm ? 'down' : 'same'),
            ],
            'max_concurrent_events' => [
                'current' => $curEvents,
                'new'     => $newEvents,
            ],
            'team_seats' => [
                'current'   => $curSeats,
                'new'       => $newSeats,
                'delta'     => $newSeats - $curSeats,
                'direction' => $newSeats > $curSeats ? 'up' : ($newSeats < $curSeats ? 'down' : 'same'),
            ],
            'features_gained' => array_map(
                fn($code) => ['code' => $code, 'label' => $featureLabels[$code] ?? $code],
                $gainedCodes,
            ),
            'features_lost' => array_map(
                fn($code) => ['code' => $code, 'label' => $featureLabels[$code] ?? $code],
                $lostCodes,
            ),
        ];
    }

    private function gb(int $bytes): float
    {
        return round($bytes / 1073741824, 2);
    }

    private function pct(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.');
    }
}
