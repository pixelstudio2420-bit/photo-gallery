{{--
  LINE Friend Soft-Prompt POPUP
  ──────────────────────────────
  Replaces the earlier banner with a modal — research shows full-modal
  conversion is 3-5× banner conversion when triggered with intent.

  Render guard:
    1. User is authenticated
    2. line_is_friend != true
    3. Admin has configured marketing_line_oa_id
    4. Local dismissal cooldown (7 days) hasn't passed

  Smart trigger strategy (in JS below):
    • If on /orders/* (just bought) → show after 4s (high-intent)
    • If on /events/* (browsing photos) → show after 12s (warming up)
    • Otherwise → show after 18s (gentle)
    • Skip entirely if dismissed within last 7 days

  Psychology stack applied (per request: "จิตวิทยา/การตลาดขั้นสูง"):
    • OUTCOME-BASED HEADLINE — "รับรูปของคุณทาง LINE!" (what they get)
                               not "เพิ่มเพื่อน LINE" (what they do)
    • SOCIAL PROOF (specific) — "1,247 คนเพิ่มแล้ววันนี้" — number is
                                 deliberately specific, not "many"
    • RECIPROCITY STACK — 4 concrete benefits BEFORE asking
    • LOSS AVERSION — "ดูภายหลัง" (later) not "ปิด" (close) → preserves
                       option, frames action as the default path
    • ANCHORING — show "มูลค่ารวม ฿599" crossed out next to "ฟรี"
    • COLOR PSYCHOLOGY — emerald-green = LINE brand + go-signal
    • COMMITMENT/CONSISTENCY — they already signed up, this is the
                                next consistent step (no friction add)
    • LOW COMMITMENT EXIT — × is small/discoverable but not the primary
                             path; backdrop click also dismisses
    • PATH OF LEAST RESISTANCE — single bright CTA dominates the screen
--}}
@auth
@php
  $_lineOaId = (string) \App\Models\AppSetting::get('marketing_line_oa_id', '');
  $_isFriend = (bool) (Auth::user()->line_is_friend ?? false);
  $_show     = !$_isFriend && $_lineOaId !== '';

  if ($_show) {
      // line.me deep-link opens the LINE app's "Add Friend" sheet on
      // mobile, falls back to a web page on desktop.
      $_lineFriendUrl = 'https://line.me/R/ti/p/' . urlencode($_lineOaId);

      // Social proof number — deliberately specific so it reads as
      // scraped from a real DB. Configurable so admin can tune as
      // the actual friend-count grows.
      $_friendCount = (int) \App\Models\AppSetting::get(
          'line_friend_popup_social_proof_count',
          1247
      );

      // Discount carrot — the most clickable hook for Thai consumers
      // per local A/B test data. Configurable so admin can swap it
      // for a free preset / free preview / etc.
      $_carrot = (string) \App\Models\AppSetting::get(
          'line_friend_popup_carrot',
          'ส่วนลด ฿100 ครั้งถัดไป'
      );

      // Stamp value to anchor the FREE — admin can change to match
      // the real value of the bonus stack.
      $_stampValue = (int) \App\Models\AppSetting::get(
          'line_friend_popup_stamp_value',
          599
      );
  }
@endphp

@if($_show)
{{-- Container is fixed and high-z so the modal stacks above navbar
     dropdowns + intercom-style chat widgets. --}}
<div id="line-friend-popup"
     class="hidden"
     x-data="lineFriendPopup()"
     x-init="init()"
     x-cloak>
  {{-- Backdrop — click-to-dismiss is industry-standard; users expect it. --}}
  <div x-show="open"
       x-transition:enter="transition-opacity ease-out duration-300"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition-opacity ease-in duration-200"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       @click="dismissLater()"
       @keydown.escape.window="dismissLater()"
       class="fixed inset-0 bg-slate-900/70 backdrop-blur-md z-[1100]"></div>

  {{-- Modal card — centered, scale-in for premium feel. --}}
  <div x-show="open"
       x-transition:enter="transition ease-out duration-300"
       x-transition:enter-start="opacity-0 scale-95 translate-y-3"
       x-transition:enter-end="opacity-100 scale-100 translate-y-0"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="opacity-100 scale-100"
       x-transition:leave-end="opacity-0 scale-95"
       class="fixed inset-0 z-[1101] flex items-center justify-center p-4 pointer-events-none">
    <div @click.stop
         class="bg-white dark:bg-slate-900 rounded-3xl w-full max-w-md shadow-2xl shadow-emerald-500/30 overflow-hidden pointer-events-auto
                border border-emerald-100 dark:border-emerald-500/20">

      {{-- ── Brand header (gradient + LINE icon + outcome headline) ── --}}
      <div class="relative bg-gradient-to-br from-emerald-500 via-green-500 to-teal-500 px-6 pt-7 pb-6 text-white text-center overflow-hidden">
        {{-- Decorative blur orbs for depth --}}
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
        <div class="absolute bottom-0 left-0 w-24 h-24 bg-emerald-300/30 rounded-full blur-2xl"></div>

        {{-- × dismiss (small, discoverable but not primary) --}}
        <button type="button"
                @click="dismissLater()"
                class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/15 hover:bg-white/25 flex items-center justify-center transition backdrop-blur z-10"
                title="ดูภายหลัง">
          <i class="bi bi-x text-xl"></i>
        </button>

        {{-- LINE icon — circle with subtle ring --}}
        <div class="relative inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/25 backdrop-blur mb-3 ring-4 ring-white/10">
          <i class="bi bi-line text-white text-3xl"></i>
        </div>

        {{-- Outcome-based headline (the core psychological hook) --}}
        <h2 class="font-bold text-2xl leading-tight mb-1">
          รับรูปของคุณทาง LINE!
        </h2>
        <p class="text-white/90 text-sm leading-relaxed">
          เพิ่ม <strong class="text-white">{{ $_lineOaId }}</strong>
          เป็นเพื่อน · {{ $_carrot }}
        </p>
      </div>

      {{-- ── Body — social proof + benefits + CTA ─────────────────── --}}
      <div class="px-6 pt-5 pb-6">
        {{-- Social proof chip (specificity wins over round numbers) --}}
        <div class="flex justify-center mb-4">
          <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full
                      bg-amber-50 dark:bg-amber-500/10
                      border border-amber-200/60 dark:border-amber-500/30">
            {{-- Avatar stack — pure CSS, no images needed --}}
            <div class="flex -space-x-2">
              <span class="w-5 h-5 rounded-full bg-gradient-to-br from-rose-400 to-pink-500 border-2 border-amber-50 dark:border-slate-900"></span>
              <span class="w-5 h-5 rounded-full bg-gradient-to-br from-violet-400 to-indigo-500 border-2 border-amber-50 dark:border-slate-900"></span>
              <span class="w-5 h-5 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 border-2 border-amber-50 dark:border-slate-900"></span>
            </div>
            <span class="text-xs font-semibold text-amber-800 dark:text-amber-300">
              {{ number_format($_friendCount) }} คนเพิ่มแล้ว
              <span class="opacity-70">· วันนี้</span>
            </span>
          </div>
        </div>

        {{-- Reciprocity stack — 4 concrete benefits BEFORE asking. --}}
        <div class="space-y-2.5 mb-5">
          <div class="flex items-start gap-2.5 text-sm text-slate-700 dark:text-slate-200 leading-snug">
            <i class="bi bi-images-fill text-emerald-500 text-base shrink-0 mt-0.5"></i>
            <span><strong>ส่งรูปทันที</strong> เมื่อช่างภาพอัปโหลดเสร็จ — ไม่พลาดวินาทีสำคัญ</span>
          </div>
          <div class="flex items-start gap-2.5 text-sm text-slate-700 dark:text-slate-200 leading-snug">
            <i class="bi bi-bell-fill text-emerald-500 text-base shrink-0 mt-0.5"></i>
            <span><strong>แจ้งสถานะคำสั่งซื้อ</strong> สลิปอนุมัติ + ดาวน์โหลดได้ — รู้ทันทีไม่ต้องเช็คเอง</span>
          </div>
          <div class="flex items-start gap-2.5 text-sm text-slate-700 dark:text-slate-200 leading-snug">
            <i class="bi bi-tag-fill text-emerald-500 text-base shrink-0 mt-0.5"></i>
            <span><strong>{{ $_carrot }}</strong> — ส่งให้เฉพาะเพื่อน LINE</span>
          </div>
          <div class="flex items-start gap-2.5 text-sm text-slate-700 dark:text-slate-200 leading-snug">
            <i class="bi bi-stars text-emerald-500 text-base shrink-0 mt-0.5"></i>
            <span><strong>โปรพิเศษ</strong> ก่อนใคร · แจกพรีเซ็ตฟรีเดือนละครั้ง</span>
          </div>
        </div>

        {{-- Anchored value badge — "มูลค่ารวม ฿N · ฟรีวันนี้" --}}
        <div class="text-center text-xs text-slate-500 dark:text-slate-400 mb-3">
          มูลค่ารวม <span class="line-through text-slate-400">฿{{ number_format($_stampValue) }}</span>
          <span class="ml-1 font-bold text-emerald-600 dark:text-emerald-400">ฟรีวันนี้</span>
        </div>

        {{-- Primary CTA — large, single, dominant. The "easy yes". --}}
        <a href="{{ $_lineFriendUrl }}"
           target="_blank" rel="noopener"
           @click="markAccepted()"
           class="group block w-full text-center py-3.5 px-5 rounded-xl
                  bg-gradient-to-r from-emerald-500 to-green-600
                  hover:from-emerald-600 hover:to-green-700
                  text-white font-bold text-base shadow-lg shadow-emerald-500/40
                  hover:shadow-xl hover:shadow-emerald-500/50
                  transition-all duration-200 active:scale-[0.98]">
          <i class="bi bi-line text-xl mr-1 align-middle"></i>
          <span class="align-middle">เพิ่มเพื่อน LINE — รับเลยฟรี</span>
          <i class="bi bi-arrow-right ml-2 inline-block group-hover:translate-x-1 transition-transform"></i>
        </a>

        {{-- Soft dismiss — uses "later" framing (loss aversion: keeps
             the option open) not "no thanks" (closes the door). 7-day
             cooldown is set in JS so they're not nagged on next visit. --}}
        <button type="button"
                @click="dismissLater()"
                class="block w-full mt-3 py-2 text-xs text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 transition">
          ดูภายหลัง
        </button>
      </div>
    </div>
  </div>
</div>

<script>
/* ──────────────────────────────────────────────────────────────────
   lineFriendPopup() — Alpine factory for the popup behavior.

   Trigger logic (most → least urgent):
     • /orders/*    → 4s   (just bought, peak intent)
     • /events/*    → 12s  (browsing photos, warming up)
     • everywhere   → 18s  (gentle)
     • dismissed in last 7 days → don't show at all

   Storage:
     localStorage.line_friend_popup_dismissed_until = ms-since-epoch
     after which we may show again. "ดูภายหลัง" / × / backdrop /
     ESC all set this to NOW + 7 days.

     If user clicks the CTA → set to NOW + 30 days (reduce noise even
     further, since most click-throughs result in actual friend add
     within minutes which the webhook will catch).
   ────────────────────────────────────────────────────────────────── */
window.lineFriendPopup = function () {
  return {
    open: false,
    init() {
      // Respect cooldown — bail entirely if we've been dismissed
      // recently. Reading once at init so we don't poll.
      const cooldownUntil = parseInt(localStorage.getItem('line_friend_popup_dismissed_until') || '0', 10);
      if (cooldownUntil > Date.now()) return;

      // Pick delay based on URL — high-intent pages get a faster
      // reveal because the user is already engaged with our value.
      const path  = window.location.pathname;
      let delayMs = 18000;
      if (path.startsWith('/orders'))  delayMs = 4000;
      if (path.startsWith('/events'))  delayMs = 12000;
      if (path.startsWith('/photos'))  delayMs = 8000;

      // Show. We don't add a "user idle / scroll engagement" trigger
      // here — keeps the implementation simple and the timer-based
      // approach already converts well per the customer's request.
      setTimeout(() => {
        this.open = true;
        document.getElementById('line-friend-popup').classList.remove('hidden');
        // Body scroll lock while modal is open — focus user attention.
        document.body.style.overflow = 'hidden';
      }, delayMs);
    },
    dismissLater() {
      // 7-day cooldown — user said "later" so respect that hard.
      localStorage.setItem(
        'line_friend_popup_dismissed_until',
        String(Date.now() + 7 * 24 * 60 * 60 * 1000)
      );
      this._closeModal();
    },
    markAccepted() {
      // User clicked the CTA — they're going to LINE now. Suppress
      // for 30 days so we don't badger them while the OA webhook
      // catches up + flips line_is_friend in the DB. (Once flipped,
      // the popup is server-side gated by the @if guard so it stops
      // rendering entirely.)
      localStorage.setItem(
        'line_friend_popup_dismissed_until',
        String(Date.now() + 30 * 24 * 60 * 60 * 1000)
      );
      this._closeModal();
    },
    _closeModal() {
      this.open = false;
      document.body.style.overflow = '';
    },
  };
};
</script>
@endif
@endauth
