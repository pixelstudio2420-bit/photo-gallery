{{--
  Photographer Storage Quota Widget
  ──────────────────────────────────
  Expects $quotaInfo (array|null) passed down from the controller.
  If $quotaInfo is null (service down or photographer not approved),
  this partial renders nothing — caller never needs to guard it.

  $quotaInfo shape:
    enabled        bool     — master enforcement toggle
    tier           string   — creator | seller | pro
    used_bytes     int      — current usage (original bytes, ×3.0 multiplier applied)
    quota_bytes    int      — limit for this profile (0 = unlimited)
    percent        float    — used/quota × 100 (0 when unlimited)
    used_human     string   — "2.4 GB"
    quota_human    string   — "5 GB"
    warn_threshold int      — percentage at which the bar turns amber
    savings        array    — result of StorageQuotaService::upgradeSavings($tier)

  Color bands:
    < warn_threshold      → teal  (#14b8a6)
    warn_threshold–89%    → amber (#f59e0b)
    ≥ 90%                 → red   (#ef4444)
--}}

@if($quotaInfo && $quotaInfo['enabled'])
  @php
    $percent = (float) $quotaInfo['percent'];
    $unlimited = ($quotaInfo['quota_bytes'] ?? 0) === 0;
    $warnAt = (int) ($quotaInfo['warn_threshold'] ?? 80);
    $barColor = $percent >= 90 ? '#ef4444'
              : ($percent >= $warnAt ? '#f59e0b' : '#14b8a6');
    $bgColor  = $percent >= 90 ? 'rgba(239,68,68,0.08)'
              : ($percent >= $warnAt ? 'rgba(245,158,11,0.08)' : 'rgba(20,184,166,0.08)');
    $textColor = $percent >= 90 ? '#dc2626'
               : ($percent >= $warnAt ? '#d97706' : '#0f766e');

    $tierLabels = [
        'creator' => ['label' => 'CREATOR', 'color' => '#6366f1', 'bg' => 'rgba(99,102,241,0.12)'],
        'seller'  => ['label' => 'SELLER',  'color' => '#059669', 'bg' => 'rgba(16,185,129,0.12)'],
        'pro'     => ['label' => 'PRO',     'color' => '#d97706', 'bg' => 'rgba(245,158,11,0.12)'],
    ];
    $tier = $tierLabels[$quotaInfo['tier']] ?? $tierLabels['creator'];
    $savings = $quotaInfo['savings'] ?? [];
  @endphp

  <div class="card border-0 mb-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="p-5">
      {{-- Header row --}}
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
          <i class="bi bi-hdd-stack text-xl" style="color:{{ $barColor }};"></i>
          <h6 class="font-semibold mb-0 text-sm">พื้นที่เก็บข้อมูล</h6>
          <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                style="background:{{ $tier['bg'] }}; color:{{ $tier['color'] }};">
            {{ $tier['label'] }}
          </span>
        </div>
        <a href="{{ route('photographer.profile') }}" class="text-[11px] text-gray-400 hover:text-indigo-500">
          <i class="bi bi-gear"></i>
        </a>
      </div>

      {{-- Usage numbers --}}
      <div class="flex items-baseline justify-between mb-2">
        <div>
          <span class="text-2xl font-bold" style="color:{{ $textColor }};">{{ $quotaInfo['used_human'] }}</span>
          @if(!$unlimited)
            <span class="text-sm text-gray-400 ml-1">/ {{ $quotaInfo['quota_human'] }}</span>
          @else
            <span class="text-sm text-gray-400 ml-1">/ ไม่จำกัด</span>
          @endif
        </div>
        @if(!$unlimited)
          <span class="text-sm font-semibold" style="color:{{ $textColor }};">
            {{ number_format($percent, 1) }}%
          </span>
        @endif
      </div>

      {{-- Progress bar --}}
      @if(!$unlimited)
        <div class="w-full h-2 rounded-full overflow-hidden mb-3"
             style="background:rgba(148,163,184,0.15);">
          <div class="h-full rounded-full transition-all"
               style="width:{{ min(100, $percent) }}%; background:{{ $barColor }};"></div>
        </div>
      @endif

      {{-- Warnings --}}
      @if(!$unlimited && $percent >= 90)
        <div class="p-3 rounded-lg mb-3 text-xs" style="background:rgba(239,68,68,0.08); color:#dc2626;">
          <i class="bi bi-exclamation-triangle-fill mr-1"></i>
          <strong>พื้นที่ใกล้เต็ม!</strong> การอัพโหลดรูปใหม่อาจถูกปฏิเสธ — ลบรูปเก่าหรืออัปเกรดแพ็คเก็จเพื่อใช้ต่อ
        </div>
      @elseif(!$unlimited && $percent >= $warnAt)
        <div class="p-3 rounded-lg mb-3 text-xs" style="background:{{ $bgColor }}; color:{{ $textColor }};">
          <i class="bi bi-info-circle mr-1"></i>
          พื้นที่ใช้งานเกิน {{ $warnAt }}% แล้ว — ลองอัปเกรดแพ็คเก็จเพื่อใช้งานสะดวกขึ้น
        </div>
      @endif

      {{-- Upgrade savings — the killer feature that makes photographers want to upgrade --}}
      @if(!empty($savings) && $quotaInfo['tier'] !== 'pro')
        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-white/10">
          <div class="text-[11px] text-gray-500 mb-2 flex items-center gap-1">
            <i class="bi bi-graph-up-arrow" style="color:#10b981;"></i>
            <span class="font-semibold">ถ้าอัปเกรด คุณจะประหยัดเท่าไหร่?</span>
          </div>
          <div class="space-y-2">
            @foreach($savings as $upgradeTier => $s)
              @php
                $upgradeLabel = $tierLabels[$upgradeTier] ?? $tierLabels['creator'];
              @endphp
              <div class="p-3 rounded-lg hover:ring-2 hover:ring-indigo-200 transition"
                   style="background:{{ $upgradeLabel['bg'] }};">
                <div class="flex items-center justify-between mb-1">
                  <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                          style="background:{{ $upgradeLabel['color'] }}; color:#fff;">
                      {{ $upgradeLabel['label'] }}
                    </span>
                    <span class="text-[11px] text-gray-500">
                      ฿{{ number_format($s['sub_cost']) }}/เดือน
                    </span>
                  </div>
                  <span class="text-[11px] font-semibold" style="color:{{ $upgradeLabel['color'] }};">
                    ค่าคอมลดเหลือ {{ number_format($s['new_commission_pct'], 0) }}%
                    <span class="text-[10px] font-normal text-gray-400">(จาก {{ number_format($s['current_commission_pct'], 0) }}%)</span>
                  </span>
                </div>
                <div class="text-[11px] text-gray-600 dark:text-gray-300 space-y-0.5">
                  @if($s['commission_saved_per_1000b'] > 0)
                    <div>
                      <i class="bi bi-cash-coin text-emerald-500"></i>
                      ประหยัด <strong>฿{{ number_format($s['commission_saved_per_1000b']) }}</strong> ต่อทุกๆ ฿1,000 ที่ขายได้
                    </div>
                  @endif
                  @if($s['fee_saved_per_photo'] > 0)
                    <div>
                      <i class="bi bi-image text-indigo-500"></i>
                      ประหยัดค่าธรรมเนียมอีก <strong>฿{{ $s['fee_saved_per_photo'] }}</strong> ต่อภาพ
                    </div>
                  @endif
                  @if($s['breakeven_photos_at_500baht'] > 0)
                    <div class="text-amber-600 dark:text-amber-400">
                      <i class="bi bi-calculator"></i>
                      ขายภาพ ฿500 เพียง <strong>{{ $s['breakeven_photos_at_500baht'] }} ภาพ/เดือน</strong> ก็คืนทุนแล้ว
                    </div>
                  @endif
                </div>
              </div>
            @endforeach
          </div>
          <a href="{{ route('photographer.profile') }}#upgrade"
             class="block text-center mt-3 py-2 rounded-lg text-xs font-semibold transition"
             style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;">
            <i class="bi bi-arrow-up-circle mr-1"></i> ดูรายละเอียดแพ็คเก็จ
          </a>
        </div>
      @endif
    </div>
  </div>
@endif
