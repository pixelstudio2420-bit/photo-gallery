<?php $__env->startSection('title', 'จัดการหมวดหมู่บล็อก'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="categoryManager()">

    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white">
                <i class="bi bi-folder2-open text-amber-500 mr-2"></i>จัดการหมวดหมู่
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">จัดการหมวดหมู่สำหรับบทความในบล็อก</p>
        </div>
        <a href="<?php echo e(route('admin.blog.categories.create')); ?>"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-medium text-sm transition-colors shadow-lg shadow-indigo-500/25">
            <i class="bi bi-plus-lg"></i>เพิ่มหมวดหมู่
        </a>
    </div>

    
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-5 mb-6"
         x-data="{ showQuick: false }">
        <button type="button" @click="showQuick = !showQuick"
                class="flex items-center gap-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300">
            <i class="bi" :class="showQuick ? 'bi-dash-circle' : 'bi-plus-circle'"></i>
            <span x-text="showQuick ? 'ซ่อนฟอร์ม' : 'สร้างหมวดหมู่ด่วน'"></span>
        </button>
        <div x-show="showQuick" x-collapse x-cloak class="mt-4">
            <form method="POST" action="<?php echo e(route('admin.blog.categories.store')); ?>" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php echo csrf_field(); ?>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">ชื่อหมวดหมู่ <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required placeholder="ชื่อหมวดหมู่"
                           class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Slug</label>
                    <input type="text" name="slug" placeholder="auto-generated"
                           class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">สี</label>
                    <input type="color" name="color" value="#6366f1"
                           class="w-full h-[38px] border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 cursor-pointer">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-medium transition-colors">
                        <i class="bi bi-plus-lg mr-1"></i>สร้าง
                    </button>
                </div>
            </form>
        </div>
    </div>

    
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/80 dark:bg-slate-700/50">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">สี/ไอคอน</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">ชื่อ</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Slug</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">หมวดหมู่หลัก</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">บทความ</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">สถานะ</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                    <?php $__empty_1 = true; $__currentLoopData = $categories ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition-colors"
                        x-data="{ editing: false }">
                        
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                                     style="background-color: <?php echo e($category->color ?? '#6366f1'); ?>20;">
                                    <i class="bi bi-<?php echo e($category->icon ?? 'folder'); ?> text-lg"
                                       style="color: <?php echo e($category->color ?? '#6366f1'); ?>;"></i>
                                </div>
                            </div>
                        </td>

                        
                        <td class="px-4 py-3">
                            <template x-if="!editing">
                                <span class="font-semibold text-slate-800 dark:text-white"><?php echo e($category->name); ?></span>
                            </template>
                            <template x-if="editing">
                                <form method="POST" action="<?php echo e(route('admin.blog.categories.update', $category)); ?>" class="flex items-center gap-2">
                                    <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
                                    <input type="text" name="name" value="<?php echo e($category->name); ?>"
                                           class="text-sm px-2 py-1 border border-indigo-300 rounded-lg bg-white dark:bg-slate-700 dark:text-white focus:ring-2 focus:ring-indigo-500 w-40">
                                    <button type="submit" class="text-emerald-500 hover:text-emerald-700"><i class="bi bi-check-lg"></i></button>
                                    <button type="button" @click="editing = false" class="text-gray-400 hover:text-red-500"><i class="bi bi-x-lg"></i></button>
                                </form>
                            </template>
                        </td>

                        
                        <td class="px-4 py-3">
                            <code class="text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-slate-700 px-2 py-1 rounded"><?php echo e($category->slug); ?></code>
                        </td>

                        
                        <td class="px-4 py-3 text-center">
                            <?php if($category->parent): ?>
                                <span class="text-xs text-gray-600 dark:text-gray-300"><?php echo e($category->parent->name); ?></span>
                            <?php else: ?>
                                <span class="text-xs text-gray-400">-</span>
                            <?php endif; ?>
                        </td>

                        
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-sm font-bold">
                                <?php echo e($category->posts_count ?? 0); ?>

                            </span>
                        </td>

                        
                        <td class="px-4 py-3 text-center">
                            <?php if($category->is_active): ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400">
                                    <i class="bi bi-check-circle-fill mr-1"></i>ใช้งาน
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-500/20 dark:text-gray-400">
                                    <i class="bi bi-x-circle mr-1"></i>ปิดใช้งาน
                                </span>
                            <?php endif; ?>
                        </td>

                        
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button @click="editing = !editing" title="แก้ไขชื่อ"
                                        class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-500 flex items-center justify-center hover:bg-indigo-100 dark:hover:bg-indigo-500/20 transition-colors">
                                    <i class="bi bi-pencil text-sm"></i>
                                </button>
                                <a href="<?php echo e(route('admin.blog.categories.edit', $category)); ?>" title="แก้ไขทั้งหมด"
                                   class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-500/10 text-amber-500 flex items-center justify-center hover:bg-amber-100 dark:hover:bg-amber-500/20 transition-colors">
                                    <i class="bi bi-gear text-sm"></i>
                                </a>
                                <button @click="deleteCategory(<?php echo e($category->id); ?>, '<?php echo e($category->name); ?>')" title="ลบ"
                                        class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-500/10 text-red-500 flex items-center justify-center hover:bg-red-100 dark:hover:bg-red-500/20 transition-colors">
                                    <i class="bi bi-trash text-sm"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-3">
                                    <i class="bi bi-folder text-2xl text-gray-400"></i>
                                </div>
                                <p class="text-gray-500 dark:text-gray-400 font-medium">ยังไม่มีหมวดหมู่</p>
                                <p class="text-sm text-gray-400 mt-1">เริ่มต้นสร้างหมวดหมู่แรก</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if(isset($categories) && $categories instanceof \Illuminate\Pagination\LengthAwarePaginator && $categories->hasPages()): ?>
        <div class="px-4 py-3 border-t border-gray-100 dark:border-white/[0.06]">
            <?php echo e($categories->links()); ?>

        </div>
        <?php endif; ?>
    </div>

    <form id="deleteCatForm" method="POST" class="hidden"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?></form>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
function categoryManager() {
    return {
        deleteCategory(id, name) {
            Swal.fire({
                title: 'ลบหมวดหมู่?',
                html: `คุณต้องการลบหมวดหมู่ <strong>${name}</strong>?<br><small class="text-gray-500">บทความในหมวดหมู่นี้จะไม่ถูกลบ</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ลบเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('deleteCatForm');
                    form.action = `<?php echo e(url('admin/blog/categories')); ?>/${id}`;
                    form.submit();
                }
            });
        }
    };
}
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/blog/categories/index.blade.php ENDPATH**/ ?>