<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CreditTransaction;
use App\Models\PhotographerCreditBundle;
use App\Models\PhotographerProfile;
use App\Models\UploadCreditPackage;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin controller for the upload credits system.
 *
 * Two distinct screens sharing one controller because they're read/write
 * faces of the same feature:
 *
 *   • /admin/credits/packages    — catalog CRUD (what we sell)
 *   • /admin/credits/photographers — balances & grants (who has credits)
 *
 * The CreditService is authoritative for every balance mutation so this
 * controller stays thin: it validates input, calls the service, and renders.
 */
class CreditController extends Controller
{
    public function __construct(private CreditService $credits) {}

    // ═══════════════════════════════════════════════════════════════════
    //  Overview dashboard
    // ═══════════════════════════════════════════════════════════════════

    public function index(): View
    {
        // KPI numbers for the admin overview — cheap enough to compute inline
        // on a lightly-trafficked admin page; no need to cache yet.
        $totalOutstanding = (int) PhotographerCreditBundle::query()
            ->usable()
            ->sum('credits_remaining');

        $totalPurchasedRevenue = (float) PhotographerCreditBundle::query()
            ->where('source', 'purchase')
            ->sum('price_paid_thb');

        $bundlesExpiringSoon = (int) PhotographerCreditBundle::query()
            ->usable()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(7))
            ->count();

        $photographersOnCredits = (int) PhotographerProfile::query()
            ->where('billing_mode', PhotographerProfile::BILLING_CREDITS)
            ->count();

        // Last-30-days purchase volume — just the ledger sum for kind=purchase.
        $last30PurchaseCredits = (int) CreditTransaction::query()
            ->where('kind', CreditTransaction::KIND_PURCHASE)
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('delta');

