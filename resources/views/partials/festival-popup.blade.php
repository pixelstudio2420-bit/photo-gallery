{{--
  Festival Popup
  ──────────────
  Renders the highest-priority active festival for the current user.
  Mirrors partials/announcement-popup.blade.php behaviour but with
  themed visuals per FestivalThemeService::THEMES variant.

  Render guard:
    1. User authenticated
    2. FestivalThemeService::activeForUser() returns a row (handles
       enabled, time-window, province targeting, dismissals)
    3. Per-user 60s cache lives inside the service

  Theming:
    Reads the variant slug from $_festival->theme_variant and looks
    up gradient_css + sparkle emoji + accent color from THEMES. New
    themes drop in by adding entries to FestivalThemeService::THEMES
    — no template change needed.

  Dismissal: POST /festivals/{id}/dismiss → server records, busts the
  user's cache, popup never returns until admin re-creates festival
  (e.g. next year's Songkran with a new id).
--}}
@auth
@php
  $_user = Auth::user();
  $_festival = app(\App\Services\FestivalThemeService::class)->activeForUser($_user);
@endphp

@if($_festival)
@php
  $_theme = \App\Services\FestivalThemeService::theme($_festival->theme_variant);

  // Cover image — defensive lookup so a stale R2 key doesn't crash
  // the popup. The customer would rather see no image than a 500.
  $_coverUrl = '';
  if (!empty($_festival->cover_image_path)) {
      try {
          $_coverUrl = app(\App\Services\StorageManager::class)->resolveUrl($_festival->cover_image_path);
      } catch (\Throwable) { /* keep blank */ }
  }

  // "X days left" copy — only show when < 14 days to ends_at, so we
  // don't manufacture urgency on a 30-day festival like Pride Month
  // unless we're actually near the end.
  $_daysLeft   = now()->startOfDay()->diffInDays($_festival->ends_at, false);
  $_showUrgency = $_daysLeft >= 0 && $_daysLeft <= 14;
@endphp

<div id="festival-popup"
     class="hidden"
     x-data="festivalPopup({{ $_festival->id }})"
     x-init="init()"
     x-cloak>
  {{-- Backdrop --}}
  <div x-show="open"
       x-transition:enter="transition-opacity ease-out duration-300"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition-opacity ease-in duration-200"
       @click="dismiss()"
       @keydown.escape.window="dismiss()"
       class="fixed inset-0 bg-slate-900/70 backdrop-blur-md z-[1090]"></div>

  {{-- Modal --}}
  <div x-show="open"
       x-transition:enter="transition ease-out duration-400"
       x-transition:enter-start="opacity-0 scale-95 translate-y-4"
       x-transition:enter-end="opacity-100 scale-100 translate-y-0"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="opacity-100 scale-100"
       x-transition:leave-end="opacity-0 scale-95"
       class="fixed inset-0 z-[1091] flex items-center justify-center p-4 pointer-events-none">
    <div @click.stop
         class="relative bg-white dark:bg-slate-900 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden pointer-events-auto
                border border-slate-200 dark:border-white/10">

      {{-- Cover image (optional) --}}
      @if($_coverUrl)
        <div class="relative aspect-[16/9] bg-slate-100 dark:bg-slate-800 overflow-hidden">
          <img src="{{ $_coverUrl }}" alt="" class="absolute inset-0 w-full h-full object-cover">
          <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
        </div>
      @endif

      {{-- Themed header band --}}
      <div class="relative px-6 py-6 text-white text-center overflow-hidden"
           style="background: {{ $_theme['gradient_css'] }};">
        {{-- Decorative floating sparkles — pure CSS, no JS dependency --}}
        <div class="festival-sparkles" aria-hidden="true">
          <span style="left: 8%;  animation-delay: 0s;">{{ $_theme['sparkle'] ?? $_festival->emoji ?? '✨' }}</span>
          <span style="left: 28%; animation-delay: 1.2s;">{{ $_theme['sparkle'] ?? $_festival->emoji ?? '✨' }}</span>
          <span style="left: 52%; animation-delay: 0.6s;">{{ $_theme['sparkle'] ?? $_festival->emoji ?? '✨' }}</span>
          <span style="left: 76%; animation-delay: 2.1s;">{{ $_theme['sparkle'] ?? $_festival->emoji ?? '✨' }}</span>
          <span style="left: 90%; animation-delay: 1.6s;">{{ $_theme['sparkle'] ?? $_festival->emoji ?? '✨' }}</span>
        </div>

        {{-- Close --}}
        <button type="button"
                @click="dismiss()"
                class="absolute top-3 right-3 z-10 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition backdrop-blur"
                title="ปิด">
          <i class="bi bi-x text-xl"></i>
        </button>

        <div class="relative z-[1]">
          <div class="inline-flex items-center justify-center text-5xl mb-2 animate-bounce-slow">
            {{ $_festival->emoji ?? $_theme['sparkle'] ?? '✨' }}
          </div>
          <h2 class="font-extrabold text-2xl leading-tight tracking-tight">{{ $_festival->headline }}</h2>
          @if($_showUrgency && $_daysLeft > 0)
            <div class="inline-flex items-center gap-1.5 mt-3 px-3 py-1.5 rounded-full bg-white/25 backdrop-blur text-xs font-bold">
              <i class="bi bi-hourglass-split"></i>
              เหลืออีก {{ $_daysLeft }} วัน — รีบจองก่อนหมด
            </div>
          @elseif($_daysLeft === 0)
            <div class="inline-flex items-center gap-1.5 mt-3 px-3 py-1.5 rounded-full bg-white/25 backdrop-blur text-xs font-bold animate-pulse">
              <i class="bi bi-fire"></i>
              วันสุดท้าย!
            </div>
          @endif
        </div>
      </div>

      {{-- Body --}}
      <div class="px-6 py-5">
        @if($_festival->body_md)
          <div class="prose prose-sm dark:prose-invert max-w-none text-slate-700 dark:text-slate-200 leading-relaxed">
            {!! \Illuminate\Support\Str::markdown($_festival->body_md) !!}
          </div>
        @endif

        @if(!empty($_festival->cta_label) && !empty($_festival->cta_url))
          <a href="{{ $_festival->cta_url }}"
             @click="markClicked()"
             class="mt-5 block w-full text-center py-3.5 px-5 rounded-xl text-white font-bold text-base shadow-lg transition-all hover:shadow-xl active:scale-[0.98]"
             style="background: {{ $_theme['gradient_css'] }};">
            {{ $_festival->cta_label }}
            <i class="bi bi-arrow-right ml-1"></i>
          </a>
        @endif

        <button type="button"
                @click="dismiss()"
                class="block w-full mt-3 py-2 text-xs text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 transition">
          ปิด · ไม่แสดงอีก
        </button>
      </div>
    </div>
  </div>
</div>

@push('styles')
<style>
  /* Floating sparkle animation in the festival header — light enough
     to feel celebratory without becoming distracting. Each <span>
     has its own animation-delay so they don't pulse in sync. */
  .festival-sparkles { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
  .festival-sparkles span {
    position: absolute;
    bottom: -20px;
    font-size: 18px;
    opacity: 0;
    animation: festival-float 4.5s linear infinite;
  }
  @keyframes festival-float {
    0%   { transform: translateY(0) rotate(0deg);    opacity: 0; }
    20%  { opacity: 0.9; }
    100% { transform: translateY(-180px) rotate(180deg); opacity: 0; }
  }
  @keyframes bounce-slow {
    0%, 100% { transform: translateY(0); }
    50%      { transform: translateY(-8px); }
  }
  .animate-bounce-slow { animation: bounce-slow 2.4s ease-in-out infinite; }
</style>
@endpush

<script>
window.festivalPopup = function (festivalId) {
  return {
    open: false,
    init() {
      // 8-second delay — slightly longer than announcement popup
      // (which is 6s) so when both fire, the user sees announcement
      // first and festival second. Last-shown wins visually.
      if (sessionStorage.getItem('festival_dismissed_' + festivalId)) return;
      setTimeout(() => {
        this.open = true;
        document.getElementById('festival-popup')?.classList.remove('hidden');
      }, 8000);
    },
    dismiss() {
      this.open = false;
      sessionStorage.setItem('festival_dismissed_' + festivalId, '1');
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
      // Persist server-side so the popup never returns even on a
      // fresh device. Best-effort: failure logs to console only.
      fetch('/festivals/' + festivalId + '/dismiss', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
      }).catch(() => {});
    },
    markClicked() {
      // Engagement = implicit dismissal. Same as announcement popup.
      this.dismiss();
    },
  };
};
</script>
@endif
@endauth
