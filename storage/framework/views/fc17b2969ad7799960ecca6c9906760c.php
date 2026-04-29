<?php
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
?>


<aside id="admin-sidebar"
  class="fixed inset-y-0 left-0 z-[1040] flex flex-col w-[260px] bg-[#12121a] text-white/70
         overflow-y-auto overflow-x-hidden transition-all duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]
         max-lg:-translate-x-full admin-scrollbar"
  :class="{
    'max-lg:translate-x-0 max-lg:shadow-2xl': sidebarOpen,
    'lg:!w-[72px]': sidebarCollapsed
  }">

  
  <div class="flex items-center gap-3 px-5 h-16 shrink-0 border-b border-white/[0.06]"
       :class="{ '!justify-center !px-0': sidebarCollapsed }">
    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white shrink-0 shadow-lg shadow-indigo-500/20">
      <i class="bi bi-camera2 text-base"></i>
    </div>
    <div class="flex flex-col leading-tight overflow-hidden" x-show="!sidebarCollapsed" x-transition.opacity>
      <span class="font-bold text-white text-sm truncate"><?php echo e($siteName ?? config('app.name')); ?></span>
      <span class="text-[0.6rem] text-indigo-400 uppercase tracking-widest font-semibold">
        <?php if($admin->isSuperAdmin()): ?> Super Admin <?php else: ?> <?php echo e($admin->role_info['label'] ?? 'Admin'); ?> <?php endif; ?>
      </span>
    </div>
  </div>

  
  <nav class="flex-1 py-3">

    
    <?php if($can('dashboard')): ?>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.dashboard') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.dashboard')); ?>"
       :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-grid-1x2-fill text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity><?php echo e(__('admin.dashboard')); ?></span>
    </a>
    <?php endif; ?>

    
    <?php if($showSales): ?>
    <div class="<?php echo e($sectionCls); ?>" x-show="!sidebarCollapsed" x-transition.opacity>ขายและคำสั่งซื้อ</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    <?php if($can('orders')): ?>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.orders.*') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.orders.index')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-bag-check text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>คำสั่งซื้อ</span>
      <?php if($badgePendingOrders > 0): ?>
      <span class="<?php echo e($badgeAmber); ?>" x-show="!sidebarCollapsed"><?php echo e($badgePendingOrders); ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>

    <?php if($can('payment_slips')): ?>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.payments.slips') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.payments.slips')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-receipt-cutoff text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>ตรวจสอบสลิป</span>
      <?php if($badgePendingSlips > 0): ?>
      <span class="<?php echo e($badgeRed); ?>" x-show="!sidebarCollapsed"><?php echo e($badgePendingSlips); ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>

    <?php if($can('finance')): ?>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.invoices.*') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.invoices.index')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-receipt text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>ใบเสร็จ</span>
    </a>
    <?php endif; ?>

    <?php if($can('reviews')): ?>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.reviews.*') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.reviews.index')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-star text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>รีวิว</span>
    </a>
    <?php endif; ?>

    
    <?php if($can('reviews') || $can('events') || $admin->isSuperAdmin()): ?>
    <?php
      $_pendingBookingsCount = \App\Models\Booking::where('status', 'pending')->count();
    ?>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.bookings.*') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.bookings.index')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-calendar-check text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>คิวงาน Booking</span>
      <?php if($_pendingBookingsCount > 0): ?>
        <span class="<?php echo e($badgeRed); ?>" x-show="!sidebarCollapsed" title="รอยืนยัน"><?php echo e($_pendingBookingsCount); ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>

    <?php if($can('reviews') || $can('events') || $can('settings')): ?>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.moderation.*') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.moderation.index')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-shield-exclamation text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>ตรวจสอบภาพ AI</span>
      <?php if($badgeFlaggedPhotos > 0): ?>
      <span class="<?php echo e($badgeRed); ?>" x-show="!sidebarCollapsed" title="ภาพที่รอตรวจสอบ/ติดธง"><?php echo e($badgeFlaggedPhotos); ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>
    <?php endif; ?>

    
    <?php if($showContent): ?>
    <div class="<?php echo e($sectionCls); ?>" x-show="!sidebarCollapsed" x-transition.opacity>เนื้อหา</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    <?php if($can('events')): ?>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.events.*') && !request()->routeIs('admin.events.qrcode') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.events.index')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-calendar-event text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity><?php echo e(__('nav.events_manage')); ?></span>
      <?php if($badgeDraftEvents > 0): ?>
      <span class="<?php echo e($badgeAmber); ?>" x-show="!sidebarCollapsed"><?php echo e($badgeDraftEvents); ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>

    <?php if($can('categories')): ?>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.categories.*') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.categories.index')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-tags text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity><?php echo e(__('nav.categories')); ?></span>
      <?php if($badgeCategoryCount > 0): ?>
      <span class="<?php echo e($badgeSlate); ?>" x-show="!sidebarCollapsed"><?php echo e($badgeCategoryCount); ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>

    <?php if($can('products')): ?>
    <?php $prodOpen = request()->routeIs('admin.products.*') || request()->routeIs('admin.digital-orders.*'); ?>
    <div x-data="{ open: <?php echo e($prodOpen ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($prodOpen ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-box-seam text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>สินค้าดิจิทัล</span>
        <?php if($badgePendingDigital > 0): ?>
        <span class="<?php echo e($badgeAmber); ?>" x-show="!sidebarCollapsed"><?php echo e($badgePendingDigital); ?></span>
        <?php endif; ?>
        <i class="<?php echo e($chevronCls); ?>" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.products.index') || request()->routeIs('admin.products.show') || request()->routeIs('admin.products.edit') ? $sublinkActive : ''); ?>"
           href="<?php echo e(route('admin.products.index')); ?>">จัดการสินค้า</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.products.create') ? $sublinkActive : ''); ?>"
           href="<?php echo e(route('admin.products.create')); ?>">เพิ่มสินค้าใหม่</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.digital-orders.*') ? $sublinkActive : ''); ?>"
           href="<?php echo e(route('admin.digital-orders.index')); ?>">
          คำสั่งซื้อ
          <?php if($badgePendingDigital > 0): ?><span class="<?php echo e($badgeRed); ?>"><?php echo e($badgePendingDigital); ?></span><?php endif; ?>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <?php $blogOpen = request()->routeIs('admin.blog.*'); ?>
    <div x-data="{ open: <?php echo e($blogOpen ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($blogOpen ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-journal-richtext text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>บล็อก</span>
        <i class="<?php echo e($chevronCls); ?>" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.blog.posts.*') ? $sublinkActive : ''); ?>"
           href="<?php echo e(route('admin.blog.posts.index')); ?>">
          <i class="bi bi-newspaper text-[0.6rem]"></i> บทความ
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.blog.categories.*') ? $sublinkActive : ''); ?>"
           href="<?php echo e(route('admin.blog.categories.index')); ?>">
          <i class="bi bi-folder2 text-[0.6rem]"></i> หมวดหมู่บล็อก
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.blog.tags.*') ? $sublinkActive : ''); ?>"
           href="<?php echo e(route('admin.blog.tags.index')); ?>">
          <i class="bi bi-tags text-[0.6rem]"></i> แท็ก
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.blog.affiliate.*') ? $sublinkActive : ''); ?>"
           href="<?php echo e(route('admin.blog.affiliate.index')); ?>">
          <i class="bi bi-link-45deg text-[0.6rem]"></i> Affiliate Links
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.blog.ai.index') || request()->routeIs('admin.blog.ai.history') || request()->routeIs('admin.blog.ai.cost') ? $sublinkActive : ''); ?>"
           href="<?php echo e(route('admin.blog.ai.index')); ?>">
          <i class="bi bi-robot text-[0.6rem]"></i> AI Tools
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.blog.ai.toggles*') ? $sublinkActive : ''); ?>"
           href="<?php echo e(route('admin.blog.ai.toggles')); ?>">
          <i class="bi bi-toggles text-[0.6rem]"></i> เปิด-ปิด AI
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.blog.news.*') ? $sublinkActive : ''); ?>"
           href="<?php echo e(route('admin.blog.news.index')); ?>">
          <i class="bi bi-rss text-[0.6rem]"></i> News Aggregator
        </a>
      </div>
    </div>
    <?php endif; ?>

    
    <?php if($showPhotogs): ?>
    <div class="<?php echo e($sectionCls); ?>" x-show="!sidebarCollapsed" x-transition.opacity>ช่างภาพ</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    <?php if($can('photographers')): ?>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.photographers.*') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.photographers.index')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-camera text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity><?php echo e(__('nav.photographers')); ?></span>
    </a>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.photographer-onboarding.*') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.photographer-onboarding.index')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-person-plus text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>Onboarding</span>
    </a>
    <?php endif; ?>

    <?php if($can('finance')): ?>
    <?php $commOpen = request()->routeIs('admin.commission.*'); ?>
    <div x-data="{ open: <?php echo e($commOpen ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($commOpen ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-percent text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>คอมมิชชั่น</span>
        <i class="<?php echo e($chevronCls); ?>" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.commission.index') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.commission.index')); ?>">แดชบอร์ด</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.commission.tiers') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.commission.tiers')); ?>">ระดับคอมมิชชั่น</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.commission.bulk') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.commission.bulk')); ?>">ปรับแบบกลุ่ม</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.commission.history') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.commission.history')); ?>">ประวัติการเปลี่ยนแปลง</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.commission.settings') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.commission.settings')); ?>">ตั้งค่า</a>
      </div>
    </div>
    <?php endif; ?>

    
    <?php if($can('finance')): ?>
    <?php $monetizeOpen = request()->routeIs('admin.monetization.*'); ?>
    <div x-data="{ open: <?php echo e($monetizeOpen ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($monetizeOpen ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-megaphone text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>Monetization</span>
        <i class="<?php echo e($chevronCls); ?>" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.monetization.dashboard') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.monetization.dashboard')); ?>">
          <i class="bi bi-graph-up-arrow text-emerald-400 text-[0.6rem]"></i> รายได้รวม
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.monetization.campaigns.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.monetization.campaigns.index')); ?>">
          <i class="bi bi-megaphone-fill text-indigo-400 text-[0.6rem]"></i> Brand Campaigns
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.monetization.addons.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.monetization.addons.index')); ?>">
          <i class="bi bi-box-seam text-indigo-400 text-[0.6rem]"></i> Addon Catalog
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.monetization.promotions*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.monetization.promotions')); ?>">
          <i class="bi bi-stars text-amber-400 text-[0.6rem]"></i> Photographer Promotions
        </a>
      </div>
    </div>
    <?php endif; ?>

    <?php if($can('commission') || $can('photographers')): ?>
    
    <?php $creditsOpen = request()->routeIs('admin.credits.*'); ?>
    <div x-data="{ open: <?php echo e($creditsOpen ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($creditsOpen ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-coin text-base w-5 text-center shrink-0 <?php echo e($creditsOpen ? '!text-amber-400' : ''); ?>"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity class="flex-1 text-left">Upload Credits</span>
        <i x-show="!sidebarCollapsed" x-transition.opacity
           class="bi text-[10px] opacity-60" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.credits.index') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.credits.index')); ?>">ภาพรวม</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.credits.packages.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.credits.packages.index')); ?>">แพ็คเก็จ</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.credits.photographers.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.credits.photographers.index')); ?>">ยอดช่างภาพ</a>
      </div>
    </div>

    
    <?php if($subsEnabled): ?>
      <?php $subsOpen = request()->routeIs('admin.subscriptions.*'); ?>
      <div x-data="{ open: <?php echo e($subsOpen ? 'true' : 'false'); ?> }">
        <button class="<?php echo e($linkCls); ?> w-full <?php echo e($subsOpen ? '!text-white !bg-indigo-500/10' : ''); ?>"
          @click="open = !open" type="button"
          :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
          <i class="bi bi-stars text-base w-5 text-center shrink-0 <?php echo e($subsOpen ? '!text-indigo-400' : ''); ?>"></i>
          <span x-show="!sidebarCollapsed" x-transition.opacity class="flex-1 text-left">Subscriptions</span>
          <i x-show="!sidebarCollapsed" x-transition.opacity
             class="bi text-[10px] opacity-60" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
        </button>
        <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
          <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.subscriptions.index') || request()->routeIs('admin.subscriptions.show') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.subscriptions.index')); ?>">ภาพรวม</a>
          <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.subscriptions.plans*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.subscriptions.plans')); ?>">แผนสมัครสมาชิก</a>
          <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.features.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.features.index')); ?>">Feature Flags</a>
          <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.subscriptions.invoices') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.subscriptions.invoices')); ?>">ใบเสร็จ</a>
        </div>
      </div>
    <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>

    
    <?php if($showCustomers): ?>
    <div class="<?php echo e($sectionCls); ?>" x-show="!sidebarCollapsed" x-transition.opacity>ลูกค้า</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    <?php if($can('users')): ?>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.users.*') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.users.index')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-people text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>จัดการผู้ใช้</span>
      <?php if($badgeNewUsers > 0): ?>
      <span class="<?php echo e($badgeBlue); ?>" x-show="!sidebarCollapsed" title="สมัครใหม่ 7 วัน"><?php echo e($badgeNewUsers); ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>

    <?php if($can('online_users')): ?>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.online-users') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.online-users')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-broadcast text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>สถานะออนไลน์</span>
    </a>
    <?php endif; ?>

    
    <?php if($can('settings')): ?>
    <?php $storageOpen = request()->routeIs('admin.user-storage.*'); ?>
    <div x-data="{ open: <?php echo e($storageOpen ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($storageOpen ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-cloud-fill text-base w-5 text-center shrink-0 <?php echo e($storageOpen ? '!text-sky-400' : ''); ?>"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity class="flex-1 text-left">Cloud Storage</span>
        <i x-show="!sidebarCollapsed" x-transition.opacity
           class="bi text-[10px] opacity-60" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.user-storage.index') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.user-storage.index')); ?>">ภาพรวม</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.user-storage.plans.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.user-storage.plans.index')); ?>">แผน</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.user-storage.subscribers.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.user-storage.subscribers.index')); ?>">สมาชิก</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.user-storage.files.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.user-storage.files.index')); ?>">ไฟล์ผู้ใช้</a>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    
    <?php if($showPricing): ?>
    <div class="<?php echo e($sectionCls); ?>" x-show="!sidebarCollapsed" x-transition.opacity>ราคา & โปรโมชัน</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    <?php if($can('pricing')): ?>
    <?php $priceOpen = request()->routeIs('admin.pricing.*') || request()->routeIs('admin.packages.*'); ?>
    <div x-data="{ open: <?php echo e($priceOpen ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($priceOpen ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-currency-exchange text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>ราคา & แพ็กเกจ</span>
        <i class="<?php echo e($chevronCls); ?>" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.pricing.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.pricing.index')); ?>">ตั้งราคารูปภาพ</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.packages.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.packages.index')); ?>">จัดการแพ็คเกจ</a>
      </div>
    </div>
    <?php endif; ?>

    <?php if($can('coupons')): ?>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.coupons.*') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.coupons.index')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-ticket-perforated text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>คูปองส่วนลด</span>
    </a>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.gift-cards.*') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.gift-cards.index')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-gift text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>Gift Cards</span>
    </a>
    <?php endif; ?>
    <?php endif; ?>

    
    <?php if($showMarketing): ?>
    <div class="<?php echo e($sectionCls); ?>" x-show="!sidebarCollapsed" x-transition.opacity>การตลาด</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    <?php $mkOpen = request()->routeIs('admin.marketing.*'); ?>
    <div x-data="{ open: <?php echo e($mkOpen ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($mkOpen ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-megaphone-fill text-base w-5 text-center shrink-0 text-indigo-400"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>Marketing Hub</span>
        <i class="<?php echo e($chevronCls); ?>" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.marketing.index') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.marketing.index')); ?>">
          <i class="bi bi-house-door text-indigo-400 text-[0.6rem]"></i> Hub Overview
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.marketing.pixels') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.marketing.pixels')); ?>">
          <i class="bi bi-activity text-blue-400 text-[0.6rem]"></i> Pixels & Analytics
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.marketing.line') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.marketing.line')); ?>">
          <i class="bi bi-chat-dots-fill text-emerald-400 text-[0.6rem]"></i> LINE Marketing
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.marketing.seo') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.marketing.seo')); ?>">
          <i class="bi bi-search text-purple-400 text-[0.6rem]"></i> SEO & Social
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.marketing.subscribers') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.marketing.subscribers')); ?>">
          <i class="bi bi-envelope-heart text-pink-400 text-[0.6rem]"></i> Newsletter Subscribers
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.marketing.campaigns.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.marketing.campaigns.index')); ?>">
          <i class="bi bi-envelope-paper-heart text-pink-400 text-[0.6rem]"></i> Email Campaigns
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.marketing.referral*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.marketing.referral')); ?>">
          <i class="bi bi-people-fill text-teal-400 text-[0.6rem]"></i> Referral Program
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.marketing.loyalty*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.marketing.loyalty')); ?>">
          <i class="bi bi-trophy-fill text-amber-400 text-[0.6rem]"></i> Loyalty Program
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.marketing.landing.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.marketing.landing.index')); ?>">
          <i class="bi bi-file-earmark-richtext text-indigo-400 text-[0.6rem]"></i> Landing Pages
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.marketing.push.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.marketing.push.index')); ?>">
          <i class="bi bi-bell-fill text-rose-400 text-[0.6rem]"></i> Web Push
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.marketing.analytics') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.marketing.analytics')); ?>">
          <i class="bi bi-bar-chart-line-fill text-cyan-400 text-[0.6rem]"></i> Analytics (UTM)
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.marketing.analytics-v2*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.marketing.analytics-v2')); ?>">
          <i class="bi bi-graph-up-arrow text-emerald-400 text-[0.6rem]"></i> Analytics v2 (Funnel)
        </a>
      </div>
    </div>
    <?php endif; ?>

    
    <?php if($showFinance): ?>
    <div class="<?php echo e($sectionCls); ?>" x-show="!sidebarCollapsed" x-transition.opacity>การเงิน</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    <?php if($can('finance')): ?>
    <?php $finOpen = request()->routeIs('admin.finance.*') || request()->routeIs('admin.refunds.*'); ?>
    <div x-data="{ open: <?php echo e($finOpen ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($finOpen ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-graph-up-arrow text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>ภาพรวมการเงิน</span>
        <i class="<?php echo e($chevronCls); ?>" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.finance.index') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.finance.index')); ?>">แดชบอร์ดการเงิน</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.finance.transactions') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.finance.transactions')); ?>">รายการชำระเงิน</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.finance.refunds') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.finance.refunds')); ?>">คืนเงิน (Manual)</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.refunds.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.refunds.index')); ?>">คำขอคืนเงิน (จากลูกค้า)</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.finance.reconciliation') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.finance.reconciliation')); ?>">กระทบยอด</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.finance.reports') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.finance.reports')); ?>">รายงาน</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.finance.cost-analysis') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.finance.cost-analysis')); ?>">📊 วิเคราะห์ต้นทุน-กำไร</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.finance.plan-profit') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.finance.plan-profit')); ?>">🎯 กำไรต่อแผนสมัคร</a>
      </div>
    </div>
    <?php endif; ?>

    <?php if($can('payment_methods')): ?>
    <?php $payOpen = request()->routeIs('admin.payments.methods') || request()->routeIs('admin.payments.banks') || request()->routeIs('admin.payments.payouts') || request()->routeIs('admin.payments.payouts.automation*'); ?>
    <div x-data="{ open: <?php echo e($payOpen ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($payOpen ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-wallet2 text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>ช่องทางชำระเงิน</span>
        <i class="<?php echo e($chevronCls); ?>" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.payments.methods') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.payments.methods')); ?>">วิธีชำระเงิน</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.payments.banks') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.payments.banks')); ?>">บัญชีธนาคาร</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.payments.payouts') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.payments.payouts')); ?>">โอนเงินช่างภาพ</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.payments.payouts.automation*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.payments.payouts.automation')); ?>">⚡ Auto-Payout</a>
      </div>
    </div>
    <?php endif; ?>

    <?php if($can('finance')): ?>
    <?php $taxOpen = request()->routeIs('admin.tax.*') || request()->routeIs('admin.business-expenses.*'); ?>
    <div x-data="{ open: <?php echo e($taxOpen ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($taxOpen ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-calculator text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>ภาษีและต้นทุน</span>
        <i class="<?php echo e($chevronCls); ?>" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.tax.index') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.tax.index')); ?>">แดชบอร์ดภาษี</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.tax.costs') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.tax.costs')); ?>">วิเคราะห์ต้นทุน</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.business-expenses.index') || request()->routeIs('admin.business-expenses.create') || request()->routeIs('admin.business-expenses.edit') || request()->routeIs('admin.business-expenses.show') ? $sublinkActive : ''); ?>"
           href="<?php echo e(route('admin.business-expenses.index')); ?>">
          <i class="bi bi-cash-stack text-rose-400 text-[0.6rem]"></i> ค่าใช้จ่ายธุรกิจ
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.business-expenses.calculator') ? $sublinkActive : ''); ?>"
           href="<?php echo e(route('admin.business-expenses.calculator')); ?>">
          <i class="bi bi-diagram-3 text-indigo-400 text-[0.6rem]"></i> คำนวณต้นทุนต่อบริการ
        </a>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    
    <?php if($showComms): ?>
    <div class="<?php echo e($sectionCls); ?>" x-show="!sidebarCollapsed" x-transition.opacity>การสื่อสาร</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    <?php if($can('messages')): ?>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.messages.*') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.messages.index')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-envelope text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>ข้อความติดต่อ</span>
      <?php if($badgeNewMessages > 0): ?>
      <span class="<?php echo e($badgeRed); ?>" x-show="!sidebarCollapsed"><?php echo e($badgeNewMessages); ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>

    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.notifications.*') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.notifications.index')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-bell text-base w-5 text-center shrink-0"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>การแจ้งเตือน</span>
    </a>
    <?php endif; ?>

    
    <?php if($can('settings')): ?>
    <div class="<?php echo e($sectionCls); ?>" x-show="!sidebarCollapsed" x-transition.opacity>ตั้งค่าระบบ</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>

    
    <?php
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
    ?>
    <div x-data="{ open: <?php echo e($g1Open ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($g1Open ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-sliders text-base w-5 text-center shrink-0" :class="{ '!text-indigo-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>ทั่วไป & แบรนด์</span>
        <i class="<?php echo e($chevronCls); ?>" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.general') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.general')); ?>">ทั่วไป</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.seo') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.seo')); ?>">
          <i class="bi bi-gear text-[0.6rem]"></i> SEO Settings
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.seo.index') || request()->routeIs('admin.seo.create') || request()->routeIs('admin.seo.show') || request()->routeIs('admin.seo.edit') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.seo.index')); ?>">
          <i class="bi bi-search-heart text-emerald-400 text-[0.6rem]"></i> SEO Management
          <?php
            $_seoIssueCount = 0;
            try { $_seoIssueCount = \App\Models\SeoPage::whereNotNull('validation_warnings')->count(); } catch (\Throwable) {}
          ?>
          <?php if($_seoIssueCount > 0): ?>
            <span class="ml-1 inline-block px-1 rounded bg-amber-500 text-white text-[9px] font-bold align-middle"><?php echo e($_seoIssueCount); ?></span>
          <?php endif; ?>
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.seo.audit') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.seo.audit')); ?>">
          <i class="bi bi-bug text-amber-400 text-[0.6rem]"></i> SEO Audit
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.seo.analyzer') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.seo.analyzer')); ?>">
          <i class="bi bi-graph-up text-[0.6rem]"></i> SEO Analyzer
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.language') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.language')); ?>">
          <i class="bi bi-translate text-[0.6rem]"></i> ภาษา
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.version') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.version')); ?>">
          <i class="bi bi-tag text-cyan-500 text-[0.6rem]"></i> เวอร์ชัน
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.legal.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.legal.index')); ?>">
          <i class="bi bi-file-earmark-text text-blue-400 text-[0.6rem]"></i> กฎหมาย & นโยบาย
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.changelog.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.changelog.index')); ?>">
          <i class="bi bi-journal-text text-purple-400 text-[0.6rem]"></i> Changelog
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.manual') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.manual')); ?>">
          <i class="bi bi-book-fill text-emerald-500 text-[0.6rem]"></i> คู่มือการใช้งาน
        </a>
      </div>
    </div>

    
    <?php
      $g2Open = request()->routeIs('admin.settings.security')
             || request()->routeIs('admin.security.*')
             || request()->routeIs('admin.settings.2fa')
             || request()->routeIs('admin.settings.source-protection')
             || request()->routeIs('admin.settings.proxy-shield')
             || request()->routeIs('admin.settings.cloudflare')
             || request()->routeIs('admin.api-keys.*')
             || request()->routeIs('admin.activity-log')
             || request()->routeIs('admin.login-history');
    ?>
    <div x-data="{ open: <?php echo e($g2Open ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($g2Open ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-shield-lock text-base w-5 text-center shrink-0" :class="{ '!text-emerald-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>ความปลอดภัย</span>
        <i class="<?php echo e($chevronCls); ?>" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.security') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.security')); ?>">ตั้งค่าความปลอดภัย</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.security.dashboard') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.security.dashboard')); ?>">
          <i class="bi bi-shield-fill-check text-green-500 text-[0.6rem]"></i> AI Security
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.security.threat-intelligence.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.security.threat-intelligence.index')); ?>">
          <i class="bi bi-radar text-rose-400 text-[0.6rem]"></i> Threat Intelligence
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.security.geo-access.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.security.geo-access.index')); ?>">
          <i class="bi bi-globe2 text-emerald-400 text-[0.6rem]"></i> Geo Access
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.2fa') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.2fa')); ?>">
          <i class="bi bi-key text-amber-500 text-[0.6rem]"></i> 2FA ยืนยันตัวตน
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.source-protection') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.source-protection')); ?>">
          <i class="bi bi-file-earmark-lock text-red-400 text-[0.6rem]"></i> ป้องกันดูโค้ด
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.proxy-shield') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.proxy-shield')); ?>">Proxy Shield</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.cloudflare') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.cloudflare')); ?>">Cloudflare</a>
        <?php if(app(\App\Services\SubscriptionService::class)->featureGloballyEnabled('api_access')): ?>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.api-keys.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.api-keys.index')); ?>">
          <i class="bi bi-key text-[0.6rem]"></i> API Keys
        </a>
        <?php endif; ?>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.activity-log') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.activity-log')); ?>">Activity Log</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.login-history') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.login-history')); ?>">Login History</a>
      </div>
    </div>

    
    <?php
      $g3Open = request()->routeIs('admin.settings.watermark')
             || request()->routeIs('admin.settings.image')
             || request()->routeIs('admin.settings.photo-performance')
             || request()->routeIs('admin.settings.moderation')
             || request()->routeIs('admin.settings.face-search')
             || request()->routeIs('admin.settings.face-search.usage')
             || request()->routeIs('admin.photo-quality.*')
             || request()->routeIs('admin.settings.retention');
    ?>
    <div x-data="{ open: <?php echo e($g3Open ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($g3Open ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-image text-base w-5 text-center shrink-0" :class="{ '!text-fuchsia-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>รูปภาพ & AI</span>
        <i class="<?php echo e($chevronCls); ?>" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.watermark') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.watermark')); ?>">Watermark</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.image') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.image')); ?>">ตั้งค่ารูปภาพ</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.photo-performance') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.photo-performance')); ?>">
          <i class="bi bi-lightning-charge-fill text-amber-500 text-[0.6rem]"></i> อัปโหลด & แสดงภาพ
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.moderation') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.moderation')); ?>">
          <i class="bi bi-shield-check text-emerald-500 text-[0.6rem]"></i> ตรวจสอบภาพ (AI)
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.face-search') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.face-search')); ?>">
          <i class="bi bi-person-bounding-box text-fuchsia-500 text-[0.6rem]"></i> ค้นหาด้วยใบหน้า (AI)
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.face-search.usage') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.face-search.usage')); ?>">
          <i class="bi bi-graph-up text-fuchsia-400 text-[0.6rem]"></i> การใช้งาน Face Search
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.photo-quality.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.photo-quality.index')); ?>">
          <i class="bi bi-stars text-indigo-400 text-[0.6rem]"></i> Photo Quality
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.retention') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.retention')); ?>">
          <i class="bi bi-hourglass-split text-red-400 text-[0.6rem]"></i> Retention (Auto-Delete)
        </a>
      </div>
    </div>

    
    <?php
      $g4Open = request()->routeIs('admin.settings.google-drive')
             || request()->routeIs('admin.settings.aws')
             || request()->routeIs('admin.settings.storage')
             || request()->routeIs('admin.settings.photographer-storage')
             || request()->routeIs('admin.storage')
             || request()->routeIs('admin.cache-purge.*')
             || request()->routeIs('admin.settings.backup');
    ?>
    <div x-data="{ open: <?php echo e($g4Open ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($g4Open ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-hdd-stack text-base w-5 text-center shrink-0" :class="{ '!text-sky-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>ที่เก็บข้อมูล</span>
        <i class="<?php echo e($chevronCls); ?>" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.storage') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.storage')); ?>">
          <i class="bi bi-hdd-stack text-sky-400 text-[0.6rem]"></i> Storage (R2/S3/Drive)
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.google-drive') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.google-drive')); ?>">Google Drive</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.aws') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.aws')); ?>">AWS Cloud</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.photographer-storage') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.photographer-storage')); ?>">
          <i class="bi bi-person-bounding-box text-teal-400 text-[0.6rem]"></i> โควต้าช่างภาพ
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.storage') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.storage')); ?>">Storage Overview</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.cache-purge.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.cache-purge.index')); ?>">
          <i class="bi bi-cloud-slash text-orange-400 text-[0.6rem]"></i> CDN Cache Purge
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.backup') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.backup')); ?>">สำรองข้อมูล</a>
      </div>
    </div>

    
    <?php
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
    ?>
    <div x-data="{ open: <?php echo e($g5Open ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($g5Open ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-diagram-2 text-base w-5 text-center shrink-0" :class="{ '!text-cyan-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>การเชื่อมต่อ</span>
        <i class="<?php echo e($chevronCls); ?>" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.payment-gateways') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.payment-gateways')); ?>">Payment Gateways</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.line') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.line')); ?>">
          <i class="bi bi-chat-dots text-green-500 text-[0.6rem]"></i> LINE
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.line-richmenu') || request()->routeIs('admin.settings.line-richmenu.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.line-richmenu')); ?>">
          <i class="bi bi-list text-green-500 text-[0.6rem]"></i> 📱 LINE Rich Menu
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.line-test') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.line-test')); ?>">
          <i class="bi bi-clipboard-check text-emerald-500 text-[0.6rem]"></i> 🧪 ทดสอบ LINE
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.social-auth') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.social-auth')); ?>">
          <i class="bi bi-shield-lock text-indigo-500 text-[0.6rem]"></i> Social Login
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.webhooks') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.webhooks')); ?>">Webhook Monitor</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.delivery') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.delivery')); ?>">
          <i class="bi bi-send-fill text-indigo-500 text-[0.6rem]"></i> จัดส่งรูปภาพ
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.analytics') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.analytics')); ?>">Analytics</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.email-logs') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.email-logs')); ?>">อีเมล Log</a>
      </div>
    </div>

    
    <?php
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
    ?>
    <div x-data="{ open: <?php echo e($g6Open ? 'true' : 'false'); ?> }">
      <button class="<?php echo e($linkCls); ?> w-full <?php echo e($g6Open ? '!text-white !bg-indigo-500/10' : ''); ?>"
        @click="open = !open" type="button"
        :class="{ '!justify-center !px-0 !mx-2': sidebarCollapsed }">
        <i class="bi bi-activity text-base w-5 text-center shrink-0" :class="{ '!text-emerald-400': open }"></i>
        <span x-show="!sidebarCollapsed" x-transition.opacity>ระบบ & การทำงาน</span>
        <i class="<?php echo e($chevronCls); ?>" :class="{ '!rotate-90 !opacity-80': open }" x-show="!sidebarCollapsed"></i>
      </button>
      <div x-show="open && !sidebarCollapsed" x-collapse x-cloak class="pb-1">
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.deployment.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.deployment.index')); ?>">
          <i class="bi bi-server text-cyan-400 text-[0.6rem]"></i> 🚀 Deployment / VPS
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.system.dashboard') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.system.dashboard')); ?>">
          <i class="bi bi-activity text-emerald-400 text-[0.6rem]"></i> System Monitor
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.system.capacity') || request()->routeIs('admin.system.capacity.refresh') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.system.capacity')); ?>">
          <i class="bi bi-speedometer2 text-amber-400 text-[0.6rem]"></i> Capacity Planner
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.performance') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.performance')); ?>">
          <i class="bi bi-speedometer2 text-green-500 text-[0.6rem]"></i> Performance
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.queue') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.queue')); ?>">Queue / Sync</a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.scheduler.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.scheduler.index')); ?>">
          <i class="bi bi-diagram-3 text-sky-400 text-[0.6rem]"></i> Scheduler & Queue
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.alerts.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.alerts.index')); ?>">
          <i class="bi bi-bell-fill text-rose-400 text-[0.6rem]"></i> Alert Rules
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.event-health.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.event-health.index')); ?>">
          <i class="bi bi-clipboard2-pulse text-green-400 text-[0.6rem]"></i> Event Health
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.system.readiness') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.system.readiness')); ?>">
          <i class="bi bi-rocket-takeoff text-indigo-400 text-[0.6rem]"></i> Production Readiness
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.unit-economics.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.unit-economics.index')); ?>">
          <i class="bi bi-graph-up-arrow text-emerald-400 text-[0.6rem]"></i> Unit Economics / LTV
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.data-export.*') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.data-export.index')); ?>">
          <i class="bi bi-shield-lock text-teal-400 text-[0.6rem]"></i> PDPA Data Export
        </a>
        <a class="<?php echo e($sublinkCls); ?> <?php echo e(request()->routeIs('admin.settings.reset') ? $sublinkActive : ''); ?>" href="<?php echo e(route('admin.settings.reset')); ?>">
          <i class="bi bi-arrow-counterclockwise text-red-400 text-[0.6rem]"></i> รีเซ็ตข้อมูล
        </a>
      </div>
    </div>
    <?php endif; ?>

    
    <?php if($admin->isSuperAdmin()): ?>
    <div class="<?php echo e($sectionCls); ?>" x-show="!sidebarCollapsed" x-transition.opacity>จัดการแอดมิน</div>
    <div class="h-px bg-white/[0.04] mx-5 my-1" x-show="sidebarCollapsed"></div>
    <a class="<?php echo e($linkCls); ?> <?php echo e(request()->routeIs('admin.admins.*') ? $linkActive : ''); ?>"
       href="<?php echo e(route('admin.admins.index')); ?>" :class="{ '!justify-center !px-0 !mx-2 !shadow-none': sidebarCollapsed }">
      <i class="bi bi-shield-lock text-base w-5 text-center shrink-0 text-red-400"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>บัญชีแอดมิน</span>
    </a>
    <?php endif; ?>

  </nav>

  
  <div class="p-4 border-t border-white/[0.06] space-y-2 shrink-0">
    
    <div class="flex items-center gap-2 px-1" x-show="!sidebarCollapsed" x-transition.opacity>
      <?php $roleInfo = $admin->role_info; ?>
      <span class="text-[0.65rem] font-semibold rounded-full inline-flex items-center gap-1 px-2.5 py-1"
            style="background:<?php echo e($roleInfo['color']); ?>18;color:<?php echo e($roleInfo['color']); ?>;">
        <i class="bi <?php echo e($roleInfo['icon']); ?>" style="font-size:0.6rem;"></i>
        <?php echo e($roleInfo['thai']); ?>

      </span>
    </div>
    
    <a href="<?php echo e(route('admin.settings.version')); ?>"
       class="flex items-center justify-center gap-1.5 px-3 py-1 rounded-lg bg-white/[0.04] text-white/30 no-underline text-[0.65rem] hover:bg-white/[0.08] hover:text-white/50 transition-all"
       x-show="!sidebarCollapsed" x-transition.opacity>
      <i class="bi bi-tag-fill"></i> v<?php echo e(config('app.version', '1.0.0')); ?>

    </a>
    
    <a href="<?php echo e(route('home')); ?>" target="_blank"
       class="flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-indigo-500/10 text-indigo-400 no-underline text-[0.8rem] font-medium hover:bg-indigo-500/20 transition-all"
       :class="{ '!px-2': sidebarCollapsed }">
      <i class="bi bi-box-arrow-up-right text-xs"></i>
      <span x-show="!sidebarCollapsed" x-transition.opacity>ดูหน้าเว็บไซต์</span>
    </a>
  </div>
</aside>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/layouts/partials/admin-sidebar.blade.php ENDPATH**/ ?>