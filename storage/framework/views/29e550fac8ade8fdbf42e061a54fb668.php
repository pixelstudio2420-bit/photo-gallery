<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>">
<head>
  <?php echo $__env->make('layouts.partials.analytics-head', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
  <?php echo app(\App\Services\SeoService::class)->render(); ?>


  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="dns-prefetch" href="https://fonts.googleapis.com">
  <link rel="dns-prefetch" href="https://fonts.gstatic.com">
  <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
  <?php if(config('filesystems.disks.r2.endpoint')): ?>
    <link rel="dns-prefetch" href="<?php echo e(config('filesystems.disks.r2.endpoint')); ?>">
  <?php endif; ?>

  
  <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
  <link rel="stylesheet" href="<?php echo e(asset('css/avatar.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/event-cover.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/darkmode.css')); ?>">
  <script src="<?php echo e(asset('js/darkmode.js')); ?>"></script>
  <?php echo $__env->yieldPushContent('styles'); ?>
  <?php if (! empty(trim($__env->yieldContent('og-meta')))): ?>
    <?php echo $__env->yieldContent('og-meta'); ?>
  <?php else: ?>
    <?php echo $__env->make('layouts.partials.og-meta', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endif; ?>

  
  <?php if (isset($component)) { $__componentOriginal48fbd4e7303a0f963bff318b803dae7a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal48fbd4e7303a0f963bff318b803dae7a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.marketing.pixels-head','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('marketing.pixels-head'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal48fbd4e7303a0f963bff318b803dae7a)): ?>
<?php $attributes = $__attributesOriginal48fbd4e7303a0f963bff318b803dae7a; ?>
<?php unset($__attributesOriginal48fbd4e7303a0f963bff318b803dae7a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal48fbd4e7303a0f963bff318b803dae7a)): ?>
<?php $component = $__componentOriginal48fbd4e7303a0f963bff318b803dae7a; ?>
<?php unset($__componentOriginal48fbd4e7303a0f963bff318b803dae7a); ?>
<?php endif; ?>

  
  <?php if (! empty(trim($__env->yieldContent('marketing-schema')))): ?>
    <?php echo $__env->yieldContent('marketing-schema'); ?>
  <?php endif; ?>
</head>
<body class="bg-white dark:bg-slate-950 text-gray-800 dark:text-gray-100">
  
  <?php if (isset($component)) { $__componentOriginal17dc1da3374e927bcea816fe3c3cbc4b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal17dc1da3374e927bcea816fe3c3cbc4b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.marketing.pixels-body','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('marketing.pixels-body'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal17dc1da3374e927bcea816fe3c3cbc4b)): ?>
<?php $attributes = $__attributesOriginal17dc1da3374e927bcea816fe3c3cbc4b; ?>
<?php unset($__attributesOriginal17dc1da3374e927bcea816fe3c3cbc4b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal17dc1da3374e927bcea816fe3c3cbc4b)): ?>
<?php $component = $__componentOriginal17dc1da3374e927bcea816fe3c3cbc4b; ?>
<?php unset($__componentOriginal17dc1da3374e927bcea816fe3c3cbc4b); ?>
<?php endif; ?>

  
  <?php if (isset($component)) { $__componentOriginal951a1d9232ab18b4409522ca2b887cb9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal951a1d9232ab18b4409522ca2b887cb9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.marketing.push-prompt','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('marketing.push-prompt'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal951a1d9232ab18b4409522ca2b887cb9)): ?>
