<!DOCTYPE html>
<html lang="th" x-data="{ darkMode: localStorage.getItem('pg-theme') === 'dark' }" :class="{ 'dark': darkMode }">
<head>
  @include('layouts.partials.analytics-head')
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @include('layouts.partials.favicon')
  <title>@yield('title', 'Dashboard') — Photographer | {{ $siteName ?? config('app.name') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <link rel="stylesheet" href="{{ asset('css/photographer.css') }}">
  @stack('styles')

  <style>
  * { font-family: 'Sarabun', sans-serif; }
  /* Thin scrollbar for sidebar */
  .pg-sidebar-scroll::-webkit-scrollbar { width: 4px; }
  .pg-sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 2px; }
  </style>
</head>
<body x-data="{
  sidebarOpen: false,
  collapsed: false,
  notifyOpen: false,
  toggleSidebar() {
    if (window.innerWidth < 1024) {
      this.sidebarOpen = !this.sidebarOpen;
    } else {
      this.collapsed = !this.collapsed;
    }
  },
  toggleTheme() {
    this.darkMode = !this.darkMode;
    localStorage.setItem('pg-theme', this.darkMode ? 'dark' : 'light');
    document.getElementById('pgThemeIcon').className = this.darkMode ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
  }
}" x-init="
  if (darkMode) { document.getElementById('pgThemeIcon') && (document.getElementById('pgThemeIcon').className = 'bi bi-sun-fill'); }
">

