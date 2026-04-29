<?php $__env->startSection('title', 'เพิ่มสินค้าดิจิทัล'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('admin.products._form', ['product' => null, 'mode' => 'create'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/products/create.blade.php ENDPATH**/ ?>