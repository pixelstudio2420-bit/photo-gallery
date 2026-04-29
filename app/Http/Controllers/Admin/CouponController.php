<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\ActivityLogger;
use App\Services\CouponAnalyticsService;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(private CouponAnalyticsService $analytics)
    {
    }

    public function index(Request $request)
    {
        $coupons = Coupon::query()
            ->withCount('usages')
            ->when($request->q, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('code', 'ilike', "%{$s}%")->orWhere('name', 'ilike', "%{$s}%");
            }))
            ->when($request->filled('status'), function ($q) use ($request) {
                switch ($request->status) {
                    case 'active':    $q->active(); break;
                    case 'expired':   $q->expired(); break;
                    case 'expiring':  $q->expiringSoon(7); break;
                    case 'exhausted': $q->exhausted(); break;
                    case 'inactive':  $q->where('is_active', false); break;
                }
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total'         => Coupon::count(),
            'active'        => Coupon::active()->count(),
            'expired'       => Coupon::expired()->count(),
            'expiring_soon' => Coupon::expiringSoon(7)->count(),
            'total_usage'   => (int) \DB::table('coupons')->sum('usage_count'),
        ];

        return view('admin.coupons.index', compact('coupons', 'stats'));
    }

    public function dashboard(Request $request)
    {
        $period = $request->input('period', '30d');

        $stats           = $this->analytics->dashboardStats($period);
        $trend           = $this->analytics->redemptionTrend(30);
        $topPerformers   = $this->analytics->topPerformers(10);
        $typeDist        = $this->analytics->typeDistribution();
        $topCustomers    = $this->analytics->topCustomers(10);
        $conversion      = $this->analytics->conversionImpact($period);
        $statusBreakdown = $this->analytics->statusBreakdown();
        $expiringSoon    = Coupon::expiringSoon(7)->orderBy('end_date')->limit(5)->get();

        return view('admin.coupons.dashboard', compact(
            'stats', 'trend', 'topPerformers', 'typeDist',
            'topCustomers', 'conversion', 'statusBreakdown',
            'expiringSoon', 'period'
        ));
    }

    public function create()
    {
        return view('admin.coupons.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'           => 'required|string|max:50|unique:coupons,code',
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'type'           => 'required|in:percent,fixed',
            'value'          => 'required|numeric|min:0',
            'min_order'      => 'nullable|numeric|min:0',
            'max_discount'   => 'nullable|numeric|min:0',
            'usage_limit'    => 'nullable|integer|min:0',
            'per_user_limit' => 'nullable|integer|min:0',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'is_active'      => 'nullable|boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['usage_count'] = 0;

        $coupon = Coupon::create($validated);

        ActivityLogger::admin(
            action: 'coupon.created',
            target: $coupon,
            description: "สร้างคูปอง {$coupon->code} ({$coupon->type} {$coupon->value})",
            oldValues: null,
            newValues: [
                'code'           => $coupon->code,
                'name'           => $coupon->name,
                'type'           => $coupon->type,
                'value'          => (float) $coupon->value,
                'min_order'      => $coupon->min_order !== null ? (float) $coupon->min_order : null,
                'max_discount'   => $coupon->max_discount !== null ? (float) $coupon->max_discount : null,
                'usage_limit'    => $coupon->usage_limit,
                'per_user_limit' => $coupon->per_user_limit,
                'start_date'     => $coupon->start_date?->toIso8601String(),
                'end_date'       => $coupon->end_date?->toIso8601String(),
                'is_active'      => (bool) $coupon->is_active,
            ],
        );

        return redirect()->route('admin.coupons.index')->with('success', 'สร้างคูปองสำเร็จ');
    }

    public function show(Coupon $coupon)
    {
        $coupon->load('usages.user', 'usages.order');

        $usageStats = [
            'redemptions'    => $coupon->usages()->count(),
            'total_discount' => $coupon->total_discount,
            'revenue'        => $coupon->revenue_generated,
            'unique_users'   => $coupon->usages()->distinct('user_id')->count('user_id'),
            'avg_discount'   => round((float) $coupon->usages()->avg('discount_amount'), 2),
        ];

        return view('admin.coupons.show', compact('coupon', 'usageStats'));
    }

    public function edit(Coupon $coupon)
    {
        return view('admin.coupons.edit', compact('coupon'));
    }

    public function update(Request $request, Coupon $coupon)
    {
        $validated = $request->validate([
            'code'           => 'required|string|max:50|unique:coupons,code,' . $coupon->id,
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'type'           => 'required|in:percent,fixed',
            'value'          => 'required|numeric|min:0',
            'min_order'      => 'nullable|numeric|min:0',
            'max_discount'   => 'nullable|numeric|min:0',
            'usage_limit'    => 'nullable|integer|min:0',
            'per_user_limit' => 'nullable|integer|min:0',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'is_active'      => 'nullable|boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);

        $old = [
            'code'         => $coupon->code,
            'name'         => $coupon->name,
            'type'         => $coupon->type,
            'value'        => (float) $coupon->value,
            'min_order'    => $coupon->min_order !== null ? (float) $coupon->min_order : null,
            'max_discount' => $coupon->max_discount !== null ? (float) $coupon->max_discount : null,
            'usage_limit'  => $coupon->usage_limit,
            'is_active'    => (bool) $coupon->is_active,
        ];

        $coupon->update($validated);

        ActivityLogger::admin(
            action: 'coupon.updated',
            target: $coupon,
            description: "แก้ไขคูปอง {$coupon->code}",
            oldValues: $old,
            newValues: [
                'code'         => $coupon->code,
                'name'         => $coupon->name,
                'type'         => $coupon->type,
                'value'        => (float) $coupon->value,
                'min_order'    => $coupon->min_order !== null ? (float) $coupon->min_order : null,
                'max_discount' => $coupon->max_discount !== null ? (float) $coupon->max_discount : null,
                'usage_limit'  => $coupon->usage_limit,
                'is_active'    => (bool) $coupon->is_active,
            ],
        );

        return redirect()->route('admin.coupons.index')->with('success', 'อัพเดทสำเร็จ');
    }

    public function destroy(Coupon $coupon)
    {
        $snapshot = [
            'id'          => $coupon->id,
            'code'        => $coupon->code,
            'name'        => $coupon->name,
            'type'        => $coupon->type,
            'value'       => (float) $coupon->value,
            'usage_count' => (int) $coupon->usage_count,
            'usage_limit' => $coupon->usage_limit,
            'is_active'   => (bool) $coupon->is_active,
        ];

        $coupon->delete();

        ActivityLogger::admin(
            action: 'coupon.deleted',
            target: ['Coupon', (int) $snapshot['id']],
            description: "ลบคูปอง {$snapshot['code']} (ใช้ไปแล้ว {$snapshot['usage_count']} ครั้ง)",
            oldValues: $snapshot,
            newValues: null,
        );

        return redirect()->route('admin.coupons.index')->with('success', 'ลบคูปองเรียบร้อย');
    }

    public function bulkCreate()
    {
        return view('admin.coupons.bulk-create');
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'count'          => 'required|integer|min:1|max:500',
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'prefix'         => 'nullable|string|max:10',
            'code_length'    => 'nullable|integer|min:4|max:16',
            'type'           => 'required|in:percent,fixed',
            'value'          => 'required|numeric|min:0',
            'min_order'      => 'nullable|numeric|min:0',
            'max_discount'   => 'nullable|numeric|min:0',
            'usage_limit'    => 'nullable|integer|min:0',
            'per_user_limit' => 'nullable|integer|min:0',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'is_active'      => 'nullable|boolean',
        ]);

        $count = (int) $validated['count'];
        $prefix = strtoupper($validated['prefix'] ?? '');
        $codeLength = (int) ($validated['code_length'] ?? 8);

        $codes = [];
        $baseData = [
            'name'           => $validated['name'],
            'description'    => $validated['description'] ?? null,
            'type'           => $validated['type'],
            'value'          => $validated['value'],
            'min_order'      => $validated['min_order'] ?? null,
            'max_discount'   => $validated['max_discount'] ?? null,
            'usage_limit'    => $validated['usage_limit'] ?? 1,
            'per_user_limit' => $validated['per_user_limit'] ?? 1,
            'start_date'     => $validated['start_date'] ?? null,
            'end_date'       => $validated['end_date'] ?? null,
            'is_active'      => $validated['is_active'] ?? true,
            'usage_count'    => 0,
            'created_at'     => now(),
        ];

        \DB::transaction(function () use ($count, $prefix, $codeLength, $baseData, &$codes) {
            for ($i = 0; $i < $count; $i++) {
                $code = Coupon::generateCode($prefix, $codeLength);
                $codes[] = $code;
                \DB::table('coupons')->insert(array_merge($baseData, ['code' => $code]));
            }
        });

        session()->flash('bulk_codes', $codes);

        ActivityLogger::admin(
            action: 'coupon.bulk_created',
            target: null,
            description: "สร้างคูปองแบบกลุ่ม {$count} รายการ (prefix: {$prefix}, type: {$validated['type']} {$validated['value']})",
            oldValues: null,
            newValues: [
                'count'          => $count,
                'prefix'         => $prefix,
                'code_length'    => $codeLength,
                'type'           => $validated['type'],
                'value'          => (float) $validated['value'],
                'min_order'      => $validated['min_order'] ?? null,
                'max_discount'   => $validated['max_discount'] ?? null,
                'usage_limit'    => $validated['usage_limit'] ?? 1,
                'per_user_limit' => $validated['per_user_limit'] ?? 1,
                'start_date'     => $validated['start_date'] ?? null,
                'end_date'       => $validated['end_date'] ?? null,
                'is_active'      => (bool) ($validated['is_active'] ?? true),
                'sample_codes'   => array_slice($codes, 0, 5),
            ],
        );

        return redirect()->route('admin.coupons.index')
            ->with('success', "สร้างคูปองจำนวน {$count} รายการเรียบร้อย");
    }

    public function exportCsv(Request $request)
    {
        $query = Coupon::withCount('usages')->orderByDesc('created_at');

        if ($request->filled('status')) {
            switch ($request->status) {
                case 'active':    $query->active(); break;
                case 'expired':   $query->expired(); break;
                case 'expiring':  $query->expiringSoon(7); break;
                case 'exhausted': $query->exhausted(); break;
            }
        }

        $coupons = $query->get();
        $filename = 'coupons-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($coupons) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Code', 'Name', 'Type', 'Value', 'Min Order', 'Max Discount',
                          'Usage Count', 'Usage Limit', 'Per User', 'Start Date', 'End Date', 'Active', 'Status']);

            foreach ($coupons as $c) {
                fputcsv($out, [
                    $c->code, $c->name, $c->type, (float) $c->value,
                    (float) ($c->min_order ?? 0), (float) ($c->max_discount ?? 0),
                    $c->usage_count, $c->usage_limit ?? 'unlimited',
                    $c->per_user_limit ?? 'unlimited',
                    $c->start_date?->format('Y-m-d H:i') ?? '',
                    $c->end_date?->format('Y-m-d H:i') ?? '',
                    $c->is_active ? 'Yes' : 'No',
                    $c->status,
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
