<?php $__env->startSection('title', 'ช่างภาพมืออาชีพ'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-7xl mx-auto p-4 md:p-6">

    
    <div class="mb-6">
        <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight">
            📸 ช่างภาพมืออาชีพ
        </h1>
        <p class="text-sm md:text-base text-slate-500 dark:text-slate-400 mt-2">
            ค้นหาช่างภาพในไทย · แต่งงาน · รับปริญญา · งานวิ่ง · คอนเสิร์ต · อีเวนต์บริษัท
        </p>
    </div>

    
    <form method="GET" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-4 mb-6 ring-1 ring-slate-100 dark:ring-slate-700">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <input type="text" name="search" value="<?php echo e(request('search')); ?>"
                   placeholder="ค้นหาด้วยชื่อ / ความเชี่ยวชาญ"
                   class="h-11 px-3.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm
                          focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
            <select name="province"
                    class="h-11 px-3.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm
                           focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                <option value="">— ทุกจังหวัด —</option>
                <?php $__currentLoopData = $provinces; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($p->id); ?>" <?php if(request('province')==$p->id): echo 'selected'; endif; ?>><?php echo e($p->name_th); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <div class="flex gap-2">
                <button class="flex-1 h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold transition">
                    <i class="bi bi-search"></i> ค้นหา
                </button>
                <a href="<?php echo e(route('photographers.index')); ?>"
                   class="h-11 px-4 inline-flex items-center rounded-xl ring-1 ring-slate-300 dark:ring-slate-600 text-slate-700 dark:text-slate-300 font-semibold text-sm transition hover:bg-slate-50 dark:hover:bg-slate-700">
                    ล้าง
                </a>
            </div>
        </div>
    </form>

    
    <?php if($rows->count() === 0): ?>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-12 text-center text-slate-500">
            <i class="bi bi-camera text-5xl mb-3 block opacity-50"></i>
            ไม่พบช่างภาพตรงเงื่อนไขที่ค้นหา
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="<?php echo e($row->slug ? route('photographers.show.slug', $row->slug) : route('photographers.show', $row->user_id)); ?>"
                   class="block bg-white dark:bg-slate-800 rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition group">
                    
                    <div class="relative h-32 bg-gradient-to-br from-indigo-500 via-violet-500 to-pink-500 flex items-center justify-center">
                        <?php if($row->avatar): ?>
                            <img src="<?php echo e(\Illuminate\Support\Facades\Storage::disk(config('media.disk', 'r2'))->url($row->avatar)); ?>"
                                 alt="" class="absolute inset-0 w-full h-full object-cover opacity-40">
                        <?php endif; ?>
                        <div class="absolute -bottom-7 left-5 w-14 h-14 rounded-2xl bg-white shadow-md flex items-center justify-center text-2xl font-extrabold text-indigo-600 ring-4 ring-white dark:ring-slate-800">
                            <?php echo e(mb_strtoupper(mb_substr($row->display_name ?? $row->first_name ?? 'P', 0, 1, 'UTF-8'), 'UTF-8')); ?>

                        </div>
                        <?php if($row->tier === 'pro'): ?>
                            <span class="absolute top-3 right-3 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold text-white bg-amber-500/90">
                                ⭐ PRO
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="px-5 pt-9 pb-5">
                        <h3 class="text-lg font-extrabold text-slate-900 dark:text-white group-hover:text-indigo-600 transition">
                            <?php echo e($row->display_name ?? trim($row->first_name . ' ' . $row->last_name)); ?>

                        </h3>
                        <?php if($row->bio): ?>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 line-clamp-2">
                                <?php echo e($row->bio); ?>

                            </p>
                        <?php endif; ?>
                        <div class="flex items-center justify-between mt-4 text-xs">
                            <span class="text-slate-500">
                                <i class="bi bi-camera"></i> <?php echo e($row->events_count); ?> อีเวนต์
                            </span>
                            <?php if($row->years_experience): ?>
                                <span class="text-slate-500">
                                    <i class="bi bi-clock-history"></i> <?php echo e($row->years_experience); ?> ปี
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        
        <div class="mt-8">
            <?php echo e($rows->links()); ?>

        </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/public/photographers/index.blade.php ENDPATH**/ ?>