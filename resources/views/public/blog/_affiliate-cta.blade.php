{{--
  Reusable Affiliate CTA Block
  @param $cta - BlogCtaButton model instance
  Styles: gradient-red, gradient-blue, gradient-green, gradient-amber, minimal
--}}
@if(isset($cta) && $cta && $cta->is_active)
@php
  $style = $cta->style ?? 'gradient-blue';
  $link  = $cta->url ?? ($cta->affiliateLink ? $cta->affiliateLink->getCloakedUrl() : '#');

  $styles = [
    'gradient-red' => [
      'bg'    => 'bg-gradient-to-r from-red-500 via-orange-500 to-amber-500',
      'ring'  => 'border-red-200 dark:border-red-400/30',
      'outer' => 'bg-gradient-to-br from-red-50 to-orange-50 dark:from-red-950/40 dark:to-orange-950/40',
      'btn'   => 'bg-gradient-to-br from-red-500 to-orange-600 hover:from-red-600 hover:to-orange-700 shadow-lg shadow-red-500/30 hover:shadow-red-500/50',
      'anim'  => 'animate-pulse',
      'icon'  => $cta->icon ?? '',
    ],
    'gradient-blue' => [
      'bg'    => 'bg-gradient-to-r from-blue-500 to-indigo-600',
      'ring'  => 'border-blue-200 dark:border-blue-400/30',
      'outer' => 'bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-950/40 dark:to-indigo-950/40',
      'btn'   => 'bg-gradient-to-br from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 shadow-lg shadow-blue-500/30 hover:shadow-blue-500/50',
      'anim'  => '',
      'icon'  => $cta->icon ?? '',
    ],
    'gradient-green' => [
      'bg'    => 'bg-gradient-to-r from-emerald-500 to-green-600',
      'ring'  => 'border-emerald-200 dark:border-emerald-400/30',
      'outer' => 'bg-gradient-to-br from-emerald-50 to-green-50 dark:from-emerald-950/40 dark:to-green-950/40',
      'btn'   => 'bg-gradient-to-br from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 shadow-lg shadow-emerald-500/30 hover:shadow-emerald-500/50',
      'anim'  => '',
      'icon'  => $cta->icon ?? '',
    ],
    'gradient-amber' => [
      'bg'    => 'bg-gradient-to-r from-amber-500 to-yellow-500',
      'ring'  => 'border-amber-200 dark:border-amber-400/30',
      'outer' => 'bg-gradient-to-br from-amber-50 to-yellow-50 dark:from-amber-950/40 dark:to-yellow-950/40',
      'btn'   => 'bg-gradient-to-br from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 shadow-lg shadow-amber-500/30 hover:shadow-amber-500/50',
      'anim'  => 'cta-shake',
      'icon'  => $cta->icon ?? '',
    ],
    'minimal' => [
      'bg'    => 'bg-gradient-to-r from-gray-500 to-slate-600',
      'ring'  => 'border-gray-200 dark:border-white/10',
      'outer' => 'bg-white dark:bg-slate-800',
      'btn'   => 'bg-gradient-to-br from-slate-700 to-slate-900 hover:from-slate-800 hover:to-black dark:from-indigo-500 dark:to-violet-600 dark:hover:from-indigo-600 dark:hover:to-violet-700 shadow-lg shadow-gray-500/20 hover:shadow-gray-500/40',
      'anim'  => '',
      'icon'  => $cta->icon ?? '',
    ],
  ];

  $s = $styles[$style] ?? $styles['gradient-blue'];
@endphp

<div class="my-8 rounded-2xl border-2 {{ $s['ring'] }} {{ $s['outer'] }} overflow-hidden shadow-lg hover:shadow-xl transition-shadow"
     data-cta-id="{{ $cta->id }}"
     data-cta-impression="true"
     x-data="{ visible: false }"
     x-intersect.once="visible = true; trackCtaImpression({{ $cta->id }})">

  {{-- Top Gradient Bar --}}
  <div class="h-1.5 {{ $s['bg'] }}"></div>

  <div class="p-6 md:p-8">
    <div class="flex flex-col sm:flex-row items-center gap-5 md:gap-6">

      {{-- Product Image --}}
      @if($cta->affiliateLink && $cta->affiliateLink->image)
      <div class="w-28 h-28 sm:w-32 sm:h-32 rounded-2xl overflow-hidden shrink-0 shadow-lg border-2 border-white dark:border-white/10">
        <img src="{{ asset('storage/' . $cta->affiliateLink->image) }}"
             alt="{{ $cta->affiliateLink->name ?? $cta->label }}"
             class="w-full h-full object-cover"
             loading="lazy">
      </div>
      @endif

      {{-- Content --}}
      <div class="flex-1 text-center sm:text-left">
        @if($cta->affiliateLink && $cta->affiliateLink->name)
          <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">แนะนำสำหรับคุณ</p>
        @endif

        <h4 class="text-lg sm:text-xl font-bold text-slate-800 dark:text-gray-100 mb-2">
          {{ $cta->label }}
        </h4>

        @if($cta->sub_label)
          <p class="text-gray-600 dark:text-gray-400 text-sm mb-4">{{ $cta->sub_label }}</p>
        @endif

        {{-- CTA Button --}}
        <a href="{{ $link }}"
           rel="nofollow noopener sponsored"
           target="_blank"
           class="inline-flex items-center justify-center gap-2 py-3.5 px-7 rounded-xl text-white text-base sm:text-lg font-bold transition-all duration-300 hover:scale-105 {{ $s['btn'] }} {{ $s['anim'] }}"
           data-cta-id="{{ $cta->id }}"
           onclick="trackCtaClick({{ $cta->id }})">
          @if($s['icon'])
            <span>{{ $s['icon'] }}</span>
          @endif
          {{ $cta->label }}
          <i class="bi bi-arrow-right"></i>
        </a>

        {{-- Trust Badges --}}
        <div class="flex items-center justify-center sm:justify-start gap-4 mt-4 text-xs text-gray-600 dark:text-gray-400 flex-wrap">
          <span class="flex items-center gap-1"><i class="bi bi-shield-check text-emerald-500 dark:text-emerald-400"></i> ปลอดภัย 100%</span>
          <span class="flex items-center gap-1"><i class="bi bi-star-fill text-amber-400 dark:text-amber-300"></i> แนะนำโดยทีมงาน</span>
        </div>
      </div>
    </div>

    {{-- Disclaimer --}}
    <p class="text-xs text-gray-500 dark:text-gray-400 mt-5 text-center border-t border-gray-200 dark:border-white/10 pt-3">
      * ลิงก์ affiliate - เราอาจได้รับค่าคอมมิชชั่นเมื่อคุณซื้อผ่านลิงก์นี้ โดยไม่มีค่าใช้จ่ายเพิ่มเติมสำหรับคุณ
    </p>
  </div>
</div>
@endif