        // User model maps to `auth_users` (first_name/last_name, no `name` col).
        $recentTxns = CreditTransaction::query()
            ->with(['photographer:id,first_name,last_name,email', 'bundle.package'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('admin.credits.index', [
            'totalOutstanding'       => $totalOutstanding,
            'totalPurchasedRevenue'  => $totalPurchasedRevenue,
            'bundlesExpiringSoon'    => $bundlesExpiringSoon,
            'photographersOnCredits' => $photographersOnCredits,
            'last30PurchaseCredits'  => $last30PurchaseCredits,
            'recentTxns'             => $recentTxns,
            'systemEnabled'          => $this->credits->systemEnabled(),
        ]);
    }

    /**
     * Flip the global credits_system_enabled switch.
     *
     * When off:
     *   • /photographer/credits/* redirects back with a warning (middleware).
     *   • Photographer sidebar link is hidden.
     *   • Dashboard widget self-hides via the `enabled` flag in
     *     CreditService::dashboardSummary().
     *   • Ongoing uploads are NOT blocked (we don't want to trap photos
     *     that were already in flight) — canUpload() treats disabled as
     *     "bypass credits check" and lets the upload through.
     *
     * Admin still keeps access to this page to flip it back on.
     */
    public function toggleSystem(Request $request): RedirectResponse
    {
        $target = ((string) AppSetting::get('credits_system_enabled', '1')) === '1' ? '0' : '1';
        AppSetting::set('credits_system_enabled', $target);

        Log::info('credits.admin.toggle', [
            'admin_id' => optional(auth('admin')->user())->id,
            'enabled'  => $target === '1',
        ]);

        return back()->with(
            'success',
            $target === '1'
                ? 'เปิดใช้งานระบบเครดิตอัปโหลดแล้ว'
                : 'ปิดระบบเครดิตอัปโหลดแล้ว — ช่างภาพจะไม่เห็นเมนูและใช้งานไม่ได้'
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Package catalog CRUD
    // ═══════════════════════════════════════════════════════════════════

    public function packagesIndex(): View
    {
        $packages = UploadCreditPackage::query()
            ->orderBy('sort_order')
            ->orderBy('price_thb')
            ->get();

        return view('admin.credits.packages.index', [
            'packages' => $packages,
        ]);
    }

    public function packagesCreate(): View
    {
        return view('admin.credits.packages.form', [
            'package' => new UploadCreditPackage([
                'is_active'     => true,
                'sort_order'    => 0,
                'validity_days' => 365,
                'color_hex'     => '#6366f1',
            ]),
            'mode'    => 'create',
        ]);
    }

    public function packagesStore(Request $request)
    {
        $data = $this->validatePackage($request);
        $data['code'] = $this->uniquePackageCode($data['code'] ?? null, $data['name']);

        $package = UploadCreditPackage::create($data);

        return redirect()
            ->route('admin.credits.packages.index')
            ->with('success', "สร้างแพ็คเก็จ {$package->name} สำเร็จ");
    }

    public function packagesEdit(UploadCreditPackage $package): View
    {
        return view('admin.credits.packages.form', [
            'package' => $package,
            'mode'    => 'edit',
        ]);
    }

    public function packagesUpdate(Request $request, UploadCreditPackage $package)
    {
        $data = $this->validatePackage($request, $package->id);
        $package->update($data);

        return redirect()
            ->route('admin.credits.packages.index')
            ->with('success', "อัปเดตแพ็คเก็จ {$package->name} สำเร็จ");
    }

    public function packagesDestroy(UploadCreditPackage $package)
    {
        // Soft-archive rather than hard delete — historical orders still
        // reference this package_id and we don't want to orphan them.
        $package->update(['is_active' => false]);

        return redirect()
            ->route('admin.credits.packages.index')
            ->with('success', "ปิดการขายแพ็คเก็จ {$package->name} แล้ว");
    }

    /**
     * Shared validation for create + update. On update, the current package's
     * code is exempted from the uniqueness check.
     */
    private function validatePackage(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code'          => 'nullable|string|max:40|regex:/^[a-z0-9_\-]+$/i|unique:upload_credit_packages,code' . ($ignoreId ? ",{$ignoreId}" : ''),
            'name'          => 'required|string|max:120',
            'description'   => 'nullable|string|max:500',
            'credits'       => 'required|integer|min:1|max:1000000',
            'price_thb'     => 'required|numeric|min:0|max:10000000',
            'validity_days' => 'nullable|integer|min:0|max:3650',
            'badge'         => 'nullable|string|max:40',
            'color_hex'     => 'nullable|string|max:9',
            'sort_order'    => 'nullable|integer|min:0|max:999',
            'is_active'     => 'nullable|boolean',
        ]);
    }

    /** Derive a slug code from the name if none supplied; enforce uniqueness. */
    private function uniquePackageCode(?string $code, string $name): string
    {
        $candidate = $code ?: Str::slug($name);
        $base = $candidate;
        $i = 2;
        while (UploadCreditPackage::where('code', $candidate)->exists()) {
            $candidate = "{$base}-{$i}";
            $i++;
        }
        return $candidate;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Photographer balances + grants
    // ═══════════════════════════════════════════════════════════════════

    public function photographers(Request $request): View
    {
        $search = trim((string) $request->string('q'));

        // NOTE: This project uses `auth_users` (not `users`) with first_name/last_name
        // split — combine them into a `user_name` alias for the view.
        $q = PhotographerProfile::query()
            ->join('auth_users', 'auth_users.id', '=', 'photographer_profiles.user_id')
            ->select([
                'photographer_profiles.*',
                DB::raw("TRIM(CONCAT(COALESCE(auth_users.first_name, ''), ' ', COALESCE(auth_users.last_name, ''))) as user_name"),
                'auth_users.email as user_email',
            ])
            ->orderByDesc('photographer_profiles.credits_balance_cached');

        if ($search !== '') {
            $like = "%{$search}%";
            $q->where(function ($qq) use ($like) {
                $qq->where('auth_users.first_name', 'ilike', $like)
                   ->orWhere('auth_users.last_name', 'ilike', $like)
                   ->orWhere('auth_users.email', 'ilike', $like)
                   ->orWhere('photographer_profiles.display_name', 'ilike', $like);
            });
        }

        $profiles = $q->paginate(25)->withQueryString();

        return view('admin.credits.photographers.index', [
            'profiles' => $profiles,
            'search'   => $search,
        ]);
    }

    public function photographerShow(PhotographerProfile $photographer): View
    {
        $userId = (int) $photographer->user_id;

        $bundles = PhotographerCreditBundle::query()
            ->where('photographer_id', $userId)
            ->with('package')
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->orderByDesc('id')
            ->get();

        $transactions = CreditTransaction::query()
            ->where('photographer_id', $userId)
            ->with(['bundle.package', 'actor:id,first_name,last_name'])
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $balance = $this->credits->balance($userId);
        $user    = $photographer->user ?? null;

        return view('admin.credits.photographers.show', [
            'photographer' => $photographer,
            'user'         => $user,
            'balance'      => $balance,
            'bundles'      => $bundles,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Admin grant (positive only). The free-form note + actor id gets stamped
     * into the ledger row's meta so the audit trail survives rebalancing.
     */
    public function photographerGrant(Request $request, PhotographerProfile $photographer)
    {
        $data = $request->validate([
            'credits'      => 'required|integer|min:1|max:100000',
            'expires_days' => 'nullable|integer|min:0|max:3650',
            'note'         => 'nullable|string|max:300',
        ]);

        try {
            $this->credits->grant(
                photographerUserId: (int) $photographer->user_id,
                credits:            (int) $data['credits'],
                source:             'grant',
                expiresDays:        isset($data['expires_days']) && (int) $data['expires_days'] > 0
                                        ? (int) $data['expires_days']
                                        : null,
                actorUserId:        Auth::guard('admin')->id() ?? Auth::id(),
                note:               $data['note'] ?? 'Admin grant',
            );
        } catch (\Throwable $e) {
            Log::error('Admin credit grant failed', [
                'photographer_id' => $photographer->user_id,
                'error'           => $e->getMessage(),
            ]);
            return back()->with('error', 'แจกเครดิตไม่สำเร็จ: ' . $e->getMessage());
        }

        return back()->with('success', "แจก {$data['credits']} เครดิตให้ช่างภาพเรียบร้อย");
    }

    /**
     * Admin adjust — delta can be negative (clawback). Goes through the
     * service's adjust() path which writes KIND_ADJUST ledger rows and
     * handles the grant-vs-consume logic based on sign.
     */
    public function photographerAdjust(Request $request, PhotographerProfile $photographer)
    {
        $data = $request->validate([
            'delta' => 'required|integer|not_in:0|min:-100000|max:100000',
            'note'  => 'nullable|string|max:300',
        ]);

        $ok = $this->credits->adjust(
            (int) $photographer->user_id,
            (int) $data['delta'],
            Auth::guard('admin')->id() ?? (int) Auth::id(),
            $data['note'] ?? 'Admin adjustment',
        );

        $msg = $ok
            ? "ปรับยอดเครดิตของช่างภาพ ({$data['delta']}) เรียบร้อย"
            : 'ปรับยอดไม่สำเร็จ: เครดิตคงเหลือไม่พอสำหรับการหัก';

        return back()->with($ok ? 'success' : 'error', $msg);
    }

    /**
     * Force recalculation of the denormalised credits_balance_cached from
     * the bundle SUM. Useful when an ops person suspects drift.
     */
    public function photographerRecalc(PhotographerProfile $photographer)
    {
        $balance = $this->credits->recalculateBalance((int) $photographer->user_id);

        return back()->with('success', "คำนวณยอดเครดิตใหม่แล้ว: {$balance}");
    }

    /**
     * Flip the photographer's billing_mode between credits and commission.
     * Doesn't touch credits — existing bundles stay put so a photographer
     * can switch back and their balance remains intact.
     */
    public function photographerSetBillingMode(Request $request, PhotographerProfile $photographer)
    {
        $data = $request->validate([
            'billing_mode' => 'required|in:' . PhotographerProfile::BILLING_CREDITS . ',' . PhotographerProfile::BILLING_COMMISSION,
        ]);

        DB::transaction(function () use ($photographer, $data) {
            $photographer->update(['billing_mode' => $data['billing_mode']]);

            CreditTransaction::create([
                'photographer_id' => $photographer->user_id,
                'bundle_id'       => null,
                'kind'            => CreditTransaction::KIND_ADJUST,
                'delta'           => 0,
                'balance_after'   => (int) ($photographer->credits_balance_cached ?? 0),
                'reference_type'  => 'billing_mode_change',
                'reference_id'    => $data['billing_mode'],
                'meta'            => ['mode' => $data['billing_mode'], 'source' => 'admin_billing_mode_toggle'],
                'actor_user_id'   => Auth::guard('admin')->id() ?? Auth::id(),
                'created_at'      => now(),
            ]);
        });

        return back()->with('success', "เปลี่ยนโหมดการเรียกเก็บเป็น {$data['billing_mode']} แล้ว");
    }
}
