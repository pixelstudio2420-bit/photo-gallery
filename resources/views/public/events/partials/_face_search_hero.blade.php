{{--
  Face Search Hero CTA
  ─────────────────────
  Replaces the inline face_match bundle card with a dedicated hero
  block that sits above the regular bundle cards. The variable-price
  flow doesn't fit well alongside fixed-price cards (the "ราคาผันแปร"
  label confuses buyers and the empty 0฿ placeholder undercuts trust),
  so we lift the killer feature into a more deserved position with:

    • A clear value prop (find ALL your photos with AI)
    • Concrete example pricing computed from per_photo
    • Strikethrough showing the discount in real-baht terms
    • A single primary CTA that opens the existing face_bundle_modal

  The button hooks into `face-bundle-modal` (already rendered in
  events/show via @include('partials._face_bundle_modal')) so the
  upload + search + quote + add-to-cart flow stays unchanged.

  Auth-gated: anonymous browsers can't add to the cart anyway
  (the API endpoints require auth), so we redirect them to login
  with the current event URL stored in `intended` so they come
  back to the right place after signing in.

  No-render conditions (the partial silently outputs nothing):
    - $packages collection missing
    - No active face_match bundle exists for this event (the
      photographer has either never enabled face_match for this
      event or has explicitly turned it off)
--}}

@php
  $faceBundle = isset($packages)
      ? $packages->first(fn ($p) => $p->bundle_type === 'face_match' && $p->is_active)
      : null;
@endphp

