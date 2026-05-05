{{--
  Withdrawal Request Widget — photographer-side
  ────────────────────────────────────────────────
  Self-contained widget for the /photographer/earnings page that:
    • shows the available balance + the admin-tuned threshold
    • exposes a "แจ้งถอน" CTA, gated by snapshot.can_request
    • opens a request form modal (Alpine x-data="withdrawalForm")
    • displays recent request history with cancel action when pending

  Server provides:
    $withdrawalSnap    — array from WithdrawalController::snapshot()
    $withdrawalHistory — paginator of WithdrawalRequest rows
--}}
@php
    $snap = $withdrawalSnap ?? null;
    $hist = $withdrawalHistory ?? null;
@endphp

@if($snap)
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-white/[0.06] overflow-hidden mb-4"
     x-data="{ showForm: false }">

    {{-- Header strip --}}
    <div class="p-4 md:p-5 border-b border-slate-100 dark:border-white/[0.06] bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-500/[0.06] dark:to-teal-500/[0.06]">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <p class="text-[10px] font-bold tracking-[0.16em] uppercase text-emerald-700 dark:text-emerald-400 mb-1">
                    <i class="bi bi-cash-coin mr-1"></i>แจ้งถอนเงินด้วยตัวเอง
                </p>
                <h3 class="font-bold text-base text-slate-900 dark:text-white">
                    ยอดที่ถอนได้:
                    <span class="text-emerald-600 dark:text-emerald-400 ml-1 tabular-nums">
                        ฿{{ number_format($snap['available_balance'] ?? 0, 2) }}
                    </span>
                </h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                    @if($snap['enabled'])
                        ขั้นต่ำ <strong>฿{{ number_format($snap['min']) }}</strong>
                        @if(($snap['fee'] ?? 0) > 0)
                            · ค่าธรรมเนียม ฿{{ number_format($snap['fee']) }}
                        @else
                            · ฟรีค่าธรรมเนียม
                        @endif
                        · ใช้เวลา {{ (int) $snap['processing_days'] }} วันทำการ
                    @else
                        ระบบแจ้งถอนปิดอยู่ชั่วคราว
                    @endif
                </p>
            </div>
            <div>
                @if($snap['can_request'])
                    <button type="button"
                            @click="showForm = !showForm"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-white text-sm font-bold shadow-md transition active:scale-[0.98]"
                            style="background:linear-gradient(135deg,#10b981,#059669);">
                        <i class="bi bi-arrow-down-circle-fill"></i>
                        <span x-show="!showForm">แจ้งถอนเงิน</span>
                        <span x-show="showForm" x-cloak>ซ่อนฟอร์ม</span>
                    </button>
                @else
                    <div class="text-right">
                        <button type="button" disabled
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-slate-200 dark:bg-white/[0.06] text-slate-500 text-sm font-medium cursor-not-allowed">
                            <i class="bi bi-lock-fill"></i> แจ้งถอนเงิน
                        </button>
                        @if($snap['blocking_reason'] ?? false)
                            <p class="text-[10px] text-rose-600 dark:text-rose-400 mt-1.5 max-w-xs">
                                {{ $snap['blocking_reason'] }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Request form (Alpine-toggled) --}}
    <div x-show="showForm" x-cloak x-transition class="p-4 md:p-5 border-b border-slate-100 dark:border-white/[0.06]">
        @if(session('success'))
            <div class="mb-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm px-3 py-2">
                <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-900 text-sm px-3 py-2">
                <i class="bi bi-exclamation-triangle-fill mr-1.5"></i>{{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('photographer.withdrawals.store') }}" x-data="{ method: 'bank_transfer' }" class="space-y-3">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        จำนวนเงิน (บาท) <span class="text-rose-500">*</span>
                    </label>
                    <input type="number"
                           name="amount"
                           min="{{ $snap['min'] }}"
                           max="{{ min($snap['max'], (int) floor($snap['available_balance'])) }}"
                           step="0.01"
                           value="{{ old('amount', (int) floor($snap['available_balance'])) }}"
                           required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm tabular-nums">
                    <p class="text-[10px] text-slate-500 mt-1">
                        ขั้นต่ำ ฿{{ number_format($snap['min']) }} — สูงสุด ฿{{ number_format(min($snap['max'], (int) floor($snap['available_balance']))) }}
                    </p>
                    @error('amount') <p class="text-[10px] text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        วิธีรับเงิน <span class="text-rose-500">*</span>
                    </label>
                    <select name="method" x-model="method" required
                            class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                        @if(in_array('bank_transfer', $snap['methods'] ?? [], true))
                            <option value="bank_transfer">โอนเข้าบัญชีธนาคาร</option>
                        @endif
                        @if(in_array('promptpay', $snap['methods'] ?? [], true))
                            <option value="promptpay">PromptPay</option>
                        @endif
                    </select>
                </div>
            </div>

            {{-- Bank transfer fields --}}
            <div x-show="method === 'bank_transfer'" x-transition class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">ธนาคาร</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name') }}"
                           placeholder="เช่น ไทยพาณิชย์, กสิกรไทย"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        ชื่อบัญชี <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="account_name" value="{{ old('account_name') }}"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        เลขบัญชี <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="account_number" value="{{ old('account_number') }}"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm tabular-nums">
                </div>
            </div>

            {{-- PromptPay fields --}}
            <div x-show="method === 'promptpay'" x-cloak x-transition class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        ชื่อบัญชี <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="account_name" value="{{ old('account_name') }}"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        PromptPay (เบอร์ / เลขบัตรประชาชน) <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="promptpay_id" value="{{ old('promptpay_id') }}"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm tabular-nums">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">หมายเหตุ (ไม่จำเป็น)</label>
                <textarea name="note" rows="2" maxlength="500"
                          placeholder="เช่น ใบกำกับภาษีงาน ABC"
                          class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">{{ old('note') }}</textarea>
            </div>

            <div class="flex items-center justify-between gap-3 pt-2 border-t border-slate-100 dark:border-white/[0.06]">
                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    <i class="bi bi-info-circle"></i>
                    @if(($snap['fee'] ?? 0) > 0)
                        คุณจะได้รับ <strong>ยอดถอน − ค่าธรรมเนียม ฿{{ number_format($snap['fee']) }}</strong>
                    @else
                        คุณจะได้รับยอดเต็ม (ฟรีค่าธรรมเนียม)
                    @endif
                </p>
                <div class="flex items-center gap-2">
                    <button type="button" @click="showForm = false"
                            class="text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/[0.04]">
                        ยกเลิก
                    </button>
                    <button type="submit"
                            class="text-sm px-4 py-2 rounded-lg text-white font-bold shadow-md"
                            style="background:linear-gradient(135deg,#10b981,#059669);">
                        <i class="bi bi-send-fill mr-1"></i>ส่งคำขอถอน
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Recent history --}}
    @if($hist && $hist->total() > 0)
        <div class="p-4 md:p-5">
            <p class="text-[10px] font-bold tracking-[0.16em] uppercase text-slate-500 dark:text-slate-400 mb-3">
                <i class="bi bi-clock-history mr-1"></i>ประวัติแจ้งถอน
            </p>
            <div class="space-y-2">
                @foreach($hist as $r)
                    @php
                        $color = $r->statusColor();
                        $colorMap = [
                            'amber'   => ['bg-amber-100 text-amber-700',     'border-amber-200'],
                            'blue'    => ['bg-blue-100 text-blue-700',       'border-blue-200'],
                            'emerald' => ['bg-emerald-100 text-emerald-700', 'border-emerald-200'],
                            'rose'    => ['bg-rose-100 text-rose-700',       'border-rose-200'],
                            'gray'    => ['bg-slate-100 text-slate-600',     'border-slate-200'],
                        ];
                        $cls = $colorMap[$color] ?? $colorMap['gray'];
                    @endphp
                    <div class="flex items-center justify-between gap-3 p-3 rounded-lg border {{ $cls[1] }} dark:border-white/[0.06] bg-white dark:bg-slate-900/40">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold {{ $cls[0] }}">{{ $r->statusLabel() }}</span>
                                <span class="text-xs font-bold tabular-nums text-slate-900 dark:text-white">฿{{ number_format($r->amount_thb, 2) }}</span>
                                <span class="text-[10px] text-slate-500">· {{ $r->methodLabel() }}</span>
                            </div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400">
                                ส่งคำขอ {{ $r->created_at?->format('d M Y H:i') ?? '—' }}
                                @if($r->paid_at)
                                    · โอนแล้ว {{ $r->paid_at->format('d M Y H:i') }}
                                @elseif($r->reviewed_at)
                                    · ตรวจแล้ว {{ $r->reviewed_at->format('d M Y H:i') }}
                                @endif
                            </div>
                            @if($r->isRejected() && $r->rejection_reason)
                                <div class="text-[10px] text-rose-600 dark:text-rose-400 mt-0.5">
                                    <i class="bi bi-info-circle"></i> {{ $r->rejection_reason }}
                                </div>
                            @endif
                            @if($r->isPaid() && $r->payment_reference)
                                <div class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-0.5 font-mono">
                                    <i class="bi bi-receipt"></i> {{ $r->payment_reference }}
                                </div>
                            @endif
                        </div>
                        @if($r->isCancellable())
                            <form method="POST" action="{{ route('photographer.withdrawals.cancel', $r->id) }}"
                                  onsubmit="return confirm('ยืนยันยกเลิกคำขอนี้?');">
                                @csrf
                                <button type="submit" class="text-xs text-rose-600 hover:text-rose-700 font-semibold">
                                    <i class="bi bi-x-circle"></i> ยกเลิก
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
            @if($hist->hasPages())
                <div class="mt-3">
                    {{ $hist->withQueryString()->links('vendor.pagination.loadroop') }}
                </div>
            @endif
        </div>
    @endif
</div>
@endif
