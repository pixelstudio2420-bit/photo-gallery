<?php $__env->startSection('title', 'หน้าแรก'); ?>

<?php $__env->startSection('hero'); ?>

<section class="relative overflow-hidden min-h-[60vh] bg-gradient-to-br from-pink-50 via-indigo-50 to-violet-50 dark:from-slate-900 dark:via-indigo-950 dark:to-purple-950"
         x-data="{ mx: 50, my: 50 }"
         @mousemove="mx = ($event.clientX / window.innerWidth) * 100; my = ($event.clientY / window.innerHeight) * 100;">
  
  <div class="absolute inset-0 pointer-events-none transition-[background] duration-500 ease-out"
       :style="`background: radial-gradient(600px circle at ${mx}% ${my}%, rgba(139,92,246,0.10), transparent 60%);`"></div>

  
  <div class="absolute inset-0 pointer-events-none opacity-60 dark:opacity-100" style="background-image:url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h40v40H0z&quot; fill=&quot;none&quot;/%3E%3Cpath d=&quot;M0 40L40 0M-10 10L10-10M30 50L50 30&quot; stroke=&quot;rgba(100,116,139,0.08)&quot; stroke-width=&quot;1&quot;/%3E%3C/svg%3E');"></div>

  
  <div class="absolute dark:hidden" style="width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(236,72,153,0.25) 0%,transparent 70%);top:-200px;right:-100px;"></div>
  <div class="absolute dark:hidden" style="width:400px;height:400px;border-radius:50%;background:radial-gradient(circle,rgba(139,92,246,0.22) 0%,transparent 70%);bottom:-100px;left:-100px;"></div>

  
  <div class="absolute hidden dark:block" style="width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(99,102,241,0.22) 0%,transparent 70%);top:-200px;right:-100px;"></div>
  <div class="absolute hidden dark:block" style="width:400px;height:400px;border-radius:50%;background:radial-gradient(circle,rgba(244,63,94,0.15) 0%,transparent 70%);bottom:-100px;left:-100px;"></div>

  <div class="max-w-7xl mx-auto px-4 relative py-12 md:py-16">
    <div class="grid grid-cols-1 lg:grid-cols-12 items-center gap-8 py-4">
      <div class="lg:col-span-7 relative z-[1]">
        
        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold backdrop-blur-md border bg-white/70 dark:bg-white/10 border-indigo-200/60 dark:border-white/10 text-indigo-700 dark:text-indigo-200 shadow-sm mb-5">
          <span class="w-2 h-2 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
          แพลตฟอร์มภาพอีเวนต์ อันดับ 1 ในไทย
        </span>

        
        <h1 class="font-extrabold mb-5 text-4xl sm:text-5xl lg:text-6xl xl:text-7xl leading-[1.15] tracking-tight">
          <span class="block bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 dark:from-indigo-300 dark:via-violet-300 dark:to-fuchsia-300 bg-clip-text text-transparent">
            ค้นหาและซื้อรูปภาพ
          </span>
          <span class="block bg-gradient-to-br from-pink-500 via-rose-500 to-orange-400 dark:from-pink-400 dark:via-rose-300 dark:to-amber-300 bg-clip-text text-transparent">
            จากงานอีเวนต์ของคุณ
          </span>
          <span class="block mt-2 text-slate-800 dark:text-white text-2xl sm:text-3xl lg:text-4xl font-bold">
            คุณภาพระดับมืออาชีพ
            <i class="bi bi-stars text-amber-500 dark:text-amber-300"></i>
          </span>
        </h1>

        <p class="mb-6 text-base sm:text-lg text-slate-600 dark:text-slate-300/80 max-w-xl" style="line-height:1.7;">
          เรียกดูและซื้อภาพถ่ายจากงานอีเวนต์ต่างๆ ได้ง่ายดาย รวดเร็ว พร้อมดาวน์โหลดทันทีที่ชำระเงิน
        </p>

        
        <form action="<?php echo e(route('events.index')); ?>" method="GET" class="hero-search mb-6 max-w-xl">
          <div class="relative group">
            <div class="absolute -inset-0.5 bg-gradient-to-r from-indigo-500 to-violet-500 rounded-2xl blur opacity-25 group-hover:opacity-50 transition-opacity"></div>
            <div class="relative flex items-center bg-white dark:bg-slate-900/80 backdrop-blur-xl border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden shadow-lg shadow-indigo-500/10 dark:shadow-black/30">
              <span class="pl-5 pr-2 text-gray-400 dark:text-slate-500"><i class="bi bi-search text-lg"></i></span>
              <input type="text" name="q" class="flex-1 bg-transparent border-0 text-slate-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-slate-500 py-3.5 px-2 text-base focus:outline-none focus:ring-0" placeholder="ค้นหาอีเวนต์...">
              <button type="submit" class="m-1.5 px-5 py-2.5 rounded-xl font-semibold text-white bg-gradient-to-br from-indigo-500 to-violet-600 hover:shadow-xl hover:shadow-indigo-500/30 transition-all duration-200">ค้นหา</button>
            </div>
          </div>
        </form>

        
        <div class="flex flex-wrap items-center gap-3 mb-8">
          <a href="<?php echo e(route('events.index')); ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-gradient-to-br from-indigo-500 to-violet-600 shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:shadow-indigo-500/40 hover:-translate-y-0.5 transition-all duration-200">
            <i class="bi bi-grid-3x3-gap-fill"></i> ดูอีเวนต์ทั้งหมด
          </a>
          <a href="<?php echo e(route('events.index')); ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold border-2 border-indigo-500/80 dark:border-indigo-300/50 text-indigo-600 dark:text-indigo-200 bg-white/70 dark:bg-white/5 backdrop-blur-sm hover:bg-indigo-50 dark:hover:bg-white/10 hover:-translate-y-0.5 hover:shadow-lg transition-all duration-200">
            <i class="bi bi-person-bounding-box"></i> ค้นหาด้วยใบหน้า
          </a>
        </div>

        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 max-w-xl">
          
          <div class="group relative rounded-2xl p-4 backdrop-blur-xl border bg-white/70 dark:bg-white/10 border-white/60 dark:border-white/10 shadow-lg shadow-indigo-500/5 dark:shadow-black/20 hover:-translate-y-1 transition-all duration-300">
            <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-md mb-2">
              <i class="bi bi-calendar-event text-sm"></i>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-white leading-none hero-stat-number"><?php echo e($categories->sum('events_count') ?? 0); ?>+</div>
            <div class="text-[11px] sm:text-xs font-medium text-slate-500 dark:text-slate-300/70 mt-1 uppercase tracking-wider">อีเวนต์</div>
          </div>
          
          <div class="group relative rounded-2xl p-4 backdrop-blur-xl border bg-white/70 dark:bg-white/10 border-white/60 dark:border-white/10 shadow-lg shadow-indigo-500/5 dark:shadow-black/20 hover:-translate-y-1 transition-all duration-300" style="animation-delay:.15s">
            <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br from-pink-500 to-rose-600 text-white shadow-md mb-2">
              <i class="bi bi-camera-fill text-sm"></i>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-white leading-none hero-stat-number"><?php echo e(isset($photographers) ? $photographers->count() : 0); ?></div>
            <div class="text-[11px] sm:text-xs font-medium text-slate-500 dark:text-slate-300/70 mt-1 uppercase tracking-wider">ช่างภาพ</div>
          </div>
          
          <div class="group relative rounded-2xl p-4 backdrop-blur-xl border bg-white/70 dark:bg-white/10 border-white/60 dark:border-white/10 shadow-lg shadow-indigo-500/5 dark:shadow-black/20 hover:-translate-y-1 transition-all duration-300" style="animation-delay:.3s">
            <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-md mb-2">
              <i class="bi bi-image-fill text-sm"></i>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-white leading-none hero-stat-number">HD</div>
            <div class="text-[11px] sm:text-xs font-medium text-slate-500 dark:text-slate-300/70 mt-1 uppercase tracking-wider">คุณภาพสูง</div>
          </div>
          
          <div class="group relative rounded-2xl p-4 backdrop-blur-xl border bg-white/70 dark:bg-white/10 border-white/60 dark:border-white/10 shadow-lg shadow-indigo-500/5 dark:shadow-black/20 hover:-translate-y-1 transition-all duration-300" style="animation-delay:.45s">
            <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-md mb-2">
              <i class="bi bi-lightning-charge-fill text-sm"></i>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-white leading-none hero-stat-number">24/7</div>
            <div class="text-[11px] sm:text-xs font-medium text-slate-500 dark:text-slate-300/70 mt-1 uppercase tracking-wider">พร้อมใช้งาน</div>
          </div>
        </div>
      </div>

      
      <div class="hidden lg:block lg:col-span-5 relative z-[1]">
        <div class="relative">
          <div class="absolute -inset-6 bg-gradient-to-tr from-indigo-400/20 via-fuchsia-400/20 to-amber-400/20 dark:from-indigo-500/20 dark:via-fuchsia-500/20 dark:to-amber-500/10 rounded-3xl blur-3xl"></div>
          <div class="relative rounded-3xl overflow-hidden border border-white/60 dark:border-white/10 backdrop-blur-xl bg-white/50 dark:bg-white/5 p-6 shadow-2xl shadow-indigo-500/10 dark:shadow-black/40">
            <div class="grid grid-cols-2 gap-3">
              <div class="aspect-square rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white"><i class="bi bi-camera-reels text-4xl"></i></div>
              <div class="aspect-square rounded-2xl bg-gradient-to-br from-pink-500 to-rose-600 flex items-center justify-center text-white"><i class="bi bi-heart-fill text-4xl"></i></div>
              <div class="aspect-square rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white"><i class="bi bi-image-fill text-4xl"></i></div>
              <div class="aspect-square rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center text-white"><i class="bi bi-stars text-4xl"></i></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>


