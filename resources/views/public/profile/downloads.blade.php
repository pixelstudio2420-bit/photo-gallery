@extends('layouts.app')

@section('title', 'ประวัติดาวน์โหลด')

{{-- ===========================================================================
     PROFILE · DOWNLOADS  (redesigned)
     ─────────────────────────────────────────────────────────────────────────
     Visual goals:
       • Hero with title + 4 stat cards so the user sees usage at a glance.
       • Filter chips bound to ?status=… (active / expiring / expired / all).
       • Grid of cards with PHOTO PREVIEWS pulled from order_items thumbnails
         instead of the generic icon — buyers recognise their shoots.
       • Custom pagination view (vendor.pagination.loadroop) replacing
         Laravel's default plain text pager.
     Controller (ProfileController::downloads) provides:
       $stats             { total, active, expiring, expired }
       $status            current filter value (string)
       $photoLookup       EventPhoto rows by id (per-photo tokens)
       $firstItemByOrder  first OrderItem per order (all-photos tokens)
   ====================================================================== --}}
@section('content')

<div class="max-w-6xl mx-auto px-4 md:px-6 py-6 md:py-8">

  {{-- ════════════════════════════════════════════════════════════════════
       HERO HEADER — title + stats
       (Replaces the small inline header. The gradient bar gives the page
       a clear identity that matches the blue-cyan accent colour we use
       for downloads everywhere else.)
       ════════════════════════════════════════════════════════════════════ --}}
  <div class="relative overflow-hidden rounded-3xl mb-6
              bg-gradient-to-br from-blue-500 via-cyan-500 to-teal-500
              text-white shadow-xl shadow-blue-500/20 p-5 md:p-7">

    {{-- Decorative blurred dots — pure CSS --}}
    <div class="absolute -top-16 -right-16 w-56 h-56 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-20 -left-10 w-64 h-64 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="relative">
      <div class="flex items-start justify-between gap-3 flex-wrap mb-4 md:mb-5">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-xs font-bold mb-2">
            <i class="bi bi-cloud-download"></i>
            <span>Downloads Center</span>
          </div>
          <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">ประวัติดาวน์โหลด</h1>
          <p class="text-sm md:text-[15px] text-white/85 mt-1">
            จัดการลิงก์ดาวน์โหลดทั้งหมดของคุณ — ดาวน์โหลดซ้ำ ตรวจสอบวันหมดอายุ และจำนวนครั้งที่เหลือ
          </p>
        </div>
        <a href="{{ route('profile.orders') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-white/15 hover:bg-white/25 backdrop-blur-sm text-white text-sm font-semibold transition shrink-0">
          <i class="bi bi-receipt"></i> ดูคำสั่งซื้อ
        </a>
      </div>

      {{-- Stats grid (4 cards) --}}
      <div class="grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-3">
        @foreach([
          ['key'=>'total',    'label'=>'ทั้งหมด',         'icon'=>'bi-collection',    'value'=>$stats['total'] ?? 0,    'tone'=>'white'],
          ['key'=>'active',   'label'=>'ใช้งานได้',        'icon'=>'bi-lightning-fill','value'=>$stats['active'] ?? 0,   'tone'=>'emerald'],
          ['key'=>'expiring', 'label'=>'ใกล้หมด (24 ชม.)','icon'=>'bi-hourglass-split','value'=>$stats['expiring'] ?? 0, 'tone'=>'amber'],
          ['key'=>'expired',  'label'=>'หมดอายุ/ครบ',     'icon'=>'bi-archive',       'value'=>$stats['expired'] ?? 0,  'tone'=>'rose'],
        ] as $s)
          <div class="rounded-2xl bg-white/15 hover:bg-white/20 backdrop-blur-sm border border-white/15 p-3 md:p-4 transition">
            <div class="flex items-center justify-between gap-2">
              <div class="text-[11px] md:text-xs font-semibold uppercase tracking-wider text-white/80 truncate">
                {{ $s['label'] }}
              </div>
              <i class="bi {{ $s['icon'] }} text-base md:text-lg
                  @if($s['tone']==='emerald') text-emerald-200
                  @elseif($s['tone']==='amber') text-amber-200
                  @elseif($s['tone']==='rose') text-rose-200
                  @else text-white/90 @endif"></i>
            </div>
            <div class="mt-1.5 md:mt-2 text-2xl md:text-3xl font-extrabold leading-none">
              {{ number_format($s['value']) }}
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════════════════
       Tab Navigation (profile sub-pages) — kept from previous design,
       slightly polished
       ════════════════════════════════════════════════════════════════════ --}}
  <div class="mb-5 flex items-center gap-1 overflow-x-auto pb-1 border-b border-slate-200 dark:border-white/10" style="scrollbar-width:none;">
    @foreach([
      ['route' => route('profile'),           'label' => 'ภาพรวม',       'icon' => 'bi-grid',       'active' => false],
      ['route' => route('profile.orders'),    'label' => 'คำสั่งซื้อ',    'icon' => 'bi-receipt',    'active' => false],
      ['route' => route('profile.downloads'), 'label' => 'ดาวน์โหลด',     'icon' => 'bi-download',   'active' => true],
      ['route' => route('profile.reviews'),   'label' => 'รีวิว',          'icon' => 'bi-star',       'active' => false],
      ['route' => route('wishlist.index'),    'label' => 'รายการโปรด',  'icon' => 'bi-heart',      'active' => false],
      ['route' => route('profile.referrals'), 'label' => 'แนะนำเพื่อน',   'icon' => 'bi-people-fill','active' => false],
    ] as $tab)
      <a href="{{ $tab['route'] }}"
         class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 whitespace-nowrap transition shrink-0
            {{ $tab['active']
                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
        <i class="bi {{ $tab['icon'] }}"></i> {{ $tab['label'] }}
      </a>
    @endforeach
  </div>

  {{-- ════════════════════════════════════════════════════════════════════
       Filter chips — narrow the grid by status. URL-driven so the
       choice survives reload + back/forward + share. Hidden when the
       user has no downloads at all.
       ════════════════════════════════════════════════════════════════════ --}}
  @if(($stats['total'] ?? 0) > 0)
    <div class="mb-5 flex items-center gap-2 overflow-x-auto pb-1" style="scrollbar-width:none;">
      @php
        $filters = [
          ['key'=>'all',      'label'=>'ทั้งหมด',      'icon'=>'bi-collection',     'count'=>$stats['total'] ?? 0,    'color'=>'blue'],
          ['key'=>'active',   'label'=>'ใช้งานได้',     'icon'=>'bi-lightning-fill', 'count'=>$stats['active'] ?? 0,   'color'=>'emerald'],
          ['key'=>'expiring', 'label'=>'ใกล้หมด',      'icon'=>'bi-hourglass-split','count'=>$stats['expiring'] ?? 0, 'color'=>'amber'],
          ['key'=>'expired',  'label'=>'หมดอายุ',     'icon'=>'bi-clock-history',  'count'=>$stats['expired'] ?? 0,  'color'=>'rose'],
        ];
      @endphp
      @foreach($filters as $f)
        @php $isActive = ($status ?? 'all') === $f['key']; @endphp
        <a href="{{ route('profile.downloads', $f['key'] === 'all' ? [] : ['status' => $f['key']]) }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap transition shrink-0 border-[1.5px]
              {{ $isActive
                  ? 'bg-gradient-to-r from-blue-500 to-cyan-500 text-white border-transparent shadow-md shadow-blue-500/25'
                  : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-white/10 hover:border-blue-300 dark:hover:border-blue-500/40 hover:text-blue-600 dark:hover:text-blue-300' }}">
          <i class="bi {{ $f['icon'] }}"></i>
          <span>{{ $f['label'] }}</span>
          <span class="inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 text-[11px] font-bold rounded-full
              {{ $isActive ? 'bg-white/25 text-white' : 'bg-slate-100 dark:bg-white/10 text-slate-500 dark:text-slate-400' }}">
            {{ number_format($f['count']) }}
          </span>
        </a>
      @endforeach
    </div>
  @endif

  {{-- ════════════════════════════════════════════════════════════════════
       Empty state — shown when the user has nothing OR when the filter
       has narrowed everything out. We branch on $stats['total'] so the
       empty CTA only points to /events when the user has no downloads
       at all (otherwise we suggest clearing the filter).
       ════════════════════════════════════════════════════════════════════ --}}
  @if($downloads->isEmpty())
    <div class="rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm text-center py-16 px-6">
      <div class="inline-flex items-center justify-center w-24 h-24 rounded-3xl bg-gradient-to-br from-blue-100 to-cyan-100 dark:from-blue-500/20 dark:to-cyan-500/20 text-blue-500 dark:text-blue-400 mb-5 shadow-inner">
        <i class="bi bi-cloud-download text-4xl"></i>
      </div>
      @if(($stats['total'] ?? 0) === 0)
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2">ยังไม่มีประวัติดาวน์โหลด</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6 max-w-sm mx-auto">
          ลิงก์ดาวน์โหลดจะปรากฏที่นี่อัตโนมัติหลังจากชำระเงินสำเร็จ — ลิงก์มีอายุ 30 วัน ดาวน์โหลดได้สูงสุด 5 ครั้งต่อรูป
        </p>
        <a href="{{ route('events.index') }}"
           class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-gradient-to-r from-blue-500 to-cyan-500 hover:from-blue-600 hover:to-cyan-600 text-white text-sm font-bold shadow-md shadow-blue-500/25 transition active:scale-[0.98]">
          <i class="bi bi-camera"></i> เริ่มเลือกซื้อรูปภาพ
        </a>
      @else
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2">ไม่มีรายการในหมวดนี้</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">
          ลองเปลี่ยนตัวกรองเพื่อดูรายการอื่น
        </p>
        <a href="{{ route('profile.downloads') }}"
           class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/15 text-slate-700 dark:text-slate-200 text-sm font-semibold transition">
          <i class="bi bi-arrow-counterclockwise"></i> ดูทั้งหมด
        </a>
      @endif
    </div>
  @else

    {{-- ════════════════════════════════════════════════════════════════
         CARDS GRID
         ════════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      @foreach($downloads as $dl)
        @php
          $isExpired   = $dl->expires_at && $dl->expires_at->isPast();
          $limitHit    = $dl->max_downloads && $dl->download_count >= $dl->max_downloads;
          $isActive    = !$isExpired && !$limitHit;
          $progress    = $dl->max_downloads ? min(100, round(($dl->download_count / $dl->max_downloads) * 100)) : 0;
          $expiringSoon= $isActive && $dl->expires_at && $dl->expires_at->diffInHours(now(), false) > -24;

          // Resolve a thumbnail for the card hero. Per-photo tokens use
          // their own photo; all-photos tokens use the first OrderItem.
          $thumbUrl = '';
          if ($dl->photo_id && is_numeric($dl->photo_id)) {
              $p = ($photoLookup ?? collect())->get((int) $dl->photo_id);
              $thumbUrl = $p?->thumbnail_url ?? '';
          }
          if (!$thumbUrl && $dl->order_id) {
              $oi = $firstItemByOrder[$dl->order_id] ?? null;
              $thumbUrl = (string) ($oi->thumbnail_url ?? '');
          }

          // Card tone: live / warning / faded
          $accentGrad = $isActive
              ? ($expiringSoon ? 'from-amber-500 to-orange-500' : 'from-blue-500 to-cyan-500')
              : 'from-slate-400 to-slate-500';
        @endphp

        <article class="group relative rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden
                        hover:shadow-xl hover:-translate-y-1 transition-all duration-300
                        {{ $isActive ? '' : 'opacity-90' }}">

          {{-- ── Photo preview band ──
               Real thumbnail when available (per-photo token or first item),
               otherwise a gradient block matching the card tone. The fade
               at the bottom keeps the badge readable across photos. --}}
          <div class="relative aspect-[16/9] overflow-hidden bg-gradient-to-br {{ $accentGrad }}">
            @if($thumbUrl)
              <img src="{{ $thumbUrl }}" alt="preview" loading="lazy"
                   class="absolute inset-0 w-full h-full object-cover
                          group-hover:scale-105 transition-transform duration-500
                          {{ $isActive ? '' : 'grayscale' }}"
                   onerror="this.remove();">
              {{-- Subtle dark gradient at the bottom so the status badge
                   stays legible on busy photos --}}
              <div class="absolute inset-0 bg-gradient-to-t from-black/35 via-transparent to-transparent pointer-events-none"></div>
            @else
              {{-- No thumbnail: show a big icon centred on the gradient --}}
              <div class="absolute inset-0 flex items-center justify-center">
                <i class="bi bi-images text-white/80 text-5xl drop-shadow-md"></i>
              </div>
            @endif

            {{-- Status badge (top-right) --}}
            <div class="absolute top-2.5 right-2.5">
              @if($isActive && !$expiringSoon)
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/95 text-white text-[10px] font-bold backdrop-blur-sm shadow-md">
                  <span class="relative flex h-1.5 w-1.5">
                    <span class="absolute inline-flex h-full w-full rounded-full bg-white opacity-75 animate-ping"></span>
                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-white"></span>
                  </span>
                  Active
                </span>
              @elseif($expiringSoon)
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-500/95 text-white text-[10px] font-bold backdrop-blur-sm shadow-md">
                  <i class="bi bi-hourglass-split"></i> ใกล้หมด
                </span>
              @elseif($isExpired)
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-500/95 text-white text-[10px] font-bold backdrop-blur-sm shadow-md">
                  <i class="bi bi-clock-fill"></i> หมดอายุ
                </span>
              @else
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-700/90 text-white text-[10px] font-bold backdrop-blur-sm shadow-md">
                  <i class="bi bi-check-all"></i> ครบแล้ว
                </span>
              @endif
            </div>

            {{-- Token-type badge (top-left) — informs the buyer this is
                 the all-photos token (single ZIP) vs a single-photo link --}}
            <div class="absolute top-2.5 left-2.5">
              @if($dl->photo_id)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-black/40 text-white text-[10px] font-semibold backdrop-blur-sm">
                  <i class="bi bi-image"></i> รูปเดี่ยว
                </span>
              @else
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-black/40 text-white text-[10px] font-semibold backdrop-blur-sm">
                  <i class="bi bi-archive"></i> ทั้งหมด
                </span>
              @endif
            </div>
          </div>

          {{-- ── Card body ── --}}
          <div class="p-4">
            <div class="font-bold text-[15px] text-slate-900 dark:text-white truncate" title="{{ $dl->order->event->name ?? 'รูปภาพ' }}">
              {{ $dl->order->event->name ?? 'รูปภาพ' }}
            </div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-0.5 truncate flex items-center gap-1">
              <i class="bi bi-receipt"></i>
              @if($dl->order)
                <a href="{{ route('orders.show', $dl->order->id) }}"
                   class="hover:text-blue-600 dark:hover:text-blue-400 transition">
                  #{{ $dl->order->order_number ?? $dl->order_id }}
                </a>
              @else
                #{{ $dl->order_id }}
              @endif
            </div>

            {{-- Usage progress (or unlimited badge) --}}
            <div class="mt-3">
              @if($dl->max_downloads)
                <div class="flex items-center justify-between text-[11px] mb-1.5">
                  <span class="text-slate-500 dark:text-slate-400">การใช้งาน</span>
                  <span class="font-bold text-slate-700 dark:text-slate-200">
                    {{ $dl->download_count }}<span class="text-slate-400 dark:text-slate-500"> / </span>{{ $dl->max_downloads }} ครั้ง
                  </span>
                </div>
                <div class="h-2 rounded-full bg-slate-100 dark:bg-white/[0.05] overflow-hidden">
                  <div class="h-full rounded-full bg-gradient-to-r {{ $accentGrad }} transition-all duration-500"
                       style="width: {{ $progress }}%;"></div>
                </div>
              @else
                <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                  <i class="bi bi-infinity"></i>
                  ไม่จำกัดครั้ง · ใช้ไปแล้ว {{ $dl->download_count }} ครั้ง
                </div>
              @endif
            </div>

            {{-- Expiry --}}
            <div class="mt-2 flex items-center gap-1.5 text-[11px]
                {{ $isExpired ? 'text-rose-600 dark:text-rose-400' : ($expiringSoon ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400') }}">
              <i class="bi {{ $dl->expires_at ? 'bi-clock-history' : 'bi-infinity' }}"></i>
              @if($dl->expires_at)
                <span>{{ $isExpired ? 'หมดอายุเมื่อ' : 'หมดอายุ' }}</span>
                <span class="font-semibold">{{ $dl->expires_at->format('d/m/Y H:i') }}</span>
                @if(!$isExpired)
                  <span class="text-slate-400 dark:text-slate-500">· {{ $dl->expires_at->diffForHumans() }}</span>
                @endif
              @else
                ไม่มีวันหมดอายุ
              @endif
            </div>

            {{-- Action --}}
            <div class="mt-4">
              @if($isActive)
                <a href="{{ route('download.show', $dl->token) }}"
                   class="inline-flex w-full items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl
                          bg-gradient-to-r {{ $accentGrad }}
                          hover:shadow-lg hover:shadow-blue-500/30
                          active:scale-[0.98] text-white text-sm font-bold transition">
                  <i class="bi bi-download"></i>
                  {{ $expiringSoon ? 'ดาวน์โหลดก่อนหมดเวลา' : 'ดาวน์โหลดรูปภาพ' }}
                  <i class="bi bi-arrow-right ml-0.5"></i>
                </a>
              @else
                <button type="button" disabled
                        class="inline-flex w-full items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl
                               bg-slate-100 dark:bg-white/[0.04] text-slate-400 dark:text-slate-500 text-sm font-medium cursor-not-allowed">
                  <i class="bi {{ $isExpired ? 'bi-clock-history' : 'bi-check-all' }}"></i>
                  {{ $isExpired ? 'ลิงก์หมดอายุแล้ว' : 'ดาวน์โหลดครบจำนวน' }}
                </button>
              @endif
            </div>
          </div>
        </article>
      @endforeach
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         Pagination — uses the new vendor.pagination.loadroop view
         ════════════════════════════════════════════════════════════════ --}}
    {{ $downloads->withQueryString()->links('vendor.pagination.loadroop') }}
  @endif

</div>
@endsection
