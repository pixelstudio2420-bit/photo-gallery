<?php $__env->startSection('title', 'Alert Rules'); ?>

<?php
    $sevBadge = [
        'info'     => 'bg-sky-500/15 text-sky-600 dark:text-sky-300 border-sky-300/40',
        'warn'     => 'bg-amber-500/15 text-amber-600 dark:text-amber-300 border-amber-300/40',
        'critical' => 'bg-rose-500/15 text-rose-600 dark:text-rose-300 border-rose-300/40',
    ];
?>

<?php $__env->startSection('content'); ?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
        <h4 class="font-bold mb-1 tracking-tight flex items-center gap-2">
            <i class="bi bi-bell-fill text-rose-500"></i>
            Alert Rules Engine
            <span class="text-xs font-normal text-gray-400 ml-2">/ ระบบแจ้งเตือนตามเงื่อนไข</span>
        </h4>
        <p class="text-xs text-gray-500 dark:text-gray-400 m-0">
            ตั้ง rule จาก metric ของระบบ — ถ้าเกิน/ต่ำกว่า threshold จะแจ้งเตือนผ่าน Admin/Email/LINE/Push
        </p>
    </div>
    <div class="flex gap-2">
        <a href="<?php echo e(route('admin.alerts.events')); ?>"
           class="px-4 py-2 border border-gray-200 dark:border-white/5 dark:text-gray-200 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-slate-700">
            <i class="bi bi-clock-history mr-1"></i>ประวัติการแจ้งเตือน
        </a>
        <form action="<?php echo e(route('admin.alerts.run-now')); ?>" method="POST" class="inline">
            <?php echo csrf_field(); ?>
            <button class="px-4 py-2 border border-indigo-200 text-indigo-700 dark:text-indigo-200 dark:border-indigo-500/30 rounded-lg text-sm hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                <i class="bi bi-arrow-clockwise mr-1"></i>ตรวจสอบทันที
            </button>
        </form>
        <a href="<?php echo e(route('admin.alerts.create')); ?>"
           class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
            <i class="bi bi-plus-lg mr-1"></i>เพิ่ม Rule
        </a>
    </div>
</div>

<?php if(session('success')): ?>
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">
        <i class="bi bi-check-circle mr-1"></i><?php echo e(session('success')); ?>

    </div>
<?php endif; ?>
<?php if(session('error')): ?>
    <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 rounded-xl p-3 mb-4 text-sm">
        <i class="bi bi-exclamation-triangle mr-1"></i><?php echo e(session('error')); ?>

    </div>
<?php endif; ?>


<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">Rules ทั้งหมด</div>
        <div class="text-2xl font-bold mt-1"><?php echo e($stats['total']); ?></div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">เปิดใช้งาน</div>
        <div class="text-2xl font-bold mt-1 text-emerald-600 dark:text-emerald-300"><?php echo e($stats['active']); ?></div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">กำลัง firing</div>
        <div class="text-2xl font-bold mt-1 <?php echo e(($stats['firing'] ?? 0) > 0 ? 'text-rose-600 dark:text-rose-300' : ''); ?>"><?php echo e($stats['firing'] ?? 0); ?></div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">ตรงเงื่อนไขอยู่ตอนนี้</div>
        <div class="text-2xl font-bold mt-1 <?php echo e($stats['triggering'] > 0 ? 'text-rose-600 dark:text-rose-300' : ''); ?>"><?php echo e($stats['triggering']); ?></div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
        <div class="text-xs text-gray-500 dark:text-gray-400">เหตุการณ์ 24 ชั่วโมง</div>
        <div class="text-2xl font-bold mt-1"><?php echo e($stats['events_24h']); ?></div>
    </div>
</div>


<div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-500/30 rounded-xl p-3 mb-4 text-xs text-indigo-800 dark:text-indigo-200 flex items-start gap-2">
    <i class="bi bi-info-circle mt-0.5"></i>
    <div>
        <strong>Fire-once-per-episode:</strong> ถ้า rule เริ่ม firing ระบบจะแจ้งเตือน <em>ครั้งเดียว</em> จนกว่าค่าจะกลับมาต่ำกว่า threshold (auto-resolve)
        หรือกดปุ่ม <i class="bi bi-check2-circle"></i> "รับทราบ" เพื่อเคลียร์ state ด้วยตัวเอง (แจ้งเตือนในกระดิ่งจะถูกล้างอัตโนมัติ)
    </div>
</div>


