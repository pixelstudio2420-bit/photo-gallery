{{--
  Bundle Cards Section — public event page
  ─────────────────────────────────────────
  Shows 3-5 cards horizontally so the buyer sees the savings ladder before
  they start picking individual photos.

  Pulls $packages (Collection of PricingPackage) which is already loaded by
  EventController::show(). Each card includes:
    • Bundle name + savings badge ("ขายดีที่สุด", "ประหยัด 20%")
    • Slashed-out original price + bundle price (loss-aversion frame)
    • Per-photo math ("เพียง ฿80/รูป")
    • Action button — count bundles open the photo picker, face_match opens
      the face-search modal, event_all goes straight to checkout.

  The "Most Popular" highlight uses ring-2 + scale-105 styling so the eye
  jumps to it. We default to is_featured=true on the 6-photo bundle when
  seeding — the photographer can pin a different one from the admin.
--}}

@php
  $activeCount = isset($packages) ? $packages->where('is_active', true)->count() : 0;
  // Static-resolution of the desktop column count keeps Tailwind v4's
  // class scanner happy (interpolated `lg:grid-cols-{{ N }}` strings get
  // skipped by the @source pass and the class never makes it to CSS).
  // We pick the densest layout the bundle count justifies, capped at 5.
  $lgColsClass = match (true) {
      $activeCount >= 5 => 'lg:grid-cols-5',
      $activeCount === 4 => 'lg:grid-cols-4',
      default            => 'lg:grid-cols-3',
  };
@endphp

