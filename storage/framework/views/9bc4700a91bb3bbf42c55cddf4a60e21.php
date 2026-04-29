
<?php $isEdit = isset($category) && $category->exists; ?>

<div x-data="categoryForm()">
    <form method="POST"
          action="<?php echo e($isEdit ? route('admin.blog.categories.update', $category) : route('admin.blog.categories.store')); ?>"
          enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <?php if($isEdit): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="space-y-6">
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-6 space-y-4">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2 mb-4">
                        <i class="bi bi-info-circle text-indigo-500"></i>ข้อมูลทั่วไป
                    </h3>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">ชื่อหมวดหมู่ <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="<?php echo e(old('name', $category->name ?? '')); ?>" required
                               x-model="name" @input="autoSlug()"
                               placeholder="ชื่อหมวดหมู่"
                               class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Slug</label>
                        <input type="text" name="slug" value="<?php echo e(old('slug', $category->slug ?? '')); ?>"
                               x-model="slug" placeholder="auto-generated"
                               class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500 font-mono">
                        <?php $__errorArgs = ['slug'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">คำอธิบาย</label>
                        <textarea name="description" rows="3" placeholder="คำอธิบายสั้นๆ ของหมวดหมู่..."
                                  class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500 resize-none"
                                  ><?php echo e(old('description', $category->description ?? '')); ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">หมวดหมู่หลัก</label>
                        <select name="parent_id"
                                class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                            <option value="">-- ไม่มี (หมวดหมู่หลัก) --</option>
                            <?php $__currentLoopData = $parentCategories ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $parent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php if(!$isEdit || $parent->id !== $category->id): ?>
                                    <option value="<?php echo e($parent->id); ?>" <?php echo e(old('parent_id', $category->parent_id ?? '') == $parent->id ? 'selected' : ''); ?>>
                                        <?php echo e($parent->name); ?>

                                    </option>
                                <?php endif; ?>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                </div>

                
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-6 space-y-4">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2 mb-4">
                        <i class="bi bi-search text-emerald-500"></i>SEO
                    </h3>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Meta Title</label>
                        <input type="text" name="meta_title" value="<?php echo e(old('meta_title', $category->meta_title ?? '')); ?>"
                               maxlength="60" placeholder="หัวข้อ SEO..."
                               class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Meta Description</label>
                        <textarea name="meta_description" rows="2" maxlength="160" placeholder="คำอธิบาย SEO..."
                                  class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500 resize-none"
                                  ><?php echo e(old('meta_description', $category->meta_description ?? '')); ?></textarea>
                    </div>
                </div>
            </div>

            
            <div class="space-y-6">
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-6 space-y-4">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2 mb-4">
                        <i class="bi bi-palette text-pink-500"></i>การแสดงผล
                    </h3>

                    
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">ไอคอน</label>
                        <div class="relative">
                            <input type="text" name="icon" x-model="selectedIcon" placeholder="เช่น folder, camera, tag..."
                                   value="<?php echo e(old('icon', $category->icon ?? '')); ?>"
                                   class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500 pl-10">
                            <div class="absolute left-3 top-1/2 -translate-y-1/2">
                                <i class="bi" :class="'bi-' + (selectedIcon || 'folder')" style="color: var(--icon-color, #6366f1)"></i>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <template x-for="icon in commonIcons">
                                <button type="button" @click="selectedIcon = icon"
                                        :class="selectedIcon === icon ? 'bg-indigo-100 text-indigo-600 ring-1 ring-indigo-300 dark:bg-indigo-500/20 dark:text-indigo-400' : 'bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-gray-400'"
                                        class="w-8 h-8 rounded-lg flex items-center justify-center hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
                                    <i class="bi" :class="'bi-' + icon"></i>
                                </button>
                            </template>
                        </div>
                    </div>

                    
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">สี</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="color" x-model="selectedColor"
                                   value="<?php echo e(old('color', $category->color ?? '#6366f1')); ?>"
                                   class="w-12 h-10 border border-gray-200 dark:border-white/10 rounded-lg cursor-pointer">
                            <input type="text" x-model="selectedColor" readonly
                                   class="w-28 text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg bg-gray-50 dark:bg-slate-700/50 dark:text-white font-mono text-center">
                            <div class="flex gap-1.5">
                                <template x-for="color in presetColors">
                                    <button type="button" @click="selectedColor = color"
                                            :style="'background-color:' + color"
                                            :class="selectedColor === color ? 'ring-2 ring-offset-2 ring-gray-400 dark:ring-offset-slate-800' : ''"
                                            class="w-7 h-7 rounded-lg transition-all"></button>
                                </template>
                            </div>
                        </div>
                    </div>

                    
                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-xl p-4">
                        <p class="text-xs text-gray-400 mb-2">ตัวอย่าง:</p>
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium"
                              :style="'background-color:' + selectedColor + '20; color:' + selectedColor">
                            <i class="bi mr-1.5" :class="'bi-' + (selectedIcon || 'folder')"></i>
                            <span x-text="name || 'ชื่อหมวดหมู่'"></span>
                        </span>
                    </div>
                </div>

                
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-6 space-y-4">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2 mb-4">
                        <i class="bi bi-gear text-gray-500"></i>ตั้งค่า
                    </h3>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">ลำดับการแสดงผล</label>
                        <input type="number" name="sort_order" value="<?php echo e(old('sort_order', $category->sort_order ?? 0)); ?>" min="0"
                               class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1"
                               <?php echo e(old('is_active', $category->is_active ?? true) ? 'checked' : ''); ?>

                               class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <div>
                            <span class="text-sm font-medium text-slate-700 dark:text-gray-200">เปิดใช้งาน</span>
                            <p class="text-xs text-gray-400">แสดงหมวดหมู่นี้บนเว็บไซต์</p>
                        </div>
                    </label>
                </div>

                
                <div class="flex items-center gap-3">
                    <a href="<?php echo e(route('admin.blog.categories.index')); ?>"
                       class="flex-1 px-4 py-2.5 text-center text-sm font-medium bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
                        ยกเลิก
                    </a>
                    <button type="submit"
                            class="flex-1 px-4 py-2.5 text-sm font-medium bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-500/25">
                        <i class="bi bi-check-lg mr-1"></i><?php echo e($isEdit ? 'อัปเดต' : 'สร้างหมวดหมู่'); ?>

                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
function categoryForm() {
    return {
        name: <?php echo json_encode(old('name', $category->name ?? ''), 512) ?>,
        slug: <?php echo json_encode(old('slug', $category->slug ?? ''), 512) ?>,
        selectedIcon: <?php echo json_encode(old('icon', $category->icon ?? 'folder'), 512) ?>,
        selectedColor: <?php echo json_encode(old('color', $category->color ?? '#6366f1'), 512) ?>,
        commonIcons: ['folder', 'camera', 'tag', 'star', 'heart', 'lightning', 'gear', 'book', 'globe', 'laptop', 'phone', 'music-note', 'palette', 'cart', 'trophy', 'briefcase', 'house', 'chat', 'film', 'controller'],
        presetColors: ['#ef4444', '#f97316', '#eab308', '#22c55e', '#06b6d4', '#3b82f6', '#6366f1', '#a855f7', '#ec4899'],

        autoSlug() {
            if (!this.slug || this.slug === '') {
                this.slug = this.name.toLowerCase()
                    .replace(/[^\u0E00-\u0E7Fa-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');
            }
        }
    };
}
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/blog/categories/_form.blade.php ENDPATH**/ ?>