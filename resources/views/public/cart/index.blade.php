@extends('layouts.app')

@section('title', 'ตะกร้าสินค้า')

@push('styles')
<style>[x-cloak]{display:none !important;}</style>
@endpush

@php
    // --- Delivery method options ---------------------------------------------
    // Read admin toggles once; never trust client-side state for this.
    $deliveryMethodsRaw     = \App\Models\AppSetting::get('delivery_methods_enabled', '["web","line","email"]');
    $deliveryMethodsDecoded = json_decode((string) $deliveryMethodsRaw, true);
    $deliveryEnabled        = is_array($deliveryMethodsDecoded) ? $deliveryMethodsDecoded : ['web', 'line', 'email'];
    if (!in_array('web', $deliveryEnabled, true)) {
        $deliveryEnabled[] = 'web';  // web is always the safety net
    }
    $deliveryDefault     = \App\Models\AppSetting::get('delivery_default_method', 'auto');
    $deliveryAutoSwitch  = \App\Models\AppSetting::get('delivery_auto_switch', '1') === '1';
    $deliveryEmailThresh = (int) \App\Models\AppSetting::get('delivery_email_threshold', '30');
    $cartPhotoCount      = !empty($cartItems) ? count($cartItems) : 0;

    // Which channel will auto-mode pick? (for the live hint under "อัตโนมัติ")
    $autoHintChannel = 'web';
    $userId          = auth()->id();
    $userHasLineOAuth = false;
    if ($userId) {
        try {
            $userHasLineOAuth = \Illuminate\Support\Facades\Schema::hasTable('auth_social_logins')
                && \Illuminate\Support\Facades\DB::table('auth_social_logins')
                    ->where('user_id', $userId)
                    ->where('provider', 'line')
                    ->exists();
        } catch (\Throwable $e) {
            $userHasLineOAuth = false;
        }
    }
    if ($deliveryAutoSwitch && $cartPhotoCount >= $deliveryEmailThresh && in_array('email', $deliveryEnabled, true)) {
        $autoHintChannel = 'email';
    } elseif (in_array('line', $deliveryEnabled, true) && $userHasLineOAuth) {
        $autoHintChannel = 'line';
    } elseif (in_array('email', $deliveryEnabled, true) && !empty(auth()->user()?->email)) {
        $autoHintChannel = 'email';
    }

    $deliveryLabels = [
        'auto'  => ['icon' => 'bi-magic',      'title' => 'อัตโนมัติ (แนะนำ)', 'sub' => 'ให้ระบบเลือกช่องทางที่ดีที่สุดให้คุณ'],
        'web'   => ['icon' => 'bi-globe2',     'title' => 'ดาวน์โหลดบนเว็บ',   'sub' => 'รับลิงก์ดาวน์โหลดจากหน้าคำสั่งซื้อ'],
        'line'  => ['icon' => 'bi-chat-dots-fill','title' => 'ส่งผ่าน LINE',    'sub' => 'ส่งลิงก์ดาวน์โหลดเข้ากล่องข้อความ LINE'],
        'email' => ['icon' => 'bi-envelope-fill','title' => 'ส่งทางอีเมล',     'sub' => 'เหมาะกับออเดอร์รูปจำนวนมาก'],
    ];

    $autoChannelThai = match($autoHintChannel) {
        'line'  => 'LINE',
        'email' => 'อีเมล',
        default => 'เว็บไซต์',
    };
@endphp

