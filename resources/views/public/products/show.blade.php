@extends('layouts.app')

@section('title', $product->name)

@push('styles')
<style>
  @keyframes show-fadein {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .show-fade { animation: show-fadein 0.5s cubic-bezier(0.16, 1, 0.3, 1) both; }
  .show-fade-1 { animation-delay: 0.05s; }
  .show-fade-2 { animation-delay: 0.15s; }
  .show-fade-3 { animation-delay: 0.25s; }
</style>
@endpush

@section('content')
@php
  $typeLabels = [
    'preset'   => ['label' => 'พรีเซ็ต',     'icon' => 'bi-sliders',   'color' => 'from-rose-500 to-pink-500'],
    'overlay'  => ['label' => 'โอเวอร์เลย์', 'icon' => 'bi-layers',    'color' => 'from-amber-500 to-orange-500'],
    'template' => ['label' => 'เทมเพลต',     'icon' => 'bi-grid-3x3',  'color' => 'from-emerald-500 to-teal-500'],
    'other'    => ['label' => 'อื่นๆ',        'icon' => 'bi-box-seam',  'color' => 'from-indigo-500 to-purple-500'],
  ];
  $typeMeta = $typeLabels[$product->product_type] ?? ['label' => 'สินค้า', 'icon' => 'bi-box-seam', 'color' => 'from-indigo-500 to-purple-500'];
  $hasSale  = !empty($product->sale_price);
  $discount = $hasSale ? round((($product->price - $product->sale_price) / max($product->price, 0.01)) * 100) : 0;
  $savings  = $hasSale ? $product->price - $product->sale_price : 0;
  $gallery  = is_array($product->gallery_images) ? $product->gallery_images : [];

  // Free-product / LINE-friend gating context
  // ─────────────────────────────────────────
  // A "free" product is one with both price and sale_price ≤ 0. It's
  // a lead-magnet — the trade we offer is "add LINE friend → get file".
  // We compute the gate state once here so the CTA + popup logic stays
  // declarative below.
  $isFree     = (float) ($product->sale_price ?? $product->price) <= 0;
  $user       = auth()->user();
  $isFriend   = (bool) ($user->line_is_friend ?? false);
  $lineOaId   = (string) \App\Models\AppSetting::get('line_oa_basic_id', '')
             ?: (string) \App\Models\AppSetting::get('marketing_line_oa_id', '');
  $addFriendUrl = $lineOaId !== '' ? 'https://line.me/R/ti/p/' . urlencode($lineOaId) : '';
  $friendRequiredFlash = session('line_friend_required'); // populated by claimFree() redirect
  $autoResumeClaim     = (bool) session('auto_resume_free_claim');
@endphp

<div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8 py-6" x-data="{ activeImage: '{{ $product->cover_image_url ?: '' }}', activeTab: 'description' }">

  {{-- Breadcrumb --}}
  <nav class="mb-5 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 flex-wrap">
    <a href="{{ url('/') }}" class="hover:text-indigo-500 transition">หน้าแรก</a>
    <i class="bi bi-chevron-right text-[10px]"></i>
    <a href="{{ route('products.index') }}" class="hover:text-indigo-500 transition">สินค้าดิจิทัล</a>
    <i class="bi bi-chevron-right text-[10px]"></i>
    <span class="text-slate-700 dark:text-slate-300 truncate max-w-[200px] md:max-w-none">{{ $product->name }}</span>
  </nav>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
    {{-- ═══════════════════════════════════════════════════════════════
         LEFT — GALLERY + DETAILS
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="lg:col-span-7 xl:col-span-8">
      {{-- Main image --}}
      <div class="show-fade show-fade-1 relative rounded-3xl overflow-hidden bg-slate-100 dark:bg-slate-900 shadow-lg aspect-[4/3] group">
        @if($product->cover_image_url)
          <img :src="activeImage"
               src="{{ $product->cover_image_url }}"
               alt="{{ $product->name }}"
               class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
        @else
          <div class="w-full h-full flex items-center justify-center bg-gradient-to-br {{ $typeMeta['color'] }}">
            <i class="bi {{ $typeMeta['icon'] }} text-white text-8xl opacity-40"></i>
          </div>
        @endif

        {{-- Badges --}}
        <div class="absolute top-4 left-4 flex flex-col gap-2">
          <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/95 dark:bg-slate-900/95 backdrop-blur text-xs font-semibold text-slate-700 dark:text-slate-200 shadow-md">
            <i class="bi {{ $typeMeta['icon'] }}"></i> {{ $typeMeta['label'] }}
          </span>
          @if($product->is_featured)
            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-gradient-to-r from-amber-400 to-orange-500 text-white text-xs font-bold shadow-md">
              <i class="bi bi-star-fill"></i> FEATURED
            </span>
          @endif
        </div>

        @if($hasSale)
          <div class="absolute top-4 right-4">
            <div class="inline-flex items-center px-3 py-1.5 rounded-full bg-gradient-to-r from-rose-500 to-pink-500 text-white text-sm font-bold shadow-lg animate-pulse">
              <i class="bi bi-lightning-fill mr-1"></i> ลด {{ $discount }}%
            </div>
          </div>
        @endif
      </div>

      {{-- Thumbnails --}}
      @if(count($gallery) > 0 || $product->cover_image_url)
      <div class="mt-4 grid grid-cols-5 gap-2">
        @if($product->cover_image_url)
        <button type="button"
                @click="activeImage = '{{ $product->cover_image_url }}'"
                class="aspect-square rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-900 border-2 transition"
                :class="activeImage === '{{ $product->cover_image_url }}' ? 'border-indigo-500 ring-2 ring-indigo-200 dark:ring-indigo-500/30' : 'border-transparent hover:border-slate-300 dark:hover:border-white/20'">
          <img src="{{ $product->cover_image_url }}" class="w-full h-full object-cover" alt="Cover">
        </button>
        @endif
        @foreach($gallery as $img)
          @php $imgUrl = str_starts_with($img, 'http') ? $img : asset('storage/' . $img); @endphp
          <button type="button"
                  @click="activeImage = '{{ $imgUrl }}'"
                  class="aspect-square rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-900 border-2 transition"
                  :class="activeImage === '{{ $imgUrl }}' ? 'border-indigo-500 ring-2 ring-indigo-200 dark:ring-indigo-500/30' : 'border-transparent hover:border-slate-300 dark:hover:border-white/20'">
            <img src="{{ $imgUrl }}" class="w-full h-full object-cover" alt="Gallery">
          </button>
        @endforeach
      </div>
      @endif

      {{-- Tabs --}}
      <div class="show-fade show-fade-3 mt-8 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-white/10 overflow-hidden shadow-sm">
        <div class="flex border-b border-slate-200 dark:border-white/10 overflow-x-auto">
          <button type="button"
                  @click="activeTab = 'description'"
                  class="px-5 py-3 text-sm font-medium whitespace-nowrap transition border-b-2"
                  :class="activeTab === 'description' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'">
            <i class="bi bi-file-text mr-1"></i> รายละเอียด
          </button>
          @if($product->features && is_array($product->features) && count($product->features) > 0)
          <button type="button"
                  @click="activeTab = 'features'"
                  class="px-5 py-3 text-sm font-medium whitespace-nowrap transition border-b-2"
                  :class="activeTab === 'features' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'">
            <i class="bi bi-check2-square mr-1"></i> คุณสมบัติ
            <span class="inline-flex items-center justify-center ml-1 min-w-[18px] px-1 rounded-full bg-slate-100 dark:bg-white/10 text-[10px]">{{ count($product->features) }}</span>
          </button>
          @endif
          @if($product->requirements || $product->compatibility)
          <button type="button"
                  @click="activeTab = 'requirements'"
                  class="px-5 py-3 text-sm font-medium whitespace-nowrap transition border-b-2"
                  :class="activeTab === 'requirements' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'">
            <i class="bi bi-gear mr-1"></i> ข้อกำหนด
          </button>
          @endif
        </div>

        {{-- Description --}}
        <div x-show="activeTab === 'description'" class="p-6">
          @if($product->description)
            <div class="prose prose-slate dark:prose-invert max-w-none text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-line">{{ $product->description }}</div>
          @else
            <p class="text-slate-500 dark:text-slate-400 text-sm">ไม่มีรายละเอียดเพิ่มเติม</p>
          @endif

          @if($product->short_description && $product->description !== $product->short_description)
            <div class="mt-4 p-4 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-100 dark:border-indigo-500/20">
              <p class="text-sm text-indigo-900 dark:text-indigo-200"><i class="bi bi-quote mr-1"></i> {{ $product->short_description }}</p>
            </div>
          @endif
        </div>

        {{-- Features --}}
        @if($product->features && is_array($product->features))
        <div x-show="activeTab === 'features'" class="p-6" x-cloak>
          <ul class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($product->features as $feature)
            <li class="flex items-start gap-3 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/20">
              <div class="w-6 h-6 rounded-lg bg-gradient-to-br from-emerald-400 to-teal-500 text-white flex items-center justify-center flex-shrink-0 shadow-sm">
                <i class="bi bi-check2 text-xs"></i>
              </div>
              <span class="text-sm text-slate-700 dark:text-slate-200 leading-relaxed">{{ $feature }}</span>
            </li>
            @endforeach
          </ul>
        </div>
        @endif

        {{-- Requirements --}}
        @if($product->requirements || $product->compatibility)
        <div x-show="activeTab === 'requirements'" class="p-6" x-cloak>
          @if($product->compatibility)
            <div class="mb-4">
              <h6 class="font-semibold text-sm text-slate-900 dark:text-white mb-2 flex items-center gap-1.5">
                <i class="bi bi-laptop text-indigo-500"></i> ความเข้ากันได้
              </h6>
              <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed whitespace-pre-line">{{ $product->compatibility }}</p>
            </div>
          @endif
          @if($product->requirements)
            <div>
              <h6 class="font-semibold text-sm text-slate-900 dark:text-white mb-2 flex items-center gap-1.5">
                <i class="bi bi-list-check text-indigo-500"></i> ข้อกำหนดระบบ
              </h6>
              <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed whitespace-pre-line">{{ $product->requirements }}</p>
            </div>
          @endif
        </div>
        @endif
      </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         RIGHT — STICKY PURCHASE CARD
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="show-fade show-fade-2 lg:col-span-5 xl:col-span-4">
      <div class="lg:sticky lg:top-24 space-y-4">

        {{-- Main Purchase Card --}}
        <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-lg overflow-hidden">
          {{-- Header gradient --}}
          <div class="h-1.5 bg-gradient-to-r {{ $typeMeta['color'] }}"></div>

          <div class="p-6">
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 dark:text-white leading-tight mb-2">{{ $product->name }}</h1>

            @if($product->short_description)
              <p class="text-sm text-slate-500 dark:text-slate-400 mb-4 leading-relaxed">{{ $product->short_description }}</p>
            @endif

            {{-- Rating / Sales --}}
            <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400 mb-5 pb-5 border-b border-slate-100 dark:border-white/5">
              @if($product->total_sales > 0)
                <span class="inline-flex items-center gap-1">
                  <i class="bi bi-download text-emerald-500"></i> <span class="font-medium text-slate-700 dark:text-slate-200">{{ number_format($product->total_sales) }}</span> ครั้งที่ขาย
                </span>
              @endif
              <span class="inline-flex items-center gap-1">
                <i class="bi bi-shield-check text-blue-500"></i> ของแท้ 100%
              </span>
            </div>

            {{-- Price Block --}}
            <div class="mb-5">
              @if($isFree)
                {{-- FREE — anchored against an "imagined" original to fight
                     "free = worthless" perception. Shows what the customer
                     gets without us having to invent a fake original price. --}}
                <div class="flex items-center gap-2 flex-wrap mb-1">
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full
                               bg-emerald-100 dark:bg-emerald-500/20
                               text-emerald-700 dark:text-emerald-300
                               text-xs font-bold">
                    <i class="bi bi-gift-fill"></i> รับฟรี
                  </span>
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                               bg-[#06C755]/10 text-[#06C755] text-[10px] font-semibold">
                    <i class="bi bi-line"></i> ผ่าน LINE
                  </span>
                </div>
                <div class="flex items-baseline gap-1">
                  <span class="text-4xl font-extrabold text-emerald-600 dark:text-emerald-400">FREE</span>
                </div>
                <p class="text-xs text-emerald-700/80 dark:text-emerald-400/80 font-medium mt-1.5 leading-snug">
                  <i class="bi bi-info-circle mr-1"></i>
                  เพิ่มเพื่อน LINE OA ของเราเพื่อรับสิทธิ์ดาวน์โหลด — ไม่ต้องโอนเงิน ไม่ต้องแนบสลิป
                </p>
              @elseif($hasSale)
                <div class="flex items-center gap-2 flex-wrap mb-1">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300 text-xs font-bold">
                    ลด {{ $discount }}%
                  </span>
                  <span class="text-xs text-slate-400 dark:text-slate-500 line-through">{{ number_format($product->price, 0) }} ฿</span>
                </div>
                <div class="flex items-baseline gap-1">
                  <span class="text-4xl font-bold text-rose-500">{{ number_format($product->sale_price, 0) }}</span>
                  <span class="text-lg text-slate-500 dark:text-slate-400 font-medium">฿</span>
                </div>
                <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium mt-1">
                  <i class="bi bi-piggy-bank-fill mr-1"></i> ประหยัด {{ number_format($savings, 0) }} บาท
                </p>
              @else
                <div class="flex items-baseline gap-1">
                  <span class="text-4xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($product->price, 0) }}</span>
                  <span class="text-lg text-slate-500 dark:text-slate-400 font-medium">฿</span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">ราคารวม · ชำระครั้งเดียว ใช้ได้ตลอดไป</p>
              @endif
            </div>

            {{-- CTA Buttons --}}
            @auth
              @if($isFree)
                {{-- ─── FREE PRODUCT FLOW ─────────────────────────────
                     Three states:
                       1. User is NOT a LINE friend → show "Add Friend"
                          deep link button + the friend-required modal
                          (popped open if redirected here from claim-free).
                       2. User IS a friend → submit the claim form.
                       3. Auto-resume after returning from LINE → JS
                          auto-submits the claim form after 1s. -}}
                @if($isFriend)
                  <form method="POST" action="{{ route('products.claim-free', $product->id) }}"
                        class="space-y-2"
                        id="free-claim-form">
                    @csrf
                    <button type="submit"
                            class="w-full py-3.5 rounded-xl bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white font-semibold shadow-lg shadow-emerald-500/25 hover:shadow-xl hover:shadow-emerald-500/40 transition-all flex items-center justify-center gap-2">
                      <i class="bi bi-gift-fill"></i> รับสินค้าฟรี — ดาวน์โหลดได้ทันที
                    </button>
                    @if($product->demo_url)
                      <a href="{{ $product->demo_url }}" target="_blank" rel="noopener"
                         class="block w-full py-2.5 rounded-xl bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 font-medium text-sm text-center transition">
                        <i class="bi bi-play-circle mr-1"></i> ดูตัวอย่าง
                      </a>
                    @endif
                  </form>
                  @if($autoResumeClaim)
                    {{-- User just came back from adding LINE — auto-submit
                         the claim after a short delay so they see what's
                         happening (ux: "Oh, it's claiming for me!") --}}
                    <p class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-2 text-center font-medium animate-pulse">
                      <i class="bi bi-arrow-clockwise"></i> ตรวจพบเพื่อน LINE แล้ว — กำลังเปิดสิทธิ์ดาวน์โหลดให้คุณ...
                    </p>
                    @push('scripts')
                      <script>
                        document.addEventListener('DOMContentLoaded', () => {
                          setTimeout(() => {
                            const f = document.getElementById('free-claim-form');
                            if (f) f.submit();
                          }, 1200);
                        });
                      </script>
                    @endpush
                  @endif
                @else
                  {{-- Not yet a LINE friend — primary CTA opens the OA
                       add-friend deep link in a new tab (LINE app on
                       mobile, web on desktop). Secondary line drives
                       intent: "ทำไมต้องเพิ่ม?" --}}
                  @if($addFriendUrl !== '')
                    <a href="{{ $addFriendUrl }}" target="_blank" rel="noopener"
                       data-line-friend-cta
                       class="w-full py-3.5 rounded-xl bg-[#06C755] hover:bg-[#05B04D] text-white font-semibold shadow-lg shadow-[#06C755]/25 hover:shadow-xl hover:shadow-[#06C755]/40 transition-all flex items-center justify-center gap-2">
                      <i class="bi bi-line text-xl"></i> เพิ่มเพื่อน LINE เพื่อรับฟรี
                    </a>
                    {{-- After they tap, browser tab is on LINE — when
                         they switch back here, we need the page to
                         re-check friend status. visibilitychange handler
                         below reloads the page silently if user might
                         have just added the friend. --}}
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 text-center leading-relaxed">
                      <i class="bi bi-info-circle"></i>
                      หลังเพิ่มเพื่อนแล้ว กลับมาที่หน้านี้ — ระบบจะปลดล็อกปุ่มดาวน์โหลดให้อัตโนมัติ
                    </p>
                  @else
                    <button type="button" disabled
                            class="w-full py-3.5 rounded-xl bg-slate-200 dark:bg-white/5 text-slate-500 font-semibold cursor-not-allowed flex items-center justify-center gap-2">
                      <i class="bi bi-exclamation-triangle"></i> ระบบยังไม่ได้ตั้งค่า LINE OA
                    </button>
                  @endif
                @endif
              @else
                <form method="POST" action="{{ route('products.purchase', $product->id) }}" class="space-y-2">
                  @csrf
                  <button type="submit"
                          class="w-full py-3.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2">
                    <i class="bi bi-cart-plus-fill"></i> ซื้อสินค้านี้เลย
                  </button>
                  @if($product->demo_url)
                    <a href="{{ $product->demo_url }}" target="_blank" rel="noopener"
                       class="block w-full py-2.5 rounded-xl bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 font-medium text-sm text-center transition">
                      <i class="bi bi-play-circle mr-1"></i> ดูตัวอย่าง
                    </a>
                  @endif
                </form>
              @endif
            @else
              <a href="{{ route('auth.login') }}"
                 class="w-full py-3.5 rounded-xl
                        {{ $isFree
                              ? 'bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 shadow-emerald-500/25'
                              : 'bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700' }}
                        text-white font-semibold shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2">
                <i class="bi {{ $isFree ? 'bi-gift-fill' : 'bi-box-arrow-in-right' }}"></i>
                {{ $isFree ? 'เข้าสู่ระบบเพื่อรับฟรี' : 'เข้าสู่ระบบเพื่อซื้อ' }}
              </a>
              <p class="text-xs text-slate-500 dark:text-slate-400 text-center mt-2">
                ยังไม่มีบัญชี?
                <a href="{{ route('auth.register') }}" class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">สมัครฟรี</a>
              </p>
            @endauth

            {{-- Trust signals --}}
            <div class="mt-5 pt-5 border-t border-slate-100 dark:border-white/5 grid grid-cols-2 gap-3 text-xs">
              <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                <i class="bi bi-lightning-charge-fill text-emerald-500"></i> ดาวน์โหลดทันที
              </div>
              <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                <i class="bi bi-infinity text-purple-500"></i> ใช้ได้ตลอดชีพ
              </div>
              <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                <i class="bi bi-shield-fill-check text-blue-500"></i> ชำระปลอดภัย
              </div>
              <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                <i class="bi bi-headset text-amber-500"></i> มีซัพพอร์ต
              </div>
            </div>
          </div>
        </div>

        {{-- Specifications --}}
        @if($product->file_format || $product->file_size || $product->version || $product->download_limit || $product->download_expiry_days)
        <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
          <div class="px-5 py-3 border-b border-slate-100 dark:border-white/5">
            <h6 class="font-semibold text-sm text-slate-900 dark:text-white flex items-center gap-1.5">
              <i class="bi bi-info-circle text-indigo-500"></i> ข้อมูลสินค้า
            </h6>
          </div>
          <dl class="px-5 py-3 text-sm divide-y divide-slate-100 dark:divide-white/5">
            @if($product->file_format)
            <div class="flex items-center justify-between py-2.5">
              <dt class="text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                <i class="bi bi-file-earmark-code text-slate-400"></i> รูปแบบไฟล์
              </dt>
              <dd class="font-medium text-slate-900 dark:text-white">{{ $product->file_format }}</dd>
            </div>
            @endif
            @if($product->file_size)
            <div class="flex items-center justify-between py-2.5">
              <dt class="text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                <i class="bi bi-hdd text-slate-400"></i> ขนาดไฟล์
              </dt>
              <dd class="font-medium text-slate-900 dark:text-white">{{ $product->file_size }}</dd>
            </div>
            @endif
            @if($product->version)
            <div class="flex items-center justify-between py-2.5">
              <dt class="text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                <i class="bi bi-tag text-slate-400"></i> เวอร์ชัน
              </dt>
              <dd class="font-medium text-slate-900 dark:text-white">v{{ $product->version }}</dd>
            </div>
            @endif
            @if($product->download_limit)
            <div class="flex items-center justify-between py-2.5">
              <dt class="text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                <i class="bi bi-download text-slate-400"></i> ดาวน์โหลดได้
              </dt>
              <dd class="font-medium text-slate-900 dark:text-white">{{ $product->download_limit }} ครั้ง</dd>
            </div>
            @endif
            @if($product->download_expiry_days)
            <div class="flex items-center justify-between py-2.5">
              <dt class="text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                <i class="bi bi-clock-history text-slate-400"></i> อายุลิงก์
              </dt>
              <dd class="font-medium text-slate-900 dark:text-white">{{ $product->download_expiry_days }} วัน</dd>
            </div>
            @endif
          </dl>
        </div>
        @endif

        {{-- Share --}}
        <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-4">
          <h6 class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 font-semibold mb-3">
            <i class="bi bi-share mr-1"></i> แชร์สินค้า
          </h6>
          <div class="flex items-center gap-2">
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->fullUrl()) }}"
               target="_blank" rel="noopener"
               class="flex-1 inline-flex items-center justify-center gap-1.5 py-2 rounded-lg bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-500/20 text-xs font-medium transition">
              <i class="bi bi-facebook"></i> Facebook
            </a>
            <a href="https://social-plugins.line.me/lineit/share?url={{ urlencode(request()->fullUrl()) }}"
               target="_blank" rel="noopener"
               class="flex-1 inline-flex items-center justify-center gap-1.5 py-2 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 text-xs font-medium transition">
              <i class="bi bi-line"></i> LINE
            </a>
            <button type="button"
                    onclick="navigator.clipboard.writeText(window.location.href); this.innerHTML='<i class=\'bi bi-check2\'></i> คัดลอกแล้ว'; setTimeout(()=>{this.innerHTML='<i class=\'bi bi-link-45deg\'></i> คัดลอก'}, 2000)"
                    class="flex-1 inline-flex items-center justify-center gap-1.5 py-2 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10 text-xs font-medium transition">
              <i class="bi bi-link-45deg"></i> คัดลอก
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════════
       RELATED / BACK TO LIST
       ═══════════════════════════════════════════════════════════════ --}}
  <div class="mt-10 pt-8 border-t border-slate-200 dark:border-white/10 flex items-center justify-between flex-wrap gap-3">
    <a href="{{ route('products.index') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/5 transition">
      <i class="bi bi-arrow-left"></i> กลับไปหน้าสินค้าทั้งหมด
    </a>
    <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
      <i class="bi bi-shield-check text-emerald-500"></i>
      ซื้อปลอดภัย — การันตีคืนเงินหากไม่ได้รับสินค้า
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════════
       FRIEND-REQUIRED POPUP — shown when:
       (a) user clicked "รับฟรี" but isn't a LINE friend yet
           (claimFree() set session('line_friend_required'))
       (b) immediately after redirect, opens automatically.

       Strategy: outcome-first headline + concrete reciprocity. We
       don't lecture about "why add friend" — we tell them what
       happens when they do.
       ═══════════════════════════════════════════════════════════════ --}}
  @if($isFree && $friendRequiredFlash && !$isFriend)
  <div x-data="{ open: true }"
       x-show="open"
       x-cloak
       @keydown.escape.window="open = false"
       class="fixed inset-0 z-[80] flex items-center justify-center px-4">
    <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" @click="open = false"></div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         class="relative w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-2xl overflow-hidden border border-slate-200 dark:border-white/10">

      {{-- Hero --}}
      <div class="relative bg-gradient-to-br from-[#06C755] to-[#04A848] p-6 text-white text-center">
        <button type="button" @click="open = false"
                class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center text-white/90 transition">
          <i class="bi bi-x-lg text-sm"></i>
        </button>
        <i class="bi bi-line text-5xl mb-2 block"></i>
        <h3 class="text-xl font-extrabold">เพิ่มเพื่อน LINE เพื่อรับฟรี</h3>
        <p class="text-sm text-white/90 mt-1">{{ $friendRequiredFlash['product_name'] ?? $product->name }}</p>
      </div>

      {{-- Body --}}
      <div class="p-6 space-y-4">
        <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
          <strong class="text-emerald-600 dark:text-emerald-400">2 ขั้นตอนเท่านั้น</strong> —
          คุณจะได้รับลิงก์ดาวน์โหลดทันที ไม่ต้องโอนเงิน ไม่ต้องแนบสลิป
        </p>

        <ol class="space-y-3">
          <li class="flex items-start gap-3">
            <span class="shrink-0 w-7 h-7 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 flex items-center justify-center text-sm font-bold">1</span>
            <div class="flex-1">
              <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">กดปุ่มเพิ่มเพื่อน LINE ด้านล่าง</p>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">ระบบจะเปิดแอป LINE — กด "เพิ่มเพื่อน" แล้วกลับมาที่หน้านี้</p>
            </div>
          </li>
          <li class="flex items-start gap-3">
            <span class="shrink-0 w-7 h-7 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 flex items-center justify-center text-sm font-bold">2</span>
            <div class="flex-1">
              <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">ระบบจะปลดล็อกปุ่มดาวน์โหลดอัตโนมัติ</p>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">รอ 2-5 วินาที — กดดาวน์โหลดได้ทันที</p>
            </div>
          </li>
        </ol>

        @if(!empty($friendRequiredFlash['add_friend_url']))
        <a href="{{ $friendRequiredFlash['add_friend_url'] }}" target="_blank" rel="noopener"
           data-line-friend-cta
           class="block w-full py-3.5 rounded-xl bg-[#06C755] hover:bg-[#05B04D] text-white font-bold text-center shadow-lg shadow-[#06C755]/30 transition-all">
          <i class="bi bi-line text-xl"></i> เพิ่มเพื่อน LINE เลย
        </a>
        @endif

        <button type="button" @click="open = false"
                class="block w-full text-xs text-slate-400 hover:text-slate-600 transition">
          ดูภายหลัง
        </button>
      </div>
    </div>
  </div>
  @endif
