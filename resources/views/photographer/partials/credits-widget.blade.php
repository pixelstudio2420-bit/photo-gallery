{{--
  Photographer Upload Credits Widget
  ──────────────────────────────────
  Expects $creditsInfo (array|null) passed down from the controller, produced
  by CreditService::dashboardSummary($profile).

  Renders NOTHING when:
    • $creditsInfo is null (service unavailable / no profile)
    • $creditsInfo['enabled'] is false (system globally off)
    • photographer's billing_mode is not 'credits' (commission-mode creators
      see nothing — they've got the storage-quota widget doing the same job)

  Shape of $creditsInfo:
    enabled       bool
    billing_mode  string                 — 'credits' | 'commission'
    balance       int                    — current non-expired credit count
    next_expiring array|null             — { credits, expires_at, days_left, warn_soon }
    last_purchase datetime|null
--}}

@if($creditsInfo && ($creditsInfo['enabled'] ?? false) && ($creditsInfo['billing_mode'] ?? null) === 'credits')
  @php
    $balance = (int) ($creditsInfo['balance'] ?? 0);
    $next    = $creditsInfo['next_expiring'] ?? null;
    $lowBal  = $balance <= 10;
    $warnBar = $lowBal ? '#ef4444' : '#6366f1';
    $warnBg  = $lowBal ? 'rgba(239,68,68,0.08)' : 'rgba(99,102,241,0.08)';
  @endphp

  <div class="card border-0 mb-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="p-5">
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
          <i class="bi bi-coin text-xl" style="color:{{ $warnBar }};"></i>
          <h6 class="font-semibold mb-0 text-sm">เครดิตอัปโหลด</h6>
          <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-500/10 text-indigo-700">CREDITS MODE</span>
        </div>
        <a href="{{ route('photographer.credits.index') }}" class="text-[11px] text-gray-400 hover:text-indigo-500">
          ดูทั้งหมด <i class="bi bi-arrow-right"></i>
        </a>
      </div>

      <div class="flex items-baseline justify-between mb-3">
        <div>
          <span class="text-3xl font-bold" style="color:{{ $warnBar }};">{{ number_format($balance) }}</span>
          <span class="text-sm text-gray-400 ml-1">เครดิต</span>
        </div>
        @if($next)
          <div class="text-right">
            <div class="text-[10px] text-gray-400 uppercase tracking-wider">ใกล้หมดอายุ</div>
            <div class="text-xs font-medium {{ ($next['warn_soon'] ?? false) ? 'text-rose-600' : 'text-gray-600' }}">
              {{ number_format($next['credits']) }} หน่วย · {{ (int) ($next['days_left'] ?? 0) }} วัน
            </div>
          </div>
        @endif
      </div>

      @if($lowBal)
        <div class="p-3 rounded-lg mb-3 text-xs" style="background:{{ $warnBg }}; color:#dc2626;">
          <i class="bi bi-exclamation-triangle-fill mr-1"></i>
          เครดิตเหลือน้อย — ซื้อแพ็คเก็จเพิ่มเพื่อไม่ให้อัปโหลดสะดุด
        </div>
      @endif

      <div class="grid grid-cols-2 gap-2 mt-2">
        <a href="{{ route('photographer.credits.store') }}"
           class="text-center py-2 rounded-lg text-xs font-semibold transition"
           style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;">
          <i class="bi bi-bag-plus mr-1"></i> ซื้อเครดิต
        </a>
        <a href="{{ route('photographer.credits.history') }}"
           class="text-center py-2 rounded-lg text-xs font-semibold border border-gray-200 hover:bg-gray-50">
          <i class="bi bi-clock-history mr-1"></i> ประวัติการใช้
        </a>
      </div>
    </div>
  </div>
@endif