@section('content')
<div class="max-w-6xl mx-auto px-4 md:px-6 py-6">

  {{-- Header --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md">
        <i class="bi bi-cart3"></i>
      </span>
      ตะกร้าสินค้า
    </h1>
    @if(!empty($cartItems) && count($cartItems) > 0)
      <span class="inline-flex items-center px-3 py-1.5 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
        <i class="bi bi-bag-check mr-1"></i>{{ count($cartItems) }} รายการ
      </span>
    @endif
  </div>

  @if(session('success'))
    <div class="mb-4 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-800 dark:text-emerald-300 text-sm flex items-start gap-2">
      <i class="bi bi-check-circle-fill mt-0.5"></i> {{ session('success') }}
    </div>
  @endif
  @if(session('error'))
    <div class="mb-4 p-4 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 text-rose-800 dark:text-rose-300 text-sm flex items-start gap-2">
      <i class="bi bi-exclamation-circle-fill mt-0.5"></i> {{ session('error') }}
    </div>
  @endif

  @if(empty($cartItems) || count($cartItems) === 0)
    {{-- ═══════════════ EMPTY STATE ═══════════════ --}}
    <div class="text-center py-20 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-500/20 dark:to-purple-500/20 text-indigo-500 dark:text-indigo-400 mb-4">
        <i class="bi bi-cart-x text-3xl"></i>
      </div>
      <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">ตะกร้าสินค้าว่างเปล่า</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">มาเลือกชมภาพถ่ายสวยๆ กันดีกว่า</p>
      <a href="{{ route('events.index') }}"
         class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-md hover:shadow-lg transition-all">
        <i class="bi bi-images"></i> เลือกซื้อภาพถ่าย
      </a>
    </div>
  @else
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      {{-- ═══════════════ CART ITEMS TABLE ═══════════════ --}}
      <div class="lg:col-span-2">
        <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-white/5">
                <tr>
                  <th class="pl-5 pr-3 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider" style="width:80px;">ตัวอย่าง</th>
                  <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">รายการ</th>
                  <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden md:table-cell">ราคา</th>
                  <th class="px-3 py-3 text-center text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider" style="width:110px;">จำนวน</th>
                  <th class="px-3 py-3 text-right text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">รวม</th>
                  <th class="pr-5 py-3" style="width:60px;"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                @foreach($cartItems as $key => $item)
                <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition">
                  <td class="pl-5 pr-3 py-4">
                    @if(!empty($item['thumbnail']))
                      <img src="{{ $item['thumbnail'] }}" class="w-14 h-14 object-cover rounded-xl shadow-sm" alt="">
                    @else
                      <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center shadow-sm">
                        <i class="bi bi-image text-white opacity-80"></i>
                      </div>
                    @endif
                  </td>
                  <td class="px-3 py-4">
                    <div class="font-semibold text-sm text-slate-900 dark:text-white line-clamp-2">{{ $item['name'] ?? 'Photo' }}</div>
                    @if(!empty($item['event_name']))
                      <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $item['event_name'] }}</div>
                    @endif
                    <div class="md:hidden text-xs text-indigo-600 dark:text-indigo-400 font-medium mt-1">
                      {{ number_format($item['price'] ?? 0, 0) }} ฿
                    </div>
                  </td>
                  <td class="px-3 py-4 hidden md:table-cell">
                    <span class="text-indigo-600 dark:text-indigo-400 font-semibold">{{ number_format($item['price'] ?? 0, 0) }} ฿</span>
                  </td>
                  <td class="px-3 py-4">
                    <form method="POST" action="{{ route('cart.update') }}" class="flex justify-center">
                      @csrf
                      <input type="hidden" name="key" value="{{ $key }}">
                      <input type="number" name="quantity" value="{{ $item['quantity'] ?? 1 }}" min="1" max="99"
                             class="w-[70px] px-2 py-1.5 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm text-center focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
                             onchange="this.form.submit()">
                    </form>
                  </td>
                  <td class="px-3 py-4 text-right">
                    <span class="font-bold text-slate-900 dark:text-white">{{ number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 1), 0) }} ฿</span>
                  </td>
                  <td class="pr-5 py-4">
                    <form method="POST" action="{{ route('cart.remove') }}">
                      @csrf
                      <input type="hidden" name="key" value="{{ $key }}">
                      <button type="submit"
                              class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-rose-50 dark:bg-rose-500/10 text-rose-500 hover:bg-rose-500 hover:text-white transition"
                              title="ลบออก">
                        <i class="bi bi-trash3 text-xs"></i>
                      </button>
                    </form>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

        {{-- Back to shopping --}}
        <a href="{{ route('events.index') }}"
           class="inline-flex items-center gap-1.5 mt-4 text-sm text-slate-500 dark:text-slate-400 hover:text-indigo-500 dark:hover:text-indigo-400 transition">
          <i class="bi bi-arrow-left"></i> เลือกซื้อเพิ่มเติม
        </a>
      </div>

      {{-- ═══════════════ SUMMARY SIDEBAR ═══════════════ --}}
      <div class="lg:col-span-1">
        <div class="lg:sticky lg:top-24 space-y-4">
          {{-- Coupon --}}
          <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-5">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-1.5 mb-3">
              <i class="bi bi-ticket-perforated text-indigo-500"></i> โค้ดส่วนลด
            </h3>
            @if(!empty($couponCode))
              {{-- Applied state — show code chip + remove button --}}
              <div class="flex items-center justify-between gap-2 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20">
                <div class="flex items-center gap-2 min-w-0">
                  <i class="bi bi-check-circle-fill text-emerald-600 dark:text-emerald-400"></i>
                  <div class="min-w-0">
                    <div class="text-sm font-bold text-emerald-700 dark:text-emerald-300">{{ $couponCode }}</div>
                    <div class="text-xs text-emerald-600 dark:text-emerald-400">ลดทันที {{ number_format($couponDiscount, 0) }} ฿</div>
                  </div>
                </div>
                <form method="POST" action="{{ url('/cart/coupon/remove') }}">
                  @csrf
                  <button type="submit"
                          class="text-xs font-semibold text-rose-600 dark:text-rose-400 hover:underline whitespace-nowrap"
                          title="ยกเลิกโค้ด">
                    <i class="bi bi-x-circle"></i> ยกเลิก
                  </button>
                </form>
              </div>
            @else
              <form method="POST" action="{{ url('/cart/coupon') }}" class="flex gap-2">
                @csrf
                <input type="text" name="coupon_code"
                       class="flex-1 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition uppercase"
                       placeholder="กรอกโค้ดส่วนลด..."
                       style="text-transform: uppercase;">
                <button type="submit"
                        class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-medium text-sm shadow-sm transition">
                  ใช้โค้ด
                </button>
              </form>
            @endif
          </div>

          {{-- Referral code --}}
          <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-5">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-1.5 mb-3">
              <i class="bi bi-people-fill text-pink-500"></i> รหัสแนะนำเพื่อน
            </h3>
            @if(!empty($referralCode))
              <div class="flex items-center justify-between gap-2 p-3 rounded-xl bg-pink-50 dark:bg-pink-500/10 border border-pink-200 dark:border-pink-500/20">
                <div class="flex items-center gap-2 min-w-0">
                  <i class="bi bi-check-circle-fill text-pink-600 dark:text-pink-400"></i>
                  <div class="min-w-0">
                    <div class="text-sm font-bold text-pink-700 dark:text-pink-300">{{ $referralCode }}</div>
                    <div class="text-xs text-pink-600 dark:text-pink-400">ลดทันที {{ number_format($referralDiscount, 0) }} ฿</div>
                  </div>
                </div>
                <form method="POST" action="{{ url('/cart/referral/remove') }}">
                  @csrf
                  <button type="submit"
                          class="text-xs font-semibold text-rose-600 dark:text-rose-400 hover:underline whitespace-nowrap"
                          title="ยกเลิกรหัสแนะนำ">
                    <i class="bi bi-x-circle"></i> ยกเลิก
                  </button>
                </form>
              </div>
            @else
              <form method="POST" action="{{ url('/cart/referral') }}" class="flex gap-2">
                @csrf
                <input type="text" name="referral_code"
                       class="flex-1 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-pink-500 transition uppercase"
                       placeholder="รหัสแนะนำของเพื่อน..."
                       style="text-transform: uppercase;">
                <button type="submit"
                        class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-pink-500 to-rose-600 hover:from-pink-600 hover:to-rose-700 text-white font-medium text-sm shadow-sm transition">
                  ใช้รหัส
                </button>
              </form>
              <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
                <i class="bi bi-info-circle"></i> ลดทันทีและเพื่อนที่แนะนำคุณก็จะได้รับรางวัล
              </p>
            @endif
          </div>

          {{-- Summary --}}
          <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-lg overflow-hidden">
            <div class="h-1.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
            <div class="p-5">
              <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-1.5 mb-4">
                <i class="bi bi-receipt text-indigo-500"></i> สรุปคำสั่งซื้อ
              </h3>

              <dl class="text-sm space-y-2.5 mb-4">
                <div class="flex justify-between">
                  <dt class="text-slate-500 dark:text-slate-400">ยอดรวม ({{ count($cartItems) }} รายการ)</dt>
                  <dd class="font-semibold text-slate-900 dark:text-white">{{ number_format($total, 0) }} ฿</dd>
                </div>
                @if(!empty($couponDiscount) && $couponDiscount > 0)
                <div class="flex justify-between text-emerald-600 dark:text-emerald-400">
                  <dt class="inline-flex items-center gap-1"><i class="bi bi-ticket-perforated-fill"></i> โค้ด {{ $couponCode }}</dt>
                  <dd class="font-semibold">-{{ number_format($couponDiscount, 0) }} ฿</dd>
                </div>
                @endif
                @if(!empty($referralDiscount) && $referralDiscount > 0)
                <div class="flex justify-between text-pink-600 dark:text-pink-400">
                  <dt class="inline-flex items-center gap-1"><i class="bi bi-people-fill"></i> รหัสแนะนำ</dt>
                  <dd class="font-semibold">-{{ number_format($referralDiscount, 0) }} ฿</dd>
                </div>
                @endif
                <div class="flex justify-between">
                  <dt class="text-slate-500 dark:text-slate-400">ค่าธรรมเนียม</dt>
                  <dd class="font-semibold text-emerald-600 dark:text-emerald-400">ฟรี</dd>
                </div>
              </dl>

              <div class="pt-4 border-t border-slate-100 dark:border-white/5 mb-5">
                <div class="flex items-baseline justify-between">
                  <span class="font-bold text-slate-900 dark:text-white">ยอดชำระ</span>
                  <span class="text-3xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                    {{ number_format(max(0, $total - ($discount ?? 0)), 0) }} <span class="text-base">฿</span>
                  </span>
                </div>
              </div>

              <form method="POST" action="{{ route('orders.store') }}"
                    x-data="{ method: '{{ $deliveryDefault }}', showChannels: false }">
                @csrf
                {{-- Carry the validated coupon + referral codes forward so the
                     order is created with the same discount the user just saw.
                     OrderController re-validates server-side; these are hints,
                     not authority. --}}
                @if(!empty($couponCode))
                  <input type="hidden" name="coupon_code" value="{{ $couponCode }}">
                @endif
                @if(!empty($referralCode))
                  <input type="hidden" name="referral_code" value="{{ $referralCode }}">
                @endif

                {{-- ── Delivery method ──────────────────────────────────
                     By default we show only a single compact line: "auto →
                     delivered via X." The full picker is collapsed behind a
                     tiny "เปลี่ยนช่องทาง" toggle to save decision fatigue —
                     95% of customers are happy with the recommended channel.
                ──────────────────────────────────────────────────────── --}}
                <div class="mb-4">
                  {{-- Collapsed summary row (always visible) --}}
                  <div x-show="!showChannels"
                       class="flex items-center justify-between gap-2 p-3 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900/50">
                    <div class="flex items-center gap-2 min-w-0">
                      <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center shrink-0">
                        <i class="bi bi-send-fill"></i>
                      </div>
                      <div class="min-w-0">
                        <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">วิธีรับรูปภาพ</div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                          ส่งอัตโนมัติทาง <strong class="text-indigo-600 dark:text-indigo-400">{{ $autoChannelThai }}</strong>
                          · ปลอดภัย · ทันที
                        </div>
                      </div>
                    </div>
                    <button type="button" @click="showChannels = true"
                            class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline whitespace-nowrap shrink-0">
                      เปลี่ยนช่องทาง
                    </button>
                  </div>

                  {{-- Expanded picker (shown on click) --}}
                  <div x-show="showChannels" x-cloak>
                  <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                      <i class="bi bi-send-fill text-indigo-500"></i> วิธีรับรูปภาพ
                    </label>
                    @if($cartPhotoCount >= $deliveryEmailThresh && $deliveryAutoSwitch)
                      <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/20">
                        <i class="bi bi-info-circle-fill"></i> รูปเยอะ — แนะนำอีเมล
                      </span>
                    @endif
                  </div>

                  <div class="space-y-2">
                    {{-- "auto" is always shown first as the recommended option --}}
                    @foreach(['auto','line','web','email'] as $method)
                      @if($method === 'auto' || in_array($method, $deliveryEnabled, true))
                        @php
                          $label     = $deliveryLabels[$method];
                          $isLineRow = $method === 'line';
                          $lineDisabled = $isLineRow && !$userHasLineOAuth;
                        @endphp
                        <label class="flex items-start gap-3 p-3 rounded-xl border transition cursor-pointer
                                      {{ $lineDisabled ? 'opacity-60 cursor-not-allowed' : '' }}"
                               :class="method === '{{ $method }}'
                                 ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10 ring-2 ring-indigo-500/20'
                                 : 'border-slate-200 dark:border-white/10 hover:border-indigo-300 dark:hover:border-indigo-500/50 bg-white dark:bg-slate-900/50'">
                          <input type="radio" name="delivery_method" value="{{ $method }}"
                                 x-model="method"
                                 @if($lineDisabled) disabled @endif
                                 class="mt-1 accent-indigo-600">
                          <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 text-sm font-semibold text-slate-900 dark:text-white">
                              <i class="bi {{ $label['icon'] }} text-indigo-500"></i>
                              {{ $label['title'] }}
                              @if($method === 'auto')
                                <span class="text-[10px] text-indigo-500 font-normal">
                                  → จะส่งทาง {{ $autoChannelThai }}
                                </span>
                              @endif
                            </div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $label['sub'] }}</div>
                            @if($lineDisabled)
                              <div class="text-[11px] text-amber-600 dark:text-amber-400 mt-1 inline-flex items-center gap-1">
                                <i class="bi bi-link-45deg"></i>
                                ยังไม่ได้เชื่อม LINE —
                                <a href="{{ \Illuminate\Support\Facades\Route::has('profile.edit') ? route('profile.edit') : '/profile' }}"
                                   class="underline hover:text-amber-700">เชื่อมบัญชี</a>
                              </div>
                            @endif
                          </div>
                        </label>
                      @endif
                    @endforeach
                  </div>
                  </div>{{-- end x-show="showChannels" wrapper --}}
                </div>

                <button type="submit"
                        class="w-full py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2">
                  <i class="bi bi-credit-card-fill"></i> ดำเนินการชำระเงิน
                </button>
              </form>

              <div class="mt-3 text-xs text-center text-slate-500 dark:text-slate-400">
                <i class="bi bi-shield-check text-emerald-500 mr-1"></i>
                ชำระเงินปลอดภัย · SSL เข้ารหัส
              </div>
            </div>
          </div>

          {{-- Trust badges --}}
          <div class="rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-white/5 p-4 grid grid-cols-2 gap-3 text-xs">
            <div class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
              <i class="bi bi-lightning-charge-fill text-emerald-500"></i> ดาวน์โหลดทันที
            </div>
            <div class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
              <i class="bi bi-infinity text-purple-500"></i> ใช้ได้ตลอดชีพ
            </div>
            <div class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
              <i class="bi bi-file-earmark-image text-blue-500"></i> ภาพคุณภาพสูง
            </div>
            <div class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
              <i class="bi bi-headset text-amber-500"></i> มีซัพพอร์ต
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif
</div>
@endsection
