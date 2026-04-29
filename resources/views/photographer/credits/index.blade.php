@extends('layouts.photographer')

@section('title', 'เครดิตอัปโหลดของฉัน')

@php
  use App\Models\CreditTransaction;
@endphp

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-coin',
  'eyebrow'  => 'บริการเสริม',
  'title'    => 'เครดิตอัปโหลด',
  'subtitle' => 'ดูเครดิตคงเหลือ · ประวัติการใช้ · ซื้อเครดิตเพิ่ม',
  'actions'  => '<a href="'.route('photographer.credits.store').'" class="pg-btn-primary"><i class="bi bi-bag-plus"></i> ซื้อเครดิตเพิ่ม</a>',
])

@if(session('success'))
  <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-4 py-3">
    <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
  </div>
@endif
@if(session('error'))
  <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-900 text-sm px-4 py-3">
    <i class="bi bi-exclamation-triangle-fill mr-1.5"></i>{{ session('error') }}
  </div>
@endif

{{-- Mode banner — if the photographer is still on commission, nudge them to the credits plan --}}
@if($profile->isCommissionMode())
  <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
      <p class="text-amber-900 font-semibold mb-1">
        <i class="bi bi-info-circle-fill mr-1"></i>บัญชีของคุณใช้โหมด "หักค่าคอมมิชชัน"
      </p>
      <p class="text-sm text-amber-800">
        เปลี่ยนมาใช้ "เครดิตอัปโหลด" ได้แต้มเครดิตฟรีรายเดือน + หักค่าขาย 0%
      </p>
    </div>
    <a href="{{ route('photographer.profile') }}"
       class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-medium hover:bg-amber-700">
      เปลี่ยนโหมดในโปรไฟล์ <i class="bi bi-arrow-right"></i>
    </a>
  </div>
@endif

