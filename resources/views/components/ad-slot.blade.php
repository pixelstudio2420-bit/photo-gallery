@props([
  // Where to look up creatives. Must match AdCreative::PLACEMENT_*.
  'placement' => 'homepage_banner',
])

@php
  // Resolve a creative server-side (1 query, cached 60s by AdServingService).
  // No client-side ad-pick — keeps the slot SSR-friendly + Cloudflare-cacheable
  // when the campaign rotation is short.
  try {
      $_creative = app(\App\Services\Monetization\AdServingService::class)->pickCreative($placement);
  } catch (\Throwable) { $_creative = null; }
@endphp

@if($_creative)
  {{-- Visible-only impression beacon — fires after the ad scrolls into view,
       not on page load. Prevents inflating impressions for ads that the
       user never actually saw. --}}
  <div class="ad-slot-wrapper my-4"
       x-data="{
         logged: false,
         async log() {
           if (this.logged) return;
           this.logged = true;
           try {
             await fetch('/ads/{{ $_creative->id }}/seen', {
               method: 'POST',
               headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
               keepalive: true,
             });
           } catch (e) { /* silent — analytics must never break UX */ }
         }
       }"
       x-intersect.threshold.50="log()">
    <a href="/ads/{{ $_creative->id }}/click"
       target="_blank" rel="noopener sponsored"
       class="block rounded-2xl overflow-hidden border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900/60 hover:shadow-lg transition group">
      <div class="relative">
        @if($_creative->image_url)
          <img src="{{ $_creative->image_url }}"
               alt="{{ $_creative->headline }}"
               loading="lazy" decoding="async"
               class="w-full aspect-[5/1] object-cover group-hover:scale-[1.01] transition">
        @endif
        <span class="absolute top-2 right-2 px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-900/70 text-white/80 backdrop-blur-sm">
          Ad
        </span>
      </div>
      <div class="px-4 py-3 flex items-center justify-between gap-3">
        <div class="min-w-0">
          <h4 class="text-sm font-bold text-slate-900 dark:text-white line-clamp-1">{{ $_creative->headline }}</h4>
          @if($_creative->body)
            <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-1 mt-0.5">{{ $_creative->body }}</p>
          @endif
        </div>
        <span class="shrink-0 inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 dark:text-indigo-300 group-hover:underline">
          {{ $_creative->cta_label }}
          <i class="bi bi-arrow-right"></i>
        </span>
      </div>
    </a>
  </div>
@endif
