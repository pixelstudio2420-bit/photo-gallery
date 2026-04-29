<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
  /** customer | photographer (used to pick recommended provider) */
  'role'         => 'customer',
  /** lg | md */
  'size'         => 'md',
  /** true → render only recommended provider, large & centred */
  'primaryOnly'  => false,
  /** list of providers to hide e.g. ['line'] */
  'exclude'      => [],
  /** Hide the recommended badge on the primary button */
  'hideRecommended' => false,
  /** Label for secondary caption. Defaults based on role */
  'intent'       => 'register',
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
  /** customer | photographer (used to pick recommended provider) */
  'role'         => 'customer',
  /** lg | md */
  'size'         => 'md',
  /** true → render only recommended provider, large & centred */
  'primaryOnly'  => false,
  /** list of providers to hide e.g. ['line'] */
  'exclude'      => [],
  /** Hide the recommended badge on the primary button */
  'hideRecommended' => false,
  /** Label for secondary caption. Defaults based on role */
  'intent'       => 'register',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
  /** @var \App\Services\Auth\SocialAuthService $svc */
  $svc       = app(\App\Services\Auth\SocialAuthService::class);
  $providers = $svc->enabledProviders();

  // remove excluded
  foreach ((array) $exclude as $ex) {
      unset($providers[$ex]);
  }

  $recommended = $svc->defaultProviderForRole($role);

  // Reorder: recommended first
  if (isset($providers[$recommended])) {
      $recMeta   = $providers[$recommended];
      unset($providers[$recommended]);
      $providers = [$recommended => $recMeta] + $providers;
  }

  $primary   = $providers[$recommended] ?? null;
  $secondary = array_filter($providers, fn($k) => $k !== $recommended, ARRAY_FILTER_USE_KEY);

  $verb = $intent === 'login' ? 'เข้าสู่ระบบด้วย' : 'สมัครผ่าน';

  $lgClass = 'py-3.5 text-base';
  $mdClass = 'py-2.5 text-sm';
  $btnSize = $size === 'lg' ? $lgClass : $mdClass;
?>

<?php
  // Build URL with optional role intent (so AuthController can redirect
  // a new signup to photographer onboarding vs customer home).
  $roleQuery = $intent === 'register' ? '?role=' . urlencode($role) : '';
?>

<?php if(empty($providers)): ?>
  <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 rounded-xl p-3 text-sm dark:bg-yellow-500/10 dark:border-yellow-400/30 dark:text-yellow-300">
    <i class="bi bi-exclamation-triangle mr-1"></i>ผู้ดูแลยังไม่ได้เปิดใช้ช่องทาง Social Login
  </div>
<?php else: ?>
  <div class="space-y-2.5">
    
    <?php if($primary): ?>
      <a
        href="<?php echo e($svc->providerUrl($recommended) . $roleQuery); ?>"
        class="social-btn group relative w-full inline-flex items-center justify-center gap-3 <?php echo e($btnSize); ?> font-semibold rounded-xl transition-all duration-200 shadow-sm hover:shadow-lg hover:-translate-y-0.5 <?php echo e($primary['bg_class']); ?> <?php echo e($primary['text_class']); ?>"
        aria-label="<?php echo e($verb); ?> <?php echo e($primary['label']); ?>"
        data-provider="<?php echo e($recommended); ?>"
        onclick="this.classList.add('is-loading');"
      >
        <i class="bi <?php echo e($primary['icon']); ?> text-xl"></i>
        <span><?php echo e($verb); ?> <?php echo e($primary['label']); ?></span>
        <?php if (! ($hideRecommended)): ?>
          <span class="absolute -top-2 right-3 text-[.65rem] font-bold px-2 py-0.5 rounded-full bg-amber-400 text-amber-900 shadow">แนะนำ</span>
        <?php endif; ?>
        <i class="bi bi-arrow-right-circle-fill absolute right-4 opacity-0 group-hover:opacity-100 transition"></i>
      </a>
    <?php endif; ?>

    <?php if(!$primaryOnly && count($secondary)): ?>
      <div class="flex items-center gap-2 my-2">
        <hr class="flex-1 border-gray-200 dark:border-white/10">
        <span class="text-xs text-gray-400 dark:text-gray-500">หรือใช้ช่องทางอื่น</span>
        <hr class="flex-1 border-gray-200 dark:border-white/10">
      </div>

      <div class="grid <?php echo e(count($secondary) > 2 ? 'grid-cols-3' : 'grid-cols-' . count($secondary)); ?> gap-2">
        <?php $__currentLoopData = $secondary; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name => $meta): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <a
            href="<?php echo e($svc->providerUrl($name) . $roleQuery); ?>"
            class="social-btn inline-flex items-center justify-center gap-1.5 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md <?php echo e($meta['bg_class']); ?> <?php echo e($meta['text_class']); ?>"
            aria-label="<?php echo e($verb); ?> <?php echo e($meta['label']); ?>"
            data-provider="<?php echo e($name); ?>"
            onclick="this.classList.add('is-loading');"
          >
            <i class="bi <?php echo e($meta['icon']); ?>"></i>
            <span><?php echo e($meta['label']); ?></span>
          </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (! $__env->hasRenderedOnce('f97311ea-7196-4c46-810f-234d3957b471')): $__env->markAsRenderedOnce('f97311ea-7196-4c46-810f-234d3957b471'); ?>
<?php $__env->startPush('styles'); ?>
<style>
  .social-btn.is-loading { opacity:.75; pointer-events:none; position:relative; }
  .social-btn.is-loading::after {
    content:''; width:14px; height:14px; border:2px solid currentColor; border-right-color:transparent;
    border-radius:50%; margin-left:8px; animation:sbspin .8s linear infinite; display:inline-block;
  }
  @keyframes sbspin { to { transform:rotate(360deg); } }
  .social-btn:focus-visible { outline:2px solid #6366f1; outline-offset:3px; }
</style>
<?php $__env->stopPush(); ?>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/components/social-buttons.blade.php ENDPATH**/ ?>