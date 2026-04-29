<?php if(isset($posts) && $posts instanceof \Illuminate\Pagination\LengthAwarePaginator && $posts->hasPages()): ?>
<nav aria-label="Blog pagination" class="flex justify-center">
  <ul class="inline-flex items-center gap-1">
    
    <?php if($posts->onFirstPage()): ?>
      <li>
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-gray-300 dark:text-gray-600 cursor-not-allowed">
          <i class="bi bi-chevron-left"></i>
        </span>
      </li>
    <?php else: ?>
      <li>
        <a href="<?php echo e($posts->previousPageUrl()); ?>"
           class="blog-page-link inline-flex items-center justify-center w-10 h-10 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-white/5 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors"
           aria-label="หน้าก่อนหน้า">
          <i class="bi bi-chevron-left"></i>
        </a>
      </li>
    <?php endif; ?>

    
    <?php
      $current = $posts->currentPage();
      $last = $posts->lastPage();
      $start = max(1, $current - 2);
      $end = min($last, $current + 2);
    ?>

    <?php if($start > 1): ?>
      <li>
        <a href="<?php echo e($posts->url(1)); ?>" class="blog-page-link inline-flex items-center justify-center w-10 h-10 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-white/5 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors">1</a>
      </li>
      <?php if($start > 2): ?>
        <li><span class="inline-flex items-center justify-center w-8 h-10 text-gray-400 dark:text-gray-500 text-sm">...</span></li>
      <?php endif; ?>
    <?php endif; ?>

    <?php for($i = $start; $i <= $end; $i++): ?>
      <li>
        <?php if($i == $current): ?>
          <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-sm font-bold bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg shadow-indigo-500/30"><?php echo e($i); ?></span>
        <?php else: ?>
          <a href="<?php echo e($posts->url($i)); ?>" class="blog-page-link inline-flex items-center justify-center w-10 h-10 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-white/5 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors"><?php echo e($i); ?></a>
        <?php endif; ?>
      </li>
    <?php endfor; ?>

    <?php if($end < $last): ?>
      <?php if($end < $last - 1): ?>
        <li><span class="inline-flex items-center justify-center w-8 h-10 text-gray-400 dark:text-gray-500 text-sm">...</span></li>
      <?php endif; ?>
      <li>
        <a href="<?php echo e($posts->url($last)); ?>" class="blog-page-link inline-flex items-center justify-center w-10 h-10 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-white/5 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors"><?php echo e($last); ?></a>
      </li>
    <?php endif; ?>

    
    <?php if($posts->hasMorePages()): ?>
      <li>
        <a href="<?php echo e($posts->nextPageUrl()); ?>"
           class="blog-page-link inline-flex items-center justify-center w-10 h-10 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-white/5 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors"
           aria-label="หน้าถัดไป">
          <i class="bi bi-chevron-right"></i>
        </a>
      </li>
    <?php else: ?>
      <li>
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-gray-300 dark:text-gray-600 cursor-not-allowed">
          <i class="bi bi-chevron-right"></i>
        </span>
      </li>
    <?php endif; ?>
  </ul>
</nav>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/public/blog/_pagination.blade.php ENDPATH**/ ?>