</div>

@push('scripts')
<script>
  /* Tab visibility refresh — when the user comes back from LINE after
     adding the friend, reload the page silently so the controller can
     re-check line_is_friend and flip the CTA from "Add Friend" to
     "Get Free". Only fires after they clicked one of our LINE deep
     links (data-line-friend-cta) so we don't reload on unrelated
     tab focus.

     Throttled to once-per-30s to avoid reload loops if the LINE app
     bounces them straight back without adding. */
  (function() {
    const KEY  = 'line_friend_pending_check_at';
    const NOW  = Date.now();

    document.querySelectorAll('[data-line-friend-cta]').forEach(el => {
      el.addEventListener('click', () => {
        sessionStorage.setItem(KEY, String(NOW));
      });
    });

    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState !== 'visible') return;

      const ts = parseInt(sessionStorage.getItem(KEY) || '0', 10);
      if (!ts) return;

      const elapsed = Date.now() - ts;
      // Refresh only if (a) user clicked our LINE CTA in this tab,
      // and (b) at least 1.5s elapsed (gives LINE time to process
      // the friend-add webhook). Cap at 5 min to avoid stale reloads.
      if (elapsed > 1500 && elapsed < 5 * 60 * 1000) {
        sessionStorage.removeItem(KEY);
        window.location.reload();
      }
    });
  })();
</script>
@endpush
@endsection