{{-- ─────────────────── Summary Cards ─────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
  {{-- Balance (primary) --}}
  <div class="bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-xl shadow-sm p-5">
    <div class="flex justify-between items-start">
      <div>
        <p class="text-indigo-100 text-xs uppercase tracking-wider font-medium mb-1">เครดิตคงเหลือ</p>
        <h2 class="font-bold text-3xl tracking-tight">{{ number_format($summary['balance'] ?? 0) }}</h2>
        <p class="text-[11px] text-indigo-100 mt-1">1 เครดิต = อัปโหลด 1 ภาพ</p>
      </div>
      <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-white/20">
        <i class="bi bi-coin"></i>
      </div>
    </div>
  </div>

  {{-- Next expiring --}}
  <div class="pg-card p-5">
    <div class="flex justify-between items-start">
      <div>
        <p class="text-gray-500 text-xs uppercase tracking-wider font-medium mb-1">เครดิตใกล้หมดอายุ</p>
        @if(!empty($summary['next_expiring']))
          <h2 class="font-bold text-2xl tracking-tight {{ $summary['next_expiring']['warn_soon'] ? 'text-rose-600' : 'text-gray-900' }}">
            {{ number_format($summary['next_expiring']['credits']) }}
          </h2>
          <p class="text-[11px] mt-1 {{ $summary['next_expiring']['warn_soon'] ? 'text-rose-500' : 'text-gray-400' }}">
            หมดอายุ {{ $summary['next_expiring']['expires_at'] }}
            ({{ (int) $summary['next_expiring']['days_left'] }} วัน)
          </p>
        @else
          <h2 class="font-bold text-2xl tracking-tight text-gray-900">—</h2>
          <p class="text-[11px] text-gray-400 mt-1">ยังไม่มีเครดิตที่มีวันหมดอายุ</p>
        @endif
      </div>
      <div class="flex items-center justify-center w-10 h-10 rounded-lg {{ ($summary['next_expiring']['warn_soon'] ?? false) ? 'bg-rose-500/10 text-rose-600' : 'bg-amber-500/10 text-amber-600' }}">
        <i class="bi bi-hourglass-split"></i>
      </div>
    </div>
  </div>

  {{-- Last purchase --}}
  <div class="pg-card p-5">
    <div class="flex justify-between items-start">
      <div>
        <p class="text-gray-500 text-xs uppercase tracking-wider font-medium mb-1">ซื้อครั้งล่าสุด</p>
        <h2 class="font-bold text-lg tracking-tight text-gray-900">
          {{ $summary['last_purchase'] ? \Illuminate\Support\Carbon::parse($summary['last_purchase'])->format('d M Y') : '—' }}
        </h2>
        <p class="text-[11px] text-gray-400 mt-1">
          {{ $summary['last_purchase'] ? 'กดซื้อเพิ่มได้ทุกเมื่อ' : 'ยังไม่เคยซื้อแพ็คเก็จ' }}
        </p>
      </div>
      <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-500/10">
        <i class="bi bi-bag-check-fill text-emerald-600"></i>
      </div>
    </div>
  </div>
</div>

{{-- Pending orders — resume payment --}}
@if($pendingOrders->isNotEmpty())
  <div class="mb-6 rounded-xl border border-sky-200 bg-sky-50 p-4">
    <p class="text-sky-900 font-semibold text-sm mb-2">
      <i class="bi bi-clock-history mr-1"></i>คำสั่งซื้อที่ยังไม่ได้ชำระเงิน
    </p>
    <ul class="space-y-2">
      @foreach($pendingOrders as $po)
        <li class="flex items-center justify-between text-sm">
          <span class="text-sky-800">
            {{ $po->order_number }} — ฿{{ number_format($po->total, 2) }}
            <span class="text-xs text-sky-600">({{ optional($po->created_at)->diffForHumans() }})</span>
          </span>
          <a href="{{ route('payment.checkout', ['order' => $po->id]) }}"
             class="inline-flex items-center gap-1 px-3 py-1 rounded-md bg-sky-600 text-white text-xs font-medium hover:bg-sky-700">
            ชำระเงินต่อ <i class="bi bi-arrow-right"></i>
          </a>
        </li>
      @endforeach
    </ul>
  </div>
@endif

{{-- ─────────────────── Bundles ─────────────────── --}}
<div class="pg-card overflow-hidden mb-6 pg-anim d2">
  <div class="pg-card-header">
    <h5 class="pg-section-title m-0"><i class="bi bi-box-seam"></i> แพ็คเก็จเครดิตของคุณ</h5>
    <span class="text-xs text-gray-500">เรียงตามวันหมดอายุใกล้ที่สุดก่อน (FIFO)</span>
  </div>
  <div class="pg-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
    <table class="pg-table">
      <thead>
        <tr>
          <th>แพ็คเก็จ</th>
          <th>ที่มา</th>
          <th class="text-end">เริ่มต้น</th>
          <th class="text-end">คงเหลือ</th>
          <th class="text-end">ใช้ไป</th>
          <th class="text-end">หมดอายุ</th>
        </tr>
      </thead>
      <tbody>
        @forelse($bundles as $b)
          @php
            $used = max(0, (int)$b->credits_initial - (int)$b->credits_remaining);
            $pct  = $b->credits_initial > 0 ? round(($used / $b->credits_initial) * 100) : 0;
            $expired = $b->expires_at && $b->expires_at->isPast();

            $sourcePill = match($b->source) {
              'purchase'     => 'pg-pill--violet',
              'monthly_free' => 'pg-pill--green',
              'grant'        => 'pg-pill--amber',
              'bonus'        => 'pg-pill--violet',
              'refund'       => 'pg-pill--blue',
              default        => 'pg-pill--gray',
            };
          @endphp
          <tr class="{{ $expired ? 'opacity-60' : '' }}">
            <td>
              <div class="font-semibold">{{ $b->package?->name ?? '— (ฟรี / ปรับยอด)' }}</div>
              @if($b->note)
                <div class="text-[11px] text-gray-500 mt-0.5">{{ $b->note }}</div>
              @endif
            </td>
            <td>
              <span class="pg-pill {{ $sourcePill }}">{{ $b->source }}</span>
            </td>
            <td class="text-end is-mono">{{ number_format($b->credits_initial) }}</td>
            <td class="text-end is-mono font-bold {{ $expired ? 'text-gray-400' : 'text-indigo-700' }}">
              {{ number_format($b->credits_remaining) }}
            </td>
            <td class="text-end">
              <div class="flex items-center gap-2 justify-end">
                <div class="w-20 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                  <div class="h-1.5 rounded-full" style="width: {{ $pct }}%;background:linear-gradient(90deg,#6366f1,#7c3aed);"></div>
                </div>
                <span class="text-xs text-gray-500 is-mono">{{ $pct }}%</span>
              </div>
            </td>
            <td class="text-end text-xs">
              @if($b->expires_at)
                <span class="{{ $expired ? 'text-rose-600 font-bold' : ($b->expires_at->diffInDays(now()) < 7 ? 'text-amber-600 font-semibold' : 'text-gray-500') }}">
                  {{ $b->expires_at->format('d M Y') }}
                </span>
              @else
                <span class="text-gray-400">ไม่มีวันหมดอายุ</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6">
              <div class="pg-empty">
                <div class="pg-empty-icon"><i class="bi bi-coin"></i></div>
                <p class="font-medium">ยังไม่มีเครดิตในบัญชี</p>
                <a href="{{ route('photographer.credits.store') }}" class="pg-btn-primary mt-3">
                  <i class="bi bi-bag-plus"></i> เลือกซื้อแพ็คเก็จแรก
                </a>
              </div>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- ─────────────────── Recent transactions ─────────────────── --}}
<div class="pg-card overflow-hidden pg-anim d3">
  <div class="pg-card-header">
    <h5 class="pg-section-title m-0"><i class="bi bi-clock-history"></i> ประวัติล่าสุด</h5>
    <a href="{{ route('photographer.credits.history') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1 no-underline">
      ดูทั้งหมด <i class="bi bi-arrow-right"></i>
    </a>
  </div>
  <ul class="divide-y divide-indigo-100/40">
    @forelse($recentTxns as $tx)
      <li class="px-5 py-3 flex items-center justify-between hover:bg-indigo-50/30 transition">
        <div>
          <div class="text-sm font-semibold text-gray-900">{{ $tx->kind_label }}</div>
          <div class="text-xs text-gray-500 mt-0.5">
            {{ $tx->created_at?->format('d M Y H:i') }}
            @if($tx->reference_type)
              · {{ $tx->reference_type }}{{ $tx->reference_id ? ' #'.$tx->reference_id : '' }}
            @endif
          </div>
        </div>
        <div class="text-right">
          <div class="font-bold is-mono {{ $tx->delta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
            {{ $tx->delta >= 0 ? '+' : '' }}{{ number_format($tx->delta) }}
          </div>
          <div class="text-[11px] text-gray-400">ยอดหลังรายการ: {{ number_format($tx->balance_after) }}</div>
        </div>
      </li>
    @empty
      <li>
        <div class="pg-empty">
          <div class="pg-empty-icon"><i class="bi bi-receipt"></i></div>
          <p class="font-medium">ยังไม่มีประวัติการเคลื่อนไหว</p>
        </div>
      </li>
    @endforelse
  </ul>
</div>
@endsection
