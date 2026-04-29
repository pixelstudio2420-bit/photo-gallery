<?php $__env->startSection('title', 'สร้างหมวดหมู่ใหม่'); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-6">
    <div class="flex items-center gap-3 mb-2">
        <a href="<?php echo e(route('admin.blog.categories.index')); ?>"
           class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white">
            <i class="bi bi-plus-circle text-amber-500 mr-2"></i>สร้างหมวดหมู่ใหม่
        </h2>
    </div>
</div>

<?php echo $__env->make('admin.blog.categories._form', ['category' => (object) [
    'exists' => false, 'name' => '', 'slug' => '', 'description' => '', 'parent_id' => '',
    'icon' => 'folder', 'color' => '#6366f1', 'is_active' => true, 'sort_order' => 0,
    'meta_title' => '', 'meta_description' => '',
]], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/blog/categories/create.blade.php ENDPATH**/ ?>