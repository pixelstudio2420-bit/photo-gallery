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

        // Batch-fetch monthly AI usage for every visible subscriber so the
        // table can show "AI used / cap" without N+1 lookups. We sum across
        // every AI resource (face_search + face_index + face_compare +
        // face_detect) because monthly_ai_credits is a SHARED budget — same
        // reasoning as PlanGate::aiCreditsUsedThisMonth.
        $photographerIds = $activeSubs->pluck('photographer_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->all();
        $aiUsageByUserId = [];
        if (!empty($photographerIds)) {
            $usageRows = \DB::table('usage_counters')
                ->whereIn('user_id', $photographerIds)
                ->whereIn('resource', \App\Support\PlanGate::AI_RESOURCES)
                ->where('period', 'month')
                ->where('period_key', now()->format('Y-m'))
                ->select('user_id', \DB::raw('SUM(units) AS used'))
                ->groupBy('user_id')
                ->get();
            foreach ($usageRows as $r) {
                $aiUsageByUserId[(int) $r->user_id] = (int) $r->used;
            }
        }

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
            'aiUsageByUserId' => $aiUsageByUserId,
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
     * Plan creator — show the empty form.
     *
     * Re-uses the same plan-edit view by passing a synthetic blank
     * SubscriptionPlan instance and a `creating` flag. That way price
     * fields, feature checkboxes, colour palette and live preview all
     * work identically; the only difference is the form action and the
     * fact that `code` is editable (it's the unique slug — once set
     * it's locked because every Subscription row references it).
     */
    public function createPlan(): View
    {
        $plan = new SubscriptionPlan([
            'name'                  => '',
            'tagline'               => '',
            'description'           => '',
            'price_thb'             => 0,
            'price_annual_thb'      => null,
            'storage_bytes'         => 0,
            'commission_pct'        => 0,
            'max_concurrent_events' => null,
            'max_team_seats'        => 1,
            'monthly_ai_credits'    => 0,
            'badge'                 => '',
            'color_hex'             => '#6366f1',
            'is_public'             => true,
            'is_active'             => true,
            'ai_features'           => [],
            'features_json'         => [],
            'sort_order'            => (int) (SubscriptionPlan::max('sort_order') ?? 0) + 10,
        ]);

        return view('admin.subscriptions.plan-create', [
            'plan'        => $plan,
            'allFeatures' => \App\Http\Controllers\Admin\FeatureFlagController::FEATURES,
        ]);
    }

    /**
     * Plan creator — write a brand-new plan to the catalog.
     *
     * The `code` is required + unique because every PhotographerSubscription
     * row points to a plan by id (FK), but our display logic also looks
     * plans up by `code` (e.g. ::byCode('pro')). Once a plan ships,
     * downstream views and routes hard-code the slug — renaming would
     * silently break the customer-facing surface — so we treat code as
     * immutable after creation. Validation enforces lowercase alphanumeric
     * + dash to avoid accidental whitespace/Thai characters that would
     * fail the URL pattern in the public picker.
     */
    public function storePlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code'                  => 'required|string|max:32|regex:/^[a-z0-9_-]+$/|unique:subscription_plans,code',
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
            'is_active'             => 'nullable|boolean',
            'ai_features'           => 'nullable|array',
            'ai_features.*'         => 'string|max:50',
            'features_json'         => 'nullable|string',
        ], [
            'code.regex'  => 'รหัสแผนต้องเป็นตัวพิมพ์เล็ก ตัวเลข ขีด หรือ underscore เท่านั้น',
            'code.unique' => 'รหัสแผนนี้มีอยู่แล้วในระบบ — กรุณาใช้รหัสอื่น',
        ]);

        $bytes = (int) round($data['storage_gb'] * 1024 * 1024 * 1024);

        // Bullet list arrives as multi-line textarea content; split + trim
        // (same pattern as updatePlan)
        $bullets = [];
        if (!empty($data['features_json'])) {
            foreach (preg_split('/\r?\n/', $data['features_json']) as $line) {
                $t = trim($line);
                if ($t !== '') $bullets[] = $t;
            }
        }

        $plan = SubscriptionPlan::create([
            'code'                  => $data['code'],
            'name'                  => $data['name'],
            'tagline'               => $data['tagline'] ?? null,
            'description'           => $data['description'] ?? null,
            'price_thb'             => $data['price_thb'],
            'price_annual_thb'      => $data['price_annual_thb'] ?? null,
            'billing_cycle'         => 'monthly',
            'storage_bytes'         => $bytes,
            'commission_pct'        => $data['commission_pct'],
            // Validation marks this field nullable, so when the form omits
            // the input entirely the key isn't in $data. Use ?? null so PHP
            // 8.0+ undefined-key warnings don't bubble up to a 500.
            'max_concurrent_events' => $data['max_concurrent_events'] ?? null,
            'max_team_seats'        => $data['max_team_seats'],
            'monthly_ai_credits'    => $data['monthly_ai_credits'],
            'badge'                 => $data['badge'] ?? null,
            'color_hex'             => $data['color_hex'] ?? '#6366f1',
            'is_public'             => (bool) ($data['is_public'] ?? false),
            'is_active'             => (bool) ($data['is_active'] ?? true),
            'is_default_free'       => false,
            'ai_features'           => array_values($data['ai_features'] ?? []),
            'features_json'         => $bullets,
            'sort_order'            => (int) (SubscriptionPlan::max('sort_order') ?? 0) + 10,
        ]);

        return redirect()
            ->route('admin.subscriptions.plans')
            ->with('success', "สร้างแผน {$plan->name} (รหัส: {$plan->code}) เรียบร้อย — กดเปิด is_public เพื่อให้ลูกค้าเห็น");
    }

    /**
     * Plan delete — refuses if any photographer is currently subscribed.
     *
     * Because every PhotographerSubscription row carries plan_id as a
     * NOT NULL foreign key, deleting an in-use plan would either:
     *   • cascade-delete real subscription history (data loss), or
     *   • fail with an FK violation that rolls back to a 500.
     * Both are unacceptable, so we count active references first and
     * surface a friendly explanation. The default Free plan is also
     * protected — every photographer needs a fallback when their paid
     * plan ends. Operators can still hide a plan via toggle (sets
     * is_active=false) when they want to retire it without breaking
     * existing subscribers.
     */
    public function destroyPlan(SubscriptionPlan $plan): RedirectResponse
    {
        if ($plan->is_default_free) {
            return back()->with('error', 'ไม่สามารถลบแผน Free เริ่มต้นได้ — ระบบใช้เป็น fallback เมื่อแผนเสียเงินหมดอายุ');
        }

        $usageCount = PhotographerSubscription::where('plan_id', $plan->id)->count();
        if ($usageCount > 0) {
            return back()->with('error', sprintf(
                'ไม่สามารถลบแผน %s ได้ — มี subscription %d รายการอ้างอิงอยู่ ' .
                '(หากต้องการเลิกขายแผนนี้ ให้ปิดสถานะ is_active แทน)',
                $plan->name,
                $usageCount,
            ));
        }

        $name = $plan->name;
        $code = $plan->code;
        $plan->delete();

        return redirect()
            ->route('admin.subscriptions.plans')
            ->with('success', "ลบแผน {$name} ({$code}) เรียบร้อย");
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
            // Validation marks this field nullable, so when the form omits
            // the input entirely the key isn't in $data. Use ?? null so PHP
            // 8.0+ undefined-key warnings don't bubble up to a 500.
            'max_concurrent_events' => $data['max_concurrent_events'] ?? null,
            'max_team_seats'        => $data['max_team_seats'],
            'monthly_ai_credits'    => $data['monthly_ai_credits'],
            'badge'                 => $data['badge'] ?? null,
            // color_hex is NOT NULL in the table — fall back to the row's
            // existing colour or a sane default. Without this, an admin
            // saving the form without retouching the colour picker (which
            // can happen if the browser drops the input on submit) would
            // hit a 23502 NOT NULL violation and a 500.
            'color_hex'             => $data['color_hex'] ?? $plan->color_hex ?? '#6366f1',
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