@if($activeCount > 0)
<section class="my-8" id="bundle-cards"
         x-data="{
           open: window.matchMedia('(min-width: 768px)').matches,
           init() {
             // Re-evaluate when the user resizes between mobile / desktop
             // so the cards stay visible on rotated tablets, etc.
             window.addEventListener('resize', () => {
               if (window.matchMedia('(min-width: 768px)').matches) this.open = true;
             });
           }
         }">
  <div class="max-w-6xl mx-auto px-4">

    {{-- ── Mobile: collapsible header bar. Hidden on md+ where the cards
         render in their permanent grid above. --}}
    <button
      type="button"
      @click="open = !open"
      class="md:hidden w-full flex items-center justify-between gap-3 px-4 py-3 mb-3 rounded-2xl bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-500/10 dark:to-orange-500/10 border-2 border-amber-300 dark:border-amber-500/30 transition active:scale-[0.99]">
      <div class="flex items-center gap-3 min-w-0">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shrink-0 shadow-md">
          <i class="bi bi-lightning-charge-fill text-white text-lg"></i>
        </div>
        <div class="text-left min-w-0">
          <div class="font-bold text-base text-amber-900 dark:text-amber-200 leading-tight truncate">
            เลือกแพ็กเกจคุ้มค่า
          </div>
          <div class="text-[11px] text-amber-700 dark:text-amber-400/80 mt-0.5">
            <span x-show="!open">{{ $activeCount }} แพ็กเกจ · ลดสูงสุด 50%</span>
            <span x-show="open" x-cloak>แตะเพื่อปิด</span>
          </div>
        </div>
      </div>
      <i class="bi bi-chevron-down text-amber-700 dark:text-amber-400 text-xl transition-transform shrink-0"
         :class="open ? 'rotate-180' : ''"></i>
    </button>

    {{-- ── Desktop heading (md+) — always shown. --}}
    <div class="hidden md:block text-center mb-6">
      <h2 class="text-2xl md:text-3xl font-bold mb-2">
        <i class="bi bi-lightning-charge text-amber-500"></i> เลือกแพ็กเกจคุ้มค่า
      </h2>
      <p class="text-gray-500 text-sm">
        ยิ่งซื้อเยอะ ยิ่งประหยัด — ราคาต่อรูปลดลงสูงสุด 50%
      </p>
    </div>

    {{-- ── Cards container.
         Mobile: x-show toggled by `open`, x-collapse animates the slide.
         Desktop (md+): `open` initializes to true via matchMedia in
         x-data init, so x-show evaluates true and the cards stay
         expanded. The grid is laid out with a static lg:grid-cols class
         resolved server-side (see $lgColsClass) so Tailwind's class
         scanner picks it up.
    --}}
    <div
      x-show="open"
      x-collapse
      class="grid grid-cols-2 md:grid-cols-3 {{ $lgColsClass }} gap-3 md:gap-4">
      @foreach($packages->where('is_active', true)->sortBy('sort_order') as $pkg)
        @php
          $isCount     = $pkg->bundle_type === 'count';
          $isFace      = $pkg->bundle_type === 'face_match';
          $isEventAll  = $pkg->bundle_type === 'event_all';
          $perPhoto    = $isCount && $pkg->photo_count ? round((float)$pkg->price / $pkg->photo_count, 2) : null;
          $savingsPct  = $pkg->original_price && $pkg->original_price > $pkg->price
              ? round(((float)$pkg->original_price - (float)$pkg->price) / (float)$pkg->original_price * 100)
              : 0;
        @endphp

        <div class="relative rounded-2xl overflow-hidden transition-all duration-200 flex flex-col
                    {{ $pkg->is_featured
                        ? 'ring-2 ring-amber-400 shadow-xl bg-gradient-to-br from-amber-50 via-white to-amber-50 dark:from-amber-500/10 dark:via-slate-800 dark:to-amber-500/5 md:-translate-y-1'
                        : 'bg-white dark:bg-slate-800 border border-gray-200 dark:border-white/[0.06] shadow-sm hover:shadow-md hover:-translate-y-0.5' }}">

          {{-- Featured ribbon — corner badge that doesn't compete with price --}}
          @if($pkg->is_featured)
            <div class="absolute top-0 left-1/2 -translate-x-1/2 bg-gradient-to-r from-amber-400 to-yellow-300 text-amber-900 text-[10px] font-bold px-3 py-1 rounded-b-lg shadow-md whitespace-nowrap">
              <i class="bi bi-star-fill mr-0.5"></i> ขายดีที่สุด
            </div>
          @endif

          {{-- Badge (top-right corner, doesn't overlap featured ribbon) --}}
          @if($pkg->badge && !$pkg->is_featured)
            <div class="absolute top-2 right-2 bg-indigo-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
              {{ $pkg->badge }}
            </div>
          @endif

          <div class="p-4 md:p-5 {{ $pkg->is_featured ? 'pt-9' : 'pt-7' }} flex-1 flex flex-col">
            {{-- Type icon --}}
            <div class="w-11 h-11 md:w-12 md:h-12 rounded-xl flex items-center justify-center mb-2.5 mx-auto
              @if($isFace) bg-pink-500/10 text-pink-500
              @elseif($isEventAll) bg-purple-500/10 text-purple-500
              @else bg-indigo-500/10 text-indigo-500
              @endif">
              <i class="bi text-lg md:text-xl
                @if($isFace) bi-person-bounding-box
                @elseif($isEventAll) bi-collection
                @else bi-stack
                @endif"></i>
            </div>

            {{-- Name --}}
            <h3 class="font-bold text-center text-sm md:text-base mb-0.5 leading-tight">{{ $pkg->name }}</h3>
            @if($pkg->bundle_subtitle)
              <p class="text-center text-[10px] md:text-[11px] text-gray-500 dark:text-gray-400 mb-2.5 leading-tight line-clamp-2">{{ $pkg->bundle_subtitle }}</p>
            @else
              <div class="mb-2.5"></div>
            @endif

            {{-- Pricing Display.
                 Loss-aversion frame: show "ประหยัด ฿X" (absolute amount)
                 above "ลด N%" (percentage) — Thai consumers respond more
                 strongly to the dollar amount they're saving than to the
                 percentage off, especially above ฿500 of savings. --}}
            @php
              $savingsAmount = $pkg->original_price && $pkg->original_price > $pkg->price
                  ? (float) $pkg->original_price - (float) $pkg->price
                  : 0;
            @endphp
            <div class="text-center mb-3">
              @if($isFace)
                <div class="text-[10px] text-gray-400 mb-0.5">ส่วนลดสูงสุด {{ (int) $pkg->discount_pct }}%</div>
                <div class="text-base md:text-lg font-bold text-pink-600 dark:text-pink-400 leading-tight">ราคาผันแปร</div>
                <div class="text-[10px] text-gray-500 mt-0.5">สูงสุด ฿{{ number_format($pkg->max_price, 0) }}</div>
              @else
                @if($pkg->original_price && $pkg->original_price > $pkg->price)
                  <div class="text-[11px] text-gray-400 line-through">฿{{ number_format($pkg->original_price, 0) }}</div>
                @endif
                <div class="text-xl md:text-2xl lg:text-3xl font-extrabold leading-none {{ $pkg->is_featured ? 'text-amber-600 dark:text-amber-400' : 'text-indigo-600 dark:text-indigo-400' }}">
                  ฿{{ number_format($pkg->price, 0) }}
                </div>
                @if($savingsAmount > 0)
                  <div class="text-[11px] text-emerald-600 font-bold mt-1">
                    💰 ประหยัด ฿{{ number_format($savingsAmount, 0) }}
                  </div>
                @endif
                @if($perPhoto)
                  <div class="text-[10px] text-gray-500 mt-0.5">฿{{ number_format($perPhoto, 0) }}/รูป</div>
                @endif
              @endif
            </div>

            {{-- Spacer pushes the CTA to the bottom of every card so the
                 buttons align horizontally across cards of varying
                 height (different subtitles, etc.) --}}
            <div class="flex-1"></div>

            {{-- CTA --}}
            @if($isFace)
              {{-- Face-bundle modal is only rendered when @auth (the
                   /api/cart/face-bundle/* endpoints require auth). For
                   anonymous browsers, the same button takes them to login
                   first with an `intended` URL back to this event page. --}}
              @auth
                <button type="button" onclick="document.getElementById('face-bundle-modal')?.showModal();"
                        class="w-full py-2 rounded-xl text-xs md:text-sm font-semibold transition
                               bg-gradient-to-br from-pink-500 to-rose-600 hover:from-pink-600 hover:to-rose-700 text-white">
                  <i class="bi bi-search mr-1"></i> ค้นหารูปของฉัน
                </button>
              @else
                <a href="{{ route('login') }}?intended={{ urlencode(request()->fullUrl()) }}"
                   class="w-full block text-center py-2 rounded-xl text-xs md:text-sm font-semibold transition
                          bg-gradient-to-br from-pink-500 to-rose-600 hover:from-pink-600 hover:to-rose-700 text-white">
                  <i class="bi bi-search mr-1"></i> เข้าสู่ระบบเพื่อค้นหา
                </a>
              @endauth
            @elseif($isEventAll)
              @auth
                <form method="POST" action="/api/cart/bundle" data-bundle-add class="m-0"
                      onsubmit="event.preventDefault(); fetch(this.action, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }, body: JSON.stringify({ event_id: {{ $event->id }}, package_id: {{ $pkg->id }}, photo_ids: ['all'] }) }).then(r => r.ok ? window.location.href = '/cart' : alert('เพิ่มลงตะกร้าไม่สำเร็จ'));">
                  @csrf
                  <button type="submit" class="w-full py-2 rounded-xl text-xs md:text-sm font-semibold transition
                                                bg-gradient-to-br from-purple-500 to-violet-600 hover:from-purple-600 hover:to-violet-700 text-white">
                    <i class="bi bi-bag-plus mr-1"></i> ซื้อเลย
                  </button>
                </form>
              @else
                <a href="{{ route('login') }}?intended={{ urlencode(request()->fullUrl()) }}"
                   class="w-full block text-center py-2 rounded-xl text-xs md:text-sm font-semibold transition
                          bg-gradient-to-br from-purple-500 to-violet-600 hover:from-purple-600 hover:to-violet-700 text-white">
                  <i class="bi bi-bag-plus mr-1"></i> เข้าสู่ระบบเพื่อซื้อ
                </a>
              @endauth
            @else
              <button type="button" data-pkg-select="{{ $pkg->id }}" data-pkg-count="{{ $pkg->photo_count }}"
                      class="w-full py-2 rounded-xl text-xs md:text-sm font-semibold transition
                             {{ $pkg->is_featured
                                 ? 'bg-gradient-to-br from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white'
                                 : 'bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400 dark:hover:bg-indigo-500/20' }}">
                <i class="bi bi-check2-circle mr-1"></i> เลือก {{ $pkg->photo_count }} รูป
              </button>
            @endif

            {{-- Description --}}
            @if($pkg->description)
              <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-3 text-center leading-snug">{{ $pkg->description }}</p>
            @endif
          </div>
        </div>
      @endforeach
    </div>

    {{-- Trust signals --}}
    <div class="mt-6 flex items-center justify-center gap-6 text-xs text-gray-500 flex-wrap">
      <span class="flex items-center gap-1"><i class="bi bi-shield-check text-emerald-500"></i> ดาวน์โหลดทันทีหลังชำระเงิน</span>
      <span class="flex items-center gap-1"><i class="bi bi-camera text-blue-500"></i> ภาพคุณภาพเต็ม HD</span>
      <span class="flex items-center gap-1"><i class="bi bi-lock text-purple-500"></i> รองรับ PromptPay / โอนธนาคาร</span>
    </div>
  </div>
</section>

{{-- Bundle picker JS — handles "Select N photos" buttons --}}
<script>
(function() {
  document.querySelectorAll('[data-pkg-select]').forEach(btn => {
    btn.addEventListener('click', function() {
      const pkgId = this.getAttribute('data-pkg-select');
      const count = parseInt(this.getAttribute('data-pkg-count') || '0', 10);
      // Tell the gallery to switch into "select N photos for bundle" mode.
      window.dispatchEvent(new CustomEvent('bundle:select-mode', {
        detail: { packageId: pkgId, photoCount: count }
      }));
      // Scroll to gallery so the buyer immediately sees photos to pick.
      const gallery = document.getElementById('event-gallery') || document.querySelector('[data-photo-gallery]');
      if (gallery) gallery.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
})();
</script>
@endif
