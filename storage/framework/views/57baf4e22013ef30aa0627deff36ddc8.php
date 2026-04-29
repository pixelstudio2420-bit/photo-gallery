<?php $__env->startSection('title', 'จัดการสินค้าดิจิทัล'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex items-center justify-between mb-6">
  <div class="flex items-center gap-3">
    <div class="h-11 w-11 rounded-2xl bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 flex items-center justify-center">
      <i class="bi bi-box-seam text-xl"></i>
    </div>
    <div>
      <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">สินค้าดิจิทัล</h1>
      <p class="text-xs text-slate-500 dark:text-slate-400">Preset / Template / Overlay และสินค้าดิจิทัลอื่นๆ</p>
    </div>
  </div>
  <a href="<?php echo e(route('admin.products.create')); ?>"
     class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white px-4 py-2 text-sm font-medium shadow-sm transition-colors">
    <i class="bi bi-plus-lg"></i>
    <span>เพิ่มสินค้า</span>
  </a>
</div>

<?php if(session('success')): ?>
<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-200 px-4 py-3 text-sm flex items-start justify-between gap-3">
  <div class="flex items-start gap-2">
    <i class="bi bi-check-circle-fill mt-0.5"></i>
    <span><?php echo e(session('success')); ?></span>
  </div>
  <button type="button" class="text-emerald-600/80 hover:text-emerald-700 dark:text-emerald-300/80 dark:hover:text-emerald-200" onclick="this.parentElement.remove()" aria-label="Dismiss">
    <i class="bi bi-x-lg"></i>
  </button>
</div>
<?php endif; ?>


<div class="af-bar" x-data="adminFilter()">
  <form method="GET" action="<?php echo e(route('admin.products.index')); ?>">
    <div class="af-grid">
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="q" class="af-input" placeholder="ค้นหาชื่อหรือคำอธิบาย..." value="<?php echo e(request('q')); ?>">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทุกสถานะ</option>
          <option value="active" <?php echo e(request('status') === 'active' ? 'selected' : ''); ?>>Active</option>
          <option value="inactive" <?php echo e(request('status') === 'inactive' ? 'selected' : ''); ?>>Inactive</option>
          <option value="draft" <?php echo e(request('status') === 'draft' ? 'selected' : ''); ?>>Draft</option>
        </select>
      </div>
      <div>
        <label class="af-label">ประเภท</label>
        <select name="type" class="af-input">
          <option value="">ทุกประเภท</option>
          <option value="preset" <?php echo e(request('type') === 'preset' ? 'selected' : ''); ?>>Preset</option>
          <option value="template" <?php echo e(request('type') === 'template' ? 'selected' : ''); ?>>Template</option>
          <option value="overlay" <?php echo e(request('type') === 'overlay' ? 'selected' : ''); ?>>Overlay</option>
          <option value="other" <?php echo e(request('type') === 'other' ? 'selected' : ''); ?>>Other</option>
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
  <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-white/10">
          <tr class="text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            <th class="px-5 py-3">ID</th>
            <th class="px-4 py-3">ชื่อ</th>
            <th class="px-4 py-3">ราคา</th>
            <th class="px-4 py-3">ยอดขาย</th>
            <th class="px-4 py-3">สถานะ</th>
            <th class="px-4 py-3 text-right">จัดการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
          <?php $__empty_1 = true; $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <?php
            $isActive = ($product->status ?? 'active') === 'active';
          ?>
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 font-mono text-xs">#<?php echo e($product->id); ?></td>
            <td class="px-4 py-3">
              <span class="font-medium text-slate-900 dark:text-slate-100"><?php echo e($product->name); ?></span>
            </td>
            <td class="px-4 py-3">
              <span class="font-semibold text-indigo-600 dark:text-indigo-300"><?php echo e(number_format($product->price, 0)); ?> THB</span>
              <?php if($product->sale_price): ?>
              <div class="text-xs text-slate-400 dark:text-slate-500 line-through mt-0.5"><?php echo e(number_format($product->sale_price, 0)); ?> THB</div>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 px-2.5 py-1 text-xs font-medium">
                <?php echo e($product->total_sales ?? 0); ?>

              </span>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                <?php if($isActive): ?>
                  bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300
                <?php else: ?>
                  bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300
                <?php endif; ?>">
                <?php echo e(ucfirst($product->status ?? 'active')); ?>

              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex justify-end gap-1.5">
                <a href="<?php echo e(route('admin.products.show', $product->id)); ?>"
                   class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-300 hover:bg-sky-100 dark:hover:bg-sky-500/20 transition-colors"
                   title="ดู">
                  <i class="bi bi-eye text-sm"></i>
                </a>
                <a href="<?php echo e(route('admin.products.edit', $product->id)); ?>"
                   class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/20 transition-colors"
                   title="แก้ไข">
                  <i class="bi bi-pencil text-sm"></i>
                </a>
                <form method="POST" action="<?php echo e(route('admin.products.destroy', $product->id)); ?>" onsubmit="return confirm('ต้องการลบสินค้านี้?')">
                  <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                  <button type="submit"
                          class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-300 hover:bg-red-100 dark:hover:bg-red-500/20 transition-colors"
                          title="ลบ">
                    <i class="bi bi-trash3 text-sm"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="6" class="px-4 py-16 text-center">
              <div class="flex flex-col items-center gap-3">
                <div class="h-14 w-14 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 flex items-center justify-center">
                  <i class="bi bi-box-seam text-2xl"></i>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">ไม่พบข้อมูลสินค้า</p>
              </div>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="admin-pagination-area">
<?php if($products->hasPages()): ?>
<div class="flex justify-center mt-6"><?php echo e($products->withQueryString()->links()); ?></div>
<?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/products/index.blade.php ENDPATH**/ ?>