<?php $attributes = $__attributesOriginal951a1d9232ab18b4409522ca2b887cb9; ?>
<?php unset($__attributesOriginal951a1d9232ab18b4409522ca2b887cb9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal951a1d9232ab18b4409522ca2b887cb9)): ?>
<?php $component = $__componentOriginal951a1d9232ab18b4409522ca2b887cb9; ?>
<?php unset($__componentOriginal951a1d9232ab18b4409522ca2b887cb9); ?>
<?php endif; ?>

  
  <?php echo $__env->make('layouts.partials.navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  
  <?php if(session('impersonator.admin_id')): ?>
  <div class="bg-purple-600 border-b border-purple-800 text-white">
    <div class="max-w-7xl mx-auto px-4 py-2 flex items-center justify-between flex-wrap gap-2">
      <div class="flex items-center gap-2 text-sm">
        <i class="bi bi-incognito text-purple-100"></i>
        <span>
          <strong>กำลัง Impersonate</strong> เป็น
          <strong><?php echo e(Auth::user()?->full_name ?? Auth::user()?->email ?? 'ผู้ใช้'); ?></strong>
          (admin: <?php echo e(session('impersonator.admin_email')); ?>)
        </span>
      </div>
      <form method="POST" action="<?php echo e(route('impersonate.stop')); ?>" class="inline">
        <?php echo csrf_field(); ?>
        <button type="submit" class="bg-white text-purple-700 hover:bg-purple-50 text-xs font-semibold px-3 py-1.5 rounded-lg inline-flex items-center gap-1">
          <i class="bi bi-box-arrow-left"></i> หยุด Impersonate
        </button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  
  <?php if(auth()->guard()->check()): ?>
    <?php if(Auth::user()->auth_provider === 'local' && !Auth::user()->email_verified): ?>
    <div x-data="{ show: !sessionStorage.getItem('verify_banner_dismissed_' + <?php echo e(Auth::id()); ?>) }"
         x-show="show" x-cloak
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 -translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="relative overflow-hidden border-b border-indigo-200/60 dark:border-indigo-500/20"
         style="background: linear-gradient(90deg, rgba(99,102,241,.06) 0%, rgba(236,72,153,.05) 50%, rgba(245,158,11,.06) 100%);">
      
      <div class="absolute inset-0 pointer-events-none opacity-50"
           style="background: radial-gradient(ellipse 60% 80% at 50% 0%, rgba(99,102,241,.12) 0%, transparent 60%);"></div>

      <div class="relative max-w-7xl mx-auto px-4 py-2.5 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3 min-w-0 flex-1">
          
          <div class="flex-shrink-0 w-8 h-8 rounded-full bg-white dark:bg-slate-100 shadow-sm flex items-center justify-center ring-1 ring-slate-200/60">
            <svg viewBox="0 0 24 24" class="w-4 h-4">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
          </div>
          <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100 leading-tight">
              เชื่อมต่อบัญชี Google เพื่อใช้งานได้เต็มรูปแบบ
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-tight mt-0.5">
              ไม่ต้องยืนยันอีเมล · เข้าสู่ระบบเร็วขึ้น · ปลอดภัยกว่า
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
          
          <a href="<?php echo e(route('auth.google')); ?>"
             class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-semibold
                    bg-white dark:bg-slate-100 text-slate-700 hover:text-slate-900
                    border border-slate-200 hover:border-slate-300
                    shadow-sm hover:shadow-md transition-all duration-200
                    hover:-translate-y-0.5">
            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            <span>เชื่อมต่อ Google</span>
          </a>

          
          <form method="POST" action="<?php echo e(route('verification.send')); ?>" class="inline"><?php echo csrf_field(); ?>
            <button type="submit"
                    class="text-xs text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400
                           font-medium px-2 py-1 rounded transition"
                    title="ใช้อีเมลยืนยันแบบเดิม">
              <i class="bi bi-envelope"></i> <span class="hidden sm:inline">หรือยืนยันอีเมล</span>
            </button>
          </form>

          
          <button type="button"
                  @click="show = false; sessionStorage.setItem('verify_banner_dismissed_' + <?php echo e(Auth::id()); ?>, '1')"
                  class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition p-1 rounded"
                  title="ปิด (จะแสดงอีกในครั้งหน้า)">
            <i class="bi bi-x-lg text-xs"></i>
          </button>
        </div>
      </div>
    </div>
    <?php endif; ?>
  <?php endif; ?>

  
  <?php if(session('success')): ?>
    <div class="max-w-7xl mx-auto px-4 mt-3">
      <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 text-green-700 dark:text-green-300 rounded-xl p-4 shadow-sm dark:shadow-black/30 relative" role="alert">
        <i class="bi bi-check-circle mr-2"></i><?php echo e(session('success')); ?>

        <button type="button" onclick="this.parentElement.remove()" class="absolute top-3 right-3 text-green-400 hover:text-green-600 dark:text-green-300 dark:hover:text-green-100">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>
  <?php endif; ?>
  <?php if(session('warning')): ?>
    <div class="max-w-7xl mx-auto px-4 mt-3">
      <div class="bg-yellow-50 dark:bg-yellow-500/10 border border-yellow-200 dark:border-yellow-500/30 text-yellow-700 dark:text-yellow-300 rounded-xl p-4 shadow-sm dark:shadow-black/30 relative" role="alert">
        <i class="bi bi-exclamation-triangle mr-2"></i><?php echo e(session('warning')); ?>

        <button type="button" onclick="this.parentElement.remove()" class="absolute top-3 right-3 text-yellow-400 hover:text-yellow-600 dark:text-yellow-300 dark:hover:text-yellow-100">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>
  <?php endif; ?>
  <?php if(session('error')): ?>
    <div class="max-w-7xl mx-auto px-4 mt-3">
      <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300 rounded-xl p-4 shadow-sm dark:shadow-black/30 relative" role="alert">
        <i class="bi bi-x-circle mr-2"></i><?php echo e(session('error')); ?>

        <button type="button" onclick="this.parentElement.remove()" class="absolute top-3 right-3 text-red-400 hover:text-red-600 dark:text-red-300 dark:hover:text-red-100">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>
  <?php endif; ?>

  
  <main class="main-content">
    <?php echo $__env->yieldContent('hero'); ?>

    <?php if (! empty(trim($__env->yieldContent('content')))): ?>
    <div class="max-w-7xl mx-auto px-4 py-4">
      <?php echo $__env->yieldContent('content'); ?>
    </div>
    <?php endif; ?>

    <?php echo $__env->yieldContent('content-full'); ?>
  </main>

  
  <?php echo $__env->make('layouts.partials.footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="<?php echo e(asset('js/app.js')); ?>"></script>
  <?php if(auth()->guard()->check()): ?>
  <script>window.__csrf = '<?php echo e(csrf_token()); ?>';</script>
  <script src="<?php echo e(asset('js/user-notifications.js')); ?>"></script>
  <script src="<?php echo e(asset('js/wishlist.js')); ?>"></script>
  <?php endif; ?>
  <?php echo $__env->yieldPushContent('scripts'); ?>
  <?php echo $__env->make('partials.source-shield', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  
  <?php if(app(\App\Services\SubscriptionService::class)->featureGloballyEnabled('chatbot')): ?>
    <?php echo $__env->make('partials.chat-widget', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endif; ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/layouts/app.blade.php ENDPATH**/ ?>