<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\Order;
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
 * Photographer-facing upload credits UI.
 *
 * Three screens + one action:
 *   GET  /photographer/credits              → dashboard (balance + bundles + recent ledger)
 *   GET  /photographer/credits/store        → package catalog (buy flow entry)
 *   POST /photographer/credits/buy/{code}   → create Order + redirect to payment.checkout
 *   GET  /photographer/credits/history      → paginated ledger / transactions
 *
 * The buy action deliberately reuses the existing customer checkout infra
 * (`payment.checkout` + webhook → CreditService::issueFromPaidOrder) rather
 * than inventing its own gateway flow. One order → one payment_transaction
 * → one webhook path → credits land. Keeps gateways, audit logs, retries,
 * idempotency, and admin visibility aligned with the photo-package flow.
 */
class CreditController extends Controller
{
    public function __construct(private CreditService $credits) {}

    // ─────────────────────────────────────────────────────────────────────
    // Dashboard — balance + bundles + quick ledger excerpt
    // ─────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $profile = $this->profile();

        $summary = $this->credits->dashboardSummary($profile);

        // Bundles table — ordered so the soonest-expiring is at the top, which
        // matches FIFO consumption order and makes "what's about to run out?"
        // the first thing you see.
        $bundles = PhotographerCreditBundle::query()
            ->where('photographer_id', $profile->user_id)
            ->with('package')
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        // Recent ledger — last 10 movements; full history lives at /history.
        $recentTxns = CreditTransaction::query()
            ->where('photographer_id', $profile->user_id)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        // Pending credit orders (photographer started checkout but hasn't paid
        // yet) — surface so they can resume instead of creating a dupe.
        $pendingOrders = Order::query()
            ->where('user_id', Auth::id())
            ->where('order_type', Order::TYPE_CREDIT_PACKAGE)
            ->whereIn('status', ['pending_payment', 'pending_review'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('photographer.credits.index', [
            'profile'       => $profile,
            'summary'       => $summary,
            'bundles'       => $bundles,
            'recentTxns'    => $recentTxns,
            'pendingOrders' => $pendingOrders,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Store — the package picker
    // ─────────────────────────────────────────────────────────────────────

    public function store(): View
    {
        $profile  = $this->profile();
        $packages = $this->credits->catalog();
        $balance  = $this->credits->balance((int) $profile->user_id);

        return view('photographer.credits.store', [
            'profile'  => $profile,
            'packages' => $packages,
            'balance'  => $balance,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Buy — create the Order + hand off to payment.checkout
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Create a pending credit_package Order and redirect to the payment
     * checkout screen. Validation:
     *   • Package must be active.
     *   • Credits system must be globally enabled (no point in buying
     *     credits we won't honour).
     *   • Photographer profile must exist (middleware guarantees auth but
     *     not profile).
     */
    public function buy(Request $request, string $code): RedirectResponse
    {
        if (!$this->credits->systemEnabled()) {
            return back()->with('error', 'ระบบเครดิตปิดใช้งานชั่วคราว กรุณาลองใหม่ภายหลัง');
        }

        $profile = $this->profile();

        $package = UploadCreditPackage::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$package) {
            return back()->with('error', 'ไม่พบแพ็คเก็จที่เลือก หรือแพ็คเก็จอาจถูกปิดการขายไปแล้ว');
        }

        try {
            $order = DB::transaction(function () use ($package, $profile) {
                return Order::create([
                    'user_id'            => Auth::id(),
                    'event_id'           => null,
                    'package_id'         => null,
                    'order_number'       => $this->generateOrderNumber(),
                    'total'              => (float) $package->price_thb,
                    'status'             => 'pending_payment',
                    'note'               => "Credit package: {$package->name} ({$package->credits} credits)",
                    'delivery_method'    => 'credits',
                    'delivery_status'    => 'pending',
                    'order_type'         => Order::TYPE_CREDIT_PACKAGE,
                    'credit_package_id'  => $package->id,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('CreditController::buy — order create failed', [
                'user_id' => Auth::id(),
                'package' => $package->code,
                'error'   => $e->getMessage(),
            ]);
            return back()->with('error', 'ไม่สามารถสร้างคำสั่งซื้อได้ กรุณาลองใหม่อีกครั้ง');
        }

        // Hand off to the shared payment checkout screen. After payment, the
        // webhook path calls CreditService::issueFromPaidOrder, and the status
        // page polls /payment/check-status/{order}. No custom polling here.
        return redirect()
            ->route('payment.checkout', ['order' => $order->id])
            ->with('success', "สั่งซื้อแพ็คเก็จ {$package->name} แล้ว — กรุณาชำระเงินเพื่อรับเครดิต");
    }

    // ─────────────────────────────────────────────────────────────────────
    // History — full transaction ledger
    // ─────────────────────────────────────────────────────────────────────

    public function history(Request $request): View
    {
        $profile = $this->profile();

        $kind = $request->query('kind');
        $q = CreditTransaction::query()
            ->where('photographer_id', $profile->user_id)
            ->with('bundle.package');

        if ($kind && in_array($kind, CreditTransaction::allKinds(), true)) {
            $q->where('kind', $kind);
        }

        $transactions = $q->orderByDesc('id')->paginate(30)->withQueryString();

        return view('photographer.credits.history', [
            'profile'      => $profile,
            'transactions' => $transactions,
            'kinds'        => CreditTransaction::allKinds(),
            'currentKind'  => $kind,
            'balance'      => $this->credits->balance((int) $profile->user_id),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Resolve the PhotographerProfile for the authenticated user, or abort
     * with a friendly message. The photographer middleware runs before us
     * so auth is guaranteed — we're only defending against a photographer
     * user whose profile was deleted out-of-band.
     */
    private function profile(): PhotographerProfile
    {
        $profile = Auth::user()?->photographerProfile;
        abort_unless($profile instanceof PhotographerProfile, 403, 'ไม่พบโปรไฟล์ช่างภาพ');
        return $profile;
    }

    /**
     * Credit orders get their own CR- prefix so they're trivially spottable
     * in admin lists — sits alongside the existing ORD- prefix without
     * colliding (unique constraint on orders.order_number).
     */
    private function generateOrderNumber(): string
    {
        return 'CR-' . date('ymd') . '-' . strtoupper(Str::random(6));
    }
}