<section class="mb-10 md:mb-12 fade-up" x-data="{ show: localStorage.getItem('promo_banner_dismissed') !== '1' }" x-show="show" x-transition>
  <div class="relative rounded-3xl overflow-hidden p-5 md:p-7
              bg-gradient-to-r from-emerald-500 via-indigo-500 to-pink-500
              shadow-xl shadow-indigo-500/30 border border-white/20">
    <div class="absolute inset-0 pointer-events-none opacity-20"
         style="background-image: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.4), transparent 50%), radial-gradient(circle at 80% 50%, rgba(255,255,255,0.3), transparent 50%);"></div>
    <button type="button"
            @click="show = false; localStorage.setItem('promo_banner_dismissed', '1');"
            class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center backdrop-blur-md transition z-10"
            aria-label="ปิด">
      <i class="bi bi-x-lg text-sm"></i>
    </button>
    <div class="relative flex items-center gap-4 md:gap-6 flex-wrap">
      <div class="hidden sm:flex w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-white/25 backdrop-blur-md items-center justify-center text-2xl md:text-3xl text-white shadow-lg shrink-0">
        <i class="bi bi-stars"></i>
      </div>
      <div class="flex-1 min-w-0 text-white">
        <div class="flex items-center gap-2 mb-1">
          <span class="text-[10px] uppercase tracking-widest font-bold bg-white/25 backdrop-blur-md px-2 py-0.5 rounded-full">ใหม่</span>
          <span class="text-xs font-semibold opacity-90">เร็ว · แม่นยำ · เข้า LINE ทันที</span>
        </div>
        <h3 class="text-base md:text-xl font-extrabold leading-tight">
          จ่ายผ่าน PromptPay → รับรูปเข้า LINE · มี AI หาใบหน้าตัวเองในงาน
        </h3>
      </div>
      
      
      <a href="<?php echo e(route('promo')); ?>"
         class="group inline-flex items-center gap-2 px-4 md:px-5 py-2.5 rounded-xl text-sm font-bold
                ring-1 ring-inset ring-indigo-200/50
                shadow-lg shadow-indigo-900/20
                hover:-translate-y-0.5 hover:shadow-xl hover:shadow-indigo-900/30 hover:ring-indigo-300
                transition shrink-0"
         style="background:rgb(255,255,255);color:#4338ca;">
        ดูจุดเด่นของเรา
        <i class="bi bi-arrow-right transition-transform group-hover:translate-x-0.5"></i>
      </a>
    </div>
  </div>
