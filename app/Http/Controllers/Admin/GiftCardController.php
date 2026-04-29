<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Services\GiftCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GiftCardController extends Controller
{
    public function __construct(protected GiftCardService $svc) {}

    public function index(Request $request)
    {
        $status = $request->string('status')->toString() ?: null;
        $search = trim((string) $request->string('q'));

        $q = GiftCard::query()->orderByDesc('created_at');

        if ($status) {
            $q->where('status', $status);
        }
        if ($search !== '') {
            $q->where(function ($qq) use ($search) {
                $qq->where('code', 'ilike', '%' . strtoupper($search) . '%')
                   ->orWhere('purchaser_email', 'ilike', "%{$search}%")
                   ->orWhere('recipient_email', 'ilike', "%{$search}%")
                   ->orWhere('purchaser_name', 'ilike', "%{$search}%")
                   ->orWhere('recipient_name', 'ilike', "%{$search}%");
            });
        }

        $cards    = $q->paginate(20)->withQueryString();
        $kpis     = $this->svc->kpis();
        $statuses = GiftCard::statuses();
        $sources  = GiftCard::sources();

        return view('admin.gift-cards.index', compact('cards', 'kpis', 'statuses', 'sources', 'status', 'search'));
    }

    public function create()
    {
        $sources = GiftCard::sources();
        return view('admin.gift-cards.create', compact('sources'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'amount'           => 'required|numeric|min:1|max:500000',
            'currency'         => 'nullable|string|size:3',
            'recipient_name'   => 'nullable|string|max:120',
            'recipient_email'  => 'nullable|email|max:160',
            'personal_message' => 'nullable|string|max:1000',
            'expires_at'       => 'nullable|date|after:today',
            'admin_note'       => 'nullable|string|max:500',
            'source'           => 'nullable|string|in:admin,purchase,promo,refund',
        ]);

        $data['issued_by_admin_id'] = Auth::guard('admin')->id();

        $gc = $this->svc->issue($data);

        return redirect()->route('admin.gift-cards.show', $gc)->with('success', 'ออกบัตรแล้ว — ' . $gc->code);
    }

    public function show(GiftCard $giftCard)
    {
        $giftCard->load(['transactions' => fn ($q) => $q->latest()->limit(50)]);
        return view('admin.gift-cards.show', ['gc' => $giftCard]);
    }

    public function adjust(Request $request, GiftCard $giftCard)
    {
        $data = $request->validate([
            'delta' => 'required|numeric|not_in:0',
            'note'  => 'nullable|string|max:200',
        ]);
        $this->svc->adjust($giftCard, (float) $data['delta'], Auth::guard('admin')->id(), $data['note'] ?? null);
        return back()->with('success', 'ปรับยอดเรียบร้อย');
    }

    public function void(Request $request, GiftCard $giftCard)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:200']);
        $this->svc->void($giftCard, Auth::guard('admin')->id(), $data['reason'] ?? null);
        return back()->with('success', 'ยกเลิกบัตรแล้ว');
    }
}
