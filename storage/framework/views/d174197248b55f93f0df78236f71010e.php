<?php
  $subsEnabled    = app(\App\Services\SubscriptionService::class)->systemEnabled();
  $creditsEnabled = app(\App\Services\CreditService::class)->systemEnabled();
  $showAddons     = $subsEnabled || $creditsEnabled;
?>

<style id="pg-sidebar-styles">
  /* Photographer sidebar — match auth-flow theme (indigo→purple→pink) */
  .pg-sidebar{
    background:
      radial-gradient(900px 600px at 0% 0%, rgba(99,102,241,.4), transparent 70%),
      radial-gradient(700px 500px at 100% 100%, rgba(236,72,153,.18), transparent 60%),
      linear-gradient(180deg,#312e81 0%,#4c1d95 45%,#581c87 100%);
    box-shadow: 4px 0 32px rgba(0,0,0,.25);
  }
  /* Decorative blob accents */
  .pg-sidebar::before{
    content:''; position:absolute; left:-30px; top:30%;
    width:180px; height:180px; border-radius:50%;
    background:radial-gradient(circle, rgba(236,72,153,.18), transparent 70%);
    filter:blur(20px); pointer-events:none; z-index:0;
  }
  .pg-sidebar::after{
    content:''; position:absolute; right:-40px; bottom:25%;
    width:200px; height:200px; border-radius:50%;
    background:radial-gradient(circle, rgba(99,102,241,.22), transparent 70%);
    filter:blur(28px); pointer-events:none; z-index:0;
  }
  .pg-sidebar > *{ position:relative; z-index:1; }

  /* Section header chips */
  .pg-section-label{
    color:rgba(255,255,255,.42);
    font-weight:700;
    letter-spacing:.16em;
    text-transform:uppercase;
    font-size:.66rem;
  }

  /* Active link — gradient pill */
  .pg-link-active{
    background:linear-gradient(135deg,rgba(255,255,255,.22),rgba(255,255,255,.08)) !important;
    color:#fff !important;
    border-left-color:#f0abfc !important;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.12), 0 4px 14px -4px rgba(236,72,153,.4);
  }
  .pg-link-active::before{
    content:''; position:absolute; left:0; top:0; bottom:0; width:3px;
    background:linear-gradient(180deg,#a5b4fc,#f0abfc);
    border-radius:0 2px 2px 0;
  }

  /* Hover underline gradient */
  .pg-link:hover{
    background:rgba(255,255,255,.06);
    color:#fff;
  }

  /* Switch back to customer button */
  .pg-switch-btn{
    background:linear-gradient(135deg,rgba(255,255,255,.18),rgba(255,255,255,.06));
    border:1px solid rgba(255,255,255,.15);
    backdrop-filter:blur(8px);
    transition:all .2s;
  }
  .pg-switch-btn:hover{
    background:linear-gradient(135deg,rgba(255,255,255,.28),rgba(255,255,255,.12));
    border-color:rgba(255,255,255,.25);
    transform:translateY(-1px);
  }
</style>


<aside class="pg-sidebar pg-sidebar-scroll w-[260px] min-h-screen text-white/70 flex flex-col fixed left-0 top-0 bottom-0 z-[1040] transition-all duration-300 overflow-y-auto overflow-x-hidden"
   :class="{
     'w-[70px]': collapsed && $store?.x !== undefined || collapsed,
     '-translate-x-full lg:translate-x-0': !sidebarOpen,
     'translate-x-0': sidebarOpen
   }">
  <div class="p-5 flex items-center gap-3 border-b border-white/[0.08] relative z-10"
     :class="{ 'justify-center px-2': collapsed }">
    <div class="w-[40px] h-[40px] rounded-[12px] flex items-center justify-center text-white text-lg shrink-0 shadow-lg shadow-black/20"
      style="background:linear-gradient(135deg,rgba(255,255,255,.22),rgba(255,255,255,.08));backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.18);">
      <i class="bi bi-camera-fill"></i>
    </div>
    <div class="flex flex-col leading-tight" x-show="!collapsed" x-transition>
      <span class="font-bold text-white text-[0.95rem] tracking-tight"><?php echo e($siteName ?? config('app.name')); ?></span>
      <span class="text-[0.65rem] text-white/55 uppercase tracking-[0.18em] font-semibold mt-0.5">Photographer Studio</span>
    </div>
  </div>

  <div class="flex-1 py-3">
    <ul class="list-none p-0 m-0">

      
      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.dashboard') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.dashboard')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-grid-1x2-fill text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>Dashboard</span>
        </a>
      </li>

      
      <li class="pg-section-label px-6 pt-4 pb-1.5"
        :class="{ 'px-0 text-center': collapsed }">
        <span x-show="!collapsed" x-transition>การทำงาน</span>
      </li>

      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.events.*') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.events.index')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-calendar-event text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>อีเวนต์</span>
        </a>
      </li>

      
      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.bookings*') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.bookings')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-calendar-check text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>คิวงาน</span>
          <?php
            $_pendingBookings = \App\Models\Booking::where('photographer_id', auth()->id())
              ->where('status', 'pending')->count();
          ?>
          <?php if($_pendingBookings > 0): ?>
            <span x-show="!collapsed" x-transition class="ml-auto inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[10px] font-bold bg-amber-500 text-white"><?php echo e($_pendingBookings); ?></span>
          <?php endif; ?>
        </a>
      </li>

      
      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.availability*') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.availability')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-clock-history text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>เวลาทำงาน</span>
        </a>
      </li>

      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.chat*') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.chat')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-chat-dots text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>แชท</span>
        </a>
      </li>

      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.reviews*') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.reviews')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-star text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>รีวิว</span>
        </a>
      </li>

      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.analytics') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.analytics')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-graph-up-arrow text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>Analytics</span>
        </a>
      </li>

      
      <li class="pg-section-label px-6 pt-4 pb-1.5"
        :class="{ 'px-0 text-center': collapsed }">
        <span x-show="!collapsed" x-transition>การเงิน</span>
      </li>

      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.earnings') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.earnings')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-wallet2 text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>รายได้</span>
        </a>
      </li>

      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.setup-bank') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.setup-bank')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-bank text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>ข้อมูลธนาคาร</span>
        </a>
      </li>

      
      <?php if($showAddons): ?>
      <li class="pg-section-label px-6 pt-4 pb-1.5"
        :class="{ 'px-0 text-center': collapsed }">
        <span x-show="!collapsed" x-transition>บริการเสริม</span>
      </li>

      <?php if($subsEnabled): ?>
      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.subscription.*') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.subscription.index')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-stars text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>แผนสมัครสมาชิก</span>
        </a>
      </li>

      
      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.ai.*') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.ai.index')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-cpu text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>AI Tools</span>
        </a>
      </li>
      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.presets.*') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.presets.index')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-sliders text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>Presets</span>
        </a>
      </li>
      
      <?php if(app(\App\Services\SubscriptionService::class)->featureGloballyEnabled('team_seats')): ?>
      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.team.*') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.team.index')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-people-fill text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>ทีม</span>
        </a>
      </li>
      <?php endif; ?>
      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.branding.*') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.branding.edit')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-palette text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>Branding</span>
        </a>
      </li>
      <?php if(app(\App\Services\SubscriptionService::class)->featureGloballyEnabled('api_access')): ?>
      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.api-keys.*') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.api-keys.index')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-key text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>API Keys</span>
        </a>
      </li>
      <?php endif; ?>
      <?php endif; ?>

      <?php if($creditsEnabled): ?>
      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.credits.*') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.credits.index')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-coin text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>เครดิตอัปโหลด</span>
        </a>
      </li>
      <?php endif; ?>
      
      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.store.*') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.store.index')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-bag-heart text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>Store · โปรโมท + บริการเสริม</span>
        </a>
      </li>
      <?php endif; ?>

      
      <li class="pg-section-label px-6 pt-4 pb-1.5"
        :class="{ 'px-0 text-center': collapsed }">
        <span x-show="!collapsed" x-transition>บัญชี</span>
      </li>

      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('photographer.profile') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('photographer.profile')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-person-circle text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>โปรไฟล์</span>
        </a>
      </li>

      <li>
        <a class="pg-link relative flex items-center gap-3 px-6 py-2.5 text-white/72 no-underline text-sm font-medium transition-all border-l-[3px] border-transparent my-px <?php echo e(request()->routeIs('profile.referrals') ? 'pg-link-active' : ''); ?>"
          href="<?php echo e(route('profile.referrals')); ?>"
          :class="{ 'px-0 py-3 justify-center !border-l-0': collapsed }">
          <i class="bi bi-people-fill text-lg w-[22px] text-center"></i>
          <span x-show="!collapsed" x-transition>แนะนำเพื่อน</span>
        </a>
      </li>
    </ul>
  </div>

  <div class="p-4 px-5 border-t border-white/[0.08] space-y-2 relative z-10">
    
    <a href="<?php echo e(route('home')); ?>" class="pg-switch-btn flex items-center gap-2 px-4 py-2.5 rounded-[12px] text-white no-underline text-sm font-semibold w-full justify-center">
      <i class="bi bi-arrow-left-right"></i>
      <span x-show="!collapsed" x-transition>กลับโหมดลูกค้า</span>
    </a>
    <a href="<?php echo e(route('home')); ?>" class="flex items-center gap-2 px-4 py-1.5 text-white/55 hover:text-white text-[0.78rem] font-medium transition-all w-full justify-center rounded-lg hover:bg-white/[0.06]" target="_blank">
      <i class="bi bi-box-arrow-up-right"></i>
      <span x-show="!collapsed" x-transition>เปิดเว็บไซต์ในแท็บใหม่</span>
    </a>
  </div>
</aside>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/layouts/partials/photographer-sidebar.blade.php ENDPATH**/ ?>