<div id="photographer-wrapper">
  {{-- Impersonation Banner — shown while admin is impersonating a photographer --}}
  @if(session('impersonator.admin_id'))
  <div class="bg-purple-600 border-b border-purple-800 text-white">
    <div class="px-6 py-2 flex items-center justify-between flex-wrap gap-2">
      <div class="flex items-center gap-2 text-sm">
        <i class="bi bi-incognito text-purple-100"></i>
        <span>
          <strong>กำลัง Impersonate</strong> เป็น
          <strong>{{ Auth::user()?->full_name ?? Auth::user()?->email ?? 'ช่างภาพ' }}</strong>
          (admin: {{ session('impersonator.admin_email') }})
        </span>
      </div>
      <form method="POST" action="{{ route('impersonate.stop') }}" class="inline">
        @csrf
        <button type="submit" class="bg-white text-purple-700 hover:bg-purple-50 text-xs font-semibold px-3 py-1.5 rounded-lg inline-flex items-center gap-1">
          <i class="bi bi-box-arrow-left"></i> หยุด Impersonate
        </button>
      </form>
    </div>
  </div>
  @endif

  {{-- Sidebar --}}
  @include('layouts.partials.photographer-sidebar')

  {{-- Main Content — pg-page-bg adds the radial gradient that matches the auth flow --}}
  <div class="flex flex-col min-h-screen pg-page-bg transition-all duration-300"
     id="photographer-content"
     :class="collapsed ? 'lg:ml-[70px]' : 'lg:ml-[260px]'">
    {{-- Top Bar — solid theme color (indigo→purple gradient, matches sidebar) --}}
    <div class="px-6 h-[60px] flex items-center sticky top-0 z-[1030] text-white
                shadow-lg shadow-indigo-900/10 dark:shadow-black/30 border-b border-white/[0.08]"
         style="background:linear-gradient(135deg,#4f46e5 0%,#6d28d9 50%,#7c3aed 100%);">
      <button class="mr-3 flex items-center justify-center w-9 h-9 rounded-[10px] bg-white/15 hover:bg-white/25 border-0 text-white cursor-pointer transition-all backdrop-blur-sm"
          @click="toggleSidebar()" type="button">
        <i class="bi bi-list text-lg"></i>
      </button>
      <span class="font-semibold text-white text-base">@yield('title', 'Dashboard')</span>
      <div class="ml-auto flex items-center gap-3">
        {{-- Notification Bell --}}
        <div class="relative" @click.away="notifyOpen = false" id="pgNotifyDropdown">
          <button class="relative text-white/90 hover:text-white transition" @click="notifyOpen = !notifyOpen" id="pgNotifyBell" type="button" title="การแจ้งเตือน">
            <i class="bi bi-bell-fill"></i>
            <span class="absolute -top-1 -right-2 inline-flex items-center justify-center px-1.5 py-0.5 text-[0.6rem] font-bold leading-none text-white bg-rose-500 rounded-full hidden" id="pgNotifyBadge">0</span>
          </button>
          <div x-show="notifyOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             x-cloak
             class="absolute right-0 mt-2 w-[340px] max-h-[450px] overflow-hidden rounded-xl shadow-xl bg-white border-0 p-0 z-50 dark:bg-slate-800">
            <div class="flex justify-between items-center px-3 py-2 bg-gradient-to-br from-blue-600 to-blue-700 text-white">
              <span class="font-semibold text-sm"><i class="bi bi-bell mr-1"></i>การแจ้งเตือน</span>
              <button class="border-0 text-white/50 p-0 text-xs bg-transparent cursor-pointer" id="pgMarkAllRead" style="display:none;" onclick="PgNotify.markAllRead()">อ่านแล้วทั้งหมด</button>
            </div>
            <div id="pgNotifyList" class="max-h-[380px] overflow-y-auto">
              <div class="text-center text-gray-400 py-4 text-sm"><i class="bi bi-bell-slash mr-1"></i>ไม่มีการแจ้งเตือน</div>
            </div>
          </div>
        </div>

        <button type="button" class="w-9 h-9 rounded-[10px] bg-white/15 hover:bg-white/25 border-0 text-white flex items-center justify-center cursor-pointer transition-all" @click="toggleTheme()" title="สลับโหมด">
          <i class="bi bi-moon-fill" id="pgThemeIcon"></i>
        </button>
        {{-- User pill: shows the photographer's display-name + an icon
             that reflects their CURRENT subscription plan. The icon is
             the same one used on the /subscription/plans cards so users
             always see the same visual cue for "this is your tier". --}}
        <a href="{{ route('photographer.subscription.index') }}"
           class="hidden sm:flex items-center gap-2 pl-2 group no-underline"
           title="{{ ($photographerPlan?->name ?? 'Free') }} · จัดการแผน">
          <div class="relative flex items-center justify-center w-9 h-9 rounded-lg bg-white/15 hover:bg-white/25 backdrop-blur-sm border border-white/25 text-white text-base shadow-sm transition">
            <i class="bi {{ $photographerPlanIcon ?? 'bi-camera' }}"></i>
            {{-- Tiny accent dot on the corner echoes the plan colour
                 (color_hex on the plan row). Pure visual — admins can
                 set this in the plans table to differentiate tiers. --}}
            <span class="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full ring-2 ring-white/80 shadow"
                  style="background:{{ $photographerPlanAccent ?? '#7c3aed' }};"></span>
          </div>
          <div class="leading-tight">
            <span class="block text-sm font-semibold text-white">{{ $photographer->display_name ?? Auth::user()?->full_name ?? '' }}</span>
            <span class="block text-[10px] uppercase tracking-[0.14em] font-bold text-white/75 group-hover:text-white transition">
              <i class="bi {{ $photographerPlanIcon ?? 'bi-camera' }} text-[10px] mr-0.5"></i>
              {{ $photographerPlan?->name ?? 'Free' }}
            </span>
          </div>
        </a>
        <form method="POST" action="{{ route('photographer.logout') }}" class="inline">
          @csrf
          <button type="submit" class="flex items-center justify-center w-9 h-9 rounded-[10px] bg-white/10 hover:bg-rose-500/30 border-0 text-white cursor-pointer transition-all" title="ออกจากระบบ">
            <i class="bi bi-box-arrow-right"></i>
          </button>
        </form>
      </div>
    </div>

    <main class="p-6">
      @if (session('success'))
        <div class="flex items-center gap-2 mb-3 px-4 py-3 bg-emerald-500/[0.08] text-emerald-600 rounded-xl text-sm" x-data="{ show: true }" x-show="show">
          <i class="bi bi-check-circle mr-1"></i>{{ session('success') }}
          <button type="button" class="ml-auto text-emerald-600/60 hover:text-emerald-600 text-xs bg-transparent border-0 cursor-pointer" @click="show = false">&times;</button>
        </div>
      @endif

      {{-- ─────────────────────────────────────────────────────────────
           Google Connect Promo — shown only when photographer is missing
           a Google link. Encourages but doesn't force. Auto-hides if any
           one of: not logged in / has Google / dismissed for this session.
           ─────────────────────────────────────────────────────────── --}}
      @auth
        @php
          $_pgHasGoogle = \Illuminate\Support\Facades\DB::table('auth_social_logins')
            ->where('user_id', \Illuminate\Support\Facades\Auth::id())
            ->where('provider', 'google')->exists();
          $_pgHasLine = \Illuminate\Support\Facades\DB::table('auth_social_logins')
            ->where('user_id', \Illuminate\Support\Facades\Auth::id())
            ->where('provider', 'line')->exists();
        @endphp
        @if(!$_pgHasGoogle)
        <div x-data="{ show: !sessionStorage.getItem('pg_google_promo_dismissed_' + {{ \Illuminate\Support\Facades\Auth::id() }}) }"
             x-show="show" x-cloak
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 -translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="relative mb-4 overflow-hidden rounded-2xl border border-indigo-200/50 dark:border-indigo-500/20"
             style="background: linear-gradient(135deg, rgba(99,102,241,.08) 0%, rgba(236,72,153,.05) 50%, rgba(245,158,11,.08) 100%);">
          {{-- Subtle radial accent (decorative) --}}
          <div class="absolute inset-0 pointer-events-none opacity-60"
               style="background: radial-gradient(ellipse 60% 100% at 0% 50%, rgba(99,102,241,.15) 0%, transparent 60%);"></div>

          {{-- Dismiss button — absolute-positioned top-right so it doesn't
               compete with the main CTA for horizontal space on mobile.
               Larger 32px hit-area to satisfy touch-target a11y. --}}
          <button type="button"
                  @click="show = false; sessionStorage.setItem('pg_google_promo_dismissed_' + {{ \Illuminate\Support\Facades\Auth::id() }}, '1')"
                  class="absolute top-2 right-2 z-10 w-8 h-8 inline-flex items-center justify-center rounded-full
                         text-slate-400 hover:text-slate-700 dark:hover:text-slate-200
                         hover:bg-white/40 dark:hover:bg-slate-800/40 transition"
                  title="ปิด (จะแสดงอีกในครั้งหน้า)">
            <i class="bi bi-x-lg text-sm"></i>
            <span class="sr-only">ปิด</span>
          </button>

          {{--
            Responsive layout strategy
            ─────────────────────────
            Mobile  (<sm):  3-row stack — logo + heading row, features row,
                            full-width CTA at bottom (easier thumb tap).
            Tablet  (sm+):  2 rows — top: logo + heading, bottom: features
                            inline + CTA on the right.
            Desktop (md+):  Single horizontal row — logo · text · CTA.
          --}}
          <div class="relative p-4 sm:p-5">
            <div class="flex flex-col md:flex-row md:items-center gap-4 pr-8">

              {{-- Logo + Heading group (stays together at every breakpoint) --}}
              <div class="flex items-start gap-3 sm:gap-4 md:flex-shrink-0">
                {{-- Google G logo (official 4-color mark) --}}
                <div class="shrink-0 w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-white dark:bg-slate-100
                            shadow-md flex items-center justify-center ring-1 ring-slate-200/60">
                  <svg viewBox="0 0 24 24" class="w-6 h-6 sm:w-7 sm:h-7" aria-hidden="true">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                  </svg>
                </div>

                {{-- Heading + LINE-linked badge — visible only on mobile/tablet
                     stacked layout. The desktop version repeats this in the
                     centre column below for proper horizontal flow. --}}
                <div class="md:hidden flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="text-sm sm:text-[15px] font-bold text-slate-800 dark:text-slate-100 leading-snug">
                      เชื่อมต่อ Google เพื่อใช้งานเต็มรูปแบบ
                    </h3>
                    @if($_pgHasLine)
                      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold
                                   bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300 shrink-0">
                        <i class="bi bi-check-circle-fill"></i> LINE
                      </span>
                    @endif
                  </div>
                </div>
              </div>

              {{-- Centre column (desktop heading + features chips) --}}
              <div class="md:flex-1 md:min-w-0">
                {{-- Heading — desktop only --}}
                <div class="hidden md:flex items-center gap-2 flex-wrap mb-1.5">
                  <h3 class="text-[15px] font-bold text-slate-800 dark:text-slate-100 leading-snug">
                    เชื่อมต่อ Google เพื่อใช้งานเต็มรูปแบบ
                  </h3>
                  @if($_pgHasLine)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold
                                 bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                      <i class="bi bi-check-circle-fill"></i> LINE เชื่อมแล้ว
                    </span>
                  @endif
                </div>

                {{-- Feature chips. Each chip is its own pill so they wrap
                     cleanly when there's not enough horizontal room — no
                     more orphan dot separators on tight mobile widths. --}}
                <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
                  <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] sm:text-xs
                               bg-white/70 dark:bg-slate-800/40 text-slate-700 dark:text-slate-300
                               ring-1 ring-slate-200/60 dark:ring-slate-700/40">
                    <i class="bi bi-cloud-fill text-emerald-500"></i> Backup Drive
                  </span>
                  <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] sm:text-xs
                               bg-white/70 dark:bg-slate-800/40 text-slate-700 dark:text-slate-300
                               ring-1 ring-slate-200/60 dark:ring-slate-700/40">
                    <i class="bi bi-calendar-event text-rose-500"></i> Sync Calendar
                  </span>
                  <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] sm:text-xs
                               bg-white/70 dark:bg-slate-800/40 text-slate-700 dark:text-slate-300
                               ring-1 ring-slate-200/60 dark:ring-slate-700/40">
                    <i class="bi bi-envelope-paper-fill text-blue-500"></i> ส่งใบเสร็จ Gmail
                  </span>
                  <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] sm:text-xs
                               bg-white/70 dark:bg-slate-800/40 text-slate-700 dark:text-slate-300
                               ring-1 ring-slate-200/60 dark:ring-slate-700/40">
                    <i class="bi bi-shield-fill-check text-violet-500"></i> ยืนยันตัวตน
                  </span>
                </div>
              </div>

              {{-- Primary CTA — full-width on mobile (better thumb-tap), inline
                   pill on desktop. --}}
              <div class="md:flex-shrink-0">
                <a href="{{ route('photographer.auth.redirect', ['provider' => 'google']) }}"
                   class="w-full md:w-auto inline-flex items-center justify-center gap-2
                          px-4 py-2.5 rounded-xl text-sm font-semibold
                          bg-white dark:bg-slate-100 text-slate-700 hover:text-slate-900
                          border border-slate-200 hover:border-slate-300
                          shadow-sm hover:shadow-md transition-all duration-200
                          md:hover:-translate-y-0.5">
                  <svg viewBox="0 0 24 24" class="w-4 h-4 shrink-0" aria-hidden="true">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                  </svg>
                  <span>เชื่อมต่อ Google</span>
                </a>
              </div>
            </div>
          </div>
        </div>
        @endif
      @endauth

      @yield('content')
    </main>
  </div>