<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-900/40 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">ชื่อ / Metric</th>
                    <th class="px-4 py-3">State</th>
                    <th class="px-4 py-3">เงื่อนไข</th>
                    <th class="px-4 py-3">ค่า ณ ตอนนี้</th>
                    <th class="px-4 py-3">Severity</th>
                    <th class="px-4 py-3">Channels</th>
                    <th class="px-4 py-3">Cooldown</th>
                    <th class="px-4 py-3">ล่าสุด</th>
                    <th class="px-4 py-3 text-right">การจัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                <?php $__empty_1 = true; $__currentLoopData = $rules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $m = $metrics[$r->metric] ?? ['label' => $r->metric, 'unit' => ''];
                        $sevCls = $sevBadge[$r->severity] ?? $sevBadge['info'];
                    ?>
                    <tr class="<?php echo e($r->firing ? 'bg-rose-50/70 dark:bg-rose-900/20' : ($r->would_trigger ? 'bg-amber-50/50 dark:bg-amber-900/10' : '')); ?>">
                        <td class="px-4 py-3">
                            <div class="font-semibold"><?php echo e($r->name); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($m['label']); ?></div>
                            <?php if($r->description): ?>
                                <div class="text-[11px] text-gray-400 mt-0.5"><?php echo e($r->description); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if($r->firing): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] bg-rose-500/15 text-rose-600 dark:text-rose-300 border-rose-300/40 animate-pulse">
                                    <i class="bi bi-broadcast mr-1"></i>FIRING
                                </span>
                                <?php if($r->last_triggered_at): ?>
                                    <div class="text-[10px] text-gray-400 mt-1" title="<?php echo e($r->last_triggered_at); ?>">ตั้งแต่ <?php echo e($r->last_triggered_at->diffForHumans()); ?></div>
                                <?php endif; ?>
                            <?php elseif($r->resolved_at): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] bg-emerald-500/15 text-emerald-600 dark:text-emerald-300 border-emerald-300/40">
                                    <i class="bi bi-check2-circle mr-1"></i>Resolved
                                </span>
                                <div class="text-[10px] text-gray-400 mt-1" title="<?php echo e($r->resolved_at); ?>"><?php echo e($r->resolved_at->diffForHumans()); ?></div>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-gray-300 border-gray-200 dark:border-white/10">
                                    <i class="bi bi-circle mr-1"></i>Idle
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">
                            <?php echo e($r->operator); ?> <?php echo e(rtrim(rtrim(number_format((float) $r->threshold, 2), '0'), '.')); ?> <?php echo e($m['unit']); ?>

                        </td>
                        <td class="px-4 py-3">
                            <?php if($r->current_value === null): ?>
                                <span class="text-gray-400 text-xs">—</span>
                            <?php else: ?>
                                <span class="<?php echo e($r->would_trigger ? 'text-rose-600 dark:text-rose-300 font-semibold' : ''); ?>">
                                    <?php echo e(rtrim(rtrim(number_format($r->current_value, 2), '0'), '.')); ?> <?php echo e($m['unit']); ?>

                                </span>
                                <?php if($r->would_trigger): ?>
                                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-rose-500/15 text-rose-600 dark:text-rose-300">FIRING</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] <?php echo e($sevCls); ?>">
                                <?php echo e($severities[$r->severity] ?? $r->severity); ?>

                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <?php $__currentLoopData = ($r->channels ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php $cfg = $channelOptions[$ch] ?? ['label' => $ch, 'icon' => 'bi-circle']; ?>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 dark:bg-slate-700 text-[11px]">
                                        <i class="bi <?php echo e($cfg['icon']); ?> mr-0.5"></i><?php echo e($cfg['label']); ?>

                                    </span>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                            <?php echo e($r->cooldown_minutes); ?> นาที
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                            <?php if($r->last_triggered_at): ?>
                                <div title="<?php echo e($r->last_triggered_at); ?>"><?php echo e($r->last_triggered_at->diffForHumans()); ?></div>
                            <?php else: ?>
                                <span class="text-gray-400">ยังไม่เคย</span>
                            <?php endif; ?>
                            <?php if($r->last_checked_at): ?>
                                <div class="text-[10px] text-gray-400">check: <?php echo e($r->last_checked_at->diffForHumans()); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <?php if($r->firing): ?>
                                <form action="<?php echo e(route('admin.alerts.acknowledge', $r)); ?>" method="POST" class="inline"
                                      onsubmit="return confirm('รับทราบและเคลียร์ firing state? (แจ้งเตือนในกระดิ่งจะถูกล้างด้วย)')">
                                    <?php echo csrf_field(); ?>
                                    <button class="px-2 py-1 text-xs border border-emerald-300 text-emerald-700 dark:text-emerald-200 dark:border-emerald-500/30 rounded hover:bg-emerald-50 dark:hover:bg-emerald-900/20" title="รับทราบ / เคลียร์ firing">
                                        <i class="bi bi-check2-circle"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form action="<?php echo e(route('admin.alerts.test', $r)); ?>" method="POST" class="inline">
                                <?php echo csrf_field(); ?>
                                <button class="px-2 py-1 text-xs border border-amber-300 text-amber-700 dark:text-amber-200 dark:border-amber-500/30 rounded hover:bg-amber-50 dark:hover:bg-amber-900/20" title="ส่งทดสอบ">
                                    <i class="bi bi-send"></i>
                                </button>
                            </form>
                            <form action="<?php echo e(route('admin.alerts.toggle', $r)); ?>" method="POST" class="inline">
                                <?php echo csrf_field(); ?>
                                <button class="px-2 py-1 text-xs border rounded <?php echo e($r->is_active ? 'border-emerald-300 text-emerald-700 dark:text-emerald-200 dark:border-emerald-500/30' : 'border-gray-300 text-gray-600 dark:text-gray-300 dark:border-white/10'); ?> hover:bg-gray-50 dark:hover:bg-slate-700" title="<?php echo e($r->is_active ? 'ปิด' : 'เปิด'); ?>">
                                    <i class="bi <?php echo e($r->is_active ? 'bi-toggle-on' : 'bi-toggle-off'); ?>"></i>
                                </button>
                            </form>
                            <a href="<?php echo e(route('admin.alerts.edit', $r)); ?>" class="inline-block px-2 py-1 text-xs border border-indigo-200 text-indigo-700 dark:text-indigo-200 dark:border-indigo-500/30 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="<?php echo e(route('admin.alerts.destroy', $r)); ?>" method="POST" class="inline" onsubmit="return confirm('ลบ rule นี้?')">
                                <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                <button class="px-2 py-1 text-xs border border-rose-200 text-rose-700 dark:text-rose-200 dark:border-rose-500/30 rounded hover:bg-rose-50 dark:hover:bg-rose-900/20">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                            ยังไม่มี rule — กด "เพิ่ม Rule" ด้านบน หรือรัน <code class="bg-gray-100 dark:bg-slate-700 px-1 rounded">php artisan db:seed --class=AlertRuleSeeder</code>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/alerts/index.blade.php ENDPATH**/ ?>