</section>


<?php if($categories->count() > 0): ?>
<section class="mb-12 md:mb-16 fade-up">
  <div class="flex justify-between items-center mb-6">
    <h4 class="font-bold text-xl sm:text-2xl text-slate-800 dark:text-gray-100 flex items-center gap-2" style="letter-spacing:-0.02em;">
      <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-md shadow-indigo-500/25">
        <i class="bi bi-grid text-white text-base"></i>
      </span>
      หมวดหมู่
    </h4>
    <a href="<?php echo e(route('events.index')); ?>" class="hidden sm:inline-flex items-center gap-1 text-sm font-medium px-4 py-1.5 rounded-full bg-indigo-50 dark:bg-white/5 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-white/10 transition">ดูทั้งหมด <i class="bi bi-arrow-right"></i></a>
  </div>
  <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-4 gap-4">
    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route('events.index', ['category' => $cat->id])); ?>" class="no-underline">
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/10 h-full category-card transition-all duration-300 cursor-pointer hover:-translate-y-1.5 hover:shadow-xl hover:border-indigo-300 dark:hover:border-indigo-400/40">
        <div class="text-center py-6 px-4">
          <div class="flex items-center justify-center mx-auto mb-3 w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-100 to-violet-100 dark:from-indigo-500/20 dark:to-violet-500/10">
            <i class="bi <?php echo e($cat->icon ?? 'bi-folder-fill'); ?> text-2xl text-indigo-600 dark:text-indigo-300"></i>
          </div>
          <h6 class="font-semibold mb-1 text-base text-slate-800 dark:text-gray-100"><?php echo e($cat->name); ?></h6>
          <small class="text-gray-500 dark:text-gray-400"><?php echo e($cat->events_count ?? 0); ?> อีเวนต์</small>
        </div>
      </div>
    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</section>
