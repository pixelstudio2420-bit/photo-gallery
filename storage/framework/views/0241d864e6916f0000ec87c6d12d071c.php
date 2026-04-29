
<aside class="space-y-6">

  
  <?php if(isset($popularPosts) && $popularPosts->count()): ?>
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl p-6 shadow-sm">
    <h3 class="font-bold text-base text-slate-800 dark:text-gray-100 mb-5 flex items-center gap-2">
      <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-md shadow-amber-500/25">
        <i class="bi bi-fire text-white text-sm"></i>
      </span>
      บทความยอดนิยม
    </h3>
    <div class="space-y-4">
      <?php $__currentLoopData = $popularPosts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $popPost): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <a href="<?php echo e(route('blog.show', $popPost->slug)); ?>" class="flex items-start gap-3 group">
        <div class="relative w-16 h-16 rounded-xl overflow-hidden shrink-0 shadow-sm">
          <?php if($popPost->featured_image): ?>
            <img src="<?php echo e(asset('storage/' . $popPost->featured_image)); ?>" alt="<?php echo e($popPost->title); ?>" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110" loading="lazy">
          <?php else: ?>
            <div class="w-full h-full bg-gradient-to-br from-indigo-100 to-violet-100 dark:from-indigo-900/40 dark:to-violet-900/40 flex items-center justify-center">
              <i class="bi bi-newspaper text-indigo-300 dark:text-indigo-500"></i>
            </div>
          <?php endif; ?>
          <div class="absolute top-0 left-0 m-1 w-5 h-5 rounded-md bg-gradient-to-br from-indigo-500 to-violet-600 text-white text-xs font-bold flex items-center justify-center shadow-md">
            <?php echo e($i + 1); ?>

          </div>
        </div>
        <div class="flex-1 min-w-0">
          <h4 class="text-sm font-semibold text-slate-800 dark:text-gray-200 line-clamp-2 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition-colors leading-snug"><?php echo e($popPost->title); ?></h4>
          <span class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1 mt-1">
            <i class="bi bi-eye"></i> <?php echo e(number_format($popPost->view_count)); ?>

          </span>
        </div>
      </a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>
  <?php endif; ?>

  
  <?php if(isset($categories) && $categories->count()): ?>
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl p-6 shadow-sm">
    <h3 class="font-bold text-base text-slate-800 dark:text-gray-100 mb-5 flex items-center gap-2">
      <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-md shadow-indigo-500/25">
        <i class="bi bi-folder text-white text-sm"></i>
      </span>
      หมวดหมู่
    </h3>
    <ul class="space-y-1">
      <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <li>
        <a href="<?php echo e(route('blog.category', $cat->slug)); ?>"
           class="flex items-center justify-between px-3 py-2 rounded-xl text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-white/5 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors group">
          <span class="flex items-center gap-2">
            <?php if($cat->icon): ?><i class="<?php echo e($cat->icon); ?> text-gray-400 dark:text-gray-500 group-hover:text-indigo-500 dark:group-hover:text-indigo-300 transition-colors"></i><?php else: ?><i class="bi bi-folder2 text-gray-400 dark:text-gray-500 group-hover:text-indigo-500 dark:group-hover:text-indigo-300 transition-colors"></i><?php endif; ?>
            <?php echo e($cat->name); ?>

          </span>
          <span class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-white/5 px-2 py-0.5 rounded-full group-hover:bg-indigo-100 dark:group-hover:bg-indigo-500/20 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition-colors">
            <?php echo e($cat->post_count ?? $cat->posts_count ?? 0); ?>

          </span>
        </a>
      </li>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
  </div>
  <?php endif; ?>

  
  <?php if(isset($tags) && $tags->count()): ?>
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl p-6 shadow-sm">
    <h3 class="font-bold text-base text-slate-800 dark:text-gray-100 mb-5 flex items-center gap-2">
      <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center shadow-md shadow-cyan-500/25">
        <i class="bi bi-tags text-white text-sm"></i>
      </span>
      แท็กยอดนิยม
    </h3>
    <div class="flex flex-wrap gap-2">
      <?php $__currentLoopData = $tags; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <a href="<?php echo e(route('blog.tag', $tag->slug)); ?>"
         class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium bg-gray-50 dark:bg-white/5 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-white/10 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:text-indigo-600 dark:hover:text-indigo-300 hover:border-indigo-200 dark:hover:border-indigo-400/30 hover:scale-105 transition-all">
        <i class="bi bi-hash"></i><?php echo e($tag->name); ?>

        <?php if($tag->post_count > 0): ?>
          <span class="text-gray-400 dark:text-gray-500">(<?php echo e($tag->post_count); ?>)</span>
        <?php endif; ?>
      </a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>
  <?php endif; ?>

  
  <?php if(isset($sidebarCta) && $sidebarCta): ?>
  <div class="bg-gradient-to-br from-indigo-600 via-violet-600 to-purple-700 rounded-2xl p-6 text-white relative overflow-hidden shadow-xl shadow-indigo-500/20">
    <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(circle at 80% 20%,rgba(255,255,255,0.1) 0%,transparent 50%)"></div>
    <div class="relative">
      <?php if($sidebarCta->affiliateLink && $sidebarCta->affiliateLink->image): ?>
        <img src="<?php echo e(asset('storage/' . $sidebarCta->affiliateLink->image)); ?>" alt="<?php echo e($sidebarCta->name); ?>" class="w-full rounded-xl mb-3 shadow-lg">
      <?php endif; ?>
      <h4 class="font-bold text-base mb-2"><?php echo e($sidebarCta->label ?? 'ข้อเสนอพิเศษ'); ?></h4>
      <?php if($sidebarCta->sub_label): ?>
        <p class="text-white/80 text-sm mb-4"><?php echo e($sidebarCta->sub_label); ?></p>
      <?php endif; ?>
      <a href="<?php echo e($sidebarCta->url ?? ($sidebarCta->affiliateLink ? $sidebarCta->affiliateLink->getCloakedUrl() : '#')); ?>"
         rel="nofollow noopener sponsored"
         target="_blank"
         class="block w-full text-center py-3 px-4 bg-white text-indigo-700 rounded-xl font-bold text-sm hover:bg-gray-50 hover:scale-[1.02] transition-all shadow-lg"
         data-cta-id="<?php echo e($sidebarCta->id); ?>"
         onclick="trackCtaClick(<?php echo e($sidebarCta->id); ?>)">
        <?php echo e($sidebarCta->icon ?? ''); ?> คลิกดูรายละเอียด <i class="bi bi-arrow-right"></i>
      </a>
      <p class="text-white/50 text-xs mt-2 text-center">* ลิงก์ affiliate</p>
    </div>
  </div>
  <?php endif; ?>

  
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl p-6 shadow-sm">
    <h3 class="font-bold text-base text-slate-800 dark:text-gray-100 mb-3 flex items-center gap-2">
      <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center shadow-md shadow-orange-500/25">
        <i class="bi bi-rss text-white text-sm"></i>
      </span>
      ติดตามบทความ
    </h3>
    <p class="text-gray-600 dark:text-gray-400 text-xs mb-3">รับข่าวสารบทความใหม่ผ่าน RSS Feed</p>
    <a href="<?php echo e(route('blog.feed')); ?>"
       target="_blank"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-gradient-to-br from-orange-500 to-red-500 hover:shadow-lg hover:shadow-orange-500/30 transition-all">
      <i class="bi bi-rss"></i> RSS Feed
    </a>
  </div>

</aside>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/public/blog/_sidebar.blade.php ENDPATH**/ ?>