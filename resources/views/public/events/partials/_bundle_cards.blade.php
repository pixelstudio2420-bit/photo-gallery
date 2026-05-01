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

@if(isset($packages) && $packages->where('is_active', true)->count() > 0)
<section class="my-8" id="bundle-cards">
  <div class="max-w-6xl mx-auto px-4">
    <div class="text-center mb-6">
      <h2 class="text-2xl md:text-3xl font-bold mb-2">
        <i class="bi bi-lightning-charge text-amber-500"></i> เลือกแพ็กเกจคุ้มค่า
      </h2>
      <p class="text-gray-500 text-sm">
        ยิ่งซื้อเยอะ ยิ่งประหยัด — ราคาต่อรูปลดลงสูงสุด 50%
      </p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-{{ min(5, $packages->where('is_active', true)->count()) }} gap-3 md:gap-4">
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

        <div class="relative rounded-2xl overflow-hidden transition-all duration-200
                    {{ $pkg->is_featured
                        ? 'ring-2 ring-amber-400 shadow-2xl scale-100 md:scale-105 bg-gradient-to-br from-amber-50 via-white to-amber-50 dark:from-amber-500/10 dark:via-slate-800 dark:to-amber-500/5'
                        : 'bg-white dark:bg-slate-800 border border-gray-200 dark:border-white/[0.06] shadow-sm hover:shadow-md' }}">

          {{-- Featured ribbon --}}
          @if($pkg->is_featured)
            <div class="absolute top-0 right-0 bg-gradient-to-l from-amber-400 to-yellow-300 text-amber-900 text-[10px] font-bold px-3 py-1 rounded-bl-xl">
              <i class="bi bi-star-fill mr-0.5"></i> ขายดีที่สุด
            </div>
          @endif

          {{-- Badge (top-left) --}}
          @if($pkg->badge && !$pkg->is_featured)
            <div class="absolute top-2 left-2 bg-indigo-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
              {{ $pkg->badge }}
            </div>
          @endif

          <div class="p-4 md:p-5 pt-8">
            {{-- Type icon --}}
            <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-3 mx-auto
              @if($isFace) bg-pink-500/10 text-pink-500
              @elseif($isEventAll) bg-purple-500/10 text-purple-500
              @else bg-indigo-500/10 text-indigo-500
              @endif">
              <i class="bi text-xl
                @if($isFace) bi-person-bounding-box
                @elseif($isEventAll) bi-collection
                @else bi-stack
                @endif"></i>
            </div>

            {{-- Name --}}
            <h3 class="font-bold text-center text-base md:text-lg mb-1">{{ $pkg->name }}</h3>
            @if($pkg->bundle_subtitle)
              <p class="text-center text-[11px] text-gray-500 dark:text-gray-400 mb-3 leading-tight">{{ $pkg->bundle_subtitle }}</p>
            @endif

            {{-- Pricing Display --}}
            <div class="text-center mb-4">
              @if($isFace)
                <div class="text-xs text-gray-400 mb-0.5">ลด {{ (int) $pkg->discount_pct }}%</div>
                <div class="text-2xl font-bold text-pink-600 dark:text-pink-400">ราคาผันแปร</div>
                <div class="text-[11px] text-gray-500">สูงสุด ฿{{ number_format($pkg->max_price, 0) }}</div>
              @else
                @if($pkg->original_price && $pkg->original_price > $pkg->price)
                  <div class="text-xs text-gray-400 line-through">฿{{ number_format($pkg->original_price, 0) }}</div>
                @endif
                <div class="text-2xl md:text-3xl font-bold {{ $pkg->is_featured ? 'text-amber-600 dark:text-amber-400' : 'text-indigo-600 dark:text-indigo-400' }}">
                  ฿{{ number_format($pkg->price, 0) }}
                </div>
                @if($savingsPct > 0)
                  <div class="text-[11px] text-emerald-600 font-semibold mt-0.5">ประหยัด {{ $savingsPct }}%</div>
                @endif
                @if($perPhoto)
                  <div class="text-[11px] text-gray-500 mt-1">เพียง ฿{{ number_format($perPhoto, 0) }}/รูป</div>
                @endif
              @endif
            </div>

            {{-- CTA --}}
            @if($isFace)
              <button type="button" onclick="document.getElementById('face-bundle-modal')?.showModal();"
                      class="w-full py-2 rounded-xl text-xs md:text-sm font-semibold transition
                             bg-gradient-to-br from-pink-500 to-rose-600 hover:from-pink-600 hover:to-rose-700 text-white">
                <i class="bi bi-search mr-1"></i> ค้นหารูปของฉัน
              </button>
            @elseif($isEventAll)
              <form method="POST" action="{{ route('cart.add') ?? '#' }}" data-bundle-add class="m-0">
                @csrf
                <input type="hidden" name="event_id" value="{{ $event->id }}">
                <input type="hidden" name="package_id" value="{{ $pkg->id }}">
                <button type="submit" class="w-full py-2 rounded-xl text-xs md:text-sm font-semibold transition
                                              bg-gradient-to-br from-purple-500 to-violet-600 hover:from-purple-600 hover:to-violet-700 text-white">
                  <i class="bi bi-bag-plus mr-1"></i> ซื้อเลย
                </button>
              </form>
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
