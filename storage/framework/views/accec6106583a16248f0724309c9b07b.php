<?php $__env->startSection('title', 'จัดการหมวดหมู่'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0 tracking-tight">
    <i class="bi bi-grid-3x3-gap mr-2 text-indigo-500"></i>จัดการหมวดหมู่
  </h4>
  <a href="<?php echo e(route('admin.categories.create')); ?>" class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-5 py-2 inline-flex items-center gap-1 transition hover:from-indigo-600 hover:to-indigo-700">
    <i class="bi bi-plus-lg mr-1"></i> เพิ่มหมวดหมู่
  </a>
</div>

<?php if(session('success')): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-4 mb-4 flex items-center justify-between" x-data="{ show: true }" x-show="show">
  <span><?php echo e(session('success')); ?></span>
  <button type="button" @click="show = false" class="text-green-500 hover:text-green-700"><i class="bi bi-x-lg"></i></button>
</div>
<?php endif; ?>


<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="<?php echo e(route('admin.categories.index')); ?>">
    <div class="af-grid">
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="q" class="af-input" placeholder="ค้นหาชื่อหมวดหมู่..." value="<?php echo e(request('q')); ?>">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทุกสถานะ</option>
          <option value="active" <?php echo e(request('status') === 'active' ? 'selected' : ''); ?>>Active</option>
          <option value="inactive" <?php echo e(request('status') === 'inactive' ? 'selected' : ''); ?>>Inactive</option>
        </select>
      </div>
      <div class="af-actions">
        <button type="button" class="af-btn-clear" @click="clearFilters()">
          <i class="bi bi-x-lg mr-1"></i>ล้าง
        </button>
      </div>
    </div>
  </form>
</div>

<div id="admin-table-area">
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="p-0">
    <div class="overflow-x-auto">
      <table class="w-full text-sm [&_tbody_tr]:hover:bg-gray-50">
        <thead class="bg-gray-50/80">
          <tr>
            <th class="pl-4 px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ชื่อ</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Slug</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">สถานะ</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td class="pl-4 px-4 py-3 text-gray-500"><?php echo e($category->id); ?></td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <?php if($category->icon): ?>
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-indigo-500 text-xs">
                  <i class="bi <?php echo e($category->icon); ?>"></i>
                </div>
                <?php endif; ?>
                <span class="font-medium"><?php echo e($category->name); ?></span>
              </div>
            </td>
            <td class="px-4 py-3 text-gray-500"><?php echo e($category->slug); ?></td>
            <td class="px-4 py-3">
              <?php $isActive = ($category->status ?? 'active') === 'active'; ?>
              <span class="inline-block text-xs font-medium px-2.5 py-0.5 rounded-full" style="background:<?php echo e($isActive ? 'rgba(16,185,129,0.1)' : 'rgba(107,114,128,0.1)'); ?>;color:<?php echo e($isActive ? '#10b981' : '#6b7280'); ?>;">
                <?php echo e($isActive ? 'Active' : 'Inactive'); ?>

              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-1">
                <a href="<?php echo e(route('admin.categories.edit', $category->id)); ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-indigo-500 hover:bg-indigo-100 transition" title="แก้ไข">
                  <i class="bi bi-pencil text-xs"></i>
                </a>
                <form method="POST" action="<?php echo e(route('admin.categories.destroy', $category->id)); ?>" onsubmit="return confirm('ต้องการลบหมวดหมู่นี้?')">
                  <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                  <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 transition" title="ลบ">
                    <i class="bi bi-trash3 text-xs"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="5" class="text-center py-12">
              <i class="bi bi-grid-3x3-gap text-4xl text-gray-300"></i>
              <p class="text-gray-500 mt-2 mb-0 text-sm">ไม่พบข้อมูล</p>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>

<div id="admin-pagination-area">
<?php if($categories->hasPages()): ?>
<div class="flex justify-center mt-4"><?php echo e($categories->withQueryString()->links()); ?></div>
<?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/categories/index.blade.php ENDPATH**/ ?>