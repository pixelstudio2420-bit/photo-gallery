<?php $__env->startSection('title', '404 - Page Not Found'); ?>

<?php $__env->startSection('content'); ?>
<div class="text-center py-20">
  <div class="mb-4">
    <i class="bi bi-exclamation-triangle text-6xl text-amber-500"></i>
  </div>
  <h1 class="text-4xl font-bold">404</h1>
  <p class="text-lg text-gray-500 mb-4">Page not found</p>
  <a href="<?php echo e(url('/')); ?>" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-3 rounded-lg transition">
    <i class="bi bi-house mr-1"></i> Back to Home
  </a>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/errors/404.blade.php ENDPATH**/ ?>