</div>

{{-- Overlay for mobile sidebar --}}
<div x-show="sidebarOpen"
   x-transition:enter="transition-opacity ease-out duration-300"
   x-transition:enter-start="opacity-0"
   x-transition:enter-end="opacity-50"
   x-transition:leave="transition-opacity ease-in duration-300"
   x-transition:leave-start="opacity-50"
   x-transition:leave-end="opacity-0"
   x-cloak
   class="fixed inset-0 bg-black/50 z-[1035] lg:hidden"
   @click="sidebarOpen = false">
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Legacy theme support for scripts that check data-bs-theme
(function() {
  const saved = localStorage.getItem('pg-theme');
  if (saved === 'dark') {
    document.documentElement.setAttribute('data-bs-theme', 'dark');
  }
})();
</script>
@stack('scripts')
{{-- Photographer Notifications (lightweight) --}}
<script>
const PgNotify = {
  timer: null,
  baseUrl: (document.querySelector('meta[name="base-url"]')?.content || '').replace(/\/$/, ''),
  sinceId: 0,         // Monotonic id-based cursor (timezone-safe)
  isFirstPoll: true,  // Don't ding/flash bell on baseline poll

  init() {
    this.poll();
    this.timer = setInterval(() => { if (!document.hidden) this.poll(); }, 30000);
    // Pause when tab hidden — clearer guard than stop/start cycling.
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) this.poll();
    });
  },

  async poll() {
    try {
      // Use since_id cursor after the first baseline poll. The server
      // returns no `new_items` when no cursor is provided, so the very
      // first request gives us the latest_id baseline without surfacing
      // every old notification as if it just arrived.
      const url = this.sinceId > 0
        ? this.baseUrl + '/api/notifications?since_id=' + this.sinceId
        : this.baseUrl + '/api/notifications';
      const resp = await fetch(url, { credentials: 'same-origin' });
      if (!resp.ok) return;
      const json = await resp.json();
      if (!json.success) return;

      const badge = document.getElementById('pgNotifyBadge');
      const markBtn = document.getElementById('pgMarkAllRead');
      const count = json.unread_count || 0;

      if (badge) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.toggle('hidden', count === 0);
      }
      if (markBtn) markBtn.style.display = count > 0 ? '' : 'none';

      // Advance the cursor.
      const latestId = parseInt(json.latest_id || 0, 10) || 0;
      if (latestId > this.sinceId) this.sinceId = latestId;
      this.isFirstPoll = false;

      this.renderList(json.notifications || []);
    } catch (e) {
      console.warn('[PgNotify] Poll error:', e.message);
    }
  },

  renderList(items) {
    const list = document.getElementById('pgNotifyList');
    if (!list) return;
    if (!items.length) {
      list.innerHTML = '<div class="text-center text-gray-400 py-4 text-sm"><i class="bi bi-bell-slash mr-1"></i>ไม่มีการแจ้งเตือน</div>';
      return;
    }
    const isDark = document.documentElement.classList.contains('dark');
    list.innerHTML = items.slice(0, 15).map(n => {
      // Field names match what the UserNotification model serialises:
      //   - is_read   (boolean) — was incorrectly read as `read_at`
      //   - action_href (computed accessor) / action_url (raw column)
      //     — was incorrectly read as `link`
      // The result of the bug was that every notification rendered as
      // "unread" forever and clicking the row went to "#" instead of
      // the intended URL.
      const isUnread = n.is_read === 0 || n.is_read === false || n.is_read === '0';
      const href = this.safeUrl(n.action_href || n.action_url);
      const time = this.timeAgo(n.created_at);
      const bg = isUnread ? (isDark ? 'rgba(37,99,235,0.08)' : 'rgba(37,99,235,0.04)') : 'transparent';
      const textColor = isDark ? '#e2e8f0' : '#1e293b';
      const subColor = isDark ? '#64748b' : '#94a3b8';
      return `<a href="${href}" data-id="${n.id}" class="flex gap-2 px-3 py-2 no-underline border-b border-gray-100 dark:border-white/[0.06]" style="background:${bg}" onclick="PgNotify.markOneRead(${n.id})">
        <div class="flex-grow">
          <div class="text-sm font-medium" style="color:${textColor};">${this.esc(n.title || n.message || '')}</div>
          <div style="font-size:0.7rem;color:${subColor};">${time}</div>
        </div>
        ${isUnread ? '<span class="mt-2 shrink-0" style="width:8px;height:8px;border-radius:50%;background:#2563eb;"></span>' : ''}
      </a>`;
    }).join('');
  },

  // Reject `javascript:`, `data:`, protocol-relative `//evil.com`, etc.
  // Server-side already sanitises but we double-check on client to defend
  // against any historical row that snuck in before the sanitiser landed.
  safeUrl(url) {
    if (!url) return '#';
    const s = String(url).trim();
    if (s.startsWith('//')) return '#';
    if (/^[a-z][a-z0-9+.\-]*:/i.test(s) && !/^https?:\/\//i.test(s)) return '#';
    return s;
  },

  // Best-effort mark-as-read on click. Lets the server reconcile the
  // bell counter on the next poll naturally; we don't await.
  markOneRead(id) {
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
      fetch(this.baseUrl + '/api/notifications/' + id + '/read', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
      });
    } catch (_) {}
  },

  async markAllRead() {
    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
      await fetch(this.baseUrl + '/api/notifications/read-all', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token }
      });
      this.poll();
    } catch (e) { console.warn('[PgNotify] Mark read error:', e.message); }
  },

  timeAgo(dateStr) {
    const d = new Date(dateStr);
    const diff = Math.floor((Date.now() - d) / 1000);
    if (diff < 60) return 'เมื่อสักครู่';
    if (diff < 3600) return Math.floor(diff / 60) + ' นาทีที่แล้ว';
    if (diff < 86400) return Math.floor(diff / 3600) + ' ชั่วโมงที่แล้ว';
    return Math.floor(diff / 86400) + ' วันที่แล้ว';
  },

  esc(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
  },

  destroy() {
    if (this.timer) clearInterval(this.timer);
  }
};
document.addEventListener('DOMContentLoaded', () => PgNotify.init());
</script>
{{-- Idle Auto-Logout --}}
@php
  $_idleTimeout = (int) (\App\Models\AppSetting::where('key','idle_timeout_photographer')->value('value') ?? 30);
  $_idleWarning = (int) (\App\Models\AppSetting::where('key','idle_warning_seconds')->value('value') ?? 60);
@endphp
@if($_idleTimeout > 0)
<script src="{{ asset('js/idle-logout.js') }}"></script>
<script>
  IdleLogout.init({
    timeout: {{ $_idleTimeout }},
    warning: {{ $_idleWarning }},
    logoutUrl: '{{ route("photographer.logout") }}',
    csrfToken: '{{ csrf_token() }}',
    loginUrl: '{{ route("photographer.login") }}',
    roleName: 'Photographer',
  });
</script>
@endif
</body>
</html>
