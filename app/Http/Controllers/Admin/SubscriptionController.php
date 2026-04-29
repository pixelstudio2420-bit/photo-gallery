<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotographerSubscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-side subscription management.
 *
 * Screens:
 *   GET  /admin/subscriptions                   → overview KPIs + active subs
 *   GET  /admin/subscriptions/plans             → plan catalog (toggle active)
 *   GET  /admin/subscriptions/invoices          → global invoice ledger
 *   GET  /admin/subscriptions/{sub}             → subscription detail
 *   POST /admin/subscriptions/{sub}/cancel      → hard cancel (immediate)
 *   POST /admin/subscriptions/{sub}/expire      → force grace expiry
 *   POST /admin/subscriptions/plans/{plan}/toggle → flip is_active
 *
 * Heavy lifting lives in SubscriptionService — this controller is thin glue.
 */
class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subs) {}

    // ─────────────────────────────────────────────────────────────────────
    // Overview
    // ─────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $kpis = $this->subs->platformKpis();

        // NOTE: User model maps to `auth_users` which has first_name/last_name
        // (no `name` column). The `name` attribute exists only as an accessor.
        // Must SELECT the real columns here or Eloquent will error.
        $activeSubs = PhotographerSubscription::query()
            ->with(['plan', 'photographer:id,first_name,last_name,email'])
            ->activeOrGrace()
            ->latest('id')
            ->paginate(25);

        $planCounts = SubscriptionPlan::query()
            ->orderBy('sort_order')
            ->get()
            ->map(function (SubscriptionPlan $p) use ($kpis) {
                $p->setAttribute('subscribers', (int) ($kpis['by_plan'][$p->id] ?? 0));
                return $p;
            });

        return view('admin.subscriptions.index', [
            'kpis'       => $kpis,
            'activeSubs' => $activeSubs,
            'planCounts' => $planCounts,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Plans list + toggle
    // ─────────────────────────────────────────────────────────────────────

    public function plans(): View
    {
        $plans = SubscriptionPlan::orderBy('sort_order')->orderBy('price_thb')->get();
        return view('admin.subscriptions.plans', ['plans' => $plans]);
    }

    public function togglePlan(SubscriptionPlan $plan): RedirectResponse
    {
        $plan->update(['is_active' => !$plan->is_active]);
        return back()->with('success', "อัพเดทสถานะแผน {$plan->name} เรียบร้อย");
    }

    /**
     * Plan editor — full control over what each tier ships with.
     * Lets the admin tweak prices, storage cap, AI features, team seats,
     * concurrent events, AI credit budget, commission % — all the dials
     * the gating logic reads at request time.
     *
     * Editing here IS the source of truth — there's no override layer.
     * Any change takes effect on the next page load (no cache to bust
     * because plan rows aren't cached).
     */
    public function editPlan(SubscriptionPlan $plan): View
    {
        return view('admin.subscriptions.plan-edit', [
            'plan' => $plan,
            'allFeatures' => \App\Http\Controllers\Admin\FeatureFlagController::FEATURES,
        ]);
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:120',
            'tagline'               => 'nullable|string|max:200',
            'description'           => 'nullable|string|max:600',
            'price_thb'             => 'required|numeric|min:0',
            'price_annual_thb'      => 'nullable|numeric|min:0',
            'storage_gb'            => 'required|numeric|min:0',
            'commission_pct'        => 'required|numeric|min:0|max:100',
            'max_concurrent_events' => 'nullable|integer|min:0',
            'max_team_seats'        => 'required|integer|min:1',
            'monthly_ai_credits'    => 'required|integer|min:0',
            'badge'                 => 'nullable|string|max:30',
            'color_hex'             => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_public'             => 'nullable|boolean',
            'ai_features'           => 'nullable|array',
            'ai_features.*'         => 'string|max:50',
            'features_json'         => 'nullable|string',
        ]);

        // Convert GB → bytes
        $bytes = (int) round($data['storage_gb'] * 1024 * 1024 * 1024);

        // features_json comes in as a textarea — split lines into array
        $bullets = [];
        if (!empty($data['features_json'])) {
            foreach (preg_split('/\r?\n/', $data['features_json']) as $line) {
                $t = trim($line);
                if ($t !== '') $bullets[] = $t;
            }
        }

        $plan->update([
            'name'                  => $data['name'],
            'tagline'               => $data['tagline'] ?? null,
            'description'           => $data['description'] ?? null,
            'price_thb'             => $data['price_thb'],
            'price_annual_thb'      => $data['price_annual_thb'] ?? null,
            'storage_bytes'         => $bytes,
            'commission_pct'        => $data['commission_pct'],
            'max_concurrent_events' => $data['max_concurrent_events'],
            'max_team_seats'        => $data['max_team_seats'],
            'monthly_ai_credits'    => $data['monthly_ai_credits'],
            'badge'                 => $data['badge'] ?? null,
            'color_hex'             => $data['color_hex'] ?? null,
            'is_public'             => (bool) ($data['is_public'] ?? false),
            'ai_features'           => array_values($data['ai_features'] ?? []),
            'features_json'         => $bullets,
        ]);

        return redirect()
            ->route('admin.subscriptions.plans')
            ->with('success', "บันทึกการเปลี่ยนแปลงของแผน {$plan->name} เรียบร้อย — มีผลทันทีกับสมาชิกทุกคน");
    }

    // ─────────────────────────────────────────────────────────────────────
    // Invoice ledger
    // ─────────────────────────────────────────────────────────────────────

    public function invoices(Request $request): View
    {
        $q = SubscriptionInvoice::query()
            ->with(['subscription.plan', 'order', 'subscription.photographer:id,first_name,last_name,email'])
            ->orderByDesc('id');

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        $invoices = $q->paginate(30)->withQueryString();

        return view('admin.subscriptions.invoices', [
            'invoices'      => $invoices,
            'currentStatus' => $status,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Subscription detail + admin actions
    // ─────────────────────────────────────────────────────────────────────

    public function show(PhotographerSubscription $subscription): View
    {
        $subscription->load(['plan', 'photographer', 'invoices.order']);
        return view('admin.subscriptions.show', [
            'subscription' => $subscription,
        ]);
    }

    public function cancel(PhotographerSubscription $subscription): RedirectResponse
    {
        $this->subs->cancel($subscription, immediate: true);
        return back()->with('success', 'ยกเลิกการสมัครสมาชิกทันทีเรียบร้อย');
    }

    public function expire(PhotographerSubscription $subscription): RedirectResponse
    {
        $this->subs->expireGrace($subscription);
        return back()->with('success', 'ดาวน์เกรดเป็นแผนฟรีเรียบร้อย');
    }
}
