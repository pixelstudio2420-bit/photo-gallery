@php
  // Current admin & permissions
  $admin = Auth::guard('admin')->user();
  $can = fn(string $perm) => $admin->hasPermission($perm);

  // Badge counts (only query if admin has permission)
  $badgePendingSlips  = $can('payment_slips') ? \App\Models\PaymentSlip::where('verify_status','pending')->count() : 0;
  $badgePendingOrders = $can('orders') ? \App\Models\Order::whereIn('status',['pending_payment','pending_review'])->count() : 0;
  $badgeNewUsers    = $can('users') ? \App\Models\User::where('created_at','>=',now()->subDays(7))->count() : 0;
  $badgeNewMessages  = $can('messages') ? \Illuminate\Support\Facades\DB::table('contact_messages')->where('status','new')->count() : 0;
  $badgePendingDigital = $can('products') ? \App\Models\DigitalOrder::where('status','pending_review')->count() : 0;
  $badgeCategoryCount = $can('categories') ? \Illuminate\Support\Facades\DB::table('event_categories')->count() : 0;
  $badgeDraftEvents   = $can('events') ? \Illuminate\Support\Facades\DB::table('event_events')->where('status','draft')->count() : 0;
  $badgeFlaggedPhotos = $can('reviews') || $can('events')
    ? \Illuminate\Support\Facades\DB::table('event_photos')
        ->whereIn('moderation_status', ['flagged', 'pending'])
        ->where('status', 'active')
        ->count()
    : 0;

  // Reusable class helpers
  $linkCls = 'group flex items-center gap-3 px-5 py-2.5 text-white/60 no-underline text-[0.82rem] font-medium rounded-lg mx-2.5 my-0.5 transition-all duration-200 hover:text-white hover:bg-white/[0.07]';
  $linkActive = '!text-white !bg-indigo-500/20 shadow-[inset_3px_0_0_theme(colors.indigo.400)] !font-semibold';
  $sublinkCls = 'group flex items-center gap-2.5 py-2 px-4 ml-7 mr-3 text-white/50 no-underline text-[0.78rem] rounded-md transition-all duration-200 hover:text-white hover:bg-white/[0.06] relative before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:w-1.5 before:h-1.5 before:rounded-full before:bg-white/10 before:transition-all';
  $sublinkActive = '!text-white !font-semibold !bg-indigo-500/15 before:!bg-indigo-400 before:!shadow-[0_0_6px_theme(colors.indigo.400/50)]';
  $sectionCls = 'px-5 pt-5 pb-1.5 text-[0.65rem] uppercase tracking-[0.12em] text-white/25 font-bold';
  $badgeRed = 'text-[0.6rem] font-bold px-1.5 py-0.5 rounded-full ml-auto bg-red-500/20 text-red-400 leading-none';
  $badgeAmber = 'text-[0.6rem] font-bold px-1.5 py-0.5 rounded-full ml-auto bg-amber-500/20 text-amber-400 leading-none';
  $badgeBlue = 'text-[0.6rem] font-bold px-1.5 py-0.5 rounded-full ml-auto bg-blue-500/20 text-blue-400 leading-none';
  $badgeSlate = 'text-[0.6rem] font-bold px-1.5 py-0.5 rounded-full ml-auto bg-slate-500/20 text-slate-400 leading-none';
  $chevronCls = 'bi bi-chevron-right ml-auto text-[0.55rem] opacity-30 transition-transform duration-300';

  // Precompute section visibility flags to keep templates readable
  $showSales      = $can('orders') || $can('payment_slips') || $can('reviews') || $can('finance') || $can('events') || $can('settings');
  $showContent    = $can('events') || $can('categories') || $can('products');
  $showPhotogs    = $can('photographers') || $can('finance') || $can('commission');
  $showCustomers  = $can('users') || $can('online_users') || $can('settings');
  $showPricing    = $can('pricing') || $can('coupons');
  $showMarketing  = $admin->isSuperAdmin() || $can('settings');
  $showFinance    = $can('finance') || $can('payment_methods');
  $showComms      = $can('messages') || $can('settings');
  $subsEnabled    = app(\App\Services\SubscriptionService::class)->systemEnabled();
@endphp

{{-- ═══════════════════════════════════════════════
     Admin Sidebar — Reorganised into 10 clean sections
     1 Dashboard
     2 ขายและคำสั่งซื้อ (Sales & Orders)
     3 เนื้อหา (Content)
     4 ช่างภาพ (Photographers)
     5 ลูกค้า (Customers)
     6 ราคา & โปรโมชัน (Pricing & Promotions)
     7 การตลาด (Marketing)
     8 การเงิน (Finance)
     9 การสื่อสาร (Communications)
    10 ตั้งค่าระบบ (Settings — split into 6 sub-groups)
    11 จัดการแอดมิน (Superadmin only)
     ═══════════════════════════════════════════════ --}}
