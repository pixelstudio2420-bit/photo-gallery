<?php $__env->startSection('title', '419 - เซสชันหมดอายุ'); ?>

<?php $__env->startSection('content'); ?>
<div class="min-h-[60vh] flex items-center justify-center px-4 py-12">
  <div class="max-w-md w-full text-center">
    <div class="mb-6 flex justify-center">
      <div class="relative">
        <div class="w-24 h-24 rounded-full bg-amber-100 dark:bg-amber-900/20 flex items-center justify-center">
          <i class="bi bi-clock-history text-5xl text-amber-500"></i>
        </div>
        <div class="absolute -top-1 -right-1 w-8 h-8 rounded-full bg-rose-500 text-white flex items-center justify-center text-sm font-bold shadow-lg">
          419
        </div>
      </div>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
      เซสชันหมดอายุ
    </h1>
    <p class="text-gray-600 dark:text-gray-400 mb-1">
      หน้านี้เปิดทิ้งไว้นานเกินไป — token ความปลอดภัยหมดอายุแล้ว
    </p>
    <p class="text-sm text-gray-500 dark:text-gray-500 mb-6">
      โปรดโหลดหน้าใหม่แล้วลองอีกครั้ง ข้อมูลของคุณยังปลอดภัย
    </p>

    <div class="flex flex-col sm:flex-row gap-2 justify-center">
      <button onclick="history.back()"
              class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold transition">
        <i class="bi bi-arrow-left"></i> ย้อนกลับแล้วลองใหม่
      </button>
      <a href="<?php echo e(url()->previous() ?: url('/')); ?>"
         class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 font-semibold transition">
        <i class="bi bi-arrow-clockwise"></i> โหลดหน้านี้ใหม่
      </a>
    </div>

    <p class="text-xs text-gray-400 dark:text-gray-600 mt-8">
      หากปัญหายังคงอยู่ กรุณาล้าง cookie หรือติดต่อทีมงาน
    </p>
  </div>
</div>

<script>
  // Auto-refresh token after 2 seconds if user stays on this page
  setTimeout(() => {
    fetch('<?php echo e(url('/sanctum/csrf-cookie')); ?>', { credentials: 'same-origin' }).catch(() => {});
  }, 2000);
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/errors/419.blade.php ENDPATH**/ ?>