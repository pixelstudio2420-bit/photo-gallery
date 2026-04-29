<?php
namespace App\Http\Controllers\Photographer;
use App\Http\Controllers\Controller;
use App\Models\PhotographerDisbursement;
use App\Models\PhotographerPayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EarningsController extends Controller
{
    public function index()
    {
        $photographer = Auth::user()->photographerProfile;
        $userId = Auth::id();

        // Per-order payouts ("earnings" — each sale lands one row). Keep the
        // existing pagination semantics so deep links survive the UI change.
        $payouts = PhotographerPayout::where('photographer_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'payouts_page');

        // Per-transfer disbursements ("withdrawals" — the thing that actually
        // moves money out). Separate paginator page param so both tables can
        // page independently without one stomping the other's URL.
        $disbursements = PhotographerDisbursement::where('photographer_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'disbursements_page');

        // Summary stats — computed in the same request so the banner is
        // consistent with the tables below. Three numbers are enough:
        // - total earned (lifetime, gross to photographer)
        // - already paid out (successful disbursements)
        // - pending in the pipeline (earnings not yet attached to a
        //   succeeded disbursement)
        $totalEarnings = PhotographerPayout::where('photographer_id', $userId)->sum('payout_amount');
        $totalPaid     = PhotographerDisbursement::where('photographer_id', $userId)
            ->where('status', PhotographerDisbursement::STATUS_SUCCEEDED)
            ->sum('amount_thb');
        $pendingAmount = PhotographerPayout::where('photographer_id', $userId)
            ->where('status', 'pending')
            ->sum('payout_amount');

        return view('photographer.earnings.index', compact(
            'payouts',
            'disbursements',
            'totalEarnings',
            'totalPaid',
            'pendingAmount',
            'photographer',
        ));
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $userId = Auth::id();
        $requestedAmount = (float) $request->input('amount');

        // Calculate pending earnings: completed payouts that haven't been paid out yet
        $pendingEarnings = PhotographerPayout::where('photographer_id', $userId)
            ->where('status', 'pending')
            ->sum('payout_amount');

        if ($requestedAmount > $pendingEarnings) {
            return back()->with('error', 'ยอดเงินที่ขอถอนมากกว่ายอดรายได้ที่มี');
        }

        // Mark pending payouts as "requested" up to the requested amount
        $payouts = PhotographerPayout::where('photographer_id', $userId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        $remaining = $requestedAmount;
        DB::beginTransaction();
        try {
            foreach ($payouts as $payout) {
                if ($remaining <= 0) break;

                $payout->update([
                    'status' => 'requested',
                    'note'   => 'ขอถอนเงินจำนวน ' . number_format($requestedAmount, 2) . ' บาท',
                ]);
                $remaining -= (float) $payout->payout_amount;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
        }

        try {
            $line = app(\App\Services\LineNotifyService::class);
            $photographer = Auth::user()->photographerProfile;
            $line->notifyNewWithdrawal(['photographer_name' => $photographer->display_name, 'amount' => $request->amount]);
        } catch (\Throwable $e) {
            \Log::error('Notification error: ' . $e->getMessage());
        }

        return back()->with('success', 'ส่งคำขอถอนเงินสำเร็จ');
    }
}