<?php endif; ?>


<?php if($featuredEvents->count() > 0): ?>
<section class="mb-12 md:mb-16 fade-up">
  <div class="flex justify-between items-center mb-6">
    <h4 class="font-bold text-xl sm:text-2xl text-slate-800 dark:text-gray-100 flex items-center gap-2" style="letter-spacing:-0.02em;">
      <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-md shadow-amber-500/25">
        <i class="bi bi-star-fill text-white text-base"></i>
      </span>
      อีเวนต์แนะนำ
    </h4>
    <a href="<?php echo e(route('events.index')); ?>" class="inline-flex items-center gap-1 text-sm font-medium px-4 py-1.5 rounded-full bg-indigo-50 dark:bg-white/5 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-white/10 transition">ดูทั้งหมด <i class="bi bi-arrow-right"></i></a>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
    <?php $__currentLoopData = $featuredEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div>
      
      <a href="<?php echo e(route('events.show', $event->id)); ?>"
         class="block h-full no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900 rounded-2xl"
         aria-label="ดูรายละเอียดอีเวนต์ <?php echo e($event->name); ?>">
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/10 h-full overflow-hidden event-card transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl dark:hover:shadow-black/40 group cursor-pointer">
          <div class="relative overflow-hidden">
            <?php if (isset($component)) { $__componentOriginal9c616a15d1042d7c43db29fb4ce87bf0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9c616a15d1042d7c43db29fb4ce87bf0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.event-cover','data' => ['src' => $event->cover_image_url,'name' => $event->name,'eventId' => $event->id,'size' => 'card']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('event-cover'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['src' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($event->cover_image_url),'name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($event->name),'event-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($event->id),'size' => 'card']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9c616a15d1042d7c43db29fb4ce87bf0)): ?>
<?php $attributes = $__attributesOriginal9c616a15d1042d7c43db29fb4ce87bf0; ?>
<?php unset($__attributesOriginal9c616a15d1042d7c43db29fb4ce87bf0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9c616a15d1042d7c43db29fb4ce87bf0)): ?>
<?php $component = $__componentOriginal9c616a15d1042d7c43db29fb4ce87bf0; ?>
<?php unset($__componentOriginal9c616a15d1042d7c43db29fb4ce87bf0); ?>
<?php endif; ?>
            <div class="absolute bottom-0 left-0 w-full" style="height:60%;background:linear-gradient(to top,rgba(0,0,0,0.6),transparent);z-index:3;"></div>
            <?php if($event->category): ?>
            <span class="absolute top-0 left-0 m-2 inline-flex items-center px-3 py-1 rounded-full text-xs font-medium text-white bg-gradient-to-br from-indigo-500 to-violet-600 shadow-md" style="z-index:4;"><?php echo e($event->category->name); ?></span>
            <?php endif; ?>
            
            <div class="absolute inset-0 z-[5] flex items-center justify-center bg-indigo-900/0 group-hover:bg-indigo-900/40 transition-all duration-300 opacity-0 group-hover:opacity-100 pointer-events-none">
              <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-white/95 text-indigo-700 text-xs font-bold shadow-xl scale-90 group-hover:scale-100 transition-transform duration-300">
                <i class="bi bi-images"></i> ดูภาพถ่าย
              </span>
            </div>
          </div>
          <div class="p-5">
            <h6 class="font-semibold mb-2 text-base text-slate-800 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition-colors" style="line-height:1.4;"><?php echo e($event->name); ?></h6>
            <div class="flex items-center gap-3 text-gray-500 dark:text-gray-400 text-sm">
              <span><i class="bi bi-calendar mr-1"></i><?php echo e($event->shoot_date?->format('d M Y')); ?></span>
              <?php if($event->location): ?>
              <span><i class="bi bi-geo-alt mr-1"></i><?php echo e(Str::limit($event->location, 15)); ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="px-5 pb-5">
            <span class="block w-full text-center text-sm font-semibold text-white py-2.5 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 group-hover:shadow-lg group-hover:shadow-indigo-500/30 transition-all duration-200">
              <i class="bi bi-images mr-1"></i> ดูภาพถ่าย <i class="bi bi-arrow-right ml-1 transition-transform duration-200 group-hover:translate-x-1"></i>
            </span>
          </div>
        </div>
      </a>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</section>
