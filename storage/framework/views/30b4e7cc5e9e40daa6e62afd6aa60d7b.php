<?php $__env->startSection('title', 'ประวัติการแจ้งเตือน'); ?>

<?php
    $sevBadge = [
        'info'     => 'bg-sky-500/15 text-sky-600 dark:text-sky-300 border-sky-300/40',
        'warn'     => 'bg-amber-500/15 text-amber-600 dark:text-amber-300 border-amber-300/40',
        'critical' => 'bg-rose-500/15 text-rose-600 dark:text-rose-300 border-rose-300/40',
    ];
?>

<?php $__env->startSection('content'); ?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-clock-history text-amber-500"></i>
        ประวัติการแจ้งเตือน (Alert Events)
    </h4>
    <a href="<?php echo e(route('admin.alerts.index')); ?>" class="text-sm text-gray-500 dark:text-gray-400 hover:text-indigo-500">
        <i class="bi bi-arrow-left mr-1"></i>กลับไปหน้า Rules
    </a>
</div>

<form method="GET" class="bg-white dark:bg-slate-800 rounded-xl p-3 border border-gray-100 dark:border-white/5 mb-4 flex flex-wrap gap-2 items-end">
    <div>
        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Rule</label>
        <select name="rule" class="px-2 py-1.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
            <option value="">— ทั้งหมด —</option>
            <?php $__currentLoopData = $rules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($r->id); ?>" <?php if(request('rule') == $r->id): echo 'selected'; endif; ?>><?php echo e($r->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Severity</label>
        <select name="severity" class="px-2 py-1.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
            <option value="">— ทั้งหมด —</option>
            <?php $__currentLoopData = $severities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($key); ?>" <?php if(request('severity') === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <button class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
        <i class="bi bi-funnel mr-1"></i>กรอง
    </button>
    <?php if(request('rule') || request('severity')): ?>
        <a href="<?php echo e(route('admin.alerts.events')); ?>" class="px-3 py-1.5 border border-gray-200 dark:border-white/10 rounded-lg text-sm dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-700">
            <i class="bi bi-x-lg mr-1"></i>ล้าง
        </a>
    <?php endif; ?>
</form>

<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-900/40 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">เวลา</th>
                    <th class="px-4 py-3">Rule</th>
                    <th class="px-4 py-3">ค่าที่วัดได้</th>
                    <th class="px-4 py-3">Severity</th>
                    <th class="px-4 py-3">ช่องทาง</th>
                    <th class="px-4 py-3">หมายเหตุ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                <?php $__empty_1 = true; $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $sevCls = $sevBadge[$e->severity] ?? $sevBadge['info'];
                        $note = (string) ($e->note ?? '');
                        $isResolved = str_contains($note, 'Auto-resolved');
                        $isAck      = str_contains($note, 'Manually acknowledged');
                        $isTest     = str_contains($note, 'Manual test');
                        $rowCls     = $isResolved ? 'bg-emerald-50/30 dark:bg-emerald-900/10'
                                      : ($isAck ? 'bg-sky-50/30 dark:bg-sky-900/10' : '');
                    ?>
                    <tr class="<?php echo e($rowCls); ?>">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="flex items-center gap-1.5">
                                <?php if($isResolved): ?>
                                    <i class="bi bi-check2-circle text-emerald-500" title="Auto-resolved"></i>
                                <?php elseif($isAck): ?>
                                    <i class="bi bi-hand-thumbs-up text-sky-500" title="Acknowledged"></i>
                                <?php elseif($isTest): ?>
                                    <i class="bi bi-send text-amber-500" title="Manual test"></i>
                                <?php else: ?>
                                    <i class="bi bi-broadcast text-rose-500" title="Fired"></i>
                                <?php endif; ?>
                                <?php echo e($e->triggered_at?->format('d M Y H:i')); ?>

                            </div>
                            <div class="text-[10px] text-gray-400 ml-5"><?php echo e($e->triggered_at?->diffForHumans()); ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-semibold"><?php echo e($e->rule?->name ?? '—'); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($e->rule?->metric); ?></div>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">
                            <?php echo e(rtrim(rtrim(number_format((float) $e->value, 2), '0'), '.')); ?>

                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] <?php echo e($sevCls); ?>">
                                <?php echo e($severities[$e->severity] ?? $e->severity); ?>

                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <?php $__currentLoopData = ($e->channels_sent ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 dark:bg-slate-700 text-[11px]">
                                        <?php echo e($ch); ?>

                                    </span>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                <?php if(empty($e->channels_sent)): ?>
                                    <span class="text-gray-400 text-xs">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400"><?php echo e($e->note); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                            ยังไม่มีเหตุการณ์แจ้งเตือน
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($events->hasPages()): ?>
        <div class="p-3 border-t border-gray-100 dark:border-white/5">
            <?php echo e($events->links()); ?>

        </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/alerts/events.blade.php ENDPATH**/ ?>