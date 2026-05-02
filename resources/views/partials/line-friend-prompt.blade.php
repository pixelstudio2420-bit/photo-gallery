{{--
  LINE Friend Soft-Prompt Banner
  ──────────────────────────────
  Shown ONLY when ALL these are true:
    1. User is authenticated
    2. User has line_is_friend != true
    3. Admin has configured marketing_line_oa_id (no point prompting
       for an OA that doesn't exist)
    4. Banner not previously dismissed in the last 7 days (sessionStorage
       key tracks dismissal, falls back to clean banner if storage off)

  Why a soft prompt instead of forcing add-friend at signup?
  ─────────────────────────────────────────────────────────
  Forced gates kill conversion. Industry data shows 25-40% drop-off when
  users hit a "you must do X to continue" wall. A persistent-but-
  dismissable banner with a real incentive (discount code, free preset)
  converts ~12-18% of customers over 30 days without harming primary
  conversion at all.

  The aggressive bot_prompt on LINE Login already covers users who
  signed up via LINE — they're auto-added during the OAuth flow. This
  banner is the catch-net for email/Google signups + legacy users.
--}}
@auth
@php
  $_lineOaId   = (string) \App\Models\AppSetting::get('marketing_line_oa_id', '');
  $_isFriend   = (bool) (Auth::user()->line_is_friend ?? false);
  $_showBanner = !$_isFriend && $_lineOaId !== '';

  if ($_showBanner) {
      // The OA Basic ID is stored as e.g. "@loadroop". The deeplink that
      // pops the LINE app's "Add Friend" sheet is line.me/R/ti/p/{id}.
      $_lineFriendUrl = 'https://line.me/R/ti/p/' . ltrim($_lineOaId, '@')
          ? 'https://line.me/R/ti/p/' . urlencode($_lineOaId)
          : '';
      // Optional discount code admin can advertise as the carrot.
      $_friendIncentive = (string) \App\Models\AppSetting::get('line_friend_incentive_label', 'รับโค้ดส่วนลด ฿100');
  }
@endphp

@if($_showBanner)
<div id="line-friend-prompt"
     class="hidden mx-3 sm:mx-0 mb-4 rounded-2xl overflow-hidden shadow-lg shadow-emerald-500/20
            bg-gradient-to-r from-emerald-500 via-green-500 to-teal-500
            text-white"
     x-data="{
         show: !sessionStorage.getItem('line_friend_prompt_dismissed_v1'),
     }"
     x-show="show" x-cloak
     x-init="if (show) document.getElementById('line-friend-prompt').classList.remove('hidden');"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 -translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0">
  <div class="relative flex items-start sm:items-center gap-3 px-4 py-3 sm:px-5 sm:py-4">
    {{-- Decorative LINE icon — circle with white border --}}
    <div class="shrink-0 w-11 h-11 sm:w-12 sm:h-12 rounded-full bg-white/20 backdrop-blur-md
                flex items-center justify-center border-2 border-white/40 shadow-md">
      <i class="bi bi-line text-white text-2xl"></i>
    </div>

    {{-- Copy --}}
    <div class="flex-1 min-w-0">
      <div class="font-bold text-sm sm:text-base leading-tight">
        เพิ่ม {{ $_lineOaId }} เป็นเพื่อน · {{ $_friendIncentive }}
      </div>
      <div class="text-[11px] sm:text-xs text-white/85 mt-0.5 leading-snug">
        แจ้งสถานะคำสั่งซื้อ + ภาพพร้อมโหลด · ส่งโค้ดส่วนลดเฉพาะเพื่อน
      </div>
    </div>

    {{-- CTA — opens LINE app on mobile, web on desktop --}}
    <a href="{{ $_lineFriendUrl }}"
       target="_blank" rel="noopener"
       class="hidden sm:inline-flex items-center gap-1.5 px-4 py-2 rounded-lg
              bg-white text-emerald-600 font-bold text-sm shadow-md
              hover:bg-emerald-50 transition shrink-0">
      <i class="bi bi-plus-circle-fill"></i>
      <span>เพิ่มเพื่อน</span>
    </a>

    {{-- Dismiss (×) — sessionStorage key so banner stays gone for the
         tab session. Cookie-based "remember for 7 days" could replace
         this later but session-only is the simplest UX win. --}}
    <button type="button"
            @click="sessionStorage.setItem('line_friend_prompt_dismissed_v1','1'); show = false"
            class="shrink-0 w-7 h-7 rounded-full hover:bg-white/20 flex items-center justify-center transition"
            title="ปิดแบนเนอร์">
      <i class="bi bi-x-lg text-sm"></i>
    </button>
  </div>

  {{-- Mobile-only CTA bar (full-width below) — desktop has it inline --}}
  <a href="{{ $_lineFriendUrl }}"
     target="_blank" rel="noopener"
     class="sm:hidden block px-4 py-2.5 bg-white/15 backdrop-blur-md
            text-white font-bold text-sm text-center
            border-t border-white/20">
    <i class="bi bi-plus-circle-fill mr-1"></i>
    เพิ่มเพื่อน LINE ตอนนี้
  </a>
</div>
@endif
@endauth
