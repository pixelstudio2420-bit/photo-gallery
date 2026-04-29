<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoragePlan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin CRUD for consumer storage plans.
 *
 * Plans are mostly seeded from the migration; this controller exists so
 * operators can tune pricing, features, and sort order without a code deploy.
 * Free plan (code=free) is protected — can't be deactivated or deleted because
 * the whole system relies on it as the default fallback tier.
 */
class StoragePlanController extends Controller
{
    public function index(): View
    {
        $plans = StoragePlan::ordered()->get();
        return view('admin.user-storage.plans.index', compact('plans'));
    }

    public function create(): View
    {
        $plan = new StoragePlan([
            'billing_cycle'    => 'monthly',
            'is_active'        => true,
            'is_public'        => true,
            'sort_order'       => 10,
            'color_hex'        => '#6366f1',
            'max_shared_links' => 50,
        ]);

        return view('admin.user-storage.plans.form', ['plan' => $plan, 'mode' => 'create']);
    }

    public function store(Request $request)
    {
        $data = $this->validatePlan($request);
        $data['code']    = $this->uniqueCode($data['code'] ?? null, $data['name']);
        $data['storage_bytes'] = (int) ($data['storage_gb'] * (1024 ** 3));
        $data['max_file_size_bytes'] = !empty($data['max_file_size_mb'])
            ? (int) ($data['max_file_size_mb'] * (1024 ** 2))
            : null;
        $data['features_json'] = $this->normaliseFeatures($request);

        unset($data['storage_gb'], $data['max_file_size_mb'], $data['feature_list']);

        $plan = StoragePlan::create($data);

        return redirect()->route('admin.user-storage.plans.index')
            ->with('success', "สร้างแผน {$plan->name} เรียบร้อย");
    }

    public function edit(StoragePlan $plan): View
    {
        return view('admin.user-storage.plans.form', ['plan' => $plan, 'mode' => 'edit']);
    }

    public function update(Request $request, StoragePlan $plan)
    {
        $data = $this->validatePlan($request, $plan->id);

        // Protect the free plan — don't let an admin deactivate/hide it accidentally.
        if ($plan->code === StoragePlan::CODE_FREE) {
            $data['is_active'] = true;
            $data['is_public'] = true;
        }

        $data['storage_bytes']       = (int) ($data['storage_gb'] * (1024 ** 3));
        $data['max_file_size_bytes'] = !empty($data['max_file_size_mb'])
            ? (int) ($data['max_file_size_mb'] * (1024 ** 2))
            : null;
        $data['features_json']       = $this->normaliseFeatures($request);

        unset($data['storage_gb'], $data['max_file_size_mb'], $data['feature_list']);

        $plan->update($data);

        return redirect()->route('admin.user-storage.plans.index')
            ->with('success', "อัปเดตแผน {$plan->name} เรียบร้อย");
    }

    public function destroy(StoragePlan $plan)
    {
        if ($plan->code === StoragePlan::CODE_FREE) {
            return back()->with('error', 'ไม่สามารถปิดการใช้งานแผน Free ได้');
        }

        // Soft-archive by flipping is_active + is_public — historical
        // subscriptions still FK to this plan so we can't hard-delete.
        $plan->update(['is_active' => false, 'is_public' => false]);

        return redirect()->route('admin.user-storage.plans.index')
            ->with('success', "ปิดการขายแผน {$plan->name} แล้ว");
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════════════

    private function validatePlan(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code'              => 'nullable|string|max:40|regex:/^[a-z0-9_\-]+$/i|unique:storage_plans,code' . ($ignoreId ? ",{$ignoreId}" : ''),
            'name'              => 'required|string|max:120',
            'tagline'           => 'nullable|string|max:160',
            'storage_gb'        => 'required|numeric|min:0.1|max:10240',
            'max_file_size_mb'  => 'nullable|numeric|min:1|max:102400',
            'price_thb'         => 'required|numeric|min:0|max:1000000',
            'price_annual_thb'  => 'nullable|numeric|min:0|max:10000000',
            'billing_cycle'     => 'required|in:monthly,annual',
            'badge'             => 'nullable|string|max:40',
            'color_hex'         => 'nullable|string|max:9',
            'sort_order'        => 'required|integer|min:0|max:999',
            'is_active'         => 'nullable|boolean',
            'is_public'         => 'nullable|boolean',
            'is_default_free'   => 'nullable|boolean',
            'max_shared_links'  => 'nullable|integer|min:0|max:10000',
        ]);
    }

    private function uniqueCode(?string $code, string $name): string
    {
        $candidate = $code ?: Str::slug($name);
        $base = $candidate;
        $i = 2;
        while (StoragePlan::where('code', $candidate)->exists()) {
            $candidate = "{$base}-{$i}";
            $i++;
        }
        return $candidate;
    }

    /**
     * Two input paths for features:
     *   1. `feature_list` — textarea, one feature bullet per line (display text)
     *   2. Checkbox inputs under `features[]` — functional feature flags
     *
     * The plan stores ALL display bullets in features_json as an array of strings
     * (same format the seed migration uses), and separately keeps the functional
     * feature flags in the `has_*` shortcut columns (sharing, public_links, etc.)
     * if the model defines them. For now we just store the bullets.
     */
    private function normaliseFeatures(Request $request): array
    {
        $raw = (string) $request->input('feature_list', '');
        $lines = preg_split('/\r?\n/', $raw);
        return array_values(array_filter(array_map('trim', $lines)));
    }
}
