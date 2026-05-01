{{--
  Face Bundle Modal — "เหมารูปตัวเอง"
  ──────────────────────────────────
  Dialog that appears when:
    • The buyer clicks the "ค้นหารูปของฉัน" CTA on a face_match bundle card.
    • The buyer completes a face search and the result-count is high enough
      to justify upselling them to a bundle (handled by JS hook below).

  Two states:
    1. Pre-search:  shows the upload-selfie form. Once the user uploads a
                    photo, JS calls /api/face-search and on response the
                    modal switches to state 2.
    2. Post-search: shows "we found N photos of you, normally ฿X, bundle ฿Y"
                    + breakdown + "Add to cart" CTA.

  The actual face-search request reuses the existing FaceSearchService —
  this modal is just a different UI shell over the same endpoint.

  Renders inline (the cards' onclick opens it via showModal()) so the buyer
  sees no page transition. State management is plain Alpine + a single
  fetch() call for the quote.
--}}

@php
  // Find the active face_match bundle for this event so the modal knows
  // which package to quote. Defensively skip rendering if there is none.
  $faceBundle = $packages->first(fn ($p) => $p->bundle_type === 'face_match' && $p->is_active);
@endphp

@if($faceBundle)
<dialog id="face-bundle-modal" class="rounded-2xl backdrop:bg-black/60 backdrop:backdrop-blur-sm p-0 max-w-md w-full mx-auto bg-white dark:bg-slate-800 shadow-2xl">
  <div x-data="faceBundleModal({{ $event->id }}, {{ $faceBundle->id }})" class="p-6">

    {{-- Close button --}}
    <button @click="close()" class="absolute top-3 right-3 w-8 h-8 rounded-full hover:bg-gray-100 dark:hover:bg-white/[0.06] flex items-center justify-center transition">
      <i class="bi bi-x-lg text-gray-400"></i>
    </button>

    {{-- ── State 1: Pre-search ─────────────────────────────────── --}}
    <div x-show="step === 'upload'" class="text-center">
      <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-pink-500 to-rose-600 flex items-center justify-center mx-auto mb-4">
        <i class="bi bi-person-bounding-box text-3xl text-white"></i>
      </div>
      <h3 class="font-bold text-lg mb-1">เหมารูปตัวเอง</h3>
      <p class="text-sm text-gray-500 mb-5">
        อัปโหลดรูปเซลฟี่ของคุณ — ระบบจะใช้ AI ค้นหารูปทั้งหมดของคุณในอีเวนต์ และให้ส่วนลด <span class="font-bold text-pink-500">{{ (int) $faceBundle->discount_pct }}%</span> เมื่อซื้อทั้งชุด
      </p>

      {{-- Upload area --}}
      <label for="face-bundle-selfie" class="block border-2 border-dashed border-gray-200 dark:border-white/[0.1] hover:border-pink-400 dark:hover:border-pink-500/40 rounded-xl p-6 cursor-pointer transition mb-4">
        <i class="bi bi-cloud-upload text-3xl text-gray-300 dark:text-white/[0.2] mb-2"></i>
        <div class="text-sm font-semibold mb-0.5">อัปโหลดรูปเซลฟี่</div>
        <div class="text-xs text-gray-400">กดเพื่อเลือกรูป (JPG / PNG)</div>
        <input id="face-bundle-selfie" type="file" accept="image/*" class="hidden" @change="search($event)">
      </label>

      <div class="text-[11px] text-gray-400 leading-relaxed">
        <i class="bi bi-shield-lock mr-0.5"></i>
        รูปเซลฟี่จะถูกใช้แค่ค้นหา ไม่ถูกเก็บถาวร
      </div>
    </div>

    {{-- ── State 2: Searching ────────────────────────────────── --}}
    <div x-show="step === 'searching'" class="text-center py-8">
      <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-pink-500 to-rose-600 flex items-center justify-center mx-auto mb-4 animate-pulse">
        <i class="bi bi-search text-3xl text-white"></i>
      </div>
      <h3 class="font-bold text-lg mb-1">กำลังค้นหา…</h3>
      <p class="text-sm text-gray-500">AI กำลังตรวจหารูปของคุณในอีเวนต์</p>
    </div>

    {{-- ── State 3: Result + Quote ──────────────────────────── --}}
    <div x-show="step === 'result' && quote" class="text-center">
      <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500 to-green-600 flex items-center justify-center mx-auto mb-4">
        <i class="bi bi-check-lg text-3xl text-white"></i>
      </div>
      <h3 class="font-bold text-lg mb-1">
        🎯 พบรูปของคุณ <span class="text-pink-500" x-text="quote?.photo_count"></span> รูป!
      </h3>

      {{-- Pricing breakdown --}}
      <div class="bg-gray-50 dark:bg-slate-700 rounded-xl p-4 my-4 text-left">
        <div class="flex items-center justify-between text-sm mb-2">
          <span class="text-gray-500">ซื้อแยกทีละรูป</span>
          <span class="line-through text-gray-400" x-text="'฿' + (quote?.original_price || 0).toLocaleString()"></span>
        </div>
        <div class="flex items-center justify-between text-sm mb-2">
          <span class="text-gray-500">ส่วนลด</span>
          <span class="text-emerald-600 font-semibold" x-text="'-' + quote?.savings_pct + '%'"></span>
        </div>
        <div class="border-t border-gray-200 dark:border-white/[0.1] my-2"></div>
        <div class="flex items-center justify-between">
          <span class="font-bold">ราคาเหมา</span>
          <span class="text-2xl font-bold text-pink-600" x-text="'฿' + (quote?.price || 0).toLocaleString()"></span>
        </div>
        <div class="text-right text-xs text-gray-400 mt-1" x-text="'เพียง ฿' + (quote?.per_photo_price || 0) + '/รูป'"></div>
      </div>

      {{-- Savings badge --}}
      <div class="inline-flex items-center gap-1 bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-bold px-4 py-1.5 rounded-full mb-4">
        💰 ประหยัด ฿<span x-text="(quote?.savings || 0).toLocaleString()"></span>!
      </div>

      {{-- CTA --}}
      <button @click="addToCart()" :disabled="adding" class="w-full py-3 rounded-xl font-bold text-white bg-gradient-to-br from-pink-500 to-rose-600 hover:from-pink-600 hover:to-rose-700 transition disabled:opacity-50">
        <i class="bi bi-bag-plus mr-1"></i>
        <span x-show="!adding">ซื้อทั้งหมด ฿<span x-text="(quote?.price || 0).toLocaleString()"></span></span>
        <span x-show="adding"><i class="bi bi-arrow-clockwise animate-spin"></i> กำลังเพิ่มลงตะกร้า…</span>
      </button>

      <button @click="step = 'upload'; quote = null" class="block w-full mt-2 text-xs text-gray-400 hover:text-gray-600 transition">
        ← ค้นหาใหม่
      </button>
    </div>

    {{-- ── State 4: Empty result ───────────────────────────── --}}
    <div x-show="step === 'empty'" class="text-center py-6">
      <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-white/[0.06] flex items-center justify-center mx-auto mb-4">
        <i class="bi bi-emoji-frown text-3xl text-gray-400"></i>
      </div>
      <h3 class="font-bold text-lg mb-1">ไม่พบรูปของคุณ</h3>
      <p class="text-sm text-gray-500 mb-4">อาจไม่มีรูปคุณในอีเวนต์ หรือมุมกล้องไม่ชัดพอ</p>
      <button @click="step = 'upload'" class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-white/[0.06] text-sm">ลองใหม่</button>
    </div>

    {{-- ── State 5: Error ───────────────────────────────────── --}}
    <div x-show="step === 'error'" class="text-center py-6">
      <div class="w-16 h-16 rounded-2xl bg-red-50 dark:bg-red-500/15 flex items-center justify-center mx-auto mb-4">
        <i class="bi bi-exclamation-triangle text-3xl text-red-500"></i>
      </div>
      <h3 class="font-bold text-lg mb-1">เกิดข้อผิดพลาด</h3>
      <p class="text-sm text-gray-500 mb-4" x-text="errorMsg"></p>
      <button @click="step = 'upload'" class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-white/[0.06] text-sm">ลองใหม่</button>
    </div>

  </div>
</dialog>

<script>
function faceBundleModal(eventId, packageId) {
  return {
    step: 'upload',
    quote: null,
    adding: false,
    errorMsg: '',

    close() {
      const el = document.getElementById('face-bundle-modal');
      el?.close();
      this.step = 'upload';
      this.quote = null;
    },

    async search(e) {
      const file = e.target.files?.[0];
      if (!file) return;

      this.step = 'searching';

      try {
        // 1. Run face search via existing endpoint to get matching photo IDs
        const fd = new FormData();
        fd.append('selfie', file);
        fd.append('event_id', eventId);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const searchResp = await fetch('/face-search', {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
          body: fd,
        });
        if (!searchResp.ok) throw new Error('Face search failed');
        const searchData = await searchResp.json();
        const photoIds = (searchData.matches || []).map(m => m.photo_id || m.id).filter(Boolean);

        if (photoIds.length === 0) {
          this.step = 'empty';
          return;
        }

        // 2. Get bundle quote
        const quoteResp = await fetch('/api/cart/face-bundle/quote', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
          },
          body: JSON.stringify({ event_id: eventId, package_id: packageId, photo_ids: photoIds }),
        });
        if (!quoteResp.ok) throw new Error('Quote failed');
        const quoteData = await quoteResp.json();

        this.quote = { ...quoteData.quote, photo_ids: photoIds };
        this.step = 'result';
      } catch (err) {
        this.errorMsg = err.message || 'ลองใหม่อีกครั้ง';
        this.step = 'error';
      }
    },

    async addToCart() {
      if (!this.quote || this.adding) return;
      this.adding = true;
      try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const resp = await fetch('/api/cart/face-bundle/add', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
          },
          body: JSON.stringify({
            event_id: eventId,
            package_id: packageId,
            photo_ids: this.quote.photo_ids,
          }),
        });
        if (!resp.ok) throw new Error('Add failed');
        // Redirect to cart so the buyer immediately sees the bundle.
        window.location.href = '/cart';
      } catch (err) {
        this.errorMsg = err.message || 'เพิ่มลงตะกร้าไม่สำเร็จ';
        this.step = 'error';
      } finally {
        this.adding = false;
      }
    },
  };
}
</script>
@endif
