{{--
  Announcement Popup
  ──────────────────
  Renders the highest-priority popup-flagged announcement that:
    1. Is targeted at the current user (province/district/subdistrict
       match — or NULL = "broadcast to all")
    2. Has show_as_popup = true
    3. Is currently active (status='published', within starts_at/ends_at)
    4. The user hasn't already dismissed (announcement_dismissals join)
    5. Highest priority + most recent first

  Self-gated — renders nothing for guests, nothing when no eligible
  announcement exists. One DB query per page (cached 60s per user) so
  the cost is negligible.

  Dismissal is recorded server-side in announcement_dismissals (POST
  to /announcements/{id}/dismiss). Once recorded, the user never sees
  this announcement again until admin un-dismisses or creates a new
  announcement with the same target. Far more durable than localStorage
  flags which a privacy-focused user clears regularly.
--}}
@auth
@php
  $_user = Auth::user();
  $_announcement = \Illuminate\Support\Facades\Cache::remember(
      'announce_popup_user_' . $_user->id,
      60,
      function () use ($_user) {
          if (!\Illuminate\Support\Facades\Schema::hasTable('announcements')) {
              return null;
          }
          return \DB::table('announcements as a')
              ->leftJoin('announcement_dismissals as d', function ($j) use ($_user) {
                  $j->on('d.announcement_id', '=', 'a.id')
                    ->where('d.user_id', '=', $_user->id);
              })
              ->whereNull('d.id')                       // not dismissed
              ->where('a.show_as_popup', true)
              ->where('a.status', 'published')
              ->whereNull('a.deleted_at')
              ->where(function ($q) {
                  $q->whereNull('a.starts_at')->orWhere('a.starts_at', '<=', now());
              })
              ->where(function ($q) {
                  $q->whereNull('a.ends_at')->orWhere('a.ends_at', '>=', now());
              })
              // Geo target — match user's province/district/subdistrict
              // OR allow NULL (= broadcast). The combined OR makes the
              // single query cover both targeted + broadcast cases.
              ->where(function ($q) use ($_user) {
                  $q->whereNull('a.target_province_id')
                    ->orWhere('a.target_province_id', $_user->province_id);
              })
              ->where(function ($q) use ($_user) {
                  $q->whereNull('a.target_district_id')
                    ->orWhere('a.target_district_id', $_user->district_id);
              })
              ->where(function ($q) use ($_user) {
                  $q->whereNull('a.target_subdistrict_id')
                    ->orWhere('a.target_subdistrict_id', $_user->subdistrict_id);
              })
              ->orderByRaw("CASE a.priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
              ->orderByDesc('a.is_pinned')
              ->orderByDesc('a.created_at')
              ->select('a.*')
              ->first();
      },
  );
@endphp

@if($_announcement)
@php
  // Resolve cover image to a URL — falls back gracefully on failure
  // so a broken legacy path can't crash the popup.
  $_coverUrl = '';
  if (!empty($_announcement->cover_image_path)) {
      try {
          $_coverUrl = app(\App\Services\StorageManager::class)->resolveUrl($_announcement->cover_image_path);
      } catch (\Throwable) { /* keep blank */ }
  }
  // Priority → color theme. Same vocabulary as alert rules so admins
  // form a mental model from one screen to the next.
  $_themeMap = [
      'critical' => ['from' => '#dc2626', 'to' => '#ec4899', 'icon' => 'bi-exclamation-triangle-fill'],
      'high'     => ['from' => '#f59e0b', 'to' => '#ec4899', 'icon' => 'bi-megaphone-fill'],
      'normal'   => ['from' => '#4f46e5', 'to' => '#7c3aed', 'icon' => 'bi-bell-fill'],
      'low'      => ['from' => '#64748b', 'to' => '#475569', 'icon' => 'bi-info-circle-fill'],
  ];
  $_theme = $_themeMap[$_announcement->priority] ?? $_themeMap['normal'];
@endphp

<div id="announcement-popup"
     class="hidden"
     x-data="announcementPopup({{ $_announcement->id }})"
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

  <div x-show="open"
       x-transition:enter="transition ease-out duration-300"
       x-transition:enter-start="opacity-0 scale-95 translate-y-3"
       x-transition:enter-end="opacity-100 scale-100 translate-y-0"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="opacity-100 scale-100"
       x-transition:leave-end="opacity-0 scale-95"
       class="fixed inset-0 z-[1091] flex items-center justify-center p-4 pointer-events-none">
    <div @click.stop
         class="bg-white dark:bg-slate-900 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden pointer-events-auto
                border border-slate-200 dark:border-white/10">

      {{-- Cover image (if present) — full-bleed at the top --}}
      @if($_coverUrl)
        <div class="relative aspect-[16/9] bg-slate-100 dark:bg-slate-800 overflow-hidden">
          <img src="{{ $_coverUrl }}" alt="" class="absolute inset-0 w-full h-full object-cover">
          <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
        </div>
      @endif

      {{-- Header band — gradient based on priority --}}
      <div class="relative px-6 py-5 text-white text-center"
           style="background: linear-gradient(135deg, {{ $_theme['from'] }} 0%, {{ $_theme['to'] }} 100%);">
        <button type="button"
                @click="dismiss()"
                class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition backdrop-blur"
                title="ปิด">
          <i class="bi bi-x text-xl"></i>
        </button>

        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-white/25 backdrop-blur mb-2">
          <i class="bi {{ $_theme['icon'] }} text-2xl"></i>
        </div>
        <h2 class="font-bold text-xl leading-tight">{{ $_announcement->title }}</h2>
        @if($_announcement->excerpt)
          <p class="text-sm text-white/90 mt-1 leading-snug">{{ $_announcement->excerpt }}</p>
        @endif
      </div>

      {{-- Body --}}
      <div class="px-6 py-5">
        @if($_announcement->body)
          <div class="prose prose-sm dark:prose-invert max-w-none text-slate-700 dark:text-slate-200 leading-relaxed">
            {!! \Illuminate\Support\Str::markdown($_announcement->body) !!}
          </div>
        @endif

        @if(!empty($_announcement->cta_label) && !empty($_announcement->cta_url))
          <a href="{{ $_announcement->cta_url }}"
             @click="markClicked()"
             class="mt-4 block w-full text-center py-3 px-5 rounded-xl text-white font-bold shadow-md transition-all hover:shadow-lg active:scale-[0.98]"
             style="background: linear-gradient(135deg, {{ $_theme['from'] }} 0%, {{ $_theme['to'] }} 100%);">
            {{ $_announcement->cta_label }}
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

<script>
window.announcementPopup = function (announcementId) {
  return {
    open: false,
    init() {
      // 6-second delay so the user gets to see the page content first.
      // Long enough to feel deliberate, short enough that they haven't
      // moved on. Skip if user already dismissed in this session.
      if (sessionStorage.getItem('announcement_dismissed_' + announcementId)) return;
      setTimeout(() => {
        this.open = true;
        document.getElementById('announcement-popup')?.classList.remove('hidden');
      }, 6000);
    },
    dismiss() {
      this.open = false;
      sessionStorage.setItem('announcement_dismissed_' + announcementId, '1');
      // Persist server-side too so the popup never returns on a future
      // session. Best-effort: if the request fails we still close locally.
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
      fetch('/announcements/' + announcementId + '/dismiss', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
      }).catch(() => {});
    },
    markClicked() {
      // Treat clicking the CTA as dismissal — they engaged, no need to
      // see the popup again.
      this.dismiss();
    },
  };
};
</script>
@endif
@endauth
