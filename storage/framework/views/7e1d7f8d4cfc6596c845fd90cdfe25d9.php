<?php $__empty_1 = true; $__currentLoopData = $events ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<div class="event-card-wrap" data-event-id="<?php echo e($event->id); ?>">
  
  <a href="<?php echo e(route('events.show', $event->slug ?: $event->id)); ?>"
     class="block h-full no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900 rounded-2xl"
     aria-label="ดูรายละเอียดอีเวนต์ <?php echo e($event->name); ?>">
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl overflow-hidden h-full flex flex-col event-card transition-all duration-300 group cursor-pointer">
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
        <div class="absolute bottom-0 left-0 w-full h-[60%] bg-gradient-to-t from-black/60 to-transparent z-[3]"></div>
        <?php if($event->category): ?>
        <span class="absolute top-0 left-0 m-2 inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold text-white bg-gradient-to-br from-indigo-500 to-violet-600 z-[4] shadow-md"><?php echo e($event->category->name); ?></span>
        <?php endif; ?>
        <?php if($event->is_free): ?>
        <span class="absolute top-0 right-0 m-2 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold text-white bg-gradient-to-br from-emerald-400 to-emerald-600 z-[4] shadow-lg shadow-emerald-500/30">
          <i class="bi bi-gift-fill mr-1"></i>
        </span>
        <?php endif; ?>
        
        <div class="absolute inset-0 z-[5] flex items-center justify-center bg-indigo-600/0 group-hover:bg-indigo-900/40 transition-all duration-300 opacity-0 group-hover:opacity-100 pointer-events-none">
          <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-white/95 text-indigo-700 text-xs font-bold shadow-xl scale-90 group-hover:scale-100 transition-transform duration-300">
            <i class="bi bi-images"></i> ดูภาพถ่าย
          </span>
        </div>
      </div>
      <div class="p-5 flex-1">
        <h6 class="font-semibold mb-2 leading-relaxed text-[0.95rem] text-slate-800 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition-colors"><?php echo e($event->name); ?></h6>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-gray-500 dark:text-gray-400 text-xs">
          <?php if($event->shoot_date): ?>
          <span><i class="bi bi-calendar3 mr-1"></i><?php echo e($event->shoot_date->format('d/m/Y')); ?></span>
          <?php endif; ?>
          <?php if($event->province): ?>
          <span><i class="bi bi-geo-alt mr-1"></i><?php echo e($event->province->name_th); ?></span>
          <?php elseif($event->location): ?>
          <span><i class="bi bi-geo-alt mr-1"></i><?php echo e(Str::limit($event->location, 20)); ?></span>
          <?php endif; ?>
          <?php if($event->view_count > 0): ?>
          <span><i class="bi bi-eye mr-1"></i><?php echo e(number_format($event->view_count)); ?></span>
          <?php endif; ?>
        </div>
        <div class="mt-3">
          <?php if($event->price_per_photo > 0): ?>
          <span class="inline-flex items-center gap-1 font-bold text-indigo-600 dark:text-indigo-300 text-sm">
            <i class="bi bi-tag-fill text-xs opacity-70"></i><?php echo e(number_format($event->price_per_photo, 0)); ?> ฿<span class="text-gray-500 dark:text-gray-400 font-normal text-xs">/ภาพ</span>
          </span>
          <?php elseif($event->is_free): ?>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
            <i class="bi bi-gift mr-1"></i> ฟรี
          </span>
          <?php endif; ?>
        </div>
      </div>
      <div class="px-5 pb-5">
        
        <span class="block w-full text-center text-sm bg-gradient-to-br from-indigo-500 to-violet-600 text-white rounded-xl font-semibold py-2.5 group-hover:shadow-lg group-hover:shadow-indigo-500/30 transition-all duration-200">
          <i class="bi bi-images mr-1"></i> ดูภาพถ่าย <i class="bi bi-arrow-right ml-1 transition-transform duration-200 group-hover:translate-x-1"></i>
        </span>
      </div>
    </div>
  </a>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<div class="col-span-full">
  <div class="text-center py-20 px-6 rounded-3xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10">
    <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-gradient-to-br from-gray-100 to-gray-50 dark:from-slate-700 dark:to-slate-800 mb-5 shadow-inner">
      <i class="bi bi-search text-5xl text-gray-300 dark:text-slate-500"></i>
    </div>
    <p class="text-slate-700 dark:text-gray-100 font-bold mb-1 text-lg">ไม่พบอีเวนต์ที่ตรงกับการค้นหา</p>
    <p class="text-gray-500 dark:text-gray-400 text-sm">ลองเปลี่ยนคำค้นหาหรือตัวกรอง</p>
  </div>
</div>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/public/events/_grid.blade.php ENDPATH**/ ?>