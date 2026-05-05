@extends('layouts.admin')
@section('title', 'คำขอถอนเงิน #' . $req->id)

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-arrow-down-circle text-emerald-500"></i>
        คำขอถอนเงิน #{{ $req->id }}
    </h4>
    <a href="{{ route('admin.payments.withdrawals.index') }}"
       class="text-xs px-3 py-1.5 bg-slate-600 text-white rounded-lg hover:bg-slate-700">
        <i class="bi bi-arrow-left mr-1"></i>กลับ
    </a>
</div>

@if(session('success'))
  <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm px-4 py-3">
    <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
  </div>
@endif
@if(session('error'))
  <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-900 text-sm px-4 py-3">
    <i class="bi bi-exclamation-triangle-fill mr-1.5"></i>{{ session('error') }}
  </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    {{-- Main info card --}}
    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 p-5">
        @php
            $color = $req->statusColor();
            $badgeMap = [
                'amber'   => 'bg-amber-100 text-amber-700',
                'blue'    => 'bg-blue-100 text-blue-700',
                'emerald' => 'bg-emerald-100 text-emerald-700',
                'rose'    => 'bg-rose-100 text-rose-700',
                'gray'    => 'bg-gray-100 text-gray-600',
            ];
        @endphp

        <div class="flex items-start justify-between mb-5">
            <div>
                <h2 class="font-extrabold text-3xl tabular-nums text-slate-900">
                    ฿{{ number_format($req->amount_thb, 2) }}
                </h2>
                @if((float) $req->fee_thb > 0)
                    <p class="text-xs text-slate-500 mt-1">ค่าธรรมเนียม ฿{{ number_format($req->fee_thb, 2) }} · สุทธิ ฿{{ number_format($req->net_thb, 2) }}</p>
                @endif
            </div>
            <span class="inline-block px-3 py-1.5 rounded-lg text-sm font-bold {{ $badgeMap[$color] }}">
                {{ $req->statusLabel() }}
            </span>
        </div>

        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">ช่างภาพ</dt>
                <dd class="font-medium mt-1">{{ $req->photographer?->name ?? 'user #' . $req->photographer_id }}</dd>
                <dd class="text-xs text-slate-500">{{ $req->photographer?->email }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">วิธีรับเงิน</dt>
                <dd class="font-medium mt-1">{{ $req->methodLabel() }}</dd>
                @if(!empty($req->method_details))
                    <dd class="text-xs text-slate-700 mt-1 space-y-0.5">
                        @if(!empty($req->method_details['bank_name']))
                            <div>ธนาคาร: <strong>{{ $req->method_details['bank_name'] }}</strong></div>
                        @endif
                        @if(!empty($req->method_details['account_name']))
                            <div>ชื่อบัญชี: <strong>{{ $req->method_details['account_name'] }}</strong></div>
                        @endif
                        @if(!empty($req->method_details['account_number']))
                            <div>เลขบัญชี: <strong class="font-mono">{{ $req->method_details['account_number'] }}</strong></div>
                        @endif
                        @if(!empty($req->method_details['promptpay_id']))
                            <div>PromptPay: <strong class="font-mono">{{ $req->method_details['promptpay_id'] }}</strong></div>
                        @endif
                    </dd>
                @endif
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">ส่งคำขอเมื่อ</dt>
                <dd class="font-medium mt-1">{{ $req->created_at?->format('d M Y H:i') }}</dd>
            </div>
            @if($req->reviewed_at)
                <div>
                    <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">ตรวจโดยแอดมินเมื่อ</dt>
                    <dd class="font-medium mt-1">{{ $req->reviewed_at->format('d M Y H:i') }}</dd>
                    @if($req->reviewedBy)
                        <dd class="text-xs text-slate-500">โดย {{ $req->reviewedBy->name ?? 'admin #' . $req->reviewed_by_admin_id }}</dd>
                    @endif
                </div>
            @endif
            @if($req->paid_at)
                <div>
                    <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">โอนเมื่อ</dt>
                    <dd class="font-medium mt-1 text-emerald-700">{{ $req->paid_at->format('d M Y H:i') }}</dd>
                </div>
            @endif
            @if($req->payment_reference)
                <div>
                    <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">Reference</dt>
                    <dd class="font-mono text-xs mt-1">{{ $req->payment_reference }}</dd>
                </div>
            @endif
            @if($req->photographer_note)
                <div class="md:col-span-2">
                    <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">หมายเหตุจากช่างภาพ</dt>
                    <dd class="text-sm mt-1 p-2.5 rounded-lg bg-slate-50 border border-slate-200">{{ $req->photographer_note }}</dd>
                </div>
            @endif
            @if($req->admin_note)
                <div class="md:col-span-2">
                    <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">หมายเหตุแอดมิน</dt>
                    <dd class="text-sm mt-1 p-2.5 rounded-lg bg-indigo-50 border border-indigo-200">{{ $req->admin_note }}</dd>
                </div>
            @endif
            @if($req->rejection_reason)
                <div class="md:col-span-2">
                    <dt class="text-[10px] uppercase tracking-wider text-rose-500 font-bold">เหตุผลการปฏิเสธ</dt>
                    <dd class="text-sm mt-1 p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-900">{{ $req->rejection_reason }}</dd>
                </div>
            @endif
            @if($req->payment_slip_url)
                <div class="md:col-span-2">
                    <dt class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">สลิปการโอน</dt>
                    <dd class="mt-1">
                        <a href="{{ $req->payment_slip_url }}" target="_blank" rel="noopener"
                           class="text-xs text-indigo-600 font-semibold hover:underline">
                            <i class="bi bi-image"></i> ดูสลิป →
                        </a>
                    </dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Action panel --}}
    <div class="space-y-3">
        @if($req->isActionable())
            {{-- Approve --}}
            @if($req->isPending())
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <p class="text-xs font-bold text-blue-700 mb-2">
                        <i class="bi bi-check-circle"></i> อนุมัติคำขอ
                    </p>
                    <p class="text-[11px] text-blue-700/80 mb-3">เปลี่ยนสถานะเป็น "อนุมัติแล้ว · รอโอน" และแจ้งช่างภาพผ่าน LINE</p>
                    <form method="POST" action="{{ route('admin.payments.withdrawals.approve', $req->id) }}">
                        @csrf
                        <textarea name="admin_note" placeholder="หมายเหตุภายใน (ไม่จำเป็น)" rows="2" class="w-full px-2 py-1.5 rounded-md border border-blue-200 text-xs mb-2"></textarea>
                        <button type="submit" class="w-full text-sm font-bold py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                            <i class="bi bi-check-lg mr-1"></i>อนุมัติ
                        </button>
                    </form>
                </div>
            @endif

            {{-- Mark Paid --}}
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                <p class="text-xs font-bold text-emerald-700 mb-2">
                    <i class="bi bi-cash-coin"></i> ยืนยันการโอน
                </p>
                <p class="text-[11px] text-emerald-700/80 mb-3">บันทึกว่าโอนเรียบร้อย — เปลี่ยนสถานะเป็น "โอนแล้ว"</p>
                <form method="POST" action="{{ route('admin.payments.withdrawals.mark-paid', $req->id) }}" class="space-y-2">
                    @csrf
                    <input type="text" name="payment_reference" placeholder="Reference (เลข txn ของธนาคาร)"
                           class="w-full px-2 py-1.5 rounded-md border border-emerald-200 text-xs">
                    <input type="url" name="payment_slip_url" placeholder="URL สลิปการโอน (https://...)"
                           class="w-full px-2 py-1.5 rounded-md border border-emerald-200 text-xs">
                    <textarea name="admin_note" placeholder="หมายเหตุภายใน (ไม่จำเป็น)" rows="2"
                              class="w-full px-2 py-1.5 rounded-md border border-emerald-200 text-xs">{{ $req->admin_note }}</textarea>
                    <button type="submit" class="w-full text-sm font-bold py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
                        <i class="bi bi-check-circle-fill mr-1"></i>ยืนยันการโอน
                    </button>
                </form>
            </div>

            {{-- Reject --}}
            <div class="bg-rose-50 border border-rose-200 rounded-xl p-4">
                <p class="text-xs font-bold text-rose-700 mb-2">
                    <i class="bi bi-x-circle"></i> ปฏิเสธคำขอ
                </p>
                <p class="text-[11px] text-rose-700/80 mb-3">ระบุเหตุผลการปฏิเสธ — จะส่งให้ช่างภาพอ่าน</p>
                <form method="POST" action="{{ route('admin.payments.withdrawals.reject', $req->id) }}"
                      onsubmit="return confirm('ยืนยันปฏิเสธคำขอนี้?');">
                    @csrf
                    <textarea name="rejection_reason" placeholder="เหตุผล (เช่น เลขบัญชีไม่ถูกต้อง)" rows="3" required
                              class="w-full px-2 py-1.5 rounded-md border border-rose-200 text-xs mb-2"></textarea>
                    <button type="submit" class="w-full text-sm font-bold py-2 rounded-lg bg-rose-600 text-white hover:bg-rose-700">
                        <i class="bi bi-x-lg mr-1"></i>ปฏิเสธ
                    </button>
                </form>
            </div>
        @else
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center">
                <i class="bi bi-lock-fill text-2xl text-slate-400 block mb-2"></i>
                <p class="text-xs text-slate-600">คำขอนี้อยู่ในสถานะสุดท้าย ไม่สามารถแก้ไขได้</p>
            </div>
        @endif
    </div>
</div>
@endsection