<?php endif; ?>


<?php if($latestEvents->count() > 0): ?>
<section class="mb-12 md:mb-16 fade-up">
  <div class="flex justify-between items-center mb-6">
    <h4 class="font-bold text-xl sm:text-2xl text-slate-800 dark:text-gray-100 flex items-center gap-2" style="letter-spacing:-0.02em;">
      <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-md shadow-indigo-500/25">
        <i class="bi bi-clock-history text-white text-base"></i>
      </span>
      อีเวนต์ล่าสุด
    </h4>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
    <?php $__currentLoopData = $latestEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div>
      
      <a href="<?php echo e(route('events.show', $event->id)); ?>"
         class="block h-full no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900 rounded-2xl"
         aria-label="ดูรายละเอียดอีเวนต์ <?php echo e($event->name); ?>">
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/10 h-full overflow-hidden event-card transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl dark:hover:shadow-black/40 group cursor-pointer">
          <div class="relative overflow-hidden">
            <?php if (isset($component)) { $__componentOriginal9c616a15d1042d7c43db29fb4ce87bf0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9c616a15d1042d7c43db29fb4ce87bf0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.event-cover','data' => ['src' => $event->cover_image_url,'name' => $event->name,'eventId' => $event->id,'size' => 'small']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('event-cover'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['src' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($event->cover_image_url),'name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($event->name),'event-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($event->id),'size' => 'small']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9c616a15d1042d7c43db29fb4ce87bf0)): ?>
<?php $attributes = $__attributesOriginal9c616a15d1042d7c43db29fb4ce87bf0; ?>
<?php unset($__attributesOriginal9c616a15d1042d7c43db29fb4ce87bf0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9c616a15d1042d7c43db29fb4ce87bf0)): ?>
<?php $component = $__componentOriginal9c616a15d1042d7c43db29fb4ce87bf0; ?>
<?php unset($__componentOriginal9c616a15d1042d7c43db29fb4ce87bf0); ?>
<?php endif; ?>
            <div class="absolute bottom-0 left-0 w-full" style="height:50%;background:linear-gradient(to top,rgba(0,0,0,0.5),transparent);z-index:3;"></div>
            
            <div class="absolute inset-0 z-[5] flex items-center justify-center bg-indigo-900/0 group-hover:bg-indigo-900/40 transition-all duration-300 opacity-0 group-hover:opacity-100 pointer-events-none">
              <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-white/95 text-indigo-700 text-xs font-bold shadow-xl scale-90 group-hover:scale-100 transition-transform duration-300">
                <i class="bi bi-eye"></i> ดูรายละเอียด
              </span>
            </div>
          </div>
          <div class="p-5">
            <h6 class="font-semibold mb-1 text-base text-slate-800 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition-colors"><?php echo e($event->name); ?></h6>
            <small class="text-gray-500 dark:text-gray-400"><i class="bi bi-calendar mr-1"></i><?php echo e($event->shoot_date?->format('d M Y')); ?></small>
          </div>
          <div class="px-5 pb-5">
            <span class="block w-full text-center text-sm font-semibold border-2 border-indigo-500 dark:border-indigo-400/60 text-indigo-600 dark:text-indigo-300 group-hover:bg-gradient-to-br group-hover:from-indigo-500 group-hover:to-violet-600 group-hover:border-transparent group-hover:text-white py-2.5 rounded-xl transition-all duration-200">
              ดูรายละเอียด <i class="bi bi-arrow-right ml-1 transition-transform duration-200 group-hover:translate-x-1"></i>
            </span>
          </div>
        </div>
      </a>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</section>
