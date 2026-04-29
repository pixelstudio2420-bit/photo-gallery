<?php $__env->startSection('title', 'Store · ซื้อโปรโมท + บริการเสริม'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-[1400px] mx-auto px-4 py-6">

  <div class="mb-6">
    <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white mb-1">Store</h1>
    <p class="text-sm text-slate-600 dark:text-slate-400">
      โปรโมทช่างภาพให้ขึ้นอันดับสูง · ซื้อบริการเสริมพื้นที่ + AI credits + branding
    </p>
  </div>

  <?php if(session('success')): ?>
    <div class="mb-5 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 text-emerald-700 dark:text-emerald-300 text-sm">
      <i class="bi bi-check-circle-fill"></i> <?php echo e(session('success')); ?>

    </div>
  <?php endif; ?>
  <?php if($errors->any()): ?>
    <div class="mb-5 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
      <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div>· <?php echo e($e); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  <?php endif; ?>

  
  <?php $__currentLoopData = $catalog; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <section class="mb-8" style="--cat-accent: <?php echo e($cat['accent']); ?>;">
      <div class="flex items-end justify-between mb-3">
        <div>
          <h2 class="text-xl font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
            <i class="bi <?php echo e($cat['icon']); ?>" style="color: var(--cat-accent);"></i>
            <?php echo e($cat['title']); ?>

          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 max-w-2xl"><?php echo e($cat['description']); ?></p>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php $__currentLoopData = $cat['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="relative rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 p-4 hover:-translate-y-1 transition shadow-sm hover:shadow-lg flex flex-col">

          <?php if(!empty($item['badge'])): ?>
            <span class="absolute -top-2 right-3 inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-amber-400 text-amber-900 shadow">
              <i class="bi bi-star-fill"></i> <?php echo e($item['badge']); ?>

            </span>
          <?php endif; ?>

          <div class="text-base font-bold text-slate-900 dark:text-white"><?php echo e($item['label']); ?></div>
          <?php if(!empty($item['tagline'])): ?>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5"><?php echo e($item['tagline']); ?></div>
          <?php endif; ?>

          <div class="mt-3 mb-4">
            <span class="text-2xl font-extrabold text-slate-900 dark:text-white">
              ฿<?php echo e(number_format($item['price_thb'], 0)); ?>

            </span>
            <?php if(!empty($item['cycle']) && $item['cycle'] !== 'pay_per_use' && empty($item['one_time'])): ?>
              <span class="text-xs text-slate-500 dark:text-slate-400">
                / <?php echo e(['daily' => 'วัน', 'monthly' => 'เดือน', 'yearly' => 'ปี'][$item['cycle']] ?? $item['cycle']); ?>

              </span>
            <?php elseif(!empty($item['one_time'])): ?>
              <span class="text-xs text-slate-500">· ตลอดอายุ subscription</span>
            <?php endif; ?>
          </div>

          
          <?php if($key === 'storage'): ?>
            <div class="text-xs text-slate-600 dark:text-slate-400 mb-4 flex items-center gap-1.5">
              <i class="bi bi-hdd"></i> +<?php echo e(number_format($item['storage_gb'])); ?> GB
            </div>
          <?php elseif($key === 'ai_credits'): ?>
            <div class="text-xs text-slate-600 dark:text-slate-400 mb-4 flex items-center gap-1.5">
              <i class="bi bi-cpu"></i> +<?php echo e(number_format($item['credits'])); ?> credits
            </div>
          <?php elseif($key === 'promotion'): ?>
            <div class="text-xs text-slate-600 dark:text-slate-400 mb-4 flex items-center gap-1.5">
              <i class="bi bi-arrow-up-circle"></i> Boost +<?php echo e($item['boost_score']); ?> pts
            </div>
          <?php endif; ?>

          <form method="POST" action="<?php echo e(route('photographer.store.buy', ['sku' => $item['sku']])); ?>" class="mt-auto">
            <?php echo csrf_field(); ?>
            <button class="w-full px-3 py-2 rounded-lg font-bold text-sm transition text-white"
                    style="background: var(--cat-accent);">
              <i class="bi bi-bag-plus"></i> ซื้อตอนนี้
            </button>
          </form>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </section>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

  
  <?php if($history->count() > 0): ?>
  <section class="mt-10">
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-lg font-bold text-slate-900 dark:text-white">การซื้อล่าสุดของคุณ</h2>
      <a href="<?php echo e(route('photographer.store.history')); ?>" class="text-xs text-indigo-600 dark:text-indigo-300 hover:underline">ดูทั้งหมด →</a>
    </div>
    <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase text-slate-500">
          <tr><th class="px-3 py-2 text-left">รายการ</th><th class="px-3 py-2 text-left">หมวด</th><th class="px-3 py-2 text-right">฿</th><th class="px-3 py-2 text-left">สถานะ</th><th class="px-3 py-2 text-left">เมื่อ</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-white/10">
          <?php $__currentLoopData = $history->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php $snap = json_decode($h->snapshot ?? '{}', true) ?: []; ?>
          <tr>
            <td class="px-3 py-2 font-medium"><?php echo e($snap['label'] ?? $h->sku); ?></td>
            <td class="px-3 py-2 text-xs text-slate-500"><?php echo e($h->category); ?></td>
            <td class="px-3 py-2 text-right font-bold">฿<?php echo e(number_format($h->price_thb, 0)); ?></td>
            <td class="px-3 py-2">
              <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase
                <?php if($h->status === 'activated'): ?> bg-emerald-100 text-emerald-700
                <?php elseif($h->status === 'paid'): ?> bg-sky-100 text-sky-700
                <?php elseif($h->status === 'pending'): ?> bg-amber-100 text-amber-700
                <?php else: ?> bg-rose-100 text-rose-700 <?php endif; ?>"><?php echo e($h->status); ?></span>
            </td>
            <td class="px-3 py-2 text-xs text-slate-500"><?php echo e(\Carbon\Carbon::parse($h->created_at)->diffForHumans()); ?></td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.photographer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/photographer/store/index.blade.php ENDPATH**/ ?>