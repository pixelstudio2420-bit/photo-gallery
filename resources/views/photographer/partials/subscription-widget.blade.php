{{--
  Photographer Subscription + Storage Widget — UNIFIED
  ─────────────────────────────────────────────────────
  Single source of truth for "your plan + what you can use".
  Replaces the old quota-widget (tier-based) + subscription-widget (plan-based)
  duo to avoid showing the same storage number twice.

  Shape from SubscriptionService::dashboardSummary($profile):
    enabled, plan, subscription
    storage_used_bytes / storage_quota_bytes / storage_used_gb / storage_quota_gb
    storage_used_pct / storage_warn / storage_critical
    events_used / events_cap / events_unlimited / events_used_pct
    ai_credits_used / ai_credits_cap / ai_credits_remaining / ai_credits_used_pct
    commission_pct / photographer_share_pct
    ai_features
    is_free / has_active_paid / cancel_at_period_end / in_grace
    grace_ends_at / current_period_end / days_until_renewal
--}}
@php
    $show = $subscriptionInfo && ($subscriptionInfo['enabled'] ?? false);
@endphp

@once
<style>
  /* Plan card — matches auth-flow theme (indigo→purple→pink) */
  .plan-card{
    background:#fff;
    border-radius:20px;
    border:1px solid rgba(99,102,241,.08);
    box-shadow:0 4px 20px -4px rgba(99,102,241,.08), 0 1px 3px rgba(0,0,0,.04);
    overflow:hidden;
    position:relative;
  }
  html.dark .plan-card{
    background:#0f172a;
    border-color:rgba(255,255,255,.06);
    box-shadow:0 4px 20px -4px rgba(0,0,0,.4), 0 1px 3px rgba(0,0,0,.2);
  }
  .plan-card-header{
    background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 45%,#ec4899 100%);
    padding:1.25rem 1.5rem 2.5rem;
    color:#fff;
    position:relative;
    overflow:hidden;
  }
  .plan-card-header::before{
    content:'';position:absolute;right:-30px;top:-30px;
    width:120px;height:120px;border-radius:50%;
    background:radial-gradient(circle,rgba(255,255,255,.2),transparent 70%);
    pointer-events:none;
  }
  .plan-card-header::after{
    content:'';position:absolute;left:30%;bottom:-40px;
    width:140px;height:140px;border-radius:50%;
    background:radial-gradient(circle,rgba(255,255,255,.12),transparent 70%);
    pointer-events:none;
  }
  .plan-card-body{
    padding:1.25rem 1.5rem 1.5rem;
    margin-top:-1.5rem;
    position:relative;
    z-index:1;
    /* Explicit bg so we're never transparent over the gradient header
       in dark mode (where `inherit` resolves to whatever parent bg is). */
    background:#fff;
    border-radius:20px 20px 0 0;
  }
  html.dark .plan-card-body{
    background:#0f172a;
  }
  .plan-meter{
    background:rgba(99,102,241,.08);
    border-radius:999px;
    height:8px;
    overflow:hidden;
    position:relative;
  }
  html.dark .plan-meter{ background:rgba(255,255,255,.06); }
  .plan-meter > div{
    height:100%;
    border-radius:999px;
    transition:width .4s cubic-bezier(0.34,1.56,0.64,1);
    position:relative;
  }
  .plan-meter > div::after{
    content:'';position:absolute;inset:0;
    background:linear-gradient(90deg,transparent,rgba(255,255,255,.3),transparent);
    animation:shimmer 2.5s infinite;
  }
  @keyframes shimmer{ 0%{transform:translateX(-100%);} 100%{transform:translateX(100%);} }
  .plan-stat{
    padding:.85rem;border-radius:12px;
    background:linear-gradient(135deg,rgba(99,102,241,.04),rgba(236,72,153,.03));
    border:1px solid rgba(99,102,241,.08);
  }
  html.dark .plan-stat{
    background:linear-gradient(135deg,rgba(99,102,241,.08),rgba(236,72,153,.05));
    border-color:rgba(255,255,255,.06);
  }
  .feature-pill{
    display:inline-flex;align-items:center;gap:.35rem;
    padding:.3rem .6rem;border-radius:8px;
    background:rgba(99,102,241,.08);
    color:#4338ca;
    font-size:.7rem;font-weight:600;
    border:1px solid rgba(99,102,241,.15);
  }
  html.dark .feature-pill{
    background:rgba(99,102,241,.15);
    color:#a5b4fc;
    border-color:rgba(99,102,241,.25);
  }
  .feature-pill i{ font-size:.8rem; }

  .plan-cta{
    background:linear-gradient(135deg,#4f46e5,#7c3aed);
    color:#fff;font-weight:700;
    padding:.65rem 1.25rem;border-radius:10px;
    box-shadow:0 6px 18px -4px rgba(124,58,237,.5);
    transition:transform .15s,box-shadow .2s;
  }
  .plan-cta:hover{
    transform:translateY(-1px);
    box-shadow:0 10px 24px -4px rgba(124,58,237,.65);
    color:#fff;
  }

  .plan-badge-status{
    display:inline-flex;align-items:center;gap:.3rem;
    padding:.2rem .55rem;border-radius:999px;
    font-size:.65rem;font-weight:700;letter-spacing:.05em;
    text-transform:uppercase;
  }
  .plan-badge-grace{ background:rgba(251,191,36,.15);color:#d97706;border:1px solid rgba(251,191,36,.3); }
  html.dark .plan-badge-grace{ background:rgba(251,191,36,.2);color:#fbbf24; }
  .plan-badge-cancel{ background:rgba(239,68,68,.12);color:#dc2626;border:1px solid rgba(239,68,68,.25); }
  html.dark .plan-badge-cancel{ background:rgba(239,68,68,.2);color:#f87171; }
  .plan-badge-active{ background:rgba(16,185,129,.12);color:#059669;border:1px solid rgba(16,185,129,.25); }
  html.dark .plan-badge-active{ background:rgba(16,185,129,.2);color:#34d399; }
</style>
@endonce

@if($show)
    @php
        $plan         = $subscriptionInfo['plan'];
        $sub          = $subscriptionInfo['subscription'];
        $usedGb       = (float) ($subscriptionInfo['storage_used_gb'] ?? 0);
        $quotaGb      = (float) ($subscriptionInfo['storage_quota_gb'] ?? 0);
        $pct          = (float) ($subscriptionInfo['storage_used_pct'] ?? 0);
        $critical     = (bool)  ($subscriptionInfo['storage_critical'] ?? false);
        $warn         = (bool)  ($subscriptionInfo['storage_warn'] ?? false);
        $isFree       = (bool)  ($subscriptionInfo['is_free'] ?? true);
        $inGrace      = (bool)  ($subscriptionInfo['in_grace'] ?? false);
        $cancelAtEnd  = (bool)  ($subscriptionInfo['cancel_at_period_end'] ?? false);
        $daysLeft     = $subscriptionInfo['days_until_renewal'];
        $aiFeatures   = (array) ($subscriptionInfo['ai_features'] ?? []);
        $renewAt      = $subscriptionInfo['current_period_end'] ?? null;
        $aiCreditsUsed   = (int) ($subscriptionInfo['ai_credits_used'] ?? 0);
        $aiCreditsCap    = (int) ($subscriptionInfo['ai_credits_cap'] ?? 0);
        $aiCreditsPct    = (float) ($subscriptionInfo['ai_credits_used_pct'] ?? 0);
        $eventsUsed      = (int) ($subscriptionInfo['events_used'] ?? 0);
        $eventsCap       = $subscriptionInfo['events_cap'] ?? null;
        $eventsUnlimited = (bool) ($subscriptionInfo['events_unlimited'] ?? false);
        $sharePct        = (float) ($subscriptionInfo['photographer_share_pct'] ?? 100);

        $barColor = $critical ? '#ef4444' : ($warn ? '#f59e0b' : '#10b981');

        $featureLabels = [
            'face_search'         => ['bi-person-bounding-box', 'Face Search'],
            'quality_filter'      => ['bi-stars', 'Quality Filter'],
            'duplicate_detection' => ['bi-files', 'Duplicate Detect'],
            'auto_tagging'        => ['bi-tags', 'Auto Tag'],
            'best_shot'           => ['bi-trophy', 'Best Shot'],
            'priority_upload'     => ['bi-lightning-charge', 'Priority Upload'],
            'color_enhance'       => ['bi-palette2', 'Color Enhance'],
            'customer_analytics'  => ['bi-graph-up', 'Customer Analytics'],
            'smart_captions'      => ['bi-chat-quote', 'Smart Captions'],
            'custom_branding'     => ['bi-palette', 'Custom Branding'],
            'video_thumbnails'    => ['bi-play-btn', 'Video Thumbs'],
            'api_access'          => ['bi-key', 'API Access'],
            'white_label'         => ['bi-incognito', 'White Label'],
            'presets'             => ['bi-sliders', 'Lightroom Presets'],
        ];
    @endphp

    <div class="plan-card mb-4">
        {{-- Gradient header — plan name + status badge --}}
        <div class="plan-card-header">
            <div class="flex items-center justify-between gap-3 relative" style="z-index:2;">
                <div class="flex items-center gap-3 min-w-0">
                    {{-- Plan icon — picks the matching bi-* class for the
                         photographer's current plan code (Free=camera,
                         Starter=rocket, Pro=stars, Business=buildings,
                         Studio=gem). Uses the model helper so all surfaces
                         stay in sync. --}}
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-xl shrink-0"
                         style="background:rgba(255,255,255,.18);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.25);">
                        <i class="bi {{ $plan?->iconClass() ?? 'bi-camera' }}"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold tracking-[0.16em] uppercase opacity-80 m-0">แผนสมัครสมาชิก</p>
                        <h3 class="text-xl font-bold m-0 truncate" style="letter-spacing:-0.02em;">{{ $plan?->name ?? 'ฟรี' }}</h3>
                        @if($plan && !$isFree)
                            <p class="text-xs opacity-90 m-0 mt-0.5">฿{{ number_format((float) $plan->price_thb, 0) }}/เดือน</p>
                        @endif
                    </div>
                </div>
                <div class="flex flex-col items-end gap-1.5 shrink-0">
                    @if($inGrace)
                        <span class="plan-badge-status plan-badge-grace"><i class="bi bi-exclamation-triangle-fill"></i> ผ่อนผัน</span>
                    @elseif($cancelAtEnd)
                        <span class="plan-badge-status plan-badge-cancel"><i class="bi bi-clock-history"></i> สิ้นสุดเร็วๆ นี้</span>
                    @else
                        <span class="plan-badge-status plan-badge-active"><i class="bi bi-check-circle-fill"></i> ใช้งาน</span>
                    @endif
                    <a href="{{ route('photographer.subscription.index') }}"
                       class="text-[11px] text-white/90 hover:text-white font-semibold no-underline opacity-80 hover:opacity-100 transition">
                        จัดการ <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="plan-card-body">
            {{-- ─── Storage Meter (single source of truth) ─── --}}
            <div class="mb-4">
                <div class="flex items-baseline justify-between mb-2">
                    <div>
                        <span class="text-[10px] font-bold tracking-[0.16em] uppercase text-gray-500 dark:text-gray-400">
                            <i class="bi bi-hdd-stack mr-1"></i>พื้นที่จัดเก็บ
                        </span>
                    </div>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-base font-bold text-gray-900 dark:text-white" style="font-variant-numeric:tabular-nums;">
                            {{ number_format($usedGb, 2) }}
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">/ {{ number_format($quotaGb, 0) }} GB</span>
                        <span class="text-xs font-semibold ml-2" style="color:{{ $barColor }};">{{ number_format($pct, 1) }}%</span>
                    </div>
                </div>
                <div class="plan-meter">
                    <div style="width:{{ min(100, $pct) }}%;background:linear-gradient(90deg,{{ $barColor }} 0%,{{ $barColor }}cc 100%);"></div>
                </div>
                @if($critical)
                    <p class="text-[11px] mt-2 mb-0 text-rose-600 dark:text-rose-400">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        เต็มเกือบหมดแล้ว — ลบรูปเก่าหรือ
                        <a href="{{ route('photographer.subscription.plans') }}" class="font-semibold underline">อัปเกรดแผน</a>
                    </p>
                @elseif($warn)
                    <p class="text-[11px] mt-2 mb-0 text-amber-600 dark:text-amber-400">
                        <i class="bi bi-info-circle-fill"></i> ใช้พื้นที่ไป {{ number_format($pct, 0) }}% — เริ่มใกล้เต็ม
                    </p>
                @endif
            </div>

            {{-- ─── Plan stats (events / AI credits / commission) ─── --}}
            <div class="grid grid-cols-3 gap-2 mb-4">
                <div class="plan-stat text-center">
                    <p class="text-[10px] font-bold tracking-wider uppercase text-gray-500 dark:text-gray-400 m-0 mb-1">
                        <i class="bi bi-calendar-event"></i> อีเวนต์
                    </p>
                    <p class="font-bold text-sm text-gray-900 dark:text-white m-0" style="font-variant-numeric:tabular-nums;">
                        {{ $eventsUsed }}<span class="text-xs text-gray-400 font-medium">/{{ $eventsUnlimited ? '∞' : $eventsCap }}</span>
                    </p>
                </div>
                <div class="plan-stat text-center">
                    <p class="text-[10px] font-bold tracking-wider uppercase text-gray-500 dark:text-gray-400 m-0 mb-1">
                        <i class="bi bi-cpu"></i> AI Credits
                    </p>
                    <p class="font-bold text-sm text-gray-900 dark:text-white m-0" style="font-variant-numeric:tabular-nums;">
                        {{ number_format($aiCreditsUsed) }}<span class="text-xs text-gray-400 font-medium">/{{ number_format($aiCreditsCap) }}</span>
                    </p>
                </div>
                <div class="plan-stat text-center">
                    <p class="text-[10px] font-bold tracking-wider uppercase text-gray-500 dark:text-gray-400 m-0 mb-1">
                        <i class="bi bi-cash-coin"></i> รับเงิน
                    </p>
                    <p class="font-bold text-sm text-gray-900 dark:text-white m-0">
                        {{ rtrim(rtrim(number_format($sharePct, 1), '0'), '.') }}<span class="text-xs text-gray-400 font-medium">%</span>
                    </p>
                </div>
            </div>

            {{-- ─── AI Features (only show if any unlocked) ─── --}}
            @if(count($aiFeatures) > 0)
                <div class="mb-4">
                    <p class="text-[10px] font-bold tracking-[0.16em] uppercase text-gray-500 dark:text-gray-400 mb-2">
                        <i class="bi bi-magic mr-1"></i>ฟีเจอร์ที่ปลดล็อก ({{ count($aiFeatures) }})
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($aiFeatures as $featureKey)
                            @php $f = $featureLabels[$featureKey] ?? null; @endphp
                            @if($f)
                                <span class="feature-pill"><i class="bi {{ $f[0] }}"></i>{{ $f[1] }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ─── Renewal info + CTA ─── --}}
            <div class="flex items-center justify-between gap-3 pt-3 border-t border-gray-100 dark:border-white/[0.06]">
                <div class="text-[11px] text-gray-500 dark:text-gray-400">
                    @if($renewAt && !$isFree)
                        <i class="bi bi-arrow-clockwise"></i>
                        @if($cancelAtEnd)
                            สิ้นสุด: <strong class="text-gray-700 dark:text-gray-200">{{ \Illuminate\Support\Carbon::parse($renewAt)->format('d M Y') }}</strong>
                        @else
                            ต่ออายุ: <strong class="text-gray-700 dark:text-gray-200">{{ \Illuminate\Support\Carbon::parse($renewAt)->format('d M Y') }}</strong>
                            @if($daysLeft !== null)
                                <span class="text-gray-400">(อีก {{ $daysLeft }} วัน)</span>
                            @endif
                        @endif
                    @else
                        <i class="bi bi-info-circle"></i> แผนพื้นฐาน — ใช้งานได้ไม่จำกัดเวลา
                    @endif
                </div>
                @if($isFree)
                    <a href="{{ route('photographer.subscription.plans') }}" class="plan-cta no-underline text-xs">
                        <i class="bi bi-rocket-takeoff"></i> อัปเกรด
                    </a>
                @else
                    <a href="{{ route('photographer.subscription.plans') }}" class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 no-underline">
                        เปลี่ยนแผน <i class="bi bi-arrow-right"></i>
                    </a>
                @endif
            </div>
        </div>
    </div>
@endif