<?php endif; ?>


<?php if(isset($photographers) && $photographers->count() > 0): ?>
<section class="mb-12 md:mb-16 fade-up">
  <div class="flex justify-between items-center mb-6">
    <h4 class="font-bold text-xl sm:text-2xl text-slate-800 dark:text-gray-100 flex items-center gap-2" style="letter-spacing:-0.02em;">
      <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-pink-500 to-rose-600 flex items-center justify-center shadow-md shadow-pink-500/25">
        <i class="bi bi-people text-white text-base"></i>
      </span>
      ช่างภาพ
    </h4>
  </div>
  <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-6 gap-4 stagger-children">
    <?php $__currentLoopData = $photographers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    
    <div class="fade-up">
      <a href="<?php echo e(route('photographers.show', $pg->user_id)); ?>"
         class="group block bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/10 h-full text-center photographer-card transition-all duration-300 hover:-translate-y-1 hover:shadow-lg hover:border-indigo-200 dark:hover:border-indigo-400/40 relative overflow-hidden"
         aria-label="ดูโปรไฟล์ของ <?php echo e($pg->display_name ?? $pg->first_name); ?>">
        
        <?php if(($pg->tier ?? null) === \App\Models\PhotographerProfile::TIER_PRO): ?>
          <span class="absolute top-2 right-2 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[0.6rem] font-bold text-white shadow-sm z-10"
                style="background:linear-gradient(135deg,#f59e0b,#d97706);"
                title="Pro Verified">
            <i class="bi bi-patch-check-fill"></i>Pro
          </span>
        <?php elseif(($pg->tier ?? null) === \App\Models\PhotographerProfile::TIER_SELLER): ?>
          <span class="absolute top-2 right-2 inline-flex items-center gap-0.5 w-5 h-5 rounded-full justify-center text-[0.7rem] text-white shadow-sm z-10"
                style="background:#10b981;"
                title="Verified Seller">
            <i class="bi bi-check-lg"></i>
          </span>
        <?php endif; ?>

        <div class="py-6 px-4">
          <div class="mx-auto mb-3 transition-transform duration-300 group-hover:scale-105">
            <?php if (isset($component)) { $__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.avatar','data' => ['src' => $pg->avatar,'name' => $pg->display_name ?? $pg->first_name ?? 'P','userId' => $pg->user_id ?? $pg->id,'size' => 'xl','ring' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['src' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($pg->avatar),'name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($pg->display_name ?? $pg->first_name ?? 'P'),'user-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($pg->user_id ?? $pg->id),'size' => 'xl','ring' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b)): ?>
<?php $attributes = $__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b; ?>
<?php unset($__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b)): ?>
<?php $component = $__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b; ?>
<?php unset($__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b); ?>
<?php endif; ?>
          </div>
          <h6 class="font-semibold mb-1 text-sm text-slate-800 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors truncate px-2">
            <?php echo e($pg->display_name ?? $pg->first_name); ?>

          </h6>
          <small class="text-gray-500 dark:text-gray-400">
            <i class="bi bi-camera mr-1"></i><?php echo e($pg->events_count); ?> อีเวนต์
          </small>

          
          <div class="mt-3 text-[0.7rem] font-medium text-indigo-500 dark:text-indigo-400 opacity-0 group-hover:opacity-100 transition-opacity">
            ดูโปรไฟล์ <i class="bi bi-arrow-right ml-0.5"></i>
          </div>
        </div>
      </a>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</section>
