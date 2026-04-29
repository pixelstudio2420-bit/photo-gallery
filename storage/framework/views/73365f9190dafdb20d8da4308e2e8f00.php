
<?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
  $typeMeta = $typeLabels[$product->product_type] ?? [
    'label' => 'สินค้า',
    'icon'  => 'bi-box-seam',
    'color' => 'from-slate-500 to-slate-600',
  ];
  $hasSale  = !empty($product->sale_price);
  $discount = $hasSale
    ? round((($product->price - $product->sale_price) / max($product->price, 0.01)) * 100)
    : 0;
?>

<div class="product-card-wrap h-full">
  <a href="<?php echo e(route('products.show', $product->slug)); ?>"
     style="animation-delay: <?php echo e(($loop->index % 12) * 40); ?>ms"
     class="group relative flex flex-col h-full overflow-hidden rounded-2xl
            bg-white dark:bg-slate-900
            border border-slate-200 dark:border-slate-800
            shadow-sm shadow-slate-900/5 dark:shadow-black/20
            hover:shadow-2xl hover:shadow-indigo-500/15 dark:hover:shadow-indigo-500/20
            hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-500/40
            transition-all duration-300">

    
    <div class="relative overflow-hidden aspect-[4/3]
                bg-slate-100 dark:bg-slate-800">
      <?php if($product->cover_image_url): ?>
        <img src="<?php echo e($product->cover_image_url); ?>"
             alt="<?php echo e($product->name); ?>"
             loading="lazy"
             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700 ease-out">
      <?php else: ?>
        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br <?php echo e($typeMeta['color']); ?>">
          <i class="bi <?php echo e($typeMeta['icon']); ?> text-white text-6xl opacity-40"></i>
        </div>
      <?php endif; ?>

      
      <div class="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-black/60 via-black/10 to-transparent
                  opacity-40 group-hover:opacity-80 transition-opacity duration-300"></div>

      
      <div class="absolute top-3 left-3">
        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold
                     bg-white/95 dark:bg-slate-900/90 backdrop-blur
                     text-slate-700 dark:text-slate-200
                     border border-white/60 dark:border-white/10
                     shadow-sm">
          <i class="bi <?php echo e($typeMeta['icon']); ?> text-indigo-600 dark:text-indigo-400"></i>
          <?php echo e($typeMeta['label']); ?>

        </span>
      </div>

      
      <div class="absolute top-3 right-3 flex flex-col gap-1.5 items-end">
        <?php if($hasSale): ?>
          <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold
                       bg-gradient-to-r from-rose-500 to-pink-500 text-white
                       shadow-md shadow-rose-500/30">
            <i class="bi bi-tag-fill mr-1 text-[10px]"></i>-<?php echo e($discount); ?>%
          </span>
        <?php endif; ?>
        <?php if($product->is_featured): ?>
          <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold
                       bg-gradient-to-r from-amber-400 to-orange-500 text-white
                       shadow-md shadow-amber-500/30">
            <i class="bi bi-star-fill mr-0.5"></i>FEATURED
          </span>
        <?php endif; ?>
      </div>

      
      <div class="absolute bottom-3 left-3 right-3
                  translate-y-2 opacity-0
                  group-hover:translate-y-0 group-hover:opacity-100
                  transition-all duration-300">
        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full
                    bg-white/95 backdrop-blur text-slate-900 text-xs font-semibold shadow-lg">
          <i class="bi bi-eye-fill text-indigo-600"></i> ดูรายละเอียด
          <i class="bi bi-arrow-right text-indigo-600"></i>
        </div>
      </div>
    </div>

    
    <div class="p-4 md:p-5 flex-1 flex flex-col">
      
      <h3 class="font-bold text-[15px] leading-snug line-clamp-2 mb-1.5
                 text-slate-900 dark:text-slate-50
                 group-hover:text-indigo-600 dark:group-hover:text-indigo-300
                 transition-colors">
        <?php echo e($product->name); ?>

      </h3>

      
      <?php if($product->short_description): ?>
        <p class="text-xs leading-relaxed line-clamp-2 mb-3
                  text-slate-600 dark:text-slate-400">
          <?php echo e($product->short_description); ?>

        </p>
      <?php endif; ?>

      
      <div class="flex items-center gap-3 text-[11px] font-medium mb-3 flex-wrap">
        <?php if($product->file_format): ?>
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md
                       bg-slate-100 dark:bg-slate-800
                       text-slate-600 dark:text-slate-300">
            <i class="bi bi-file-earmark-fill text-indigo-500 dark:text-indigo-400"></i>
            <?php echo e($product->file_format); ?>

          </span>
        <?php endif; ?>
        <?php if($product->total_sales > 0): ?>
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md
                       bg-emerald-50 dark:bg-emerald-500/10
                       text-emerald-700 dark:text-emerald-300
                       border border-emerald-200/60 dark:border-emerald-500/30">
            <i class="bi bi-download"></i> <?php echo e(number_format($product->total_sales)); ?>

          </span>
        <?php endif; ?>
        <?php if($product->version): ?>
          <span class="inline-flex items-center gap-1 text-slate-500 dark:text-slate-500">
            <i class="bi bi-tag"></i> v<?php echo e($product->version); ?>

          </span>
        <?php endif; ?>
      </div>

      
      <div class="mt-auto pt-3 border-t border-slate-100 dark:border-slate-800
                  flex items-end justify-between gap-2">
        <div>
          <?php if($hasSale): ?>
            <div class="text-xs leading-none mb-0.5
                        text-slate-400 dark:text-slate-500 line-through">
              <?php echo e(number_format($product->price, 0)); ?> ฿
            </div>
            <div class="text-xl font-extrabold leading-tight
                        text-rose-600 dark:text-rose-400
                        flex items-baseline gap-1">
              <?php echo e(number_format($product->sale_price, 0)); ?>

              <span class="text-xs font-semibold">฿</span>
            </div>
          <?php else: ?>
            <div class="text-[10px] font-semibold uppercase tracking-wider leading-none mb-0.5
                        text-slate-400 dark:text-slate-500">ราคา</div>
            <div class="text-xl font-extrabold leading-tight
                        text-indigo-600 dark:text-indigo-400
                        flex items-baseline gap-1">
              <?php echo e(number_format($product->price, 0)); ?>

              <span class="text-xs font-semibold">฿</span>
            </div>
          <?php endif; ?>
        </div>
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl shrink-0
                     bg-gradient-to-br from-indigo-600 to-violet-600 text-white
                     shadow-md shadow-indigo-500/25
                     group-hover:scale-110 group-hover:shadow-lg group-hover:shadow-indigo-500/40
                     transition-all duration-200"
              aria-label="ดูรายละเอียด">
          <i class="bi bi-arrow-right"></i>
        </span>
      </div>
    </div>
  </a>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/public/products/_grid.blade.php ENDPATH**/ ?>