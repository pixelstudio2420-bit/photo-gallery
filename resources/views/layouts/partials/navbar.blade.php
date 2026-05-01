<nav class="bg-slate-900/85 backdrop-blur-xl sticky top-0 z-50 border-b border-white/10"
   x-data="{ mobileOpen: false }">
  <div class="max-w-7xl mx-auto px-4">
    <div class="flex items-center justify-between h-16">
      {{-- Brand ─ prefer the uploaded site_logo when admin has set one;
           gracefully fall back to the camera icon so the navbar never ends
           up with a broken-image hole. `$siteLogo` is a relative storage
           path on whichever driver was active at upload time, so route it
           through StorageManager::resolveUrl() to get a real URL (probes
           R2 first, sweeps other drivers for legacy rows, and passes
           http(s):// URLs through unchanged). --}}
      @php
        $_brandLogoUrl = null;
        if (!empty($siteLogo)) {
          try {
            $_brandLogoUrl = app(\App\Services\StorageManager::class)->resolveUrl($siteLogo);
          } catch (\Throwable) { /* fall through to icon */ }
        }
        $_brandName = $siteName ?: config('app.name');
      @endphp
      <a class="font-bold flex items-center gap-2 text-white text-lg" href="{{ route('home') }}">
        @if($_brandLogoUrl)
          <img src="{{ $_brandLogoUrl }}" alt="{{ $_brandName }}"
               class="h-8 w-auto max-w-[140px] object-contain"
               onerror="this.onerror=null;this.replaceWith(Object.assign(document.createElement('i'),{className:'bi bi-camera2'}));">
        @else
          <i class="bi bi-camera2"></i>
        @endif
        <span>{{ $_brandName }}</span>
      </a>

      {{-- Mobile Toggle --}}
      <button class="lg:hidden text-white/70 hover:text-white p-2" type="button"
          @click="mobileOpen = !mobileOpen" aria-label="Toggle navigation">
        <i class="bi bi-list text-2xl"></i>
      </button>

      {{-- Desktop Nav --}}
      <div class="hidden lg:flex items-center flex-1 ml-6">
        {{-- Left Nav --}}
        <ul class="flex items-center gap-1">
          <li>
            <a class="px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('home') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white' }}"
              href="{{ route('home') }}">
              <i class="bi bi-house-door mr-1"></i>{{ __('nav.home') }}
            </a>
          </li>
          <li>
            <a class="px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('events.*') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white' }}"
              href="{{ route('events.index') }}">
              <i class="bi bi-grid-3x3-gap mr-1"></i>{{ __('nav.events') }}
            </a>
          </li>
          <li>
            {{-- Photographer search/discovery — links to the public
                 /photographers index so customers can browse and filter
                 by province/specialty/experience before booking. --}}
            <a class="px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('photographers.*') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white' }}"
              href="{{ route('photographers.index') }}">
              <i class="bi bi-camera-fill mr-1"></i>ช่างภาพ
            </a>
          </li>
          <li>
            <a class="px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('blog.*') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white' }}"
              href="{{ route('blog.index') }}">
              <i class="bi bi-newspaper mr-1"></i>{{ __('nav.blog') }}
            </a>
          </li>
          <li>
            <a class="px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('products.*') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white' }}"
              href="{{ route('products.index') }}">
              <i class="bi bi-box-seam mr-1"></i>{{ __('nav.products') }}
            </a>
          </li>
          <li>
            <a class="px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('contact') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white' }}"
              href="{{ route('contact') }}">
              <i class="bi bi-envelope mr-1"></i>{{ __('nav.contact') }}
            </a>
          </li>
          {{-- B2B sales entry — hidden once the user is logged in as a
               photographer (they already converted) to avoid the "sell to
               existing customer" anti-pattern. --}}
          @auth
            @if(!Auth::user()->photographerProfile)
            <li>
              <a class="px-3 py-2 rounded-lg text-sm font-semibold transition {{ request()->routeIs('sell-photos') ? 'text-white bg-white/10' : 'text-amber-300 hover:text-amber-200' }}"
                href="{{ route('sell-photos') }}">
                <i class="bi bi-camera-fill mr-1"></i>เริ่มขายรูป
              </a>
            </li>
            @endif
          @else
            <li>
              <a class="px-3 py-2 rounded-lg text-sm font-semibold transition {{ request()->routeIs('sell-photos') ? 'text-white bg-white/10' : 'text-amber-300 hover:text-amber-200' }}"
                href="{{ route('sell-photos') }}">
                <i class="bi bi-camera-fill mr-1"></i>เริ่มขายรูป
              </a>
            </li>
          @endauth
        </ul>

        {{-- Right Nav --}}
        <ul class="ml-auto flex items-center gap-2">
          {{-- Language Switcher (only shown when multi-lang is enabled AND there's more than 1 language) --}}
          @php
            $_multilangOn = \App\Http\Controllers\Api\LanguageApiController::isEnabled();
            $_enabledLocales = \App\Http\Controllers\Api\LanguageApiController::enabled();
            $_current = app()->getLocale();
            $_currentMeta = $_enabledLocales[$_current]
                ?? \App\Http\Controllers\Api\LanguageApiController::SUPPORTED[$_current]
                ?? ['native' => 'ไทย', 'flag' => '🇹🇭'];
          @endphp
          @if($_multilangOn && count($_enabledLocales) > 1)
          <li class="px-1">
            <div x-data="{ open: false }" class="relative">
              <button @click="open = !open" @click.outside="open = false" type="button"
                  class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-sm text-white/70 hover:text-white hover:bg-white/10 transition">
                <span class="text-base leading-none">{{ $_currentMeta['flag'] }}</span>
                <span class="hidden xl:inline font-medium">{{ strtoupper($_current) }}</span>
                <i class="bi bi-chevron-down text-[10px]"></i>
              </button>
              <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="absolute right-0 top-full mt-2 w-44 bg-white dark:bg-slate-800 rounded-xl shadow-xl dark:shadow-black/30 border border-gray-100 dark:border-white/10 overflow-hidden z-50">
                @foreach($_enabledLocales as $code => $meta)
                  <a href="{{ route('lang.switch', $code) }}?redirect={{ urlencode(request()->getRequestUri()) }}"
                     class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition {{ $_current === $code ? 'bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 font-semibold' : 'text-gray-700 dark:text-gray-300' }}">
                    <span class="text-lg leading-none">{{ $meta['flag'] }}</span>
                    <span class="flex-1">{{ $meta['native'] }}</span>
                    @if($_current === $code)<i class="bi bi-check-circle-fill text-indigo-600 dark:text-indigo-400"></i>@endif
                  </a>
                @endforeach
              </div>
            </div>
          </li>
          @endif

          {{-- Dark Mode Toggle --}}
          <li>
            <button type="button" class="theme-toggle text-white/70 hover:text-white p-2 transition" title="สลับโหมดกลางวัน/กลางคืน">
              <i class="bi bi-moon-fill"></i>
            </button>
          </li>

          @auth
            {{-- Notifications --}}
            <li class="relative" x-data="userNotifications()" x-init="init()" @click.outside="open = false">
              <button type="button" @click="toggle()" class="relative text-white/70 hover:text-white px-3 py-2 transition" title="{{ __('nav.notifications') }}">
                <i class="bi bi-bell text-lg" :class="unreadCount > 0 ? 'animate-pulse' : ''"></i>
                <span x-show="unreadCount > 0"
                      x-text="unreadCount > 99 ? '99+' : unreadCount"
                      class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full px-1.5 min-w-[18px] text-center font-semibold"></span>
              </button>

              {{-- Notification Dropdown --}}
              <div x-show="open" x-cloak
                   x-transition:enter="transition ease-out duration-150"
                   x-transition:enter-start="opacity-0 translate-y-1"
                   x-transition:enter-end="opacity-100 translate-y-0"
                   class="absolute right-0 top-full mt-2 w-[360px] bg-white dark:bg-slate-800 rounded-xl shadow-2xl dark:shadow-black/30 border border-gray-100 dark:border-white/10 overflow-hidden z-50">

                {{-- Header --}}
                <div class="flex items-center justify-between p-3 border-b border-gray-100 dark:border-white/10">
                  <h4 class="font-semibold text-gray-800 dark:text-gray-100 text-sm flex items-center gap-2">
                    <i class="bi bi-bell-fill text-indigo-500 dark:text-indigo-400"></i>
                    {{ __('nav.notifications') }}
                    <span x-show="unreadCount > 0"
                          x-text="unreadCount"
                          class="bg-indigo-500 text-white text-[10px] rounded-full px-2 py-0.5 font-medium"></span>
                  </h4>
                  <button type="button" x-show="unreadCount > 0" @click="markAllRead()"
                          class="text-xs text-indigo-500 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium transition">
                    <i class="bi bi-check2-all mr-1"></i>อ่านทั้งหมด
                  </button>
                </div>

                {{-- Loading --}}
                <div x-show="loading && notifications.length === 0" class="p-8 text-center">
                  <i class="bi bi-arrow-repeat animate-spin text-2xl text-gray-300 dark:text-gray-600"></i>
                </div>

                {{-- Empty --}}
                <template x-if="!loading && notifications.length === 0">
                  <div class="p-8 text-center">
                    <i class="bi bi-bell-slash text-3xl text-gray-300 dark:text-gray-600"></i>
                    <p class="text-gray-400 dark:text-gray-500 text-sm mt-2">ยังไม่มีการแจ้งเตือน</p>
                  </div>
                </template>

                {{-- List --}}
                <div class="max-h-[400px] overflow-y-auto">
                  <template x-for="n in notifications.slice(0, 8)" :key="n.id">
                    {{-- Server pre-normalizes via UserNotification::getActionHrefAttribute()
                         (handles absolute URLs, leading slashes, and empty values).
                         Falls back to the legacy JS guard if an older API payload
                         only ships action_url. --}}
                    <a :href="n.action_href
                        ? n.action_href
                        : (n.action_url
                            ? (/^https?:\/\//i.test(n.action_url)
                                ? n.action_url
                                : ('/' + n.action_url.replace(/^\//, '')))
                            : '#')"
                       @click="markRead(n.id, $event)"
                       :class="n.is_read ? 'bg-white dark:bg-slate-800' : 'bg-indigo-50/50 dark:bg-indigo-500/10'"
                       class="flex gap-3 p-3 border-b border-gray-50 dark:border-white/5 hover:bg-gray-50 dark:hover:bg-white/5 transition cursor-pointer">
                      <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 text-sm"
                           :class="typeStyle(n.type).bg + ' ' + typeStyle(n.type).text">
                        <i :class="'bi bi-' + typeStyle(n.type).icon"></i>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="flex items-start gap-2">
                          <p class="font-medium text-sm text-gray-800 dark:text-gray-100 line-clamp-1" x-text="n.title"></p>
                          <span x-show="!n.is_read" class="inline-block w-2 h-2 bg-indigo-500 rounded-full shrink-0 mt-1.5"></span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2" x-text="n.message"></p>
                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1" x-text="timeAgo(n.created_at)"></p>
                      </div>
                    </a>
                  </template>
                </div>

                {{-- Footer --}}
                <div class="border-t border-gray-100 dark:border-white/10 p-2 flex gap-1">
                  <a href="{{ route('notifications.index') }}"
                     class="flex-1 px-3 py-2 text-center text-sm text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 rounded-lg transition font-medium">
                    ดูทั้งหมด
                  </a>
                  <a href="{{ route('profile.notification-preferences') }}"
                     class="px-3 py-2 text-gray-400 dark:text-gray-500 hover:bg-gray-50 dark:hover:bg-white/5 rounded-lg transition" title="ตั้งค่า">
                    <i class="bi bi-gear"></i>
                  </a>
                </div>
              </div>
            </li>
            {{-- Wishlist --}}
            <li>
              <a class="relative text-white/70 hover:text-white px-3 py-2 transition" href="{{ route('wishlist.index') }}" title="{{ __('nav.my_wishlist') }}">
                <i class="bi bi-heart text-lg"></i>
                <span class="wishlist-badge absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full px-1 hidden"
                   id="wishlistBadge">0</span>
              </a>
            </li>
            {{-- Cart --}}
            <li>
              <a class="relative text-white/70 hover:text-white px-3 py-2 transition" href="{{ route('cart.index') }}" title="{{ __('nav.cart') }}">
                <i class="bi bi-bag text-lg"></i>
                <span class="cart-badge absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full px-1 {{ ($cartCount ?? 0) > 0 ? '' : 'hidden' }}">
                  {{ $cartCount ?? 0 }}
                </span>
              </a>
            </li>
            {{-- User Dropdown --}}
            <li class="relative" x-data="{ open: false }" @click.away="open = false">
              <button class="flex items-center gap-2 text-white/70 hover:text-white px-3 py-2 transition text-sm"
                  @click="open = !open">
                <x-avatar :src="Auth::user()->avatar"
                      :name="Auth::user()->first_name . ' ' . Auth::user()->last_name"
                      :user-id="Auth::id()"
                      size="sm" />
                <span class="text-sm">{{ Auth::user()->first_name }}</span>
                <i class="bi bi-chevron-down text-xs"></i>
              </button>
              @php
                $_isPhotographer = Auth::user()->photographerProfile !== null;
                $_photographerApproved = $_isPhotographer && Auth::user()->photographerProfile->status === 'approved';
                $_photographerPending = $_isPhotographer && Auth::user()->photographerProfile->status === 'pending';
              @endphp
              <div x-show="open"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute right-0 mt-2 bg-white dark:bg-slate-800 rounded-xl shadow-xl dark:shadow-black/30 border border-gray-100 dark:border-white/10 min-w-[260px] py-1 z-50"
                 x-cloak>
                {{-- User Info Header --}}
                <div class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <x-avatar :src="Auth::user()->avatar"
                          :name="Auth::user()->first_name . ' ' . Auth::user()->last_name"
                          :user-id="Auth::id()"
                          size="md" />
                    <div class="overflow-hidden">
                      <div class="font-semibold truncate text-gray-900 dark:text-gray-100">{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}</div>
                      <div class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ Auth::user()->email }}</div>
                    </div>
                  </div>
                  @if($_photographerApproved)
                  <div class="mt-2">
                    <span class="inline-flex items-center rounded-full bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-500 dark:to-blue-600 text-white text-[10px] px-2.5 py-1">
                      <i class="bi bi-camera mr-1"></i>ช่างภาพ
                    </span>
                  </div>
                  @elseif($_photographerPending)
                  <div class="mt-2">
                    <span class="inline-flex items-center rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-300 text-[10px] px-2.5 py-1">
                      <i class="bi bi-hourglass-split mr-1"></i>รอการอนุมัติช่างภาพ
                    </span>
                  </div>
                  @endif
                </div>
                <div class="border-t border-gray-100 dark:border-white/10 my-1"></div>

                {{-- Admin Quick-Access (if user is also admin) --}}
                @if(Auth::guard('admin')->check())
                <div class="px-4 pt-1 pb-1">
                  <span class="text-gray-500 dark:text-gray-400 font-semibold uppercase text-[10px] tracking-wide">ผู้ดูแลระบบ</span>
                </div>
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="{{ route('admin.dashboard') }}">
                  <i class="bi bi-shield-lock mr-2 text-red-500 dark:text-red-400"></i>แดชบอร์ดแอดมิน
                </a>
                <div class="border-t border-gray-100 dark:border-white/10 my-1"></div>
                @endif

                {{-- Photographer Section --}}
                @if($_photographerApproved)
                <div class="px-4 pt-1 pb-1">
                  <span class="text-gray-500 dark:text-gray-400 font-semibold uppercase text-[10px] tracking-wide">ช่างภาพ</span>
                </div>
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="{{ route('photographer.dashboard') }}">
                  <i class="bi bi-speedometer2 mr-2 text-blue-600 dark:text-blue-400"></i>แดชบอร์ดช่างภาพ
                </a>
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="{{ route('photographer.events.index') }}">
                  <i class="bi bi-calendar-event mr-2 text-cyan-600 dark:text-cyan-400"></i>จัดการอีเวนต์
                </a>
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="{{ route('photographer.earnings') }}">
                  <i class="bi bi-wallet2 mr-2 text-emerald-500 dark:text-emerald-400"></i>รายได้ของฉัน
                </a>
                <div class="border-t border-gray-100 dark:border-white/10 my-1"></div>
                <div class="px-4 pt-1 pb-1">
                  <span class="text-gray-500 dark:text-gray-400 font-semibold uppercase text-[10px] tracking-wide">ซื้อรูปภาพ</span>
                </div>
                @endif

                {{-- Customer Section (always shown) --}}
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="{{ route('profile') }}">
                  <i class="bi bi-grid mr-2 text-indigo-500 dark:text-indigo-400"></i>{{ __('nav.my_account') }}
                </a>
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="{{ route('profile.orders') }}">
                  <i class="bi bi-receipt mr-2 text-blue-500 dark:text-blue-400"></i>{{ __('nav.my_orders') }}
                </a>
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="{{ route('profile.edit') }}">
                  <i class="bi bi-person-gear mr-2 text-slate-500 dark:text-slate-400"></i>{{ __('profile.edit_profile') }}
                </a>
                @if(app(\App\Services\UserStorageService::class)->systemEnabled())
                  <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="{{ route('storage.index') }}">
                    <i class="bi bi-cloud-fill mr-2 text-sky-500 dark:text-sky-400"></i>คลาวด์ของฉัน
                  </a>
                @endif
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="{{ route('profile.referrals') }}">
                  <i class="bi bi-people-fill mr-2 text-rose-500 dark:text-rose-400"></i>แนะนำเพื่อน
                </a>
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="{{ route('support.index') }}">
                  <i class="bi bi-life-preserver mr-2 text-pink-500 dark:text-pink-400"></i>Support Tickets
                </a>

                {{-- Mode switcher / Upgrade CTA --}}
                @if($_isPhotographer && $_photographerApproved)
                  {{-- Already a photographer → show "Switch to Photographer Mode" --}}
                  <div class="border-t border-gray-100 dark:border-white/10 my-1"></div>
                  <div class="px-4 py-2">
                    <a href="{{ route('photographer.dashboard') }}"
                      class="flex items-center justify-center gap-2 w-full py-2 bg-gradient-to-r from-indigo-600 to-purple-600 dark:from-indigo-500 dark:to-purple-500 text-white rounded-[10px] text-[0.82rem] font-semibold hover:from-indigo-700 hover:to-purple-700 transition shadow-md shadow-indigo-600/20">
                      <i class="bi bi-camera"></i> เปลี่ยนเป็นโหมดช่างภาพ
                    </a>
                  </div>
                @elseif(!$_isPhotographer)
                  {{-- Customer-only → quick upgrade flow (uses existing account data) --}}
                  <div class="border-t border-gray-100 dark:border-white/10 my-1"></div>
                  <div class="px-4 py-2">
                    <a href="{{ route('photographer-onboarding.quick') }}"
                      class="flex items-center justify-center gap-2 w-full py-2 bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-500 dark:to-blue-600 text-white rounded-[10px] text-[0.82rem] font-semibold hover:from-blue-700 hover:to-blue-800 dark:hover:from-blue-600 dark:hover:to-blue-500 transition">
                      <i class="bi bi-camera"></i> {{ __('nav.become_photographer') }}
                    </a>
                    <p class="text-[10px] text-center text-gray-500 dark:text-gray-400 mt-1.5 mb-0">
                      ใช้บัญชีเดิม · ไม่ต้องสมัครใหม่
                    </p>
                  </div>
                @endif

                <div class="border-t border-gray-100 dark:border-white/10 my-1"></div>
                <form method="POST" action="{{ route('auth.logout') }}">
                  @csrf
                  <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-500 dark:text-red-400 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                    <i class="bi bi-box-arrow-right mr-2"></i>{{ __('nav.logout') }}
                  </button>
                </form>
              </div>
            </li>
          @else
            <li class="hidden lg:block">
              <a class="text-white/70 hover:text-white px-3 py-2 rounded-lg text-sm font-medium transition" href="{{ route('auth.register') }}">{{ __('nav.register') }}</a>
            </li>
            <li>
              <a class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white font-semibold px-6 py-2 rounded-full text-sm hover:from-indigo-600 hover:to-indigo-700 transition inline-flex items-center gap-1"
                href="{{ route('auth.login') }}">
                <i class="bi bi-person mr-1"></i>{{ __('nav.login') }}
              </a>
            </li>
          @endauth
        </ul>
      </div>
    </div>

    {{-- Mobile Nav --}}
    <div class="lg:hidden" x-show="mobileOpen" x-collapse x-cloak>
      <div class="pb-4 pt-2 border-t border-white/10">
        <ul class="space-y-1">
          <li>
            <a class="block px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('home') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white' }}"
              href="{{ route('home') }}">
              <i class="bi bi-house-door mr-1"></i>{{ __('nav.home') }}
            </a>
          </li>
          <li>
            <a class="block px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('events.*') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white' }}"
              href="{{ route('events.index') }}">
              <i class="bi bi-grid-3x3-gap mr-1"></i>{{ __('nav.events') }}
            </a>
          </li>
          <li>
            {{-- Mobile mirror of the desktop "ช่างภาพ" link — same
                 /photographers index, with active-state highlight when
                 the user is viewing a photographer page. --}}
            <a class="block px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('photographers.*') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white' }}"
              href="{{ route('photographers.index') }}">
              <i class="bi bi-camera-fill mr-1"></i>ช่างภาพ
            </a>
          </li>
          <li>
            <a class="block px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('blog.*') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white' }}"
              href="{{ route('blog.index') }}">
              <i class="bi bi-newspaper mr-1"></i>{{ __('nav.blog') }}
            </a>
          </li>
          <li>
            <a class="block px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('products.*') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white' }}"
              href="{{ route('products.index') }}">
              <i class="bi bi-box-seam mr-1"></i>{{ __('nav.products') }}
            </a>
          </li>
          <li>
            <a class="block px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('contact') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white' }}"
              href="{{ route('contact') }}">
              <i class="bi bi-envelope mr-1"></i>{{ __('nav.contact') }}
            </a>
          </li>
        </ul>

        {{-- Mobile Language Switcher (only shown when multi-lang is enabled AND there's more than 1 language) --}}
        @if($_multilangOn && count($_enabledLocales) > 1)
        <div class="mt-3 px-3 border-t border-white/10 pt-3">
          <p class="text-white/50 text-xs uppercase tracking-wide mb-2">Language / ภาษา</p>
          <div class="flex gap-2">
            @foreach($_enabledLocales as $code => $meta)
              <a href="{{ route('lang.switch', $code) }}?redirect={{ urlencode(request()->getRequestUri()) }}"
                 class="flex-1 flex items-center justify-center gap-1.5 px-2 py-2 rounded-lg text-sm transition
                    {{ app()->getLocale() === $code ? 'bg-white/20 text-white' : 'bg-white/5 text-white/60 hover:bg-white/10' }}">
                <span>{{ $meta['flag'] }}</span>
                <span class="text-xs font-medium">{{ strtoupper($code) }}</span>
              </a>
            @endforeach
          </div>
        </div>
        @endif

        <div class="border-t border-white/10 mt-3 pt-3">
          @auth
            <div class="flex items-center gap-2 px-3 py-2">
              <x-avatar :src="Auth::user()->avatar"
                    :name="Auth::user()->first_name . ' ' . Auth::user()->last_name"
                    :user-id="Auth::id()"
                    size="sm" />
              <span class="text-white text-sm font-medium">{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}</span>
            </div>
            <ul class="space-y-1 mt-2">
              <li>
                <a class="block px-3 py-2 text-white/70 hover:text-white text-sm transition" href="{{ route('profile') }}">
                  <i class="bi bi-grid mr-2"></i>{{ __('nav.my_account') }}
                </a>
              </li>
              <li>
                <a class="block px-3 py-2 text-white/70 hover:text-white text-sm transition" href="{{ route('profile.orders') }}">
                  <i class="bi bi-receipt mr-2"></i>{{ __('nav.my_orders') }}
                </a>
              </li>
              <li>
                <a class="block px-3 py-2 text-white/70 hover:text-white text-sm transition" href="{{ route('profile.referrals') }}">
                  <i class="bi bi-people-fill mr-2"></i>แนะนำเพื่อน
                </a>
              </li>
              <li>
                <form method="POST" action="{{ route('auth.logout') }}">
                  @csrf
                  <button type="submit" class="block w-full text-left px-3 py-2 text-red-400 hover:text-red-300 text-sm transition">
                    <i class="bi bi-box-arrow-right mr-2"></i>{{ __('nav.logout') }}
                  </button>
                </form>
              </li>
            </ul>
          @else
            <div class="flex items-center gap-3 px-3 mt-2">
              <a class="text-white/70 hover:text-white text-sm transition" href="{{ route('auth.register') }}">{{ __('nav.register') }}</a>
              <a class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white font-semibold px-6 py-2 rounded-full text-sm inline-flex items-center gap-1"
                href="{{ route('auth.login') }}">
                <i class="bi bi-person mr-1"></i>{{ __('nav.login') }}
              </a>
            </div>
          @endauth
        </div>
      </div>
    </div>
  </div>
</nav>

@auth
<script>
  function userNotifications() {
    return {
      open: false,
      notifications: [],
      unreadCount: 0,
      loading: false,
      lastCheck: null,
      pollInterval: null,

      init() {
        this.fetchUnreadCount();
        // Poll every 30 seconds — gated on document.hidden so a tab
        // that's been backgrounded for hours doesn't keep hammering
        // the API. We still re-fetch immediately when the tab becomes
        // visible again so the badge isn't stale on focus.
        this.pollInterval = setInterval(() => {
          if (!document.hidden) this.fetchUnreadCount();
        }, 30000);

        document.addEventListener('visibilitychange', () => {
          if (!document.hidden) this.fetchUnreadCount();
        });
      },

      toggle() {
        this.open = !this.open;
        if (this.open && this.notifications.length === 0) {
          this.fetch();
        }
      },

      async fetch() {
        this.loading = true;
        try {
          const response = await fetch('/api/notifications', { headers: { 'Accept': 'application/json' } });
          if (response.ok) {
            const data = await response.json();
            this.notifications = data.notifications || [];
            this.unreadCount = data.unread_count || 0;
          }
        } catch (e) { console.error('Failed to fetch notifications', e); }
        this.loading = false;
      },

      async fetchUnreadCount() {
        try {
          const response = await fetch('/api/notifications/unread-count', { headers: { 'Accept': 'application/json' } });
          if (response.ok) {
            const data = await response.json();
            this.unreadCount = data.unread_count || 0;
          }
        } catch (e) {}
      },

      async markRead(id, event) {
        // Mark as read in background, allow navigation
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        try {
          await fetch(`/api/notifications/${id}/read`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
          });
          // Update local state
          const n = this.notifications.find(x => x.id === id);
          if (n && !n.is_read) {
            n.is_read = true;
            this.unreadCount = Math.max(0, this.unreadCount - 1);
          }
        } catch (e) {}
      },

      async markAllRead() {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        try {
          await fetch('/api/notifications/read-all', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
          });
          this.notifications.forEach(n => n.is_read = true);
          this.unreadCount = 0;
        } catch (e) {}
      },

      typeStyle(type) {
        const styles = {
          order:                { icon: 'cart-check', bg: 'bg-blue-100 dark:bg-blue-500/20', text: 'text-blue-600 dark:text-blue-300' },
          payment_approved:     { icon: 'check-circle', bg: 'bg-emerald-100 dark:bg-emerald-500/20', text: 'text-emerald-600 dark:text-emerald-300' },
          payment_rejected:     { icon: 'x-circle', bg: 'bg-red-100 dark:bg-red-500/20', text: 'text-red-600 dark:text-red-300' },
          download_ready:       { icon: 'download', bg: 'bg-indigo-100 dark:bg-indigo-500/20', text: 'text-indigo-600 dark:text-indigo-300' },
          refund:               { icon: 'arrow-counterclockwise', bg: 'bg-amber-100 dark:bg-amber-500/20', text: 'text-amber-600 dark:text-amber-300' },
          new_sale:             { icon: 'cash-coin', bg: 'bg-emerald-100 dark:bg-emerald-500/20', text: 'text-emerald-600 dark:text-emerald-300' },
          payout:               { icon: 'wallet2', bg: 'bg-purple-100 dark:bg-purple-500/20', text: 'text-purple-600 dark:text-purple-300' },
          review:               { icon: 'star-fill', bg: 'bg-yellow-100 dark:bg-yellow-500/20', text: 'text-yellow-600 dark:text-yellow-300' },
          photographer_approved:{ icon: 'camera', bg: 'bg-indigo-100 dark:bg-indigo-500/20', text: 'text-indigo-600 dark:text-indigo-300' },
          contact:              { icon: 'chat-dots', bg: 'bg-pink-100 dark:bg-pink-500/20', text: 'text-pink-600 dark:text-pink-300' },
          system:               { icon: 'megaphone', bg: 'bg-slate-100 dark:bg-slate-500/20', text: 'text-slate-600 dark:text-slate-300' },
          welcome:              { icon: 'emoji-smile', bg: 'bg-green-100 dark:bg-green-500/20', text: 'text-green-600 dark:text-green-300' },
        };
        return styles[type] || { icon: 'bell', bg: 'bg-gray-100 dark:bg-gray-500/20', text: 'text-gray-600 dark:text-gray-300' };
      },

      timeAgo(dt) {
        if (!dt) return '';
        const now = new Date();
        const date = new Date(dt);
        const diff = Math.floor((now - date) / 1000);
        if (diff < 60) return 'เมื่อสักครู่';
        if (diff < 3600) return Math.floor(diff/60) + ' นาทีที่แล้ว';
        if (diff < 86400) return Math.floor(diff/3600) + ' ชั่วโมงที่แล้ว';
        if (diff < 604800) return Math.floor(diff/86400) + ' วันที่แล้ว';
        return date.toLocaleDateString('th-TH');
      },
    };
  }
</script>
@endauth