@if($faceBundle)
  @php
    $eventPerPhoto = (float) ($event->price_per_photo ?? 0);
    $discountPct   = (float) ($faceBundle->discount_pct ?? 50);
    $maxPrice      = (float) ($faceBundle->max_price ?? 1500);

    // Two example calculations to anchor the buyer's expectation —
    // a low estimate (5 matches) and a high one (15 matches), each
    // capped at $maxPrice so the numbers stay realistic.
    $exampleLow   = $eventPerPhoto > 0
        ? min(round(5 * $eventPerPhoto * (1 - $discountPct / 100)), $maxPrice)
        : null;
    $exampleHigh  = $eventPerPhoto > 0
        ? min(round(15 * $eventPerPhoto * (1 - $discountPct / 100)), $maxPrice)
        : null;
    $originalLow  = $eventPerPhoto > 0 ? 5 * $eventPerPhoto : null;
    $originalHigh = $eventPerPhoto > 0 ? 15 * $eventPerPhoto : null;
  @endphp

  <section class="my-6 md:my-8" id="face-search-hero">
    <div class="max-w-6xl mx-auto px-4">
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-pink-500 via-rose-500 to-fuchsia-600 p-6 md:p-8 text-white shadow-2xl">

        {{-- Decorative blur dots — pure CSS, no images. --}}
        <div class="absolute -top-12 -right-12 w-48 h-48 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-16 -left-16 w-56 h-56 bg-white/10 rounded-full blur-3xl"></div>

        <div class="relative grid grid-cols-1 md:grid-cols-2 gap-6 items-center">

          {{-- Left: copy + CTA --}}
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-xs font-bold mb-3">
              <i class="bi bi-stars"></i>
              <span>AI Face Recognition</span>
            </div>

            <h3 class="text-2xl md:text-3xl font-extrabold mb-2 leading-tight">
              ค้นหารูปของคุณด้วย AI<br>
              <span class="text-yellow-200">ลด {{ (int) $discountPct }}% ทันที</span>
            </h3>

            <p class="text-white/90 text-sm md:text-base mb-5 leading-relaxed">
              อัปโหลดรูปเซลฟี่ — ระบบจะหารูปทั้งหมดของคุณในอีเวนต์นี้ ไม่ว่ากี่รูปก็ได้ราคาเหมาที่ถูกที่สุด
            </p>

            {{-- Primary CTA — auth-gated --}}
            @auth
              <button type="button"
                      onclick="document.getElementById('face-bundle-modal')?.showModal();"
                      class="inline-flex items-center gap-2 bg-white text-rose-600 hover:bg-yellow-100 font-bold px-6 py-3 rounded-xl shadow-lg transition active:scale-95">
                <i class="bi bi-search text-lg"></i>
                <span>ค้นหารูปของฉัน</span>
                <i class="bi bi-arrow-right text-lg"></i>
              </button>
            @else
              <a href="{{ route('login') }}?intended={{ urlencode(request()->fullUrl()) }}"
                 class="inline-flex items-center gap-2 bg-white text-rose-600 hover:bg-yellow-100 font-bold px-6 py-3 rounded-xl shadow-lg transition active:scale-95">
                <i class="bi bi-search text-lg"></i>
                <span>เข้าสู่ระบบเพื่อค้นหา</span>
                <i class="bi bi-arrow-right text-lg"></i>
              </a>
            @endauth

            <div class="mt-3 text-[11px] text-white/70 flex items-center gap-1.5">
              <i class="bi bi-shield-lock"></i>
              <span>รูปเซลฟี่ใช้แค่ค้นหา ไม่เก็บถาวร</span>
            </div>
          </div>

          {{-- Right: example pricing card --}}
          <div class="bg-white/15 backdrop-blur-sm rounded-2xl p-4 md:p-5 border border-white/20">
            <div class="text-[11px] uppercase tracking-wider text-white/70 mb-3 text-center font-semibold">
              ตัวอย่างราคาเหมา
            </div>

            @if($exampleLow !== null)
              <div class="space-y-2.5">
                <div class="flex items-center justify-between gap-3 pb-2 border-b border-white/15">
                  <div>
                    <div class="text-sm font-bold">เจอ 5 รูป</div>
                    <div class="text-[10px] text-white/70 line-through">ปกติ ฿{{ number_format($originalLow, 0) }}</div>
                  </div>
                  <div class="text-right">
                    <div class="text-xl md:text-2xl font-extrabold text-yellow-200">฿{{ number_format($exampleLow, 0) }}</div>
                    @php $savedLow = $originalLow - $exampleLow; @endphp
                    @if($savedLow > 0)
                      <div class="text-[10px] font-semibold">ประหยัด ฿{{ number_format($savedLow, 0) }}</div>
                    @endif
                  </div>
                </div>

                <div class="flex items-center justify-between gap-3 pb-2 border-b border-white/15">
                  <div>
                    <div class="text-sm font-bold">เจอ 15 รูป</div>
                    <div class="text-[10px] text-white/70 line-through">ปกติ ฿{{ number_format($originalHigh, 0) }}</div>
                  </div>
                  <div class="text-right">
                    <div class="text-xl md:text-2xl font-extrabold text-yellow-200">฿{{ number_format($exampleHigh, 0) }}</div>
                    @php $savedHigh = $originalHigh - $exampleHigh; @endphp
                    @if($savedHigh > 0)
                      <div class="text-[10px] font-semibold">ประหยัด ฿{{ number_format($savedHigh, 0) }}</div>
                    @endif
                  </div>
                </div>

                <div class="text-center text-[10px] text-white/70 mt-2">
                  เพดานสูงสุด ฿{{ number_format($maxPrice, 0) }} (ป้องกันราคาบาน)
                </div>
              </div>
            @else
              {{-- Event has no per_photo set — show a generic teaser. --}}
              <div class="text-center py-4">
                <i class="bi bi-tag-fill text-4xl text-yellow-200 mb-2"></i>
                <div class="text-sm font-semibold">ลด {{ (int) $discountPct }}% ของราคารวม</div>
                <div class="text-[11px] text-white/70 mt-1">เพดาน ฿{{ number_format($maxPrice, 0) }}</div>
              </div>
            @endif
          </div>

        </div>
      </div>
    </div>
  </section>
@endif
