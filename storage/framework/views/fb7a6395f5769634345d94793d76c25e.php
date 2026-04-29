<!DOCTYPE html>
<html lang="th" x-data="{
  darkMode: localStorage.getItem('admin-theme') === 'dark',
  sidebarCollapsed: localStorage.getItem('sidebar-collapsed') === 'true' && window.innerWidth >= 1024,
  sidebarOpen: false,
  init() {
    this.$watch('darkMode', val => {
      document.documentElement.classList.toggle('dark', val);
      localStorage.setItem('admin-theme', val ? 'dark' : 'light');
    });
    if (this.darkMode) document.documentElement.classList.add('dark');
    this.$watch('sidebarCollapsed', val => {
      localStorage.setItem('sidebar-collapsed', val);
    });
    // Ctrl+B / Cmd+B — toggle sidebar (matches VSCode/JetBrains conventions)
    window.addEventListener('keydown', e => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b' &&
          !['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName)) {
        e.preventDefault();
        if (window.innerWidth < 1024) { this.sidebarOpen = !this.sidebarOpen; }
        else { this.sidebarCollapsed = !this.sidebarCollapsed; }
      }
    });
  }
}" :class="{ 'dark': darkMode }">
<head>
  <?php echo $__env->make('layouts.partials.analytics-head', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
  <meta name="base-url" content="<?php echo e(url('/')); ?>">
  <title><?php echo $__env->yieldContent('title', 'Dashboard'); ?> — Admin | <?php echo e($siteName ?? config('app.name')); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="<?php echo e(asset('css/avatar.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/event-cover.css')); ?>">
  <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
  <link rel="stylesheet" href="<?php echo e(asset('css/admin.css')); ?>">
  <?php echo $__env->yieldPushContent('styles'); ?>
  <style>* { font-family: 'Sarabun', sans-serif; }</style>
</head>
<body class="antialiased bg-gray-50 dark:bg-[#0f1419] text-gray-800 dark:text-gray-100">


<div class="min-h-screen">

  
  <?php echo $__env->make('layouts.partials.admin-sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  
  <div x-show="sidebarOpen" x-transition.opacity.duration.300ms
       class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[1035] lg:hidden" x-cloak
       @click="sidebarOpen = false"></div>

  
  <div class="transition-all duration-300 min-h-screen flex flex-col bg-gray-50 dark:bg-[#0f1419]"
       :class="[sidebarCollapsed ? 'lg:ml-[72px]' : 'lg:ml-[260px]']">

    
    <header class="sticky top-0 z-[1030] h-16 bg-white/80 backdrop-blur-xl border-b border-gray-200/60
                   dark:bg-slate-800/80 dark:border-white/[0.06] flex items-center px-4 lg:px-6 gap-3 shrink-0">
      
      <button type="button"
              class="relative w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center
                     hover:bg-indigo-100 transition-colors dark:bg-indigo-500/10 dark:text-indigo-400 dark:hover:bg-indigo-500/20"
              @click="window.innerWidth < 1024 ? sidebarOpen = !sidebarOpen : sidebarCollapsed = !sidebarCollapsed"
              :title="sidebarCollapsed ? 'ขยาย sidebar (Ctrl+B)' : 'หุบ sidebar (Ctrl+B)'">
        <i class="bi text-xl" :class="sidebarCollapsed ? 'bi-layout-sidebar-inset' : 'bi-list'"></i>
        
        <span x-show="sidebarCollapsed" x-cloak
              class="absolute -top-1 -right-1 flex h-3 w-3">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-3 w-3 bg-indigo-500"></span>
        </span>
      </button>

      
      <h1 class="text-base font-semibold text-slate-800 dark:text-gray-100 truncate">
        <?php echo $__env->yieldContent('title', 'Dashboard'); ?>
      </h1>

      
      <div class="ml-auto flex items-center gap-2">

        
        <div x-data="adminSearch()" class="relative hidden md:block">
          <div class="relative">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="text" x-model="query" @input.debounce.250ms="search()" @click.outside="showResults = false"
                   @focus="showResults = results.length > 0"
                   placeholder="ค้นหาอีเวนต์ ผู้ใช้ คำสั่งซื้อ..."
                   class="w-72 pl-9 pr-3 py-2 text-sm border border-gray-200 dark:border-white/10 rounded-xl bg-white dark:bg-slate-900 dark:text-gray-200 focus:ring-2 focus:ring-indigo-200">
            <div x-show="loading" class="absolute right-3 top-1/2 -translate-y-1/2">
              <i class="bi bi-arrow-repeat animate-spin text-gray-400 text-sm"></i>
            </div>
          </div>

          <div x-show="showResults" x-cloak
               class="absolute right-0 top-full mt-1 w-[380px] max-h-[500px] overflow-y-auto bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-gray-100 dark:border-white/10 z-50">
            <template x-for="(group, type) in results" :key="type">
              <div x-show="group.length > 0" class="p-2">
                <div class="text-[10px] uppercase font-bold text-gray-500 px-2 py-1 border-b border-gray-100 dark:border-white/5">
                  <span x-text="type"></span>
                </div>
                <template x-for="item in group" :key="item.id">
                  <a :href="item.url" class="flex items-center gap-2 px-2 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 rounded-lg transition">
                    <i :class="'bi bi-' + item.icon" class="text-gray-400"></i>
                    <div class="flex-1 min-w-0">
                      <div class="font-medium text-sm text-slate-800 dark:text-gray-100 truncate" x-text="item.title"></div>
                      <div class="text-xs text-gray-500 truncate" x-text="item.subtitle"></div>
                    </div>
                  </a>
                </template>
              </div>
            </template>
            <div x-show="!loading && totalResults === 0 && query.length >= 2" class="p-4 text-center text-gray-400 text-sm">
              ไม่พบผลลัพธ์
            </div>
          </div>
        </div>

        
        <div class="relative" id="notifyDropdown" x-data="{ notifyOpen: false }" @click.outside="notifyOpen = false">
          <button class="relative w-9 h-9 rounded-xl bg-gray-100 flex items-center justify-center text-gray-600
                         hover:bg-gray-200 transition-colors dark:bg-white/[0.06] dark:text-gray-300 dark:hover:bg-white/10"
                  id="adminNotifyBell" type="button" @click="notifyOpen = !notifyOpen">
            <i class="bi bi-bell-fill text-sm"></i>
            <span class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[9px] font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-1 hidden"
                  id="notifyBadge">0</span>
          </button>

          
          <div class="absolute right-0 mt-2 w-[380px] max-h-[520px] overflow-hidden rounded-2xl shadow-2xl
                      bg-white border border-gray-100 z-50
                      dark:bg-slate-800 dark:border-white/[0.06]"
               x-show="notifyOpen"
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="opacity-0 scale-95 translate-y-1"
               x-transition:enter-end="opacity-100 scale-100 translate-y-0"
               x-transition:leave="transition ease-in duration-100"
               x-transition:leave-start="opacity-100 scale-100"
               x-transition:leave-end="opacity-0 scale-95"
               x-cloak>
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-white/[0.06]">
              <span class="font-bold text-sm text-slate-800 dark:text-gray-100">
                การแจ้งเตือน <span class="text-gray-400 font-normal text-xs" id="notifyCountLabel"></span>
              </span>
              <div class="flex gap-2 items-center">
                <button class="hidden text-xs text-indigo-500 hover:text-indigo-700 font-medium" id="markAllReadBtn" onclick="AdminNotify.markAllRead()">
                  <i class="bi bi-check2-all mr-0.5"></i>อ่านแล้ว
                </button>
                <button class="p-0 border-0 bg-transparent cursor-pointer text-gray-400 hover:text-indigo-500 transition-colors" onclick="AdminNotify.toggleSound()" id="soundToggleBtn" title="เสียง">
                  <i class="bi bi-volume-up-fill text-sm"></i>
                </button>
              </div>
            </div>
            <div id="notifyList" class="max-h-[430px] overflow-y-auto"></div>
          </div>
        </div>

        
        <?php
          $_adminMultilangOn = \App\Http\Controllers\Api\LanguageApiController::isEnabled();
          $_adminEnabledLocales = \App\Http\Controllers\Api\LanguageApiController::enabled();
          $_adminCurrent = app()->getLocale();
          $_adminMeta = $_adminEnabledLocales[$_adminCurrent]
              ?? \App\Http\Controllers\Api\LanguageApiController::SUPPORTED[$_adminCurrent]
              ?? ['flag' => '🇹🇭', 'native' => 'ไทย'];
        ?>
        <?php if($_adminMultilangOn && count($_adminEnabledLocales) > 1): ?>
        <div x-data="{ open: false }" class="relative">
          <button @click="open = !open" @click.outside="open = false" type="button"
                  class="w-9 h-9 rounded-xl bg-gray-100 text-gray-600 flex items-center justify-center
                         hover:bg-gray-200 transition-colors dark:bg-white/[0.06] dark:text-gray-300 dark:hover:bg-white/10"
                  title="Language">
            <span class="text-base leading-none"><?php echo e($_adminMeta['flag']); ?></span>
          </button>
          <div x-show="open" x-cloak
               x-transition:enter="transition ease-out duration-150"
               x-transition:enter-start="opacity-0 translate-y-1"
               x-transition:enter-end="opacity-100 translate-y-0"
               class="absolute right-0 top-full mt-1 w-44 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-100 dark:border-white/10 overflow-hidden z-50">
            <?php $__currentLoopData = $_adminEnabledLocales; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code => $meta): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <a href="<?php echo e(route('lang.switch', $code)); ?>?redirect=<?php echo e(urlencode(request()->getRequestUri())); ?>"
                 class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition <?php echo e($_adminCurrent === $code ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-gray-700 dark:text-gray-300'); ?>">
                <span class="text-lg leading-none"><?php echo e($meta['flag']); ?></span>
                <span class="flex-1"><?php echo e($meta['native']); ?></span>
                <?php if($_adminCurrent === $code): ?><i class="bi bi-check-circle-fill text-indigo-600"></i><?php endif; ?>
              </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>
        </div>
        <?php endif; ?>

        
        <button type="button"
                class="w-9 h-9 rounded-xl bg-gray-100 text-gray-600 flex items-center justify-center
                       hover:bg-gray-200 transition-colors dark:bg-white/[0.06] dark:text-gray-300 dark:hover:bg-white/10"
                @click="darkMode = !darkMode" title="Toggle theme">
          <i class="bi text-sm" :class="darkMode ? 'bi-sun-fill' : 'bi-moon-fill'"></i>
        </button>

        
        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
          <button type="button"
                  class="w-9 h-9 rounded-xl bg-gray-100 text-gray-600 flex items-center justify-center
                         hover:bg-gray-200 transition-colors dark:bg-white/[0.06] dark:text-gray-300 dark:hover:bg-white/10"
                  @click="open = !open" title="UI Preferences">
            <i class="bi bi-sliders text-sm"></i>
          </button>
          <div x-show="open" x-cloak
               x-transition:enter="transition ease-out duration-150"
               x-transition:enter-start="opacity-0 scale-95 translate-y-1"
               x-transition:enter-end="opacity-100 scale-100 translate-y-0"
               class="absolute right-0 top-full mt-2 w-72 bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-gray-100 dark:border-white/10 z-50 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-white/5">
              <div class="text-xs font-bold text-slate-700 dark:text-gray-100">UI Preferences</div>
              <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">การแสดงผลของ admin panel</div>
            </div>
            
            <button type="button"
                    @click="window.innerWidth < 1024 ? sidebarOpen = !sidebarOpen : sidebarCollapsed = !sidebarCollapsed; open = false;"
                    class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-white/5 transition">
              <i class="bi bi-layout-sidebar-inset text-indigo-500 text-base"></i>
              <div class="flex-1">
                <div class="text-sm font-medium text-slate-700 dark:text-gray-100">
                  <span x-show="!sidebarCollapsed">หุบ Sidebar</span>
                  <span x-show="sidebarCollapsed">ขยาย Sidebar</span>
                </div>
                <div class="text-[11px] text-gray-500">หรือกด <kbd class="px-1 py-0.5 rounded bg-gray-100 dark:bg-white/10 font-mono text-[10px]">Ctrl+B</kbd></div>
              </div>
            </button>
            
            <button type="button"
                    @click="darkMode = !darkMode; open = false;"
                    class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-white/5 transition">
              <i class="bi text-base" :class="darkMode ? 'bi-sun-fill text-amber-400' : 'bi-moon-fill text-indigo-500'"></i>
              <div class="flex-1">
                <div class="text-sm font-medium text-slate-700 dark:text-gray-100">
                  <span x-show="!darkMode">เปลี่ยนเป็น Dark Mode</span>
                  <span x-show="darkMode">เปลี่ยนเป็น Light Mode</span>
                </div>
                <div class="text-[11px] text-gray-500">ค่าจะจดจำในเบราว์เซอร์นี้</div>
              </div>
            </button>
            <div class="border-t border-gray-100 dark:border-white/5"></div>
            
            <button type="button"
                    @click="if (confirm('Reset UI preferences ทั้งหมด?\n\n• Sidebar จะกลับมาขยายเต็ม\n• Theme จะใช้ค่า default\n• Tab states / collapsed groups จะ reset')) {
                              ['sidebar-collapsed','admin-theme','pg-theme','promo_banner_dismissed'].forEach(k => localStorage.removeItem(k));
                              Object.keys(localStorage).filter(k => k.startsWith('admin-')).forEach(k => localStorage.removeItem(k));
                              location.reload();
                            }"
                    class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-rose-50 dark:hover:bg-rose-500/10 transition">
              <i class="bi bi-arrow-counterclockwise text-rose-500 text-base"></i>
              <div class="flex-1">
                <div class="text-sm font-medium text-rose-600 dark:text-rose-400">Reset UI State</div>
                <div class="text-[11px] text-gray-500">ล้าง localStorage + reload หน้า</div>
              </div>
            </button>
          </div>
        </div>

        
        <div class="hidden sm:flex items-center gap-2.5 pl-2 border-l border-gray-200 dark:border-white/10 ml-1">
          <?php $_adm = Auth::guard('admin')->user(); $_ri = $_adm->role_info; ?>
          <div class="w-8 h-8 rounded-lg text-xs font-bold flex items-center justify-center"
               style="background:linear-gradient(135deg,<?php echo e($_ri['color']); ?>30,<?php echo e($_ri['color']); ?>10);color:<?php echo e($_ri['color']); ?>;">
            <?php echo e(mb_strtoupper(mb_substr($_adm->full_name ?? 'A', 0, 1, 'UTF-8'), 'UTF-8')); ?>

          </div>
          <div class="flex flex-col leading-tight">
            <span class="text-sm font-medium text-slate-700 dark:text-gray-200"><?php echo e($_adm->full_name ?? 'Admin'); ?></span>
            <span class="text-[0.6rem] font-semibold" style="color:<?php echo e($_ri['color']); ?>;"><?php echo e($_ri['thai']); ?></span>
          </div>
        </div>

        
        <form method="POST" action="<?php echo e(route('admin.logout')); ?>" class="inline">
          <?php echo csrf_field(); ?>
          <button type="submit"
                  class="w-9 h-9 rounded-xl bg-red-50 text-red-500 flex items-center justify-center
                         hover:bg-red-100 transition-colors dark:bg-red-500/10 dark:hover:bg-red-500/20"
                  title="ออกจากระบบ">
            <i class="bi bi-box-arrow-right text-sm"></i>
          </button>
        </form>
      </div>
    </header>

    
    <main class="flex-1 p-4 lg:p-6 dark:bg-[#0f1419] text-gray-800 dark:text-gray-100">
      
      <?php if(session('success')): ?>
      <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl bg-emerald-50 text-emerald-700 text-sm border border-emerald-100
                  dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20"
           x-data="{ show: true }" x-show="show" x-transition>
        <i class="bi bi-check-circle-fill"></i>
        <span class="flex-1"><?php echo e(session('success')); ?></span>
        <button type="button" class="text-emerald-400 hover:text-emerald-600 text-lg leading-none" @click="show = false">&times;</button>
      </div>
      <?php endif; ?>
      <?php if(session('error')): ?>
      <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm border border-red-100
                  dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20"
           x-data="{ show: true }" x-show="show" x-transition>
        <i class="bi bi-exclamation-circle-fill"></i>
        <span class="flex-1"><?php echo e(session('error')); ?></span>
        <button type="button" class="text-red-400 hover:text-red-600 text-lg leading-none" @click="show = false">&times;</button>
      </div>
      <?php endif; ?>

      <?php echo $__env->yieldContent('content'); ?>
    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo e(asset('js/admin-realtime-search.js')); ?>"></script>


<script>
function adminSearch() {
  return {
    query: '',
    results: {},
    totalResults: 0,
    loading: false,
    showResults: false,

    async search() {
      if (this.query.length < 2) {
        this.results = {};
        this.totalResults = 0;
        this.showResults = false;
        return;
      }

      this.loading = true;
      try {
        const res = await fetch('/admin/search?q=' + encodeURIComponent(this.query), { credentials: 'include' });
        const data = await res.json();
        if (data.success) {
          this.results = data.results || {};
          this.totalResults = Object.values(this.results).reduce((sum, arr) => sum + arr.length, 0);
          this.showResults = true;
        }
      } catch (e) {}
      this.loading = false;
    },
  };
}
</script>

<?php echo $__env->yieldPushContent('scripts'); ?>
<script src="<?php echo e(asset('js/admin-notifications.js')); ?>?v=<?php echo e(@filemtime(public_path('js/admin-notifications.js')) ?: time()); ?>"></script>


<?php
  $_idleTimeout = (int) (\App\Models\AppSetting::where('key','idle_timeout_admin')->value('value') ?? 15);
  $_idleWarning = (int) (\App\Models\AppSetting::where('key','idle_warning_seconds')->value('value') ?? 60);
?>
<?php if($_idleTimeout > 0): ?>
<script src="<?php echo e(asset('js/idle-logout.js')); ?>"></script>
<script>
  IdleLogout.init({
    timeout: <?php echo e($_idleTimeout); ?>,
    warning: <?php echo e($_idleWarning); ?>,
    logoutUrl: '<?php echo e(route("admin.logout")); ?>',
    csrfToken: '<?php echo e(csrf_token()); ?>',
    loginUrl: '<?php echo e(route("admin.login")); ?>',
    roleName: 'Admin',
  });
</script>
<?php endif; ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/layouts/admin.blade.php ENDPATH**/ ?>