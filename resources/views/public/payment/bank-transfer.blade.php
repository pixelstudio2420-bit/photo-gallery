@extends('layouts.app')

@section('title', 'โอนผ่านธนาคาร — ชำระเงิน')

@section('content')
<div class="max-w-4xl mx-auto px-4 md:px-6 py-6">

  {{-- Pay-now countdown — same banner used on checkout + promptpay. --}}
  @include('public.payment._countdown', ['order' => $order])

  {{-- Header --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-md">
        <i class="bi bi-bank2"></i>
      </span>
      โอนผ่านธนาคาร
    </h1>
    <a href="{{ route('orders.index') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 text-sm transition">
      <i class="bi bi-list-ul"></i> คำสั่งซื้อของฉัน
    </a>
  </div>

  <div class="space-y-5">

    {{-- Amount card --}}
    <div class="rounded-2xl bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 text-white shadow-lg p-6 text-center">
      <div class="text-sm opacity-80 mb-1">ยอดที่ต้องโอน</div>
      <div class="text-4xl md:text-5xl font-bold mb-2">
        {{ number_format((float)($amount ?? 0), 2) }} <span class="text-xl">฿</span>
      </div>
      @if(!empty($order->order_number))
        <div class="text-xs opacity-80">เลขอ้างอิง: <strong>{{ $order->order_number }}</strong></div>
      @endif
      @if(!empty($transaction->transaction_id))
        <div class="text-xs opacity-80 mt-1 font-mono">Transaction: {{ $transaction->transaction_id }}</div>
      @endif
    </div>

    {{-- Bank accounts --}}
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center shadow-md">
          <i class="bi bi-building-check"></i>
        </div>
        <h3 class="font-semibold text-slate-900 dark:text-white">บัญชีธนาคารสำหรับโอน</h3>
      </div>
      <div class="divide-y divide-slate-100 dark:divide-white/5">
        @forelse($bankAccounts as $bank)
        <div class="flex items-start gap-3 p-5 hover:bg-slate-50 dark:hover:bg-white/5 transition">
          <div class="w-13 h-13 w-[52px] h-[52px] rounded-xl bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center flex-shrink-0">
            <i class="bi bi-bank2 text-indigo-600 dark:text-indigo-400 text-xl"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-semibold text-slate-900 dark:text-white">{{ $bank->bank_name }}</div>
            @if(!empty($bank->branch))
              <div class="text-xs text-slate-500 dark:text-slate-400">สาขา: {{ $bank->branch }}</div>
            @endif
            <div class="mt-1 font-mono font-bold text-slate-900 dark:text-white tracking-wider">{{ $bank->account_number }}</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $bank->account_holder_name ?? '' }}</div>
          </div>
          <button type="button"
                  onclick="copyText('{{ $bank->account_number }}', this)"
                  class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-500 hover:text-white text-xs font-medium transition flex-shrink-0">
            <i class="bi bi-copy"></i> คัดลอก
          </button>
        </div>
        @empty
        <p class="text-sm text-slate-500 dark:text-slate-400 text-center py-8">ไม่มีบัญชีธนาคาร กรุณาติดต่อผู้ดูแลระบบ</p>
        @endforelse
      </div>
    </div>

    {{-- Slip upload --}}
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 text-white flex items-center justify-center shadow-md">
          <i class="bi bi-cloud-upload-fill"></i>
        </div>
        <div>
          <h3 class="font-semibold text-slate-900 dark:text-white">อัปโหลดสลิปการโอน</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">ถ่ายภาพหรือ screenshot สลิป แล้วอัปโหลดเพื่อให้เจ้าหน้าที่ตรวจสอบ</p>
        </div>
      </div>

      <div class="p-5">
        <form method="POST" action="{{ route('payment.slip.upload') }}" enctype="multipart/form-data" class="space-y-4">
          @csrf
          <input type="hidden" name="transaction_id" value="{{ $transaction->transaction_id ?? '' }}">
          @if(isset($order))<input type="hidden" name="order_id" value="{{ $order->id }}">@endif
          <input type="hidden" name="payment_method" value="bank_transfer">
          <input type="hidden" name="transfer_amount" value="{{ $amount ?? $order->total ?? 0 }}">
          <input type="hidden" name="transfer_date" value="{{ date('Y-m-d') }}">

          <div>
            <label for="bankRefCode" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">หมายเลขอ้างอิง (ถ้ามี)</label>
            <input type="text" id="bankRefCode" name="ref_code"
                   placeholder="เช่น เลขที่รายการโอน"
                   class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
          </div>

          <label class="relative block cursor-pointer rounded-xl border-2 border-dashed border-slate-200 dark:border-white/10 hover:border-indigo-400 dark:hover:border-indigo-500 bg-slate-50 dark:bg-slate-900/50 transition p-6 text-center">
            <input type="file" id="bankSlipInput" name="slip_image" accept="image/*" required
                   class="absolute inset-0 opacity-0 cursor-pointer"
                   onchange="document.getElementById('bankSlipName').textContent = this.files[0]?.name || ''">
            <div class="w-12 h-12 mx-auto rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center mb-2 shadow-md">
              <i class="bi bi-cloud-upload text-xl"></i>
            </div>
            <div class="font-semibold text-sm text-slate-900 dark:text-white">คลิกเพื่อเลือกรูปสลิป</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">รองรับ JPG, PNG — สูงสุด 5 MB</div>
            <div id="bankSlipName" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 mt-2"></div>
          </label>

          <button type="submit"
                  class="w-full py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2">
            <i class="bi bi-upload"></i> อัปโหลดสลิป
          </button>
        </form>
      </div>
    </div>

    {{-- Info --}}
    <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 flex gap-3">
      <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center flex-shrink-0 shadow-sm">
        <i class="bi bi-clock-history"></i>
      </div>
      <div class="text-xs">
        <div class="font-semibold text-emerald-900 dark:text-emerald-200">ระยะเวลาตรวจสอบ</div>
        <p class="text-emerald-800 dark:text-emerald-300/90 mt-0.5">ปกติภายใน 1–2 ชั่วโมงในเวลาทำการ หลังจากได้รับสลิปแล้ว</p>
      </div>
    </div>
  </div>
</div>

<script>
function copyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2"></i> คัดลอกแล้ว';
    btn.classList.add('bg-emerald-500', 'text-white');
    setTimeout(() => {
      btn.innerHTML = orig;
      btn.classList.remove('bg-emerald-500', 'text-white');
    }, 2000);
  });
}
</script>
@endsection