<aside id="admin-sidebar"
  class="fixed inset-y-0 left-0 z-[1040] flex flex-col w-[260px] bg-[#12121a] text-white/70
         overflow-y-auto overflow-x-hidden transition-all duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]
         max-lg:-translate-x-full admin-scrollbar"
  :class="{
    'max-lg:translate-x-0 max-lg:shadow-2xl': sidebarOpen,
    'lg:!w-[72px]': sidebarCollapsed
  }">

  {{-- ── Brand ── --}}
  <div class="flex items-center gap-3 px-5 h-16 shrink-0 border-b border-white/[0.06]"
       :class="{ '!justify-center !px-0': sidebarCollapsed }">
    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white shrink-0 shadow-lg shadow-indigo-500/20">
      <i class="bi bi-camera2 text-base"></i>
    </div>
    <div class="flex flex-col leading-tight overflow-hidden" x-show="!sidebarCollapsed" x-transition.opacity>
      <span class="font-bold text-white text-sm truncate">{{ $siteName ?? config('app.name') }}</span>
      <span class="text-[0.6rem] text-indigo-400 uppercase tracking-widest font-semibold">
        @if($admin->isSuperAdmin()) Super Admin @else {{ $admin->role_info['label'] ?? 'Admin' }} @endif
      </span>
    </div>
  </div>

  {{-- ── Navigation ── --}}
  <nav class="flex-1 py-3">

    {{-- ═══════════════════════════════════════════
         1. Dashboard
         ═══════════════════════════════════════════ --}}
    @if($can('dashboard'))
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.dashboard') ? $linkActive : '' }}"
       href="{{ route('admin.dashboard') }}"
       :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-grid-1x2-fill text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>{{ __('admin.dashboard') }}</span>
    </a>
    @endif

    {{-- ═══════════════════════════════════════════
         2. ขายและคำสั่งซื้อ (Sales & Orders)
         ═══════════════════════════════════════════ --}}
    @if($showSales)
    <div class="{{ $sectionCls }}" x-show="!sidebarCollapsed" x-transition.opacity>ขายและคำสั่งซื้อ</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    @if($can('orders'))
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.orders.*') ? $linkActive : '' }}"
       href="{{ route('admin.orders.index') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-bag-check text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>คำสั่งซื้อ</span>
      @if($badgePendingOrders > 0)
      <span class="{{ $badgeAmber }}" x-show="!sidebarCollapsed">{{ $badgePendingOrders }}</span>
      @endif
    </a>
    @endif

    @if($can('payment_slips'))
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.payments.slips') ? $linkActive : '' }}"
       href="{{ route('admin.payments.slips') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-receipt-cutoff text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>ตรวจสอบสลิป</span>
      @if($badgePendingSlips > 0)
      <span class="{{ $badgeRed }}" x-show="!sidebarCollapsed">{{ $badgePendingSlips }}</span>
      @endif
    </a>
    @endif

    @if($can('finance'))
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.invoices.*') ? $linkActive : '' }}"
       href="{{ route('admin.invoices.index') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-receipt text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>ใบเสร็จ</span>
    </a>
    @endif

    @if($can('reviews'))
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.reviews.*') ? $linkActive : '' }}"
       href="{{ route('admin.reviews.index') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-star text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>รีวิว</span>
    </a>
    @endif

    {{-- Bookings — calendar + admin oversight (visible to anyone with reviews permission for now) --}}
    @if($can('reviews') || $can('events') || $admin->isSuperAdmin())
    @php
      $_pendingBookingsCount = \App\Models\Booking::where('status', 'pending')->count();
    @endphp
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.bookings.*') ? $linkActive : '' }}"
       href="{{ route('admin.bookings.index') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-calendar-check text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>คิวงาน Booking</span>
      @if($_pendingBookingsCount > 0)
        <span class="{{ $badgeRed }}" x-show="!sidebarCollapsed" title="รอยืนยัน">{{ $_pendingBookingsCount }}</span>
      @endif
    </a>
    @endif

    @if($can('reviews') || $can('events') || $can('settings'))
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.moderation.*') ? $linkActive : '' }}"
       href="{{ route('admin.moderation.index') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-shield-exclamation text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>ตรวจสอบภาพ AI</span>
      @if($badgeFlaggedPhotos > 0)
      <span class="{{ $badgeRed }}" x-show="!sidebarCollapsed" title="ภาพที่รอตรวจสอบ/ติดธง">{{ $badgeFlaggedPhotos }}</span>
      @endif
    </a>
    @endif
    @endif

    {{-- ═══════════════════════════════════════════
         3. เนื้อหา (Content)
         ═══════════════════════════════════════════ --}}
    @if($showContent)
    <div class="{{ $sectionCls }}" x-show="!sidebarCollapsed" x-transition.opacity>เนื้อหา</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    @if($can('events'))
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.events.*') && !request()->routeIs('admin.events.qrcode') ? $linkActive : '' }}"
       href="{{ route('admin.events.index') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-calendar-event text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>{{ __('nav.events_manage') }}</span>
      @if($badgeDraftEvents > 0)
      <span class="{{ $badgeAmber }}" x-show="!sidebarCollapsed">{{ $badgeDraftEvents }}</span>
      @endif
    </a>
    @endif

    @if($can('categories'))
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.categories.*') ? $linkActive : '' }}"
       href="{{ route('admin.categories.index') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-tags text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>{{ __('nav.categories') }}</span>
      @if($badgeCategoryCount > 0)
      <span class="{{ $badgeSlate }}" x-show="!sidebarCollapsed">{{ $badgeCategoryCount }}</span>
      @endif
    </a>
    @endif

    @if($can('products'))
    @php $prodOpen = request()->routeIs('admin.products.*') || request()->routeIs('admin.digital-orders.*'); @endphp
    <div x-data="{ open: {{ $prodOpen ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $prodOpen ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-box-seam text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>สินค้าดิจิทัล</span>
        @if($badgePendingDigital > 0)
        <span class="{{ $badgeAmber }}" x-show="!sidebarCollapsed">{{ $badgePendingDigital }}</span>
        @endif
        <i class="{{ $chevronCls }}" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.products.index') || request()->routeIs('admin.products.show') || request()->routeIs('admin.products.edit') ? $sublinkActive : '' }}"
           href="{{ route('admin.products.index') }}">จัดการสินค้า</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.products.create') ? $sublinkActive : '' }}"
           href="{{ route('admin.products.create') }}">เพิ่มสินค้าใหม่</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.digital-orders.*') ? $sublinkActive : '' }}"
           href="{{ route('admin.digital-orders.index') }}">
          คำสั่งซื้อ
          @if($badgePendingDigital > 0)<span class="{{ $badgeRed }}">{{ $badgePendingDigital }}</span>@endif
        </a>
      </div>
    </div>
    @endif

    @php $blogOpen = request()->routeIs('admin.blog.*'); @endphp
    <div x-data="{ open: {{ $blogOpen ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $blogOpen ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-journal-richtext text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>บล็อก</span>
        <i class="{{ $chevronCls }}" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.blog.posts.*') ? $sublinkActive : '' }}"
           href="{{ route('admin.blog.posts.index') }}">
          <i class="bi bi-newspaper text-[0.6rem]"></i> บทความ
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.blog.categories.*') ? $sublinkActive : '' }}"
           href="{{ route('admin.blog.categories.index') }}">
          <i class="bi bi-folder2 text-[0.6rem]"></i> หมวดหมู่บล็อก
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.blog.tags.*') ? $sublinkActive : '' }}"
           href="{{ route('admin.blog.tags.index') }}">
          <i class="bi bi-tags text-[0.6rem]"></i> แท็ก
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.blog.affiliate.*') ? $sublinkActive : '' }}"
           href="{{ route('admin.blog.affiliate.index') }}">
          <i class="bi bi-link-45deg text-[0.6rem]"></i> Affiliate Links
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.blog.ai.index') || request()->routeIs('admin.blog.ai.history') || request()->routeIs('admin.blog.ai.cost') ? $sublinkActive : '' }}"
           href="{{ route('admin.blog.ai.index') }}">
          <i class="bi bi-robot text-[0.6rem]"></i> AI Tools
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.blog.ai.toggles*') ? $sublinkActive : '' }}"
           href="{{ route('admin.blog.ai.toggles') }}">
          <i class="bi bi-toggles text-[0.6rem]"></i> เปิด-ปิด AI
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.blog.news.*') ? $sublinkActive : '' }}"
           href="{{ route('admin.blog.news.index') }}">
          <i class="bi bi-rss text-[0.6rem]"></i> News Aggregator
        </a>
      </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════
         4. ช่างภาพ (Photographers)
         ═══════════════════════════════════════════ --}}
    @if($showPhotogs)
    <div class="{{ $sectionCls }}" x-show="!sidebarCollapsed" x-transition.opacity>ช่างภาพ</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    @if($can('photographers'))
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.photographers.*') ? $linkActive : '' }}"
       href="{{ route('admin.photographers.index') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-camera text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>{{ __('nav.photographers') }}</span>
    </a>
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.photographer-onboarding.*') ? $linkActive : '' }}"
       href="{{ route('admin.photographer-onboarding.index') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-person-plus text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>Onboarding</span>
    </a>
    @endif

    @if($can('finance'))
    @php $commOpen = request()->routeIs('admin.commission.*'); @endphp
    <div x-data="{ open: {{ $commOpen ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $commOpen ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-percent text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>คอมมิชชั่น</span>
        <i class="{{ $chevronCls }}" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.commission.index') ? $sublinkActive : '' }}" href="{{ route('admin.commission.index') }}">แดชบอร์ด</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.commission.tiers') ? $sublinkActive : '' }}" href="{{ route('admin.commission.tiers') }}">ระดับคอมมิชชั่น</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.commission.bulk') ? $sublinkActive : '' }}" href="{{ route('admin.commission.bulk') }}">ปรับแบบกลุ่ม</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.commission.history') ? $sublinkActive : '' }}" href="{{ route('admin.commission.history') }}">ประวัติการเปลี่ยนแปลง</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.commission.settings') ? $sublinkActive : '' }}" href="{{ route('admin.commission.settings') }}">ตั้งค่า</a>
      </div>
    </div>
    @endif

    {{-- Monetization — Brand Ads + Photographer Promotions revenue.
         Distinct from Subscriptions (own paid tiers) and Commission (cut
         on photographer sales) — this section sells ad slots to brands
         and boost slots to photographers. --}}
    @if($can('finance'))
    @php $monetizeOpen = request()->routeIs('admin.monetization.*'); @endphp
    <div x-data="{ open: {{ $monetizeOpen ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $monetizeOpen ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-megaphone text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>Monetization</span>
        <i class="{{ $chevronCls }}" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.monetization.dashboard') ? $sublinkActive : '' }}" href="{{ route('admin.monetization.dashboard') }}">
          <i class="bi bi-graph-up-arrow text-emerald-400 text-[0.6rem]"></i> รายได้รวม
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.monetization.campaigns.*') ? $sublinkActive : '' }}" href="{{ route('admin.monetization.campaigns.index') }}">
          <i class="bi bi-megaphone-fill text-indigo-400 text-[0.6rem]"></i> Brand Campaigns
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.monetization.addons.*') ? $sublinkActive : '' }}" href="{{ route('admin.monetization.addons.index') }}">
          <i class="bi bi-box-seam text-indigo-400 text-[0.6rem]"></i> Addon Catalog
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.monetization.promotions*') ? $sublinkActive : '' }}" href="{{ route('admin.monetization.promotions') }}">
          <i class="bi bi-stars text-amber-400 text-[0.6rem]"></i> Photographer Promotions
        </a>
      </div>
    </div>
    @endif

    @if($can('commission') || $can('photographers'))
    {{-- Upload Credits — photographer-facing currency --}}
    @php $creditsOpen = request()->routeIs('admin.credits.*'); @endphp
    <div x-data="{ open: {{ $creditsOpen ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $creditsOpen ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-coin text-base w-5 text-center shrink-0 {{ $creditsOpen ? '!text-amber-400' : '' }}"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity class="flex-1 text-left">Upload Credits</span>
        <i x-show="!sidebarCollapsed" x-transition.opacity
           class="bi text-[10px] opacity-60" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.credits.index') ? $sublinkActive : '' }}" href="{{ route('admin.credits.index') }}">ภาพรวม</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.credits.packages.*') ? $sublinkActive : '' }}" href="{{ route('admin.credits.packages.index') }}">แพ็คเก็จ</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.credits.photographers.*') ? $sublinkActive : '' }}" href="{{ route('admin.credits.photographers.index') }}">ยอดช่างภาพ</a>
      </div>
    </div>

    {{-- Subscriptions — hidden when globally disabled --}}
    @if($subsEnabled)
      @php $subsOpen = request()->routeIs('admin.subscriptions.*'); @endphp
      <div x-data="{ open: {{ $subsOpen ? 'true' : 'false' }} }">
        <button class="{{ $linkCls }} w-full {{ $subsOpen ? '!text-white !bg-indigo-500/10' : '' }}"
          @click="open = !open" type="button"
          :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
          <i class="bi bi-stars text-base w-5 text-center shrink-0 {{ $subsOpen ? '!text-indigo-400' : '' }}"></i>
          <span x-show="!sidebarCollapsed" x-transition.opacity class="flex-1 text-left">Subscriptions</span>
          <i x-show="!sidebarCollapsed" x-transition.opacity
             class="bi text-[10px] opacity-60" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
        </button>
        <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
          <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.subscriptions.index') || request()->routeIs('admin.subscriptions.show') ? $sublinkActive : '' }}" href="{{ route('admin.subscriptions.index') }}">ภาพรวม</a>
          <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.subscriptions.plans*') ? $sublinkActive : '' }}" href="{{ route('admin.subscriptions.plans') }}">แผนสมัครสมาชิก</a>
          <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.features.*') ? $sublinkActive : '' }}" href="{{ route('admin.features.index') }}">Feature Flags</a>
          <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.subscriptions.invoices') ? $sublinkActive : '' }}" href="{{ route('admin.subscriptions.invoices') }}">ใบเสร็จ</a>
        </div>
      </div>
    @endif
    @endif
    @endif

    {{-- ═══════════════════════════════════════════
         5. ลูกค้า (Customers)
         ═══════════════════════════════════════════ --}}
    @if($showCustomers)
    <div class="{{ $sectionCls }}" x-show="!sidebarCollapsed" x-transition.opacity>ลูกค้า</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    @if($can('users'))
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.users.*') ? $linkActive : '' }}"
       href="{{ route('admin.users.index') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-people text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>จัดการผู้ใช้</span>
      @if($badgeNewUsers > 0)
      <span class="{{ $badgeBlue }}" x-show="!sidebarCollapsed" title="สมัครใหม่ 7 วัน">{{ $badgeNewUsers }}</span>
      @endif
    </a>
    @endif

    @if($can('online_users'))
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.online-users') ? $linkActive : '' }}"
       href="{{ route('admin.online-users') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-broadcast text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>สถานะออนไลน์</span>
    </a>
    @endif

    {{-- Cloud Storage (consumer-facing pay-for-GB product) --}}
    @if($can('settings'))
    @php $storageOpen = request()->routeIs('admin.user-storage.*'); @endphp
    <div x-data="{ open: {{ $storageOpen ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $storageOpen ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-cloud-fill text-base w-5 text-center shrink-0 {{ $storageOpen ? '!text-sky-400' : '' }}"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity class="flex-1 text-left">Cloud Storage</span>
        <i x-show="!sidebarCollapsed" x-transition.opacity
           class="bi text-[10px] opacity-60" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.user-storage.index') ? $sublinkActive : '' }}" href="{{ route('admin.user-storage.index') }}">ภาพรวม</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.user-storage.plans.*') ? $sublinkActive : '' }}" href="{{ route('admin.user-storage.plans.index') }}">แผน</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.user-storage.subscribers.*') ? $sublinkActive : '' }}" href="{{ route('admin.user-storage.subscribers.index') }}">สมาชิก</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.user-storage.files.*') ? $sublinkActive : '' }}" href="{{ route('admin.user-storage.files.index') }}">ไฟล์ผู้ใช้</a>
      </div>
    </div>
    @endif
    @endif

    {{-- ═══════════════════════════════════════════
         6. ราคา & โปรโมชัน (Pricing & Promotions)
         ═══════════════════════════════════════════ --}}
    @if($showPricing)
    <div class="{{ $sectionCls }}" x-show="!sidebarCollapsed" x-transition.opacity>ราคา & โปรโมชัน</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    @if($can('pricing'))
    @php $priceOpen = request()->routeIs('admin.pricing.*') || request()->routeIs('admin.packages.*'); @endphp
    <div x-data="{ open: {{ $priceOpen ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $priceOpen ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-currency-exchange text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>ราคา & แพ็กเกจ</span>
        <i class="{{ $chevronCls }}" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.pricing.*') ? $sublinkActive : '' }}" href="{{ route('admin.pricing.index') }}">ตั้งราคารูปภาพ</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.packages.*') ? $sublinkActive : '' }}" href="{{ route('admin.packages.index') }}">จัดการแพ็คเกจ</a>
      </div>
    </div>
    @endif

    @if($can('coupons'))
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.coupons.*') ? $linkActive : '' }}"
       href="{{ route('admin.coupons.index') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-ticket-perforated text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>คูปองส่วนลด</span>
    </a>
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.gift-cards.*') ? $linkActive : '' }}"
       href="{{ route('admin.gift-cards.index') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-gift text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>Gift Cards</span>
    </a>
    @endif
    @endif

    {{-- ═══════════════════════════════════════════
         7. การตลาด (Marketing)
         ═══════════════════════════════════════════ --}}
    @if($showMarketing)
    <div class="{{ $sectionCls }}" x-show="!sidebarCollapsed" x-transition.opacity>การตลาด</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    @php $mkOpen = request()->routeIs('admin.marketing.*'); @endphp
    <div x-data="{ open: {{ $mkOpen ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $mkOpen ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-megaphone-fill text-base w-5 text-center shrink-0 text-indigo-400"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>Marketing Hub</span>
        <i class="{{ $chevronCls }}" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.marketing.index') ? $sublinkActive : '' }}" href="{{ route('admin.marketing.index') }}">
          <i class="bi bi-house-door text-indigo-400 text-[0.6rem]"></i> Hub Overview
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.marketing.pixels') ? $sublinkActive : '' }}" href="{{ route('admin.marketing.pixels') }}">
          <i class="bi bi-activity text-blue-400 text-[0.6rem]"></i> Pixels & Analytics
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.marketing.line') ? $sublinkActive : '' }}" href="{{ route('admin.marketing.line') }}">
          <i class="bi bi-chat-dots-fill text-emerald-400 text-[0.6rem]"></i> LINE Marketing
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.marketing.seo') ? $sublinkActive : '' }}" href="{{ route('admin.marketing.seo') }}">
          <i class="bi bi-search text-purple-400 text-[0.6rem]"></i> SEO & Social
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.marketing.subscribers') ? $sublinkActive : '' }}" href="{{ route('admin.marketing.subscribers') }}">
          <i class="bi bi-envelope-heart text-pink-400 text-[0.6rem]"></i> Newsletter Subscribers
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.marketing.campaigns.*') ? $sublinkActive : '' }}" href="{{ route('admin.marketing.campaigns.index') }}">
          <i class="bi bi-envelope-paper-heart text-pink-400 text-[0.6rem]"></i> Email Campaigns
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.marketing.referral*') ? $sublinkActive : '' }}" href="{{ route('admin.marketing.referral') }}">
          <i class="bi bi-people-fill text-teal-400 text-[0.6rem]"></i> Referral Program
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.marketing.loyalty*') ? $sublinkActive : '' }}" href="{{ route('admin.marketing.loyalty') }}">
          <i class="bi bi-trophy-fill text-amber-400 text-[0.6rem]"></i> Loyalty Program
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.marketing.landing.*') ? $sublinkActive : '' }}" href="{{ route('admin.marketing.landing.index') }}">
          <i class="bi bi-file-earmark-richtext text-indigo-400 text-[0.6rem]"></i> Landing Pages
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.marketing.push.*') ? $sublinkActive : '' }}" href="{{ route('admin.marketing.push.index') }}">
          <i class="bi bi-bell-fill text-rose-400 text-[0.6rem]"></i> Web Push
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.marketing.analytics') ? $sublinkActive : '' }}" href="{{ route('admin.marketing.analytics') }}">
          <i class="bi bi-bar-chart-line-fill text-cyan-400 text-[0.6rem]"></i> Analytics (UTM)
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.marketing.analytics-v2*') ? $sublinkActive : '' }}" href="{{ route('admin.marketing.analytics-v2') }}">
          <i class="bi bi-graph-up-arrow text-emerald-400 text-[0.6rem]"></i> Analytics v2 (Funnel)
        </a>
      </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════
         8. การเงิน (Finance)
         ═══════════════════════════════════════════ --}}
    @if($showFinance)
    <div class="{{ $sectionCls }}" x-show="!sidebarCollapsed" x-transition.opacity>การเงิน</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    @if($can('finance'))
    @php $finOpen = request()->routeIs('admin.finance.*') || request()->routeIs('admin.refunds.*'); @endphp
    <div x-data="{ open: {{ $finOpen ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $finOpen ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-graph-up-arrow text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>ภาพรวมการเงิน</span>
        <i class="{{ $chevronCls }}" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.finance.index') ? $sublinkActive : '' }}" href="{{ route('admin.finance.index') }}">แดชบอร์ดการเงิน</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.finance.transactions') ? $sublinkActive : '' }}" href="{{ route('admin.finance.transactions') }}">รายการชำระเงิน</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.finance.refunds') ? $sublinkActive : '' }}" href="{{ route('admin.finance.refunds') }}">คืนเงิน (Manual)</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.refunds.*') ? $sublinkActive : '' }}" href="{{ route('admin.refunds.index') }}">คำขอคืนเงิน (จากลูกค้า)</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.finance.reconciliation') ? $sublinkActive : '' }}" href="{{ route('admin.finance.reconciliation') }}">กระทบยอด</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.finance.reports') ? $sublinkActive : '' }}" href="{{ route('admin.finance.reports') }}">รายงาน</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.finance.cost-analysis') ? $sublinkActive : '' }}" href="{{ route('admin.finance.cost-analysis') }}">📊 วิเคราะห์ต้นทุน-กำไร</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.finance.plan-profit') ? $sublinkActive : '' }}" href="{{ route('admin.finance.plan-profit') }}">🎯 กำไรต่อแผนสมัคร</a>
      </div>
    </div>
    @endif

    @if($can('payment_methods'))
    @php $payOpen = request()->routeIs('admin.payments.methods') || request()->routeIs('admin.payments.banks') || request()->routeIs('admin.payments.payouts') || request()->routeIs('admin.payments.payouts.automation*'); @endphp
    <div x-data="{ open: {{ $payOpen ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $payOpen ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-wallet2 text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>ช่องทางชำระเงิน</span>
        <i class="{{ $chevronCls }}" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.payments.methods') ? $sublinkActive : '' }}" href="{{ route('admin.payments.methods') }}">วิธีชำระเงิน</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.payments.banks') ? $sublinkActive : '' }}" href="{{ route('admin.payments.banks') }}">บัญชีธนาคาร</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.payments.payouts') ? $sublinkActive : '' }}" href="{{ route('admin.payments.payouts') }}">โอนเงินช่างภาพ</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.payments.payouts.automation*') ? $sublinkActive : '' }}" href="{{ route('admin.payments.payouts.automation') }}">⚡ Auto-Payout</a>
      </div>
    </div>
    @endif

    @if($can('finance'))
    @php $taxOpen = request()->routeIs('admin.tax.*') || request()->routeIs('admin.business-expenses.*'); @endphp
    <div x-data="{ open: {{ $taxOpen ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $taxOpen ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-calculator text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>ภาษีและต้นทุน</span>
        <i class="{{ $chevronCls }}" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.tax.index') ? $sublinkActive : '' }}" href="{{ route('admin.tax.index') }}">แดชบอร์ดภาษี</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.tax.costs') ? $sublinkActive : '' }}" href="{{ route('admin.tax.costs') }}">วิเคราะห์ต้นทุน</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.business-expenses.index') || request()->routeIs('admin.business-expenses.create') || request()->routeIs('admin.business-expenses.edit') || request()->routeIs('admin.business-expenses.show') ? $sublinkActive : '' }}"
           href="{{ route('admin.business-expenses.index') }}">
          <i class="bi bi-cash-stack text-rose-400 text-[0.6rem]"></i> ค่าใช้จ่ายธุรกิจ
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.business-expenses.calculator') ? $sublinkActive : '' }}"
           href="{{ route('admin.business-expenses.calculator') }}">
          <i class="bi bi-diagram-3 text-indigo-400 text-[0.6rem]"></i> คำนวณต้นทุนต่อบริการ
        </a>
      </div>
    </div>
    @endif
    @endif

    {{-- ═══════════════════════════════════════════
         9. การสื่อสาร (Communications)
         ═══════════════════════════════════════════ --}}
    @if($showComms)
    <div class="{{ $sectionCls }}" x-show="!sidebarCollapsed" x-transition.opacity>การสื่อสาร</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    @if($can('messages'))
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.messages.*') ? $linkActive : '' }}"
       href="{{ route('admin.messages.index') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-envelope text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>ข้อความติดต่อ</span>
      @if($badgeNewMessages > 0)
      <span class="{{ $badgeRed }}" x-show="!sidebarCollapsed">{{ $badgeNewMessages }}</span>
      @endif
    </a>
    @endif

    <a class="{{ $linkCls }} {{ request()->routeIs('admin.notifications.*') ? $linkActive : '' }}"
       href="{{ route('admin.notifications.index') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-bell text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>การแจ้งเตือน</span>
    </a>
    @endif

    {{-- ═══════════════════════════════════════════
         10. ตั้งค่าระบบ (Settings) — 6 sub-groups
             10a ทั่วไป & แบรนด์
             10b ความปลอดภัย
             10c รูปภาพ & AI
             10d ที่เก็บข้อมูล
             10e การเชื่อมต่อ
             10f ระบบ & การทำงาน
         ═══════════════════════════════════════════ --}}
    @if($can('settings'))
    <div class="{{ $sectionCls }}" x-show="!sidebarCollapsed" x-transition.opacity>ตั้งค่าระบบ</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    {{-- 10a. ทั่วไป & แบรนด์ --}}
    @php
      // `admin.seo.*` is the per-page SEO CMS group; co-locate the
      // expanded-state trigger here so opening any SEO subtree opens
      // this section.
      $g1Open = request()->routeIs('admin.settings.general')
             || request()->routeIs('admin.settings.seo')
             || request()->routeIs('admin.settings.seo.analyzer')
             || request()->routeIs('admin.seo.*')
             || request()->routeIs('admin.settings.language')
             || request()->routeIs('admin.settings.version')
             || request()->routeIs('admin.legal.*')
             || request()->routeIs('admin.manual')
             || request()->routeIs('admin.changelog.*');
    @endphp
    <div x-data="{ open: {{ $g1Open ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $g1Open ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-sliders text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>ทั่วไป & แบรนด์</span>
        <i class="{{ $chevronCls }}" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.general') ? $sublinkActive : '' }}" href="{{ route('admin.settings.general') }}">ทั่วไป</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.seo') ? $sublinkActive : '' }}" href="{{ route('admin.settings.seo') }}">
          <i class="bi bi-gear text-[0.6rem]"></i> SEO Settings
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.seo.index') || request()->routeIs('admin.seo.create') || request()->routeIs('admin.seo.show') || request()->routeIs('admin.seo.edit') ? $sublinkActive : '' }}" href="{{ route('admin.seo.index') }}">
          <i class="bi bi-search-heart text-emerald-400 text-[0.6rem]"></i> SEO Management
          @php
            $_seoIssueCount = 0;
            try { $_seoIssueCount = \App\Models\SeoPage::whereNotNull('validation_warnings')->count(); } catch (\Throwable) {}
          @endphp
          @if($_seoIssueCount > 0)
            <span class="ml-1 inline-block px-1 rounded bg-amber-500 text-white text-[9px] font-bold align-middle">{{ $_seoIssueCount }}</span>
          @endif
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.seo.audit') ? $sublinkActive : '' }}" href="{{ route('admin.seo.audit') }}">
          <i class="bi bi-bug text-amber-400 text-[0.6rem]"></i> SEO Audit
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.seo.analyzer') ? $sublinkActive : '' }}" href="{{ route('admin.settings.seo.analyzer') }}">
          <i class="bi bi-graph-up text-[0.6rem]"></i> SEO Analyzer
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.language') ? $sublinkActive : '' }}" href="{{ route('admin.settings.language') }}">
          <i class="bi bi-translate text-[0.6rem]"></i> ภาษา
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.version') ? $sublinkActive : '' }}" href="{{ route('admin.settings.version') }}">
          <i class="bi bi-tag text-cyan-500 text-[0.6rem]"></i> เวอร์ชัน
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.legal.*') ? $sublinkActive : '' }}" href="{{ route('admin.legal.index') }}">
          <i class="bi bi-file-earmark-text text-blue-400 text-[0.6rem]"></i> กฎหมาย & นโยบาย
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.changelog.*') ? $sublinkActive : '' }}" href="{{ route('admin.changelog.index') }}">
          <i class="bi bi-journal-text text-purple-400 text-[0.6rem]"></i> Changelog
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.manual') ? $sublinkActive : '' }}" href="{{ route('admin.manual') }}">
          <i class="bi bi-book-fill text-emerald-500 text-[0.6rem]"></i> คู่มือการใช้งาน
        </a>
      </div>
    </div>

    {{-- 10b. ความปลอดภัย --}}
    @php
      $g2Open = request()->routeIs('admin.settings.security')
             || request()->routeIs('admin.security.*')
             || request()->routeIs('admin.settings.2fa')
             || request()->routeIs('admin.settings.source-protection')
             || request()->routeIs('admin.settings.proxy-shield')
             || request()->routeIs('admin.settings.cloudflare')
             || request()->routeIs('admin.api-keys.*')
             || request()->routeIs('admin.activity-log')
             || request()->routeIs('admin.login-history');
    @endphp
    <div x-data="{ open: {{ $g2Open ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $g2Open ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-shield-lock text-base w-5 text-center shrink-0" :class="{ '!text-emerald-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>ความปลอดภัย</span>
        <i class="{{ $chevronCls }}" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.security') ? $sublinkActive : '' }}" href="{{ route('admin.settings.security') }}">ตั้งค่าความปลอดภัย</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.security.dashboard') ? $sublinkActive : '' }}" href="{{ route('admin.security.dashboard') }}">
          <i class="bi bi-shield-fill-check text-green-500 text-[0.6rem]"></i> AI Security
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.security.threat-intelligence.*') ? $sublinkActive : '' }}" href="{{ route('admin.security.threat-intelligence.index') }}">
          <i class="bi bi-radar text-rose-400 text-[0.6rem]"></i> Threat Intelligence
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.security.geo-access.*') ? $sublinkActive : '' }}" href="{{ route('admin.security.geo-access.index') }}">
          <i class="bi bi-globe2 text-emerald-400 text-[0.6rem]"></i> Geo Access
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.2fa') ? $sublinkActive : '' }}" href="{{ route('admin.settings.2fa') }}">
          <i class="bi bi-key text-amber-500 text-[0.6rem]"></i> 2FA ยืนยันตัวตน
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.source-protection') ? $sublinkActive : '' }}" href="{{ route('admin.settings.source-protection') }}">
          <i class="bi bi-file-earmark-lock text-red-400 text-[0.6rem]"></i> ป้องกันดูโค้ด
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.proxy-shield') ? $sublinkActive : '' }}" href="{{ route('admin.settings.proxy-shield') }}">Proxy Shield</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.cloudflare') ? $sublinkActive : '' }}" href="{{ route('admin.settings.cloudflare') }}">Cloudflare</a>
        @if(app(\App\Services\SubscriptionService::class)->featureGloballyEnabled('api_access'))
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.api-keys.*') ? $sublinkActive : '' }}" href="{{ route('admin.api-keys.index') }}">
          <i class="bi bi-key text-[0.6rem]"></i> API Keys
        </a>
        @endif
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.activity-log') ? $sublinkActive : '' }}" href="{{ route('admin.activity-log') }}">Activity Log</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.login-history') ? $sublinkActive : '' }}" href="{{ route('admin.login-history') }}">Login History</a>
      </div>
    </div>

    {{-- 10c. รูปภาพ & AI --}}
    @php
      $g3Open = request()->routeIs('admin.settings.watermark')
             || request()->routeIs('admin.settings.image')
             || request()->routeIs('admin.settings.photo-performance')
             || request()->routeIs('admin.settings.moderation')
             || request()->routeIs('admin.settings.face-search')
             || request()->routeIs('admin.settings.face-search.usage')
             || request()->routeIs('admin.photo-quality.*')
             || request()->routeIs('admin.settings.retention');
    @endphp
    <div x-data="{ open: {{ $g3Open ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $g3Open ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-image text-base w-5 text-center shrink-0" :class="{ '!text-fuchsia-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>รูปภาพ & AI</span>
        <i class="{{ $chevronCls }}" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.watermark') ? $sublinkActive : '' }}" href="{{ route('admin.settings.watermark') }}">Watermark</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.image') ? $sublinkActive : '' }}" href="{{ route('admin.settings.image') }}">ตั้งค่ารูปภาพ</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.photo-performance') ? $sublinkActive : '' }}" href="{{ route('admin.settings.photo-performance') }}">
          <i class="bi bi-lightning-charge-fill text-amber-500 text-[0.6rem]"></i> อัปโหลด & แสดงภาพ
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.moderation') ? $sublinkActive : '' }}" href="{{ route('admin.settings.moderation') }}">
          <i class="bi bi-shield-check text-emerald-500 text-[0.6rem]"></i> ตรวจสอบภาพ (AI)
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.face-search') ? $sublinkActive : '' }}" href="{{ route('admin.settings.face-search') }}">
          <i class="bi bi-person-bounding-box text-fuchsia-500 text-[0.6rem]"></i> ค้นหาด้วยใบหน้า (AI)
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.face-search.usage') ? $sublinkActive : '' }}" href="{{ route('admin.settings.face-search.usage') }}">
          <i class="bi bi-graph-up text-fuchsia-400 text-[0.6rem]"></i> การใช้งาน Face Search
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.photo-quality.*') ? $sublinkActive : '' }}" href="{{ route('admin.photo-quality.index') }}">
          <i class="bi bi-stars text-indigo-400 text-[0.6rem]"></i> Photo Quality
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.retention') ? $sublinkActive : '' }}" href="{{ route('admin.settings.retention') }}">
          <i class="bi bi-hourglass-split text-red-400 text-[0.6rem]"></i> Retention (Auto-Delete)
        </a>
      </div>
    </div>

    {{-- 10d. ที่เก็บข้อมูล --}}
    @php
      $g4Open = request()->routeIs('admin.settings.google-drive')
             || request()->routeIs('admin.settings.aws')
             || request()->routeIs('admin.settings.storage')
             || request()->routeIs('admin.settings.photographer-storage')
             || request()->routeIs('admin.storage')
             || request()->routeIs('admin.cache-purge.*')
             || request()->routeIs('admin.settings.backup');
    @endphp
    <div x-data="{ open: {{ $g4Open ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $g4Open ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-hdd-stack text-base w-5 text-center shrink-0" :class="{ '!text-sky-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>ที่เก็บข้อมูล</span>
        <i class="{{ $chevronCls }}" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.storage') ? $sublinkActive : '' }}" href="{{ route('admin.settings.storage') }}">
          <i class="bi bi-hdd-stack text-sky-400 text-[0.6rem]"></i> Storage (R2/S3/Drive)
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.google-drive') ? $sublinkActive : '' }}" href="{{ route('admin.settings.google-drive') }}">Google Drive</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.aws') ? $sublinkActive : '' }}" href="{{ route('admin.settings.aws') }}">AWS Cloud</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.photographer-storage') ? $sublinkActive : '' }}" href="{{ route('admin.settings.photographer-storage') }}">
          <i class="bi bi-person-bounding-box text-teal-400 text-[0.6rem]"></i> โควต้าช่างภาพ
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.storage') ? $sublinkActive : '' }}" href="{{ route('admin.storage') }}">Storage Overview</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.cache-purge.*') ? $sublinkActive : '' }}" href="{{ route('admin.cache-purge.index') }}">
          <i class="bi bi-cloud-slash text-orange-400 text-[0.6rem]"></i> CDN Cache Purge
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.backup') ? $sublinkActive : '' }}" href="{{ route('admin.settings.backup') }}">สำรองข้อมูล</a>
      </div>
    </div>

    {{-- 10e. การเชื่อมต่อ --}}
    @php
      $g5Open = request()->routeIs('admin.settings.line')
             || request()->routeIs('admin.settings.line-test')
             || request()->routeIs('admin.settings.line-richmenu')
             || request()->routeIs('admin.settings.line-richmenu.*')
             || request()->routeIs('admin.settings.social-auth')
             || request()->routeIs('admin.settings.webhooks')
             || request()->routeIs('admin.settings.delivery')
             || request()->routeIs('admin.settings.analytics')
             || request()->routeIs('admin.settings.payment-gateways')
             || request()->routeIs('admin.settings.email-logs');
    @endphp
    <div x-data="{ open: {{ $g5Open ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $g5Open ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-diagram-2 text-base w-5 text-center shrink-0" :class="{ '!text-cyan-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>การเชื่อมต่อ</span>
        <i class="{{ $chevronCls }}" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.payment-gateways') ? $sublinkActive : '' }}" href="{{ route('admin.settings.payment-gateways') }}">Payment Gateways</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.line') ? $sublinkActive : '' }}" href="{{ route('admin.settings.line') }}">
          <i class="bi bi-chat-dots text-green-500 text-[0.6rem]"></i> LINE
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.line-richmenu') || request()->routeIs('admin.settings.line-richmenu.*') ? $sublinkActive : '' }}" href="{{ route('admin.settings.line-richmenu') }}">
          <i class="bi bi-list text-green-500 text-[0.6rem]"></i> 📱 LINE Rich Menu
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.line-test') ? $sublinkActive : '' }}" href="{{ route('admin.settings.line-test') }}">
          <i class="bi bi-clipboard-check text-emerald-500 text-[0.6rem]"></i> 🧪 ทดสอบ LINE
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.social-auth') ? $sublinkActive : '' }}" href="{{ route('admin.settings.social-auth') }}">
          <i class="bi bi-shield-lock text-indigo-500 text-[0.6rem]"></i> Social Login
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.webhooks') ? $sublinkActive : '' }}" href="{{ route('admin.settings.webhooks') }}">Webhook Monitor</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.delivery') ? $sublinkActive : '' }}" href="{{ route('admin.settings.delivery') }}">
          <i class="bi bi-send-fill text-indigo-500 text-[0.6rem]"></i> จัดส่งรูปภาพ
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.analytics') ? $sublinkActive : '' }}" href="{{ route('admin.settings.analytics') }}">Analytics</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.email-logs') ? $sublinkActive : '' }}" href="{{ route('admin.settings.email-logs') }}">อีเมล Log</a>
      </div>
    </div>

    {{-- 10f. ระบบ & การทำงาน --}}
    @php
      $g6Open = request()->routeIs('admin.settings.queue')
             || request()->routeIs('admin.settings.performance')
             || request()->routeIs('admin.system.dashboard')
             || request()->routeIs('admin.system.capacity')
             || request()->routeIs('admin.system.capacity.refresh')
             || request()->routeIs('admin.scheduler.*')
             || request()->routeIs('admin.alerts.*')
             || request()->routeIs('admin.event-health.*')
             || request()->routeIs('admin.system.readiness')
             || request()->routeIs('admin.unit-economics.*')
             || request()->routeIs('admin.data-export.*')
             || request()->routeIs('admin.deployment.*')
             || request()->routeIs('admin.settings.reset');
    @endphp
    <div x-data="{ open: {{ $g6Open ? 'true' : 'false' }} }">
      <button class="{{ $linkCls }} w-full {{ $g6Open ? '!text-white !bg-indigo-500/10' : '' }}"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-activity text-base w-5 text-center shrink-0" :class="{ '!text-emerald-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>ระบบ & การทำงาน</span>
        <i class="{{ $chevronCls }}" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.deployment.*') ? $sublinkActive : '' }}" href="{{ route('admin.deployment.index') }}">
          <i class="bi bi-server text-cyan-400 text-[0.6rem]"></i> 🚀 Deployment / VPS
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.system.dashboard') ? $sublinkActive : '' }}" href="{{ route('admin.system.dashboard') }}">
          <i class="bi bi-activity text-emerald-400 text-[0.6rem]"></i> System Monitor
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.system.capacity') || request()->routeIs('admin.system.capacity.refresh') ? $sublinkActive : '' }}" href="{{ route('admin.system.capacity') }}">
          <i class="bi bi-speedometer2 text-amber-400 text-[0.6rem]"></i> Capacity Planner
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.performance') ? $sublinkActive : '' }}" href="{{ route('admin.settings.performance') }}">
          <i class="bi bi-speedometer2 text-green-500 text-[0.6rem]"></i> Performance
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.queue') ? $sublinkActive : '' }}" href="{{ route('admin.settings.queue') }}">Queue / Sync</a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.scheduler.*') ? $sublinkActive : '' }}" href="{{ route('admin.scheduler.index') }}">
          <i class="bi bi-diagram-3 text-sky-400 text-[0.6rem]"></i> Scheduler & Queue
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.alerts.*') ? $sublinkActive : '' }}" href="{{ route('admin.alerts.index') }}">
          <i class="bi bi-bell-fill text-rose-400 text-[0.6rem]"></i> Alert Rules
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.event-health.*') ? $sublinkActive : '' }}" href="{{ route('admin.event-health.index') }}">
          <i class="bi bi-clipboard2-pulse text-green-400 text-[0.6rem]"></i> Event Health
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.system.readiness') ? $sublinkActive : '' }}" href="{{ route('admin.system.readiness') }}">
          <i class="bi bi-rocket-takeoff text-indigo-400 text-[0.6rem]"></i> Production Readiness
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.unit-economics.*') ? $sublinkActive : '' }}" href="{{ route('admin.unit-economics.index') }}">
          <i class="bi bi-graph-up-arrow text-emerald-400 text-[0.6rem]"></i> Unit Economics / LTV
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.data-export.*') ? $sublinkActive : '' }}" href="{{ route('admin.data-export.index') }}">
          <i class="bi bi-shield-lock text-teal-400 text-[0.6rem]"></i> PDPA Data Export
        </a>
        <a class="{{ $sublinkCls }} {{ request()->routeIs('admin.settings.reset') ? $sublinkActive : '' }}" href="{{ route('admin.settings.reset') }}">
          <i class="bi bi-arrow-counterclockwise text-red-400 text-[0.6rem]"></i> รีเซ็ตข้อมูล
        </a>
      </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════
         11. จัดการแอดมิน (Superadmin only)
         ═══════════════════════════════════════════ --}}
    @if($admin->isSuperAdmin())
    <div class="{{ $sectionCls }}" x-show="!sidebarCollapsed" x-transition.opacity>จัดการแอดมิน</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>
    <a class="{{ $linkCls }} {{ request()->routeIs('admin.admins.*') ? $linkActive : '' }}"
       href="{{ route('admin.admins.index') }}" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-shield-lock text-base w-5 text-center shrink-0 text-red-400"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>บัญชีแอดมิน</span>
    </a>
    @endif

  </nav>

  {{-- ── Footer ── --}}
  <div class="p-4 border-t border-white/[0.06] space-y-2 shrink-0">
    {{-- Role badge --}}
    <div class="flex items-center gap-2 px-1" x-show="!sidebarCollapsed" x-transition.opacity>
      @php $roleInfo = $admin->role_info; @endphp
      <span class="text-[0.65rem] font-semibold rounded-full inline-flex items-center gap-1 px-2.5 py-1"
            style="background:{{ $roleInfo['color'] }}18;color:{{ $roleInfo['color'] }};">
        <i class="bi {{ $roleInfo['icon'] }}" style="font-size:0.6rem;"></i>
        {{ $roleInfo['thai'] }}
      </span>
    </div>
    {{-- Version --}}
    <a href="{{ route('admin.settings.version') }}"
       class="flex items-center justify-center gap-1.5 px-3 py-1 rounded-lg bg-white/[0.04] text-white/30 no-underline text-[0.65rem] hover:bg-white/[0.08] hover:text-white/50 transition-all"
       x-show="!sidebarCollapsed" x-transition.opacity>
      <i class="bi bi-tag-fill"></i> v{{ config('app.version', '1.0.0') }}
    </a>
    {{-- View website --}}
    <a href="{{ route('home') }}" target="_blank"
       class="flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-indigo-500/10 text-indigo-400 no-underline text-[0.8rem] font-medium hover:bg-indigo-500/20 transition-all"
       :class="{ '!px-2': sidebarCollapsed }">
      <i class="bi bi-box-arrow-up-right text-xs"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>ดูหน้าเว็บไซต์</span>
    </a>
  </div>
</aside>
