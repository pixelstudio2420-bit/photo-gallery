<?php $__env->startSection('title', 'Photographer Promotions'); ?>
<?php $__env->startSection('content'); ?>
<div class="max-w-7xl mx-auto px-4 py-6">
  <a href="<?php echo e(route('admin.monetization.dashboard')); ?>" class="text-xs text-slate-500 hover:underline">← Monetization</a>
  <div class="flex items-end justify-between mb-5 mt-1">
    <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Photographer Promotions</h1>
    <div class="text-sm text-slate-500">รายได้ 30 วัน: <span class="font-extrabold text-emerald-600 text-lg">฿<?php echo e(number_format($revenue30d, 0)); ?></span></div>
  </div>

  <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase text-slate-500">
        <tr>
          <th class="px-3 py-2 text-left">ช่างภาพ</th>
          <th class="px-3 py-2 text-left">Kind</th>
          <th class="px-3 py-2 text-left">Placement</th>
          <th class="px-3 py-2 text-right">Boost</th>
          <th class="px-3 py-2 text-left">Cycle</th>
          <th class="px-3 py-2 text-right">฿</th>
          <th class="px-3 py-2 text-left">เริ่ม → สิ้น</th>
          <th class="px-3 py-2 text-left">สถานะ</th>
          <th class="px-3 py-2 text-right">จัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200 dark:divide-white/10">
        <?php $__empty_1 = true; $__currentLoopData = $promos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
          <td class="px-3 py-2 text-slate-700 dark:text-slate-200"><?php echo e(optional($p->photographer)->first_name ?? '#'.$p->photographer_id); ?></td>
          <td class="px-3 py-2 text-xs uppercase text-slate-500"><?php echo e($p->kind); ?></td>
          <td class="px-3 py-2 text-xs"><?php echo e($p->placement); ?><?php echo e($p->placement_target ? ' · ' . $p->placement_target : ''); ?></td>
          <td class="px-3 py-2 text-right font-bold">+<?php echo e($p->boost_score); ?></td>
          <td class="px-3 py-2 text-xs"><?php echo e($p->billing_cycle); ?></td>
          <td class="px-3 py-2 text-right font-bold">฿<?php echo e(number_format($p->amount_thb, 0)); ?></td>
          <td class="px-3 py-2 text-xs text-slate-500"><?php echo e($p->starts_at?->format('d M')); ?> → <?php echo e($p->ends_at?->format('d M y')); ?></td>
          <td class="px-3 py-2">
            <?php
              $statusTone = match ($p->status) {
                'active'    => ['rgba(16,185,129,0.18)', 'text-emerald-700 dark:text-emerald-300', 'ring-emerald-300/60'],
                'expired'   => ['rgba(100,116,139,0.18)','text-slate-700 dark:text-slate-300',     'ring-slate-300/60'],
                'cancelled' => ['rgba(244,63,94,0.18)',  'text-rose-700 dark:text-rose-300',       'ring-rose-300/60'],
                'refunded'  => ['rgba(244,63,94,0.18)',  'text-rose-700 dark:text-rose-300',       'ring-rose-300/60'],
                default     => ['rgba(245,158,11,0.18)', 'text-amber-700 dark:text-amber-300',     'ring-amber-300/60'],
              };
            ?>
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold uppercase ring-1 ring-inset <?php echo e($statusTone[1]); ?> <?php echo e($statusTone[2]); ?>"
                  style="background:<?php echo e($statusTone[0]); ?>;"><?php echo e($p->status); ?></span>
          </td>
          <td class="px-3 py-2 text-right whitespace-nowrap">
            <a href="<?php echo e(route('admin.monetization.promotions.edit', $p->id)); ?>"
               class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-semibold text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30">
              <i class="bi bi-pencil"></i> แก้ไข
            </a>
            <?php if(in_array($p->status, ['pending','active'], true)): ?>
              <form method="POST" action="<?php echo e(route('admin.monetization.promotions.cancel', $p->id)); ?>" class="inline"
                    onsubmit="return confirm('ยกเลิก promotion #<?php echo e($p->id); ?>?');">
                <?php echo csrf_field(); ?>
                <button class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-semibold text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/30">
                  <i class="bi bi-x-circle"></i> ยกเลิก
                </button>
              </form>
            <?php endif; ?>
            <?php if(!in_array($p->status, ['refunded'], true)): ?>
              <form method="POST" action="<?php echo e(route('admin.monetization.promotions.refund', $p->id)); ?>" class="inline"
                    onsubmit="return confirm('บันทึก refund promotion #<?php echo e($p->id); ?>?');">
                <?php echo csrf_field(); ?>
                <button class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-semibold text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/30">
                  <i class="bi bi-arrow-counterclockwise"></i> Refund
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="9" class="px-3 py-12 text-center text-slate-500">ยังไม่มี promotion</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="mt-4"><?php echo e($promos->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/monetization/promotions.blade.php ENDPATH**/ ?>