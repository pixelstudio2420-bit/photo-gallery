<?php
    /** @var \App\Models\AlertRule|null $rule */
    $rule = $rule ?? null;
    $isEdit = (bool) $rule;
    $selectedChannels = old('channels', $rule?->channels ?? ['admin']);
?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1 dark:text-gray-200">ชื่อ Rule <span class="text-rose-500">*</span></label>
        <input type="text" name="name" value="<?php echo e(old('name', $rule?->name)); ?>" required
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
        <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-xs text-rose-500 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1 dark:text-gray-200">คำอธิบาย</label>
        <input type="text" name="description" value="<?php echo e(old('description', $rule?->description)); ?>"
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm"
               placeholder="บันทึกช่วยจำสั้นๆ ว่า rule นี้ทำอะไร">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1 dark:text-gray-200">Metric <span class="text-rose-500">*</span></label>
        <select name="metric" required
                class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
            <?php $__currentLoopData = $metrics; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($key); ?>" <?php if(old('metric', $rule?->metric) === $key): echo 'selected'; endif; ?>>
                    <?php echo e($m['label']); ?> (<?php echo e($m['unit']); ?>)
                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <p class="text-[11px] text-gray-400 mt-1">ค่าที่เราจะอ่านมาเทียบกับ threshold</p>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1 dark:text-gray-200">Severity <span class="text-rose-500">*</span></label>
        <select name="severity" required
                class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
            <?php $__currentLoopData = $severities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($key); ?>" <?php if(old('severity', $rule?->severity ?? 'warn') === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1 dark:text-gray-200">Operator <span class="text-rose-500">*</span></label>
        <select name="operator" required
                class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
            <?php $__currentLoopData = $operators; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($key); ?>" <?php if(old('operator', $rule?->operator ?? '>') === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1 dark:text-gray-200">Threshold <span class="text-rose-500">*</span></label>
        <input type="number" step="0.0001" name="threshold" value="<?php echo e(old('threshold', $rule?->threshold)); ?>" required
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1 dark:text-gray-200">Cooldown (นาที) <span class="text-rose-500">*</span></label>
        <input type="number" min="1" max="10080" name="cooldown_minutes" value="<?php echo e(old('cooldown_minutes', $rule?->cooldown_minutes ?? 60)); ?>" required
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
        <p class="text-[11px] text-gray-400 mt-1">หลังแจ้งครั้งหนึ่ง จะเงียบไปนานเท่าไรก่อนยอมแจ้งซ้ำ</p>
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-2 dark:text-gray-200">ช่องทางแจ้งเตือน</label>
        <div class="flex flex-wrap gap-3">
            <?php $__currentLoopData = $channelOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $cfg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <label class="inline-flex items-center gap-2 px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-700">
                    <input type="checkbox" name="channels[]" value="<?php echo e($key); ?>"
                           <?php if(in_array($key, $selectedChannels, true)): echo 'checked'; endif; ?>
                           class="rounded">
                    <i class="bi <?php echo e($cfg['icon']); ?>"></i>
                    <span class="text-sm"><?php echo e($cfg['label']); ?></span>
                </label>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>

    <div class="md:col-span-2">
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" <?php if(old('is_active', $rule?->is_active ?? true)): echo 'checked'; endif; ?> class="rounded">
            <span class="text-sm dark:text-gray-200">เปิดใช้งาน rule นี้</span>
        </label>
    </div>
</div>

<div class="flex gap-2 mt-6 justify-end">
    <a href="<?php echo e(route('admin.alerts.index')); ?>"
       class="px-4 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-700">
        ยกเลิก
    </a>
    <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
        <i class="bi bi-check-lg mr-1"></i><?php echo e($isEdit ? 'บันทึก' : 'สร้าง'); ?>

    </button>
</div>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/alerts/_form.blade.php ENDPATH**/ ?>