<?php endif; ?>


<section class="mb-4">
  <div class="relative overflow-hidden text-center py-14 md:py-16 px-6 rounded-3xl bg-gradient-to-br from-indigo-50 via-white to-pink-50 dark:from-slate-800 dark:via-slate-800 dark:to-indigo-950/60 border border-white/60 dark:border-white/10 shadow-xl shadow-indigo-500/5 dark:shadow-black/30">
    <div class="absolute -top-24 -right-24 w-64 h-64 rounded-full bg-indigo-400/10 dark:bg-indigo-500/10 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-24 -left-24 w-64 h-64 rounded-full bg-pink-400/10 dark:bg-pink-500/10 blur-3xl pointer-events-none"></div>
    <div class="relative">
    <?php if(auth()->guard()->check()): ?>
      <?php
        $_hasProfile = Auth::user()->photographerProfile !== null;
        $_approved  = $_hasProfile && Auth::user()->photographerProfile->status === 'approved';
        $_pending  = $_hasProfile && Auth::user()->photographerProfile->status === 'pending';
      ?>

      <?php if($_approved): ?>
        
        <div class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 items-center justify-center shadow-lg shadow-blue-500/30 mb-4">
          <i class="bi bi-camera-reels text-white text-2xl"></i>
        </div>
        <h4 class="font-bold mb-2 text-xl sm:text-2xl text-slate-800 dark:text-gray-100">จัดการงานของคุณ</h4>
        <p class="text-gray-600 dark:text-gray-400 mb-5 max-w-md mx-auto">เข้าสู่แดชบอร์ดช่างภาพเพื่อจัดการอีเวนต์และรูปภาพ</p>
        <a href="<?php echo e(route('photographer.dashboard')); ?>" class="inline-flex items-center gap-2 font-semibold text-white px-6 py-3 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-600 shadow-lg shadow-blue-500/30 hover:shadow-xl hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all duration-200">
          <i class="bi bi-speedometer2"></i> แดชบอร์ดช่างภาพ
        </a>
      <?php elseif($_pending): ?>
        
        <div class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 items-center justify-center shadow-lg shadow-amber-500/30 mb-4">
          <i class="bi bi-hourglass-split text-white text-2xl"></i>
        </div>
        <h4 class="font-bold mb-2 text-xl sm:text-2xl text-slate-800 dark:text-gray-100">กำลังรอการอนุมัติ</h4>
        <p class="text-gray-600 dark:text-gray-400 mb-5 max-w-md mx-auto">คำขอสมัครช่างภาพของคุณอยู่ระหว่างการตรวจสอบ เราจะแจ้งให้ทราบเมื่อได้รับการอนุมัติ</p>
        <a href="<?php echo e(route('profile')); ?>" class="inline-flex items-center gap-2 font-semibold text-white px-6 py-3 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 shadow-lg shadow-amber-500/30 hover:shadow-xl hover:shadow-amber-500/40 hover:-translate-y-0.5 transition-all duration-200">
          <i class="bi bi-person"></i> ดูโปรไฟล์ของฉัน
        </a>
      <?php else: ?>
        
        <div class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 items-center justify-center shadow-lg shadow-indigo-500/30 mb-4">
          <i class="bi bi-camera2 text-white text-2xl"></i>
        </div>
        <h4 class="font-bold mb-2 text-xl sm:text-2xl text-slate-800 dark:text-gray-100">อยากขายรูปภาพอีเวนต์?</h4>
        <p class="text-gray-600 dark:text-gray-400 mb-5 max-w-md mx-auto">อัพเกรดบัญชีเป็นช่างภาพเพื่อเริ่มอัปโหลดและขายรูปภาพของคุณ</p>
        <a href="<?php echo e(route('photographer.register')); ?>" class="inline-flex items-center gap-2 font-semibold text-white px-6 py-3 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:shadow-indigo-500/40 hover:-translate-y-0.5 transition-all duration-200">
          <i class="bi bi-arrow-up-circle"></i> อัพเกรดเป็นช่างภาพ
        </a>
      <?php endif; ?>
    <?php else: ?>
      
      <div class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 items-center justify-center shadow-lg shadow-indigo-500/30 mb-4">
        <i class="bi bi-camera2 text-white text-2xl"></i>
      </div>
      <h4 class="font-bold mb-2 text-xl sm:text-2xl text-slate-800 dark:text-gray-100">คุณเป็นช่างภาพ?</h4>
      <p class="text-gray-600 dark:text-gray-400 mb-5 max-w-md mx-auto">เข้าร่วมกับเราเพื่อขายภาพถ่ายอีเวนต์ของคุณ</p>
      <a href="<?php echo e(route('photographer.register')); ?>" class="inline-flex items-center gap-2 font-semibold text-white px-6 py-3 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:shadow-indigo-500/40 hover:-translate-y-0.5 transition-all duration-200">
        <i class="bi bi-person-plus"></i> สมัครเป็นช่างภาพ
      </a>
    <?php endif; ?>
    </div>
  </div>
</section>

<style>
.event-card:hover img { transform:scale(1.08); }

/* Float animation on hero stat numbers */
@keyframes heroStatFloat {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
}
.hero-stat-number {
  animation: heroStatFloat 4s ease-in-out infinite;
  display: inline-block;
}
</style>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/public/home.blade.php ENDPATH**/ ?>