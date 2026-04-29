<?php $__env->startSection('title', 'เพิ่ม Alert Rule'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-bell-fill text-rose-500"></i>
        เพิ่ม Alert Rule
    </h4>
    <a href="<?php echo e(route('admin.alerts.index')); ?>" class="text-sm text-gray-500 dark:text-gray-400 hover:text-indigo-500">
        <i class="bi bi-arrow-left mr-1"></i>กลับ
    </a>
</div>

<form action="<?php echo e(route('admin.alerts.store')); ?>" method="POST"
      class="bg-white dark:bg-slate-800 rounded-xl p-6 border border-gray-100 dark:border-white/5">
    <?php echo csrf_field(); ?>
    <?php echo $__env->make('admin.alerts._form', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/alerts/create.blade.php ENDPATH**/ ?>