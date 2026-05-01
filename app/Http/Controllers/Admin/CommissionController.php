<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesCommissionBounds;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CommissionLog;
use App\Models\CommissionTier;
use App\Models\PhotographerPayout;
use App\Models\PhotographerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    use ResolvesCommissionBounds;


    // ═══════════════════════════════════════
    //  Dashboard
    // ═══════════════════════════════════════
    public function index()
    {
        // ── Plan-based commission breakdown ────────────────────────────
        // Since 2026-04-30 the AUTHORITATIVE commission source is the
        // photographer's subscription plan (`subscription_plans.commission_pct`).
        // The legacy `platform_commission` AppSetting only acts as a
        // fallback for accounts with no plan attached. So the dashboard
        // banner needs to reflect plan rates, not the legacy single number.
        $plans = DB::table('subscription_plans')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get(['code', 'name', 'commission_pct', 'is_public']);

        // "Default rate" = the rate a brand-new photographer effectively
        // pays. Brand-new accounts are auto-assigned to the Free plan, so
        // that's what we display. Fall back to the legacy AppSetting if
        // the Free plan row somehow isn't there.
        $freePlan = $plans->firstWhere('code', 'free');
        $defaultPlatformPct = $freePlan
            ? (float) $freePlan->commission_pct
            : (float) AppSetting::get('platform_commission', 30);

        // Per-plan photographer counts (so the banner can show
        // "X photographers on Free, Y on Pro, …" for context).
        $planCounts = PhotographerProfile::where('status', 'approved')
            ->select('subscription_plan_code', DB::raw('COUNT(*) as count'))
            ->groupBy('subscription_plan_code')
            ->pluck('count', 'subscription_plan_code');

        // Overall stats
        $stats = [
            'default_platform_rate'     => $defaultPlatformPct,
            'default_photographer_rate' => 100 - $defaultPlatformPct,
            'total_revenue'             => PhotographerPayout::sum('gross_amount'),
            'total_platform_fee'        => PhotographerPayout::sum('platform_fee'),
            'total_photographer_payout' => PhotographerPayout::sum('payout_amount'),
            'pending_payout'            => PhotographerPayout::where('status', 'pending')->sum('payout_amount'),
            'paid_payout'               => PhotographerPayout::whereIn('status', ['paid', 'completed'])->sum('payout_amount'),
            'avg_commission_rate'       => PhotographerProfile::where('status', 'approved')->avg('commission_rate') ?? 0,
            'photographers_count'       => PhotographerProfile::where('status', 'approved')->count(),
        ];

        // Top earners
        $topEarners = PhotographerProfile::where('status', 'approved')
            ->with('user')
            ->get()
            ->map(function ($pg) {
                $payouts = PhotographerPayout::where('photographer_id', $pg->user_id);
                $pg->total_gross    = $payouts->sum('gross_amount');
                $pg->total_payout   = $payouts->sum('payout_amount');
                $pg->total_platform = $payouts->sum('platform_fee');
                $pg->payout_count   = $payouts->count();
                return $pg;
            })
            ->sortByDesc('total_gross')
            ->take(10);

        // Commission distribution (group photographers by their commission rate)
        $distribution = PhotographerProfile::where('status', 'approved')
            ->selectRaw('commission_rate, COUNT(*) as count')
            ->groupBy('commission_rate')
            ->orderBy('commission_rate')
            ->get();

        // Active tiers
        $tiers = CommissionTier::active()->ordered()->get();

        // Recent changes
        $recentLogs = CommissionLog::with('photographer')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.commission.index', compact('stats', 'topEarners', 'distribution', 'tiers', 'recentLogs', 'plans', 'planCounts'));
    }

    // ═══════════════════════════════════════
    //  Settings (default rates)
    // ═══════════════════════════════════════
    public function settings()
    {
        $settings = [
            'platform_commission'          => (float) AppSetting::get('platform_commission', 20),
            'photographer_commission_rate'  => (float) AppSetting::get('photographer_commission_rate', 80),
            'min_commission_rate'           => (float) AppSetting::get('min_commission_rate', 50),
            'max_commission_rate'           => (float) AppSetting::get('max_commission_rate', 95),
            'auto_tier_enabled'            => AppSetting::get('auto_tier_enabled', '0'),
            'min_event_price'              => (float) AppSetting::get('min_event_price', 100),
            'allow_free_events'            => AppSetting::get('allow_free_events', '1'),
        ];

        $tiers = CommissionTier::ordered()->get();

        return view('admin.commission.settings', compact('settings', 'tiers'));
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'platform_commission'         => 'required|numeric|min:1|max:50',
            'min_commission_rate'         => 'required|numeric|min:30|max:95',
            'max_commission_rate'         => 'required|numeric|min:50|max:99|gte:min_commission_rate',
            'auto_tier_enabled'           => 'nullable|in:0,1',
            'min_event_price'             => 'nullable|numeric|min:100|max:10000',
            'allow_free_events'           => 'nullable|in:0,1',
        ], [
            'max_commission_rate.gte' => 'ค่าสูงสุดต้องมากกว่าหรือเท่ากับค่าต่ำสุด',
            'min_event_price.min' => 'ราคาขั้นต่ำต่อภาพต้องไม่น้อยกว่า 100 บาท',
            'min_event_price.max' => 'ราคาขั้นต่ำต่อภาพต้องไม่เกิน 10,000 บาท',
        ]);

        $photographerRate = 100 - $request->platform_commission;

        AppSetting::set('platform_commission', $request->platform_commission);
        AppSetting::set('photographer_commission_rate', $photographerRate);
        AppSetting::set('min_commission_rate', $request->min_commission_rate);
        AppSetting::set('max_commission_rate', $request->max_commission_rate);
        AppSetting::set('auto_tier_enabled', $request->auto_tier_enabled ?? '0');

        // Event pricing settings
        if ($request->has('min_event_price')) {
            AppSetting::set('min_event_price', $request->min_event_price);
        }
        AppSetting::set('allow_free_events', $request->allow_free_events ?? '0');

        return back()->with('success', 'บันทึกการตั้งค่าสำเร็จ');
    }

    // ═══════════════════════════════════════
    //  Tiers CRUD
    // ═══════════════════════════════════════
    public function tiers()
    {
        $tiers = CommissionTier::ordered()->get();
        return view('admin.commission.tiers', compact('tiers'));
    }

    public function storeTier(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'min_revenue'     => 'required|numeric|min:0',
            'commission_rate' => $this->commissionRateRule(),
            'color'           => 'required|string|max:7',
            'icon'            => 'nullable|string|max:50',
            'description'     => 'nullable|string|max:500',
            'is_active'       => 'nullable|boolean',
        ], $this->commissionRateMessages());

        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = CommissionTier::max('sort_order') + 1;

        CommissionTier::create($validated);

        return back()->with('success', "สร้างระดับ \"{$validated['name']}\" สำเร็จ");
    }

    public function updateTier(Request $request, CommissionTier $tier)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'min_revenue'     => 'required|numeric|min:0',
            'commission_rate' => $this->commissionRateRule(),
            'color'           => 'required|string|max:7',
            'icon'            => 'nullable|string|max:50',
            'description'     => 'nullable|string|max:500',
            'is_active'       => 'nullable|boolean',
        ], $this->commissionRateMessages());

        $validated['is_active'] = $request->has('is_active');
        $tier->update($validated);

        return back()->with('success', "อัพเดทระดับ \"{$tier->name}\" สำเร็จ");
    }

    public function destroyTier(CommissionTier $tier)
    {
        $name = $tier->name;
        $tier->delete();
        return back()->with('success', "ลบระดับ \"{$name}\" สำเร็จ");
    }

    public function applyTiers()
    {
        $tiers = CommissionTier::active()->ordered()->get();

        if ($tiers->isEmpty()) {
            return back()->with('error', 'ไม่มีระดับคอมมิชชั่นที่ใช้งานอยู่');
        }

        $adminId = Auth::guard('admin')->id();
        $photographers = PhotographerProfile::where('status', 'approved')->get();
        $updated = 0;

        foreach ($photographers as $pg) {
            $totalRevenue = PhotographerPayout::where('photographer_id', $pg->user_id)->sum('gross_amount');
            $tier = CommissionTier::resolveForRevenue($totalRevenue);

            if ($tier && abs($pg->commission_rate - $tier->commission_rate) > 0.01) {
                $oldRate = $pg->commission_rate;
                $pg->update(['commission_rate' => $tier->commission_rate]);

                CommissionLog::record(
                    $pg->user_id, $oldRate, $tier->commission_rate,
                    'tier_auto', "ปรับอัตโนมัติตามระดับ \"{$tier->name}\" (รายได้ ฿" . number_format($totalRevenue, 0) . ")",
                    $adminId
                );
                $updated++;
            }
        }

        return back()->with('success', "ปรับค่าคอมมิชชั่นตามระดับสำเร็จ ({$updated} ช่างภาพ)");
    }

    // ═══════════════════════════════════════
    //  History / Logs
    // ═══════════════════════════════════════
    public function history(Request $request)
    {
        $logs = CommissionLog::with('photographer')
            ->when($request->q, function ($q, $s) {
                $q->whereHas('photographer', fn($p) => $p->where('display_name', 'ilike', "%{$s}%"));
            })
            ->when($request->source, fn($q, $s) => $q->where('source', $s))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.commission.history', compact('logs'));
    }

    // ═══════════════════════════════════════
    //  Bulk Adjustment
    // ═══════════════════════════════════════
    public function bulk()
    {
        $photographers = PhotographerProfile::with('user')
            ->where('status', 'approved')
            ->orderBy('display_name')
            ->get()
            ->map(function ($pg) {
                $pg->total_revenue = PhotographerPayout::where('photographer_id', $pg->user_id)->sum('gross_amount');
                return $pg;
            });

        $tiers = CommissionTier::active()->ordered()->get();

        return view('admin.commission.bulk', compact('photographers', 'tiers'));
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'photographer_ids'  => 'required|array|min:1',
            'photographer_ids.*'=> 'exists:photographer_profiles,id',
            'commission_rate'   => $this->commissionRateRule(),
            'reason'            => 'nullable|string|max:255',
        ], $this->commissionRateMessages());

        $adminId = Auth::guard('admin')->id();
        $newRate = $request->commission_rate;
        $reason  = $request->reason ?: 'ปรับแบบกลุ่ม';
        $updated = 0;

        $photographers = PhotographerProfile::whereIn('id', $request->photographer_ids)->get();

        foreach ($photographers as $pg) {
            if (abs($pg->commission_rate - $newRate) > 0.01) {
                $oldRate = $pg->commission_rate;
                $pg->update(['commission_rate' => $newRate]);

                CommissionLog::record($pg->user_id, $oldRate, $newRate, 'bulk', $reason, $adminId);
                $updated++;
            }
        }

        return back()->with('success', "ปรับค่าคอมมิชชั่นสำเร็จ ({$updated} ช่างภาพ)");